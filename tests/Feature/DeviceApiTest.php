<?php

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Enums\FamilyPermission;
use App\Models\Alert;
use App\Models\BlindUser;
use App\Models\Device;
use App\Models\Location;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Support\Str;

function deviceToken(Device $device, array $abilities = ['device:heartbeat', 'device:location', 'device:alert', 'device:arrive', 'device:config']): string
{
    return $device->createToken('test-device', $abilities)->plainTextToken;
}

test('device can record an idempotent heartbeat', function () {
    $device = Device::factory()->create();
    $messageId = (string) Str::uuid();
    $token = deviceToken($device, ['device:heartbeat']);

    $payload = [
        'message_id' => $messageId,
        'battery_level' => 74,
        'firmware_version' => '2.0.1',
        'power_state' => 'discharging',
    ];

    $this->withToken($token)->postJson('/api/device/heartbeat', $payload)->assertOk();
    $this->withToken($token)->postJson('/api/device/heartbeat', $payload)->assertOk();

    expect($device->refresh()->battery_level)->toBe(74)
        ->and($device->batteryLogs()->count())->toBe(1);
});

test('device location is scoped to its token and persists a history row', function () {
    $device = Device::factory()->create();
    $token = deviceToken($device, ['device:location']);

    $response = $this->withToken($token)->postJson('/api/device/location', [
        'message_id' => (string) Str::uuid(),
        'lat' => 30.0444,
        'lng' => 31.2357,
        'accuracy' => 8.5,
        'timestamp' => now()->toISOString(),
    ]);

    $response->assertCreated()->assertJsonPath('data.recorded_at', fn ($value): bool => is_string($value));
    expect(Location::query()->where('device_id', $device->getKey())->count())->toBe(1);
});

test('device alert validation rejects dashboard-owned fields', function () {
    $device = Device::factory()->create();
    $token = deviceToken($device, ['device:alert']);

    $this->withToken($token)->postJson('/api/device/alert', [
        'type' => AlertType::ObstacleFront->value,
        'severity' => AlertSeverity::Warning->value,
        'device_id' => $device->getKey(),
    ])->assertUnprocessable();

    expect(Alert::query()->count())->toBe(0);
});

test('device SOS alert is accepted and remains idempotent', function () {
    $device = Device::factory()->create();
    $token = deviceToken($device, ['device:alert']);
    $messageId = (string) Str::uuid();
    $payload = [
        'message_id' => $messageId,
        'type' => AlertType::Sos->value,
        'severity' => AlertSeverity::Critical->value,
        'lat' => 30.0444,
        'lng' => 31.2357,
    ];

    $this->withToken($token)->postJson('/api/device/alert', $payload)->assertCreated();
    $this->withToken($token)->postJson('/api/device/alert', $payload)->assertOk();

    expect(Alert::query()->where('device_id', $device->getKey())->count())->toBe(1);
});

test('regular alerts create dashboard notifications for opted-in family members', function () {
    $blindUser = BlindUser::factory()->create();
    $family = User::factory()->family()->create();
    $family->blindUsers()->attach($blindUser, [
        'relationship' => 'caregiver',
        'permissions' => [
            FamilyPermission::ViewLocation->value => true,
            FamilyPermission::ReceiveAlerts->value => true,
            FamilyPermission::ManageSafeZones->value => false,
        ],
    ]);
    $device = Device::factory()->for($blindUser)->create();

    $this->withToken(deviceToken($device, ['device:alert']))
        ->postJson('/api/device/alert', [
            'type' => AlertType::ObstacleFront->value,
            'severity' => AlertSeverity::Warning->value,
            'distance_cm' => 120,
        ])
        ->assertCreated();

    expect($family->notifications()->count())->toBe(1);
});

test('SOS dispatch records SMS and voice delivery outcomes', function () {
    $blindUser = BlindUser::factory()->create();
    $family = User::factory()->family()->create(['phone' => '+201011234567']);
    $family->blindUsers()->attach($blindUser, [
        'relationship' => 'caregiver',
        'permissions' => [
            FamilyPermission::ViewLocation->value => true,
            FamilyPermission::ReceiveAlerts->value => true,
            FamilyPermission::ManageSafeZones->value => false,
        ],
    ]);
    $device = Device::factory()->for($blindUser)->create();

    $this->withToken(deviceToken($device, ['device:alert']))
        ->postJson('/api/device/alert', [
            'type' => AlertType::Sos->value,
            'severity' => AlertSeverity::Critical->value,
        ])
        ->assertCreated();

    expect($family->notificationDeliveries()->count())->toBe(2)
        ->and($family->notificationDeliveries()->where('status', 'delivered')->count())->toBe(2);
});

test('missing device token ability is forbidden', function () {
    $device = Device::factory()->create();

    $this->withToken(deviceToken($device, ['device:heartbeat']))
        ->postJson('/api/device/location', [
            'lat' => 30,
            'lng' => 31,
            'timestamp' => now()->toISOString(),
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'missing_token_ability');
});

test('api responses honor the requested locale', function () {
    $device = Device::factory()->create();

    $this->withToken(deviceToken($device, ['device:heartbeat']))
        ->withHeader('Accept-Language', 'ar')
        ->postJson('/api/device/heartbeat', [
            'battery_level' => 74,
            'firmware_version' => '2.0.1',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'تم تسجيل نبضة الجهاز.');
});

test('device configuration returns safe zones and supports conditional requests', function () {
    $device = Device::factory()->create();
    $response = $this->withToken(deviceToken($device, ['device:config']))->getJson('/api/device/config');

    $response->assertOk()->assertHeader('ETag')->assertJsonStructure([
        'data' => ['configuration_version', 'preferred_language', 'settings', 'safe_zones'],
    ]);
    $this->withToken(deviceToken($device, ['device:config']))
        ->withHeader('If-None-Match', $response->headers->get('ETag'))
        ->getJson('/api/device/config')
        ->assertStatus(304);
});

test('Twilio status callbacks update the delivery metric timestamps', function () {
    $delivery = NotificationDelivery::factory()->create([
        'provider' => 'twilio',
        'provider_reference' => 'SM-test-reference',
        'status' => 'accepted',
        'delivered_at' => null,
    ]);

    $this->post('/api/webhooks/twilio/status', [
        'MessageSid' => 'SM-test-reference',
        'MessageStatus' => 'delivered',
    ])->assertNoContent();

    expect($delivery->refresh()->status->value)->toBe('delivered')
        ->and($delivery->delivered_at)->not->toBeNull();
});
