<?php

use App\Enums\FamilyPermission;
use App\Models\BlindUser;
use App\Models\User;

test('filament login page is available', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertDontSee('Deprecated: Constant PDO::MYSQL_ATTR_SSL_CA');
});

test('verified active dashboard user is allowed into the panel', function () {
    $user = User::factory()->superAdmin()->create();

    expect($user->canAccessPanel(filament()->getCurrentPanel()))->toBeTrue();
});

test('authenticated panel dashboard renders the operational widgets', function () {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)->get('/admin')->assertOk();
});

test('super admins can render every resource index', function () {
    $user = User::factory()->superAdmin()->create();

    foreach (['blind-users', 'devices', 'alerts', 'safe-zones', 'ngos', 'users'] as $resource) {
        $this->actingAs($user)->get('/admin/'.$resource)->assertOk();
    }
});

test('family dashboard is scoped to linked users', function () {
    $family = User::factory()->family()->create();
    $linked = BlindUser::factory()->create(['name' => 'Linked Person']);
    BlindUser::factory()->create(['name' => 'Hidden Person']);
    $family->blindUsers()->attach($linked, [
        'relationship' => 'caregiver',
        'permissions' => [
            FamilyPermission::ViewLocation->value => true,
            FamilyPermission::ReceiveAlerts->value => true,
            FamilyPermission::ManageSafeZones->value => false,
        ],
    ]);

    $this->actingAs($family)->get('/admin/blind-users')
        ->assertOk()
        ->assertSee('Linked Person')
        ->assertDontSee('Hidden Person');
});

test('resource create pages render their domain forms', function () {
    $user = User::factory()->superAdmin()->create();

    foreach (['blind-users/create', 'devices/create', 'safe-zones/create', 'users/create'] as $page) {
        $this->actingAs($user)->get('/admin/'.$page)->assertOk();
    }
});

test('analytics page renders scoped metrics', function () {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)->get('/admin/analytics')->assertOk();
});
