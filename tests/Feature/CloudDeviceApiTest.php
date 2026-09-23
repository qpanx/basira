<?php

use App\Enums\DeviceStatus;
use App\Enums\FamilyPermission;
use App\Events\DeviceAlertTriggered;
use App\Events\DeviceSOSTriggered;
use App\Events\LocationUpdated;
use App\Jobs\SendSosNotifications;
use App\Models\Device;
use App\Models\User;
use App\Notifications\DeviceAlertNotification;
use App\Notifications\SosAlertNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/** @return array<string, mixed> */
function cloudPayload(string $kind, string $serial = 'SAC-CAIRO-0001'): array
{
    return [
        'device_id' => $serial,
        'timestamp' => '2026-09-22T14:32:10Z',
        ...match ($kind) {
            'location' => ['lat' => 30.0444, 'lng' => 31.2357, 'gps_valid' => true],
            'battery' => ['voltage' => 3.85, 'percentage' => 62],
            'alert' => ['type' => 'front_obstacle', 'severity' => 'warning', 'value' => 45, 'lat' => 30.0444, 'lng' => 31.2357],
            'sos' => ['type' => 'sos_critical', 'severity' => 'critical', 'lat' => 30.0444, 'lng' => 31.2357],
            'status' => ['online' => true],
        },
    ];
}

function cloudPath(string $kind): string
{
    return '/api/device/'.match ($kind) {
        'battery', 'status' => 'heartbeat',
        'sos' => 'alert',
        default => $kind,
    };
}

function cloudDevice(): Device
{
    return Device::factory()->offline()->create([
        'serial_number' => 'SAC-CAIRO-0001',
        'firmware_version' => 'existing-firmware',
        'battery_level' => 80,
        'last_location_recorded_at' => null,
        'geofence_evaluated_at' => null,
    ]);
}

function cloudToken(Device $device): string
{
    return $device->createToken('existing-cloud-token', ['device:heartbeat', 'device:location', 'device:alert'])->plainTextToken;
}

function fakeCloudDelivery(): void
{
    Notification::fake();
    Queue::fake();
    Http::preventStrayRequests();
}

dataset('cloud payloads', ['location', 'battery', 'alert', 'sos', 'status']);

test('accepts every exact PDF example through existing routes using one existing token', function () {
    $this->travelTo('2026-09-22 14:32:20');
    $device = cloudDevice();
    $token = cloudToken($device);
    fakeCloudDelivery();

    $this->withToken($token)->postJson(cloudPath('location'), cloudPayload('location'))->assertCreated();
    $this->withToken($token)->postJson(cloudPath('battery'), cloudPayload('battery'))->assertOk()->assertJsonPath('data.battery_level', 62);
    $this->withToken($token)->postJson(cloudPath('alert'), cloudPayload('alert'))->assertCreated()->assertJsonPath('data.type', 'obstacle_front');
    $this->withToken($token)->postJson(cloudPath('sos'), cloudPayload('sos'))->assertCreated()->assertJsonPath('data.type', 'sos');
    $this->withToken($token)->postJson(cloudPath('status'), cloudPayload('status'))->assertOk();

    expect($device->refresh())->battery_level->toBe(62)->firmware_version->toBe('existing-firmware')->status->toBe(DeviceStatus::Active);
    expect($device->tokens()->count())->toBe(1);
    expect($device->locations()->sole())->lat->toBe('30.0444000')->lng->toBe('31.2357000');
    expect($device->batteryLogs()->sole())->voltage->toBe('3.850')->power_state->value->toBe('unknown');
    expect($device->alerts()->where('type', 'obstacle_front')->sole())->sensor_value->toBe('45.000')->sensor_unit->toBe('cm')->distance_cm->toBe(45);
    Queue::assertPushedOn('sos', SendSosNotifications::class);
    Notification::assertNothingSent();
});

test('keeps event time separate from receipt time for Cloud telemetry', function (string $kind) {
    $this->travelTo('2026-09-22 14:32:20');
    $device = cloudDevice();
    fakeCloudDelivery();

    $this->withToken(cloudToken($device))->postJson(cloudPath($kind), cloudPayload($kind))->assertSuccessful();

    $record = match ($kind) {
        'location' => $device->locations()->sole(),
        'battery' => $device->batteryLogs()->sole(),
        'alert', 'sos' => $device->alerts()->sole(),
        'status' => $device->refresh(),
    };
    $eventField = match ($kind) {
        'alert', 'sos' => 'occurred_at',
        'status' => 'last_status_recorded_at',
        default => 'recorded_at',
    };
    $receiptField = match ($kind) {
        'alert', 'sos' => 'created_at',
        'status' => 'last_status_received_at',
        default => 'received_at',
    };
    expect($record->$eventField->toISOString())->toBe('2026-09-22T14:32:10.000000Z');
    expect($record->$receiptField->toISOString())->toBe('2026-09-22T14:32:20.000000Z');
})->with('cloud payloads');

test('deduplicates Cloud retries without refreshing connectivity or dispatching SOS again', function (string $kind) {
    $this->travelTo('2026-09-22 14:32:20');
    $device = cloudDevice();
    fakeCloudDelivery();
    $token = cloudToken($device);
    $payload = cloudPayload($kind);
    $first = $this->withToken($token)->postJson(cloudPath($kind), $payload)->assertSuccessful();
    $lastSeen = $device->refresh()->last_seen_at->toISOString();
    $this->travel(1)->minutes();

    $retry = $this->withToken($token)->postJson(cloudPath($kind), array_reverse($payload, true))->assertOk();

    expect($device->refresh()->last_seen_at->toISOString())->toBe($lastSeen);
    expect($retry->json('data'))->toBe($first->json('data'));
    expect($device->locations()->count())->toBe($kind === 'location' ? 1 : 0);
    expect($device->batteryLogs()->count())->toBe($kind === 'battery' ? 1 : 0);
    expect($device->alerts()->count())->toBe(in_array($kind, ['alert', 'sos'], true) ? 1 : 0);
    if ($kind === 'sos') {
        Queue::assertPushed(SendSosNotifications::class, 1);
    } else {
        Queue::assertNotPushed(SendSosNotifications::class);
    }
    Notification::assertNothingSent();
})->with('cloud payloads');

test('rejects unauthenticated Cloud events with 401', function (string $kind) {
    fakeCloudDelivery();

    $this->postJson(cloudPath($kind), cloudPayload($kind))->assertUnauthorized();

    $this->assertDatabaseCount('alerts', 0);
    $this->assertDatabaseCount('locations', 0);
    $this->assertDatabaseCount('battery_logs', 0);
    Queue::assertNotPushed(SendSosNotifications::class);
    Notification::assertNothingSent();
})->with('cloud payloads');

test('rejects another devices serial with 422 and makes no changes', function (string $kind) {
    $this->travelTo('2026-09-22 14:32:20');
    $device = cloudDevice();
    $otherDevice = Device::factory()->create();
    $original = $device->refresh()->getAttributes();
    $otherOriginal = $otherDevice->refresh()->getAttributes();
    fakeCloudDelivery();

    $this->withToken(cloudToken($device))->postJson(cloudPath($kind), cloudPayload($kind, $otherDevice->serial_number))
        ->assertUnprocessable()->assertJsonValidationErrors('device_id');

    expect($device->refresh()->getAttributes())->toBe($original);
    expect($otherDevice->refresh()->getAttributes())->toBe($otherOriginal);
    $this->assertDatabaseCount('alerts', 0);
    $this->assertDatabaseCount('locations', 0);
    $this->assertDatabaseCount('battery_logs', 0);
    Queue::assertNotPushed(SendSosNotifications::class);
    Notification::assertNothingSent();
})->with('cloud payloads');

test('retains device token authorization for Cloud events', function (string $kind, string $restriction, int $status) {
    $this->travelTo('2026-09-22 14:32:20');
    $device = cloudDevice();
    fakeCloudDelivery();
    $token = match ($restriction) {
        'user' => User::factory()->create()->createToken('user')->plainTextToken,
        'ability' => $device->createToken('config-only', ['device:config'])->plainTextToken,
        'invalid' => 'invalid-token',
        default => cloudToken($device),
    };
    if ($restriction === 'disabled') {
        $device->update(['is_enabled' => false]);
    }
    if ($restriction === 'unassigned') {
        $device->update(['blind_user_id' => null]);
    }
    if ($restriction === 'revoked') {
        $device->tokens()->delete();
    }
    if ($restriction === 'expired') {
        $device->tokens()->update(['expires_at' => now()->subMinute()]);
    }

    $this->withToken($token)->postJson(cloudPath($kind), cloudPayload($kind))->assertStatus($status);

    $this->assertDatabaseCount('alerts', 0);
    $this->assertDatabaseCount('locations', 0);
    $this->assertDatabaseCount('battery_logs', 0);
    Queue::assertNotPushed(SendSosNotifications::class);
    Notification::assertNothingSent();
})->with('cloud payloads')->with([
    'user token' => ['user', 401], 'missing ability' => ['ability', 403],
    'disabled' => ['disabled', 403], 'unassigned' => ['unassigned', 403],
    'invalid token' => ['invalid', 401], 'revoked token' => ['revoked', 401], 'expired token' => ['expired', 401],
]);

test('rejects invalid Cloud fields with 422 before persisting or dispatching', function (string $kind, string $field, mixed $value) {
    $this->travelTo('2026-09-22 14:32:20');
    $device = cloudDevice();
    fakeCloudDelivery();
    $payload = cloudPayload($kind);
    $payload[$field] = $value;

    $this->withToken(cloudToken($device))->postJson(cloudPath($kind), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors($field);

    $this->assertDatabaseCount('alerts', 0);
    $this->assertDatabaseCount('locations', 0);
    $this->assertDatabaseCount('battery_logs', 0);
    expect($device->refresh()->last_status_recorded_at)->toBeNull();
    Queue::assertNotPushed(SendSosNotifications::class);
    Notification::assertNothingSent();
})->with([
    'missing serial' => ['battery', 'device_id', null],
    'numeric identity' => ['location', 'device_id', 1],
    'invalid event time' => ['status', 'timestamp', 'yesterday'],
    'missing event time' => ['sos', 'timestamp', null],
    'future event time' => ['status', 'timestamp', '2026-09-22T15:32:10Z'],
    'non UTC event time' => ['battery', 'timestamp', '2026-09-22T14:32:10+03:00'],
    'latitude high' => ['location', 'lat', 91],
    'longitude low' => ['location', 'lng', -181],
    'missing latitude' => ['alert', 'lat', null],
    'missing longitude' => ['sos', 'lng', null],
    'invalid GPS flag' => ['location', 'gps_valid', 'yes'],
    'missing GPS flag' => ['location', 'gps_valid', null],
    'negative voltage' => ['battery', 'voltage', -1],
    'unbounded voltage' => ['battery', 'voltage', 100],
    'invalid voltage' => ['battery', 'voltage', 'high'],
    'missing voltage' => ['battery', 'voltage', null],
    'too precise voltage' => ['battery', 'voltage', 3.1234],
    'negative percentage' => ['battery', 'percentage', -1],
    'high percentage' => ['battery', 'percentage', 101],
    'fractional percentage' => ['battery', 'percentage', 62.5],
    'missing percentage' => ['battery', 'percentage', null],
    'unknown type' => ['alert', 'type', 'unknown'],
    'unknown severity' => ['alert', 'severity', 'urgent'],
    'noncritical SOS' => ['sos', 'severity', 'warning'],
    'missing reading' => ['alert', 'value', null],
    'negative reading' => ['alert', 'value', -1],
    'invalid reading' => ['alert', 'value', 'near'],
    'oversized reading' => ['alert', 'value', 65536],
    'invalid connectivity' => ['status', 'online', 'false'],
    'missing connectivity' => ['status', 'online', null],
    'mixed heartbeat' => ['battery', 'battery_level', 9],
    'mixed status' => ['status', 'percentage', 9],
    'spoofed timestamp' => ['location', 'received_at', '2026-09-22T14:32:10Z'],
    'spoofed user' => ['sos', 'blind_user_id', 99],
    'spoofed status' => ['battery', 'status', 'active'],
    'spoofed last seen' => ['status', 'last_seen_at', '2026-09-22T14:32:10Z'],
    'spoofed resolution' => ['alert', 'resolved_by', 1],
    'spoofed message id' => ['sos', 'message_id', 'a517d279-1ff2-4c0b-b78a-924460c00a54'],
]);

test('maps every Cloud sensor type and severity while retaining its original unit', function (string $type, string $mapped, float $value, string $unit, ?int $distance, string $severity) {
    $this->travelTo('2026-09-22 14:32:20');
    $device = cloudDevice();
    fakeCloudDelivery();

    $this->withToken(cloudToken($device))->postJson(cloudPath('alert'), [
        ...cloudPayload('alert'), 'type' => $type, 'severity' => $severity, 'value' => $value,
    ])->assertCreated()->assertJsonPath('data.type', $mapped)->assertJsonPath('data.severity', $severity);

    expect($device->alerts()->sole())->sensor_value->toBe(number_format($value, 3, '.', ''))->sensor_unit->toBe($unit)->distance_cm->toBe($distance);
    Queue::assertNotPushed(SendSosNotifications::class);
    Notification::assertNothingSent();
})->with([
    ['front_obstacle', 'obstacle_front', 45.0, 'cm', 45],
    ['upper_obstacle', 'obstacle_upper', 45.5, 'cm', 46],
    ['pit_detected', 'obstacle_lower', 451.0, 'mm', 45],
    ['water_detected', 'water_hazard', 4095.0, 'adc', null],
])->with(['info', 'warning', 'critical']);

test('ignores invalid GPS fixes without changing location or dispatching map events', function () {
    $this->travelTo('2026-09-22 14:32:20');
    $device = cloudDevice();
    Event::fake([LocationUpdated::class]);
    fakeCloudDelivery();
    $token = cloudToken($device);

    for ($attempt = 0; $attempt < 2; $attempt++) {
        $this->withToken($token)->postJson(cloudPath('location'), [...cloudPayload('location'), 'gps_valid' => false])
            ->assertOk()->assertJsonPath('data.reason', 'invalid_gps_fix');
    }

    expect($device->refresh()->last_location_recorded_at)->toBeNull();
    $this->assertDatabaseCount('locations', 0);
    Event::assertNotDispatched(LocationUpdated::class);
    Queue::assertNotPushed(SendSosNotifications::class);
    Notification::assertNothingSent();
});

test('dispatches the existing SOS job exactly once and safely tests its notification pipeline', function () {
    $this->travelTo('2026-09-22 14:32:20');
    $device = cloudDevice();
    $family = User::factory()->family()->create();
    $family->blindUsers()->attach($device->blind_user_id, [
        'relationship' => 'caregiver',
        'permissions' => [FamilyPermission::ReceiveAlerts->value => true],
    ]);
    fakeCloudDelivery();
    Event::fake([DeviceAlertTriggered::class, DeviceSOSTriggered::class]);
    $token = cloudToken($device);

    $this->withToken($token)->postJson(cloudPath('sos'), cloudPayload('sos'))->assertCreated();
    $this->withToken($token)->postJson(cloudPath('sos'), cloudPayload('sos'))->assertOk();

    $alert = $device->alerts()->sole();
    Event::assertDispatchedTimes(DeviceAlertTriggered::class, 1);
    Event::assertDispatched(DeviceSOSTriggered::class, fn (DeviceSOSTriggered $event): bool => $event->alert->is($alert));
    Event::assertDispatchedTimes(DeviceSOSTriggered::class, 1);
    Queue::assertPushed(SendSosNotifications::class, fn (SendSosNotifications $job): bool => $job->alert->is($alert) && $job->queue === 'sos');
    Queue::assertPushed(SendSosNotifications::class, 1);
    Notification::assertNothingSent();

    (new SendSosNotifications($alert))->handle();

    Notification::assertSentTo($family, SosAlertNotification::class, fn (SosAlertNotification $notification): bool => $notification->alert->is($alert));
});

test('dispatches existing regular alert notifications once for a Cloud retry', function () {
    $this->travelTo('2026-09-22 14:32:20');
    $device = cloudDevice();
    $family = User::factory()->family()->create();
    $family->blindUsers()->attach($device->blind_user_id, [
        'relationship' => 'caregiver',
        'permissions' => [FamilyPermission::ReceiveAlerts->value => true],
    ]);
    fakeCloudDelivery();
    $token = cloudToken($device);

    $this->withToken($token)->postJson(cloudPath('alert'), cloudPayload('alert'))->assertCreated();
    $this->withToken($token)->postJson(cloudPath('alert'), cloudPayload('alert'))->assertOk();

    Notification::assertSentToTimes($family, DeviceAlertNotification::class, 1);
    Queue::assertNotPushed(SendSosNotifications::class);
});

test('does not require battery or firmware fields for Cloud status', function () {
    $this->travelTo('2026-09-22 14:32:20');
    $device = cloudDevice();

    $this->withToken(cloudToken($device))->postJson(cloudPath('status'), cloudPayload('status'))
        ->assertOk()->assertJsonPath('data.status', 'active');

    expect($device->refresh())->battery_level->toBe(80)->firmware_version->toBe('existing-firmware')->last_status_online->toBeTrue();
    expect($device->last_seen_at->toISOString())->toBe('2026-09-22T14:32:20.000000Z');
    $this->assertDatabaseCount('battery_logs', 0);
});

test('accepts newer offline status but ignores stale and duplicate connectivity messages', function () {
    $this->travelTo('2026-09-22 14:32:20');
    $device = cloudDevice();
    $token = cloudToken($device);
    $this->withToken($token)->postJson(cloudPath('status'), cloudPayload('status'))->assertOk();
    $this->travel(1)->minutes();

    $offline = [...cloudPayload('status'), 'online' => false, 'timestamp' => '2026-09-22T14:33:10Z'];
    $this->withToken($token)->postJson(cloudPath('status'), $offline)->assertOk()->assertJsonPath('data.status', 'offline');
    $this->withToken($token)->postJson(cloudPath('status'), cloudPayload('status'))->assertOk()->assertJsonPath('data.status', 'offline');
    $this->withToken($token)->postJson(cloudPath('status'), $offline)->assertOk()->assertJsonPath('data.status', 'offline');

    expect($device->refresh()->last_status_recorded_at->toISOString())->toBe('2026-09-22T14:33:10.000000Z');
    expect($device->last_status_online)->toBeFalse();
    expect($device->last_seen_at->toISOString())->toBe('2026-09-22T14:32:20.000000Z');
});

test('delayed offline status cannot override more recent activity', function (string $kind) {
    $this->travelTo('2026-09-22 14:32:20');
    $device = cloudDevice();
    $token = cloudToken($device);
    fakeCloudDelivery();
    $payload = $kind === 'legacy' ? ['battery_level' => 75, 'firmware_version' => '2.0.1'] : cloudPayload($kind);
    $path = $kind === 'legacy' ? '/api/device/heartbeat' : cloudPath($kind);
    $this->withToken($token)->postJson($path, $payload)->assertSuccessful();
    $this->travel(1)->minutes();

    $this->withToken($token)->postJson(cloudPath('status'), [...cloudPayload('status'), 'online' => false])
        ->assertOk()->assertJsonPath('data.status', 'active');

    expect($device->refresh()->last_seen_at->toISOString())->toBe('2026-09-22T14:32:20.000000Z');
})->with(['legacy', 'location', 'battery', 'alert', 'sos']);

test('backlogged telemetry does not resurrect an offline cane', function (string $kind) {
    $this->travelTo('2026-09-22 16:32:20');
    $device = cloudDevice();
    $lastSeen = $device->last_seen_at->toISOString();
    fakeCloudDelivery();

    $this->withToken(cloudToken($device))->postJson(cloudPath($kind), cloudPayload($kind))->assertSuccessful();

    expect($device->refresh()->status)->toBe(DeviceStatus::Offline);
    expect($device->last_seen_at->toISOString())->toBe($lastSeen);
})->with('cloud payloads');

test('keeps maintenance mode when receiving Cloud telemetry', function (string $kind) {
    $this->travelTo('2026-09-22 14:32:20');
    $device = cloudDevice();
    $device->update(['status' => DeviceStatus::Maintenance]);
    fakeCloudDelivery();

    $this->withToken(cloudToken($device))->postJson(cloudPath($kind), cloudPayload($kind))->assertSuccessful();

    expect($device->refresh()->status)->toBe(DeviceStatus::Maintenance);
})->with('cloud payloads');

test('keeps the newest battery reading when older events or retries arrive', function () {
    $this->travelTo('2026-09-22 14:32:20');
    $device = cloudDevice();
    $token = cloudToken($device);
    $first = cloudPayload('battery');
    $this->withToken($token)->postJson(cloudPath('battery'), $first)->assertOk();
    $newest = [...$first, 'percentage' => 61, 'timestamp' => '2026-09-22T14:32:15Z'];
    $this->withToken($token)->postJson(cloudPath('battery'), $newest)->assertOk();

    $this->withToken($token)->postJson(cloudPath('battery'), $first)->assertOk()->assertJsonPath('data.battery_level', 61);
    $this->withToken($token)->postJson(cloudPath('battery'), [...$first, 'percentage' => 90, 'timestamp' => '2026-09-22T14:31:10Z'])
        ->assertOk()->assertJsonPath('data.battery_level', 61);

    expect($device->refresh())->battery_level->toBe(61)->firmware_version->toBe('existing-firmware');
    expect($device->batteryLogs()->count())->toBe(3);
});

test('preserves legacy heartbeat requirements', function () {
    $device = Device::factory()->create();

    $this->withToken(cloudToken($device))->postJson('/api/device/heartbeat', [])
        ->assertUnprocessable()->assertJsonValidationErrors(['battery_level', 'firmware_version']);

    $this->assertDatabaseCount('battery_logs', 0);
});

test('deduplicates equivalent Cloud numeric and UTC timestamp representations', function () {
    $this->travelTo('2026-09-22 14:32:20');
    $device = cloudDevice();
    $token = cloudToken($device);
    fakeCloudDelivery();
    $this->withToken($token)->postJson(cloudPath('alert'), cloudPayload('alert'))->assertCreated();

    $this->withToken($token)->postJson(cloudPath('alert'), [
        ...cloudPayload('alert'), 'value' => 45.0, 'lat' => '30.044400', 'timestamp' => '2026-09-22T14:32:10.000+00:00',
    ])->assertOk();

    expect($device->alerts()->count())->toBe(1);
});

test('keeps distinct readings and devices separate in duplicate detection', function () {
    $this->travelTo('2026-09-22 14:32:20');
    $first = cloudDevice();
    $second = Device::factory()->create();
    $firstToken = cloudToken($first);
    fakeCloudDelivery();
    $this->withToken($firstToken)->postJson(cloudPath('alert'), cloudPayload('alert'))->assertCreated();
    $this->withToken($firstToken)->postJson(cloudPath('alert'), [...cloudPayload('alert'), 'value' => 46])->assertCreated();
    $this->app['auth']->forgetGuards();

    $this->withToken(cloudToken($second))->postJson(cloudPath('alert'), cloudPayload('alert', $second->serial_number))->assertCreated();

    expect($first->alerts()->count())->toBe(2);
    expect($second->alerts()->count())->toBe(1);
});

test('preserves millisecond Cloud timestamps and ignores status retries at that precision', function (string $kind) {
    $this->travelTo('2026-09-22 14:32:20');
    $device = cloudDevice();
    $token = cloudToken($device);
    fakeCloudDelivery();
    $payload = [...cloudPayload($kind), 'timestamp' => '2026-09-22T14:32:10.123Z'];
    $this->withToken($token)->postJson(cloudPath($kind), $payload)->assertSuccessful();
    $this->travel(1)->minutes();

    $this->withToken($token)->postJson(cloudPath($kind), $payload)->assertOk();

    $time = match ($kind) {
        'location' => $device->locations()->sole()->recorded_at,
        'battery' => $device->batteryLogs()->sole()->recorded_at,
        'alert', 'sos' => $device->alerts()->sole()->occurred_at,
        'status' => $device->refresh()->last_status_recorded_at,
    };
    expect($time->toISOString())->toBe('2026-09-22T14:32:10.123000Z');
    expect($device->refresh()->last_seen_at->toISOString())->toBe('2026-09-22T14:32:20.000000Z');
})->with('cloud payloads');

test('marks silent devices offline and reconnects on a new status event', function () {
    $this->travelTo('2026-09-22 14:32:20');
    $device = cloudDevice();
    $token = cloudToken($device);
    $this->withToken($token)->postJson(cloudPath('status'), cloudPayload('status'))->assertOk();
    $this->travel(11)->minutes();

    $this->artisan('devices:mark-offline')->assertSuccessful();
    expect($device->refresh()->status)->toBe(DeviceStatus::Offline);
    $this->withToken($token)->postJson(cloudPath('status'), cloudPayload('status'))->assertOk()->assertJsonPath('data.status', 'offline');
    $this->withToken($token)->postJson(cloudPath('status'), [...cloudPayload('status'), 'timestamp' => '2026-09-22T14:43:10Z'])
        ->assertOk()->assertJsonPath('data.status', 'active');
});

test('out of order GPS history does not move the current location backwards', function () {
    $this->travelTo('2026-09-22 14:32:20');
    $device = cloudDevice();
    $token = cloudToken($device);
    fakeCloudDelivery();
    Event::fake([LocationUpdated::class]);
    $this->withToken($token)->postJson(cloudPath('location'), cloudPayload('location'))->assertCreated();

    $this->withToken($token)->postJson(cloudPath('location'), [
        ...cloudPayload('location'), 'lat' => 29.0, 'timestamp' => '2026-09-22T14:31:10Z',
    ])->assertCreated();

    expect($device->refresh()->last_location_recorded_at->toISOString())->toBe('2026-09-22T14:32:10.000000Z');
    expect($device->locations()->count())->toBe(2);
    Event::assertDispatchedTimes(LocationUpdated::class, 1);
});

test('Cloud SOS uses the existing dedicated SOS rate limit', function () {
    config(['smart-cane.rate_limits.sos' => 1, 'smart-cane.rate_limits.alert' => 30]);
    $this->travelTo('2026-09-22 14:32:20');
    $device = cloudDevice();
    fakeCloudDelivery();
    $token = cloudToken($device);
    $this->withToken($token)->postJson(cloudPath('sos'), cloudPayload('sos'))->assertCreated();

    $this->withToken($token)->postJson(cloudPath('sos'), cloudPayload('sos'))->assertTooManyRequests();
    $this->withToken($token)->postJson(cloudPath('alert'), cloudPayload('alert'))->assertCreated();

    Queue::assertPushed(SendSosNotifications::class, 1);
});
