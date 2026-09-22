<?php

use App\Enums\NavigationStatus;
use App\Events\LocationUpdated;
use App\Models\Device;
use App\Models\Location;
use App\Models\NavigationSession;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    Http::preventStrayRequests();
    config(['services.google_maps.api_key' => 'private-maps-key', 'services.navigation.provider' => 'google']);
});

function mapsPlaceFixture(string $language = 'en'): array
{
    return ['id' => 'test-destination', 'displayName' => ['text' => $language === 'ar' ? 'وجهة الاختبار' : 'Test destination'], 'formattedAddress' => 'Synthetic test address', 'location' => ['latitude' => 30.001, 'longitude' => 31.0]];
}

function mapsRouteFixture(string $language = 'en'): array
{
    return ['routes' => [[
        'distanceMeters' => 111, 'duration' => '90s',
        'warnings' => ['Test pedestrian warning'],
        'polyline' => ['geoJsonLinestring' => ['type' => 'LineString', 'coordinates' => [[31.0, 30.0], [31.0, 30.001]]]],
        'legs' => [['steps' => [[
            'distanceMeters' => 111,
            'navigationInstruction' => ['instructions' => $language === 'ar' ? 'امشِ للأمام' : '<b>Walk</b> straight'],
            'endLocation' => ['latLng' => ['latitude' => 30.001, 'longitude' => 31.0]],
        ]]]],
    ]]];
}

function navigationToken(Device $device): string
{
    return $device->createToken('navigation-test', ['device:navigation'])->plainTextToken;
}

function navigationLocationPayload(array $overrides = []): array
{
    return [...['lat' => 30.0001, 'lng' => 31, 'accuracy' => 5, 'timestamp' => now()->toISOString(), 'message_id' => (string) Str::uuid()], ...$overrides];
}

test('navigation endpoints enforce authentication and abilities', function (string $method, string $endpoint) {
    $this->{$method}('/api/device/navigation/'.$endpoint)->assertUnauthorized();
    $device = Device::factory()->create();
    $this->withToken($device->createToken('wrong', ['device:location'])->plainTextToken)
        ->{$method}('/api/device/navigation/'.$endpoint)->assertForbidden();
    $this->app['auth']->forgetGuards();
    $user = User::factory()->create();
    $this->withToken($user->createToken('user', ['*'])->plainTextToken)
        ->{$method}('/api/device/navigation/'.$endpoint)->assertUnauthorized();
    Http::assertNothingSent();
})->with([['getJson', 'search'], ['postJson', 'start'], ['getJson', 'active'], ['postJson', 'update'], ['postJson', 'stop']]);

test('search uses Places API and passes the selected language', function (string $language) {
    $device = Device::factory()->create();
    Http::fake(['https://places.googleapis.com/v1/places:searchText' => Http::response(['places' => [mapsPlaceFixture($language)]])]);

    $this->withToken(navigationToken($device))->withHeader('Accept-Language', $language)
        ->getJson('/api/device/navigation/search?query=Library')->assertOk()
        ->assertJsonPath('data.0.place_id', 'test-destination')->assertDontSee('private-maps-key');
    Http::assertSent(fn (Request $request): bool => $request['languageCode'] === $language && $request['textQuery'] === 'Library' && $request->hasHeader('X-Goog-Api-Key', 'private-maps-key'));
})->with(['en', 'ar']);

test('search returns an empty list when Google has no matches', function () {
    $device = Device::factory()->create();
    Http::fake(['https://places.googleapis.com/v1/places:searchText' => Http::response([])]);
    $this->withToken(navigationToken($device))->getJson('/api/device/navigation/search?query=Unknown')->assertOk()->assertJsonPath('data', []);
});

test('navigation starts from trusted stored GPS and retries do not create or bill another route', function (string $language) {
    $this->freezeTime();
    $device = Device::factory()->create();
    $location = Location::factory()->for($device)->create(['lat' => 30, 'lng' => 31, 'accuracy' => 5, 'recorded_at' => now()]);
    Http::fake([
        'https://places.googleapis.com/v1/places/test-destination*' => Http::response(mapsPlaceFixture($language)),
        'https://routes.googleapis.com/directions/v2:computeRoutes' => Http::response(mapsRouteFixture($language)),
    ]);
    $this->withToken(navigationToken($device));
    $response = $this->postJson('/api/device/navigation/start', ['destination_place_id' => 'test-destination', 'language' => $language])
        ->assertCreated()->assertJsonPath('data.distance_meters', 111)
        ->assertJsonPath('data.instruction', $language === 'ar' ? 'امشِ للأمام' : 'Walk straight')
        ->assertJsonPath('data.status', 'active')->assertDontSee('private-maps-key');
    $this->postJson('/api/device/navigation/start', ['destination_place_id' => 'test-destination'])
        ->assertOk()->assertJsonPath('data.session_id', $response->json('data.session_id'));
    $this->assertDatabaseCount('navigation_sessions', 1);
    $this->assertDatabaseHas('navigation_sessions', ['origin_location_id' => $location->id, 'blind_user_id' => $device->blind_user_id, 'device_id' => $device->id]);
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'computeRoutes') && $request['travelMode'] === 'WALK' && $request['languageCode'] === $language && $request['origin']['location']['latLng']['latitude'] === 30.0);
})->with(['en', 'ar']);

test('navigation requires recent accurate GPS belonging to the current assignment', function (string $problem) {
    $this->freezeTime();
    $device = Device::factory()->create();
    if ($problem !== 'missing') {
        Location::factory()->for($device)->create([
            'recorded_at' => match ($problem) {
                'stale' => now()->subMinutes(5), 'future' => now()->addMinute(), default => now()
            },
            'accuracy' => match ($problem) {
                'inaccurate' => 80, 'unknown' => null, default => 5
            },
            'blind_user_id' => $problem === 'reassigned' ? Device::factory()->create()->blind_user_id : $device->blind_user_id,
        ]);
    }
    $this->withToken(navigationToken($device))->postJson('/api/device/navigation/start', ['destination_place_id' => 'test-destination'])
        ->assertUnprocessable()->assertJsonPath('code', 'recent_location_required');
    $this->assertDatabaseCount('navigation_sessions', 0);
    Http::assertNothingSent();
})->with(['missing', 'stale', 'future', 'inaccurate', 'unknown', 'reassigned']);

test('Google failures do not create a navigation session', function (string $failure) {
    $device = Device::factory()->create();
    Location::factory()->for($device)->create(['recorded_at' => now(), 'accuracy' => 5]);
    Http::fake([
        'https://places.googleapis.com/v1/places/test-destination*' => Http::response(mapsPlaceFixture()),
        'https://routes.googleapis.com/directions/v2:computeRoutes' => match ($failure) {
            'timeout' => Http::failedConnection(),
            'server' => Http::response(['secret' => 'private-maps-key'], 500),
            'malformed' => Http::response(['routes' => [['distanceMeters' => 10]]]),
            'no_route' => Http::response([]),
        },
    ]);
    $this->withToken(navigationToken($device))->postJson('/api/device/navigation/start', ['destination_place_id' => 'test-destination'])
        ->assertStatus(match ($failure) {
            'malformed' => 502, 'no_route' => 422, default => 503
        })->assertDontSee('private-maps-key');
    $this->assertDatabaseCount('navigation_sessions', 0);
})->with(['timeout', 'server', 'malformed', 'no_route']);

test('missing Google credentials fail without a remote request', function () {
    config(['services.google_maps.api_key' => null]);
    $device = Device::factory()->create();
    $this->withToken(navigationToken($device))->getJson('/api/device/navigation/search?query=Library')
        ->assertStatus(503)->assertJsonPath('code', 'provider_not_configured');
    Http::assertNothingSent();
});

test('active sessions can only be cancelled by their own device', function () {
    $session = NavigationSession::factory()->create();
    $otherDevice = Device::factory()->create();
    $this->withToken(navigationToken($otherDevice));
    $this->getJson('/api/device/navigation/active')->assertOk()->assertJsonPath('data', null);
    $this->postJson('/api/device/navigation/stop', ['session_id' => $session->id])->assertUnprocessable();
    $this->postJson('/api/device/navigation/stop')->assertOk()->assertJsonPath('data', null);
    expect($session->refresh()->status)->toBe(NavigationStatus::Active);
    $this->app['auth']->forgetGuards();
    $this->withToken(navigationToken($session->device));
    $this->getJson('/api/device/navigation/active')->assertJsonPath('data.session_id', $session->id);
    $this->postJson('/api/device/navigation/stop')->assertOk()->assertJsonPath('data.status', 'cancelled');
    expect($session->refresh()->cancelled_at)->not->toBeNull();
    $this->getJson('/api/device/navigation/active')->assertJsonPath('data', null);
    Http::assertNothingSent();
});

test('navigation update rejects invalid and client-owned GPS identity fields', function (string $field, mixed $value) {
    $session = NavigationSession::factory()->create();
    $this->withToken(navigationToken($session->device))
        ->postJson('/api/device/navigation/update', navigationLocationPayload([$field => $value]))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('locations', 1);
    Http::assertNothingSent();
})->with([
    ['lat', 91], ['lng', -181], ['accuracy', -1], ['accuracy', 51], ['accuracy', null],
    ['timestamp', '2100-01-01T00:00:00Z'], ['timestamp', '2000-01-01T00:00:00Z'], ['timestamp', 'invalid'],
    ['device_id', 999], ['blind_user_id', 999], ['session_id', 999], ['received_at', '2026-01-01'],
]);

test('navigation progress reuses GPS records and broadcasts and completes near the last step', function () {
    $this->freezeTime();
    $session = NavigationSession::factory()->create();
    Event::fake([LocationUpdated::class]);
    $this->travel(10)->seconds();
    $payload = navigationLocationPayload(['lat' => 30.001]);
    $this->withToken(navigationToken($session->device))->postJson('/api/device/navigation/update', $payload)
        ->assertOk()->assertJsonPath('data.status', 'completed')->assertJsonPath('data.instruction', null);
    expect($session->refresh()->completed_at)->not->toBeNull();
    $this->assertDatabaseCount('locations', 2);
    $this->assertDatabaseHas('locations', ['message_id' => $payload['message_id'], 'device_id' => $session->device_id]);
    Event::assertDispatched(LocationUpdated::class, fn (LocationUpdated $event): bool => $event->location->message_id === $payload['message_id']);
    Http::assertNothingSent();
});

test('on-route updates and duplicate messages do not request new Google routes', function () {
    $this->freezeTime();
    $session = NavigationSession::factory()->create();
    $this->travel(10)->seconds();
    $payload = navigationLocationPayload();
    $this->withToken(navigationToken($session->device));
    $this->postJson('/api/device/navigation/update', $payload)->assertOk()->assertJsonPath('data.status', 'active');
    $this->postJson('/api/device/navigation/update', $payload)->assertOk();
    $this->assertDatabaseCount('locations', 2);
    Http::assertNothingSent();
});

test('deviation recalculates only after the cooldown', function () {
    $this->freezeTime();
    $session = NavigationSession::factory()->create();
    Http::fake(['https://routes.googleapis.com/directions/v2:computeRoutes' => Http::response(mapsRouteFixture())]);
    $this->withToken(navigationToken($session->device));
    $this->travel(10)->seconds();
    $this->postJson('/api/device/navigation/update', navigationLocationPayload(['lng' => 31.01]))
        ->assertOk()->assertJsonPath('data.off_route', true)->assertJsonPath('data.instruction', null);
    Http::assertNothingSent();
    $this->travel(60)->seconds();
    $this->postJson('/api/device/navigation/update', navigationLocationPayload(['lng' => 31.01]))->assertOk();
    $this->travel(10)->seconds();
    $this->postJson('/api/device/navigation/update', navigationLocationPayload(['lng' => 31.01]))->assertOk();
    Http::assertSentCount(1);
});

test('failed reroutes preserve GPS and session and throttle subsequent attempts', function () {
    $this->freezeTime();
    $session = NavigationSession::factory()->create();
    Http::fake(['https://routes.googleapis.com/directions/v2:computeRoutes' => Http::response([], 503)]);
    $this->withToken(navigationToken($session->device));
    $this->travel(61)->seconds();
    $this->postJson('/api/device/navigation/update', navigationLocationPayload(['lng' => 31.01]))->assertStatus(503);
    expect($session->refresh()->status)->toBe(NavigationStatus::Active)->and($session->off_route)->toBeTrue();
    $this->travel(10)->seconds();
    $this->postJson('/api/device/navigation/update', navigationLocationPayload(['lng' => 31.01]))->assertOk()->assertJsonPath('data.off_route', true);
    Http::assertSentCount(1);
    $this->assertDatabaseCount('locations', 3);
});

test('new assignments cannot read or stop the previous persons navigation', function () {
    $session = NavigationSession::factory()->create();
    $device = $session->device;
    $device->update(['blind_user_id' => Device::factory()->create()->blind_user_id]);
    $this->withToken(navigationToken($device))->getJson('/api/device/navigation/active')->assertJsonPath('data', null);
    $this->postJson('/api/device/navigation/stop')->assertJsonPath('data', null);
    expect($session->refresh()->status)->toBe(NavigationStatus::Active);
});

test('concurrent navigation mutations are rejected while a device lock is held', function () {
    $device = Device::factory()->create();
    $lock = Cache::lock('device-navigation:'.$device->id, 90);
    $lock->get();
    try {
        $this->withToken(navigationToken($device))->postJson('/api/device/navigation/start', ['destination_place_id' => 'test-destination'])
            ->assertConflict()->assertJsonPath('code', 'navigation_busy');
    } finally {
        $lock->release();
    }
    Http::assertNothingSent();
});

test('navigation refuses a different destination until the active session is stopped', function () {
    $session = NavigationSession::factory()->create();
    $this->withToken(navigationToken($session->device))->postJson('/api/device/navigation/start', ['destination_place_id' => 'different-place'])
        ->assertConflict()->assertJsonPath('code', 'navigation_already_active');
    $this->assertDatabaseCount('navigation_sessions', 1);
    Http::assertNothingSent();
});

test('navigation update without an active session does not store GPS', function () {
    $device = Device::factory()->create();
    $this->withToken(navigationToken($device))->postJson('/api/device/navigation/update', navigationLocationPayload())
        ->assertNotFound()->assertJsonPath('code', 'navigation_not_active');
    $this->assertDatabaseCount('locations', 0);
});

test('out of order navigation fixes cannot rewind progress', function () {
    $this->freezeTime();
    $session = NavigationSession::factory()->create();
    $this->travel(30)->seconds();
    $this->withToken(navigationToken($session->device))->postJson('/api/device/navigation/update', navigationLocationPayload())->assertOk();
    $latestLocationId = $session->refresh()->last_location_id;
    $this->postJson('/api/device/navigation/update', navigationLocationPayload(['lat' => 30.001, 'timestamp' => now()->subSeconds(15)->toISOString()]))
        ->assertOk()->assertJsonPath('data.status', 'active');
    expect($session->refresh()->last_location_id)->toBe($latestLocationId);
    Http::assertNothingSent();
});

test('navigation start rejects a spoofed identity origin or malformed place id', function (string $field, mixed $value) {
    $device = Device::factory()->create();
    $this->withToken(navigationToken($device))->postJson('/api/device/navigation/start', ['destination_place_id' => 'test-destination', $field => $value])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    Http::assertNothingSent();
})->with([['device_id', 1], ['blind_user_id', 1], ['origin', ['lat' => 1, 'lng' => 2]], ['lat', 30], ['destination_place_id', '../other?key=secret'], ['language', 'fr']]);

test('search rejects missing queries and limits paid requests per device', function () {
    config(['smart-cane.rate_limits.navigation-search' => 1]);
    $device = Device::factory()->create();
    $this->withToken(navigationToken($device))->getJson('/api/device/navigation/search')->assertUnprocessable();
    $this->getJson('/api/device/navigation/search?query=Library')->assertTooManyRequests();
    Http::assertNothingSent();
});

test('Google destination errors do not produce fabricated places', function (int $status) {
    $device = Device::factory()->create();
    Location::factory()->for($device)->create(['recorded_at' => now(), 'accuracy' => 5]);
    Http::fake(['https://places.googleapis.com/v1/places/test-destination*' => Http::response(['error' => ['message' => 'private-maps-key']], $status)]);
    $this->withToken(navigationToken($device))->postJson('/api/device/navigation/start', ['destination_place_id' => 'test-destination'])
        ->assertStatus($status === 404 ? 422 : 503)->assertDontSee('private-maps-key');
    $this->assertDatabaseCount('navigation_sessions', 0);
})->with([404, 403, 429, 500]);

test('device reassignment during provider calls prevents storing an old assignment session', function () {
    $device = Device::factory()->create();
    $newPerson = Device::factory()->create()->blind_user_id;
    Location::factory()->for($device)->create(['recorded_at' => now(), 'accuracy' => 5]);
    Http::fake([
        'https://places.googleapis.com/v1/places/test-destination*' => Http::response(mapsPlaceFixture()),
        'https://routes.googleapis.com/directions/v2:computeRoutes' => function () use ($device, $newPerson) {
            Device::whereKey($device->id)->update(['blind_user_id' => $newPerson]);

            return Http::response(mapsRouteFixture());
        },
    ]);
    $this->withToken(navigationToken($device))->postJson('/api/device/navigation/start', ['destination_place_id' => 'test-destination'])
        ->assertConflict()->assertJsonPath('code', 'device_assignment_changed');
    $this->assertDatabaseCount('navigation_sessions', 0);
});
