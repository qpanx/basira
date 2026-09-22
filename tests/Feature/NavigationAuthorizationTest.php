<?php

use App\Enums\FamilyPermission;
use App\Filament\Resources\NavigationSessionResource;
use App\Filament\Resources\NavigationSessionResource\Pages\ManageNavigationSessions;
use App\Models\BlindUser;
use App\Models\Device;
use App\Models\NavigationSession;
use App\Models\Ngo;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

test('caregiver navigation history requires the existing location permission', function (bool $permission) {
    $user = User::factory()->family()->create();
    $session = NavigationSession::factory()->create();
    $other = NavigationSession::factory()->create();
    $user->blindUsers()->attach($session->blind_user_id, ['relationship' => 'caregiver', 'permissions' => [FamilyPermission::ViewLocation->value => $permission]]);
    $this->actingAs($user);

    expect(Gate::allows('view', $session))->toBe($permission)
        ->and(Gate::allows('view', $other))->toBeFalse();
    $component = Livewire::test(ManageNavigationSessions::class)->assertSuccessful()->assertCanNotSeeTableRecords([$other]);
    if ($permission) {
        $component->assertCanSeeTableRecords([$session]);
    } else {
        $component->assertCanNotSeeTableRecords([$session]);
    }
})->with([true, false]);

test('NGO navigation history and policy exclude other organizations', function () {
    $ngo = Ngo::factory()->create();
    $user = User::factory()->ngoStaff($ngo)->create();
    $blindUser = BlindUser::factory()->for($ngo)->create();
    $device = Device::factory()->for($blindUser)->create();
    $session = NavigationSession::factory()->for($device)->create();
    $other = NavigationSession::factory()->create();
    $this->actingAs($user);

    expect(Gate::allows('view', $session))->toBeTrue()->and(Gate::allows('view', $other))->toBeFalse();
    Livewire::test(ManageNavigationSessions::class)->assertSuccessful()
        ->assertCanSeeTableRecords([$session])->assertCanNotSeeTableRecords([$other]);
});

test('NGO staff without an organization cannot see navigation history', function () {
    $user = User::factory()->ngoStaff()->create(['ngo_id' => null]);
    $session = NavigationSession::factory()->create();
    $this->actingAs($user);
    expect(Gate::allows('view', $session))->toBeFalse();
    Livewire::test(ManageNavigationSessions::class)->assertCanNotSeeTableRecords([$session]);
});

test('administrators see navigation history and can filter active sessions', function () {
    $user = User::factory()->superAdmin()->create();
    $active = NavigationSession::factory()->create();
    $cancelled = NavigationSession::factory()->cancelled()->create();
    $this->actingAs($user);

    expect(Gate::allows('view', $active))->toBeTrue()->and(NavigationSessionResource::canCreate())->toBeFalse();
    Livewire::test(ManageNavigationSessions::class)->assertCanSeeTableRecords([$active, $cancelled])
        ->filterTable('status', 'active')->assertCanSeeTableRecords([$active])->assertCanNotSeeTableRecords([$cancelled]);
});

test('navigation history page supports Arabic and escapes destination text', function () {
    $user = User::factory()->superAdmin()->create(['preferred_language' => 'ar']);
    NavigationSession::factory()->create(['destination' => ['name' => '<script>alert("destination")</script>', 'latitude' => 30, 'longitude' => 31]]);

    $this->actingAs($user)->get('/admin/navigation-sessions')->assertOk()
        ->assertSee('سجل الملاحة')->assertDontSee('<script>alert("destination")</script>', false);
});

test('navigation history remains assigned to the original person after device reassignment', function () {
    $session = NavigationSession::factory()->create();
    $newPerson = BlindUser::factory()->create();
    $user = User::factory()->family()->create();
    $user->blindUsers()->attach($newPerson, ['relationship' => 'caregiver', 'permissions' => [FamilyPermission::ViewLocation->value => true]]);
    $session->device->update(['blind_user_id' => $newPerson->id]);
    $this->actingAs($user);

    expect(Gate::allows('view', $session))->toBeFalse();
    Livewire::test(ManageNavigationSessions::class)->assertCanNotSeeTableRecords([$session]);
});
