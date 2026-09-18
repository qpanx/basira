<?php

use App\Actions\RecordDeviceLocation;
use App\Enums\FamilyPermission;
use App\Events\SafeZoneEntered;
use App\Events\SafeZoneExited;
use App\Models\BlindUser;
use App\Models\Device;
use App\Models\Ngo;
use App\Models\SafeZone;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

test('family scope only returns linked blind users and permitted alerts', function () {
    $ngo = Ngo::factory()->create();
    $family = User::factory()->family()->create();
    $linked = BlindUser::factory()->for($ngo)->create();
    $unlinked = BlindUser::factory()->for($ngo)->create();
    Device::factory()->for($linked)->create();
    $family->blindUsers()->attach($linked, [
        'relationship' => 'daughter',
        'permissions' => [
            FamilyPermission::ViewLocation->value => true,
            FamilyPermission::ReceiveAlerts->value => true,
            FamilyPermission::ManageSafeZones->value => false,
        ],
    ]);

    expect(BlindUser::query()->accessibleTo($family)->pluck('id')->all())->toBe([$linked->getKey()])
        ->and(Device::query()->accessibleTo($family)->count())->toBe(1)
        ->and($family->canAccessBlindUser($unlinked))->toBeFalse();
});

test('ngo staff scope is limited to their organization', function () {
    $firstNgo = Ngo::factory()->create();
    $secondNgo = Ngo::factory()->create();
    $staff = User::factory()->ngoStaff($firstNgo)->create();
    $firstUser = BlindUser::factory()->for($firstNgo)->create();
    BlindUser::factory()->for($secondNgo)->create();

    expect(BlindUser::query()->accessibleTo($staff)->pluck('id')->all())->toBe([$firstUser->getKey()]);
});

test('safe-zone monitoring emits transitions only after an initial state', function () {
    Event::fake([SafeZoneEntered::class, SafeZoneExited::class]);
    $blindUser = BlindUser::factory()->create();
    $device = Device::factory()->for($blindUser)->create(['last_location_recorded_at' => null, 'geofence_evaluated_at' => null]);
    $zone = SafeZone::factory()->for($blindUser)->create([
        'center_lat' => 30.0444,
        'center_lng' => 31.2357,
        'radius_meters' => 100,
    ]);
    $action = app(RecordDeviceLocation::class);

    $action->handle($device, ['message_id' => (string) Str::uuid(), 'lat' => 30.0444, 'lng' => 31.2357, 'timestamp' => now()->subMinute()->toISOString()]);
    Event::assertNotDispatched(SafeZoneEntered::class);
    Event::assertNotDispatched(SafeZoneExited::class);

    $action->handle($device, ['message_id' => (string) Str::uuid(), 'lat' => 30.047, 'lng' => 31.2357, 'timestamp' => now()->toISOString()]);
    Event::assertDispatched(SafeZoneExited::class, fn (SafeZoneExited $event): bool => $event->safeZone->is($zone));
});
