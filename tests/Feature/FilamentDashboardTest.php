<?php

use App\Enums\FamilyPermission;
use App\Models\BlindUser;
use App\Models\User;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Cookie;

test('Livewire updates preserve the browser session and csrf token', function () {
    // Exercise CSRF middleware, which normally bypasses verification in tests.
    $this->app['env'] = 'local';
    $page = $this->get('/admin/login')->assertOk();
    $sessionId = session()->getId();
    $token = session()->token();
    $document = new DOMDocument;
    @$document->loadHTML($page->getContent());
    $xpath = new DOMXPath($document);
    $snapshot = $xpath->query('//*[@*[name()="wire:snapshot"]]')->item(0)->getAttribute('wire:snapshot');
    $cookies = collect($page->headers->getCookies())
        ->mapWithKeys(fn (Cookie $cookie): array => [$cookie->getName() => $cookie->getValue()])
        ->all();

    $this->withCredentials()->withUnencryptedCookies($cookies)->withHeader('X-Livewire', 'true')
        ->postJson('/livewire/update', [
            '_token' => $token,
            'components' => [['snapshot' => $snapshot, 'updates' => (object) [], 'calls' => []]],
        ])->assertOk();

    expect(session()->getId())->toBe($sessionId);
    expect(session()->token())->toBe($token);
});

test('expired Livewire requests return 419 without redirecting to the dashboard', function () {
    Route::post('/test/livewire-expired', function (): never {
        throw new TokenMismatchException;
    });

    $this->withHeaders([
        'X-Livewire' => 'true',
        'Referer' => url('/admin'),
        'Accept' => 'text/html',
    ])->post('/test/livewire-expired')
        ->assertStatus(419)
        ->assertHeaderMissing('Location');
});

test('Livewire updates without a csrf token are rejected', function () {
    $this->app['env'] = 'local';

    $this->withHeader('X-Livewire', 'true')
        ->postJson('/livewire/update', ['components' => []])
        ->assertStatus(419)
        ->assertHeaderMissing('Location');
});

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
