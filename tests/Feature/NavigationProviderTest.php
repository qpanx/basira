<?php

use App\Contracts\DestinationProvider;
use App\Contracts\RoutingProvider;
use App\Enums\NavigationStatus;
use App\Events\LocationUpdated;
use App\Models\Device;
use App\Models\Location;
use App\Models\NavigationSession;
use App\Services\CoordinateDestinationProvider;
use App\Services\GoogleRoutingProvider;
use App\Services\OsrmRoutingProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    Http::preventStrayRequests();
    config([
        'services.navigation.provider' => 'osrm',
        'services.osrm.url' => 'https://pedestrian.test',
        'services.osrm.profile' => 'foot',
        'services.osrm.walking_enabled' => true,
        'services.google_maps.api_key' => null,
    ]);
});

function pedestrianRouteFixture(): array
{
    return ['code' => 'Ok', 'routes' => [[
        'distance' => 150.4, 'duration' => 110.2,
        'geometry' => ['type' => 'LineString', 'coordinates' => [[31, 30], [31, 30.0005], [31.001, 30.0005]]],
        'legs' => [['steps' => [
            ['distance' => 55.6, 'mode' => 'walking', 'name' => '', 'maneuver' => ['type' => 'depart', 'location' => [31, 30]], 'geometry' => ['type' => 'LineString', 'coordinates' => [[31, 30], [31, 30.0005]]]],
            ['distance' => 94.8, 'mode' => 'walking', 'name' => '', 'maneuver' => ['type' => 'turn', 'modifier' => 'right', 'location' => [31, 30.0005]], 'geometry' => ['type' => 'LineString', 'coordinates' => [[31, 30.0005], [31.001, 30.0005]]]],
            ['distance' => 0, 'mode' => 'walking', 'name' => '', 'maneuver' => ['type' => 'arrive', 'location' => [31.001, 30.0005]], 'geometry' => ['type' => 'LineString', 'coordinates' => [[31.001, 30.0005]]]],
        ]]],
    ]]];
}

function pedestrianDevice(): Device
{
    $device = Device::factory()->create();
    Location::factory()->for($device)->create(['recorded_at' => now(), 'lat' => 30, 'lng' => 31, 'accuracy' => 5]);

    return $device;
}

function pedestrianToken(Device $device): string
{
    return $device->createToken('pedestrian-test', ['device:navigation'])->plainTextToken;
}

function pedestrianFix(array $changes = []): array
{
    return [...['message_id' => (string) Str::uuid(), 'lat' => 30.0005, 'lng' => 31, 'accuracy' => 5, 'timestamp' => now()->toISOString()], ...$changes];
}

test('the selected provider resolves separate routing and destination contracts', function () {
    expect(app(RoutingProvider::class))->toBeInstanceOf(OsrmRoutingProvider::class)
        ->and(app(DestinationProvider::class))->toBeInstanceOf(CoordinateDestinationProvider::class);
    config(['services.navigation.provider' => 'google']);
    expect(app(RoutingProvider::class))->toBeInstanceOf(GoogleRoutingProvider::class)
        ->and(app(DestinationProvider::class))->toBeInstanceOf(GoogleRoutingProvider::class);
    Http::assertNothingSent();
});

test('OSRM destinations use caller coordinates without Google or a fabricated place name', function (string $query, float $latitude, float $longitude) {
    $device = Device::factory()->create();
    $this->withToken(pedestrianToken($device))->getJson('/api/device/navigation/search?'.http_build_query(['query' => $query]))
        ->assertOk()->assertJsonPath('data.0.latitude', $latitude)->assertJsonPath('data.0.longitude', $longitude)
        ->assertJsonPath('data.0.address', '')->assertJsonPath('meta.attribution', 'User-supplied coordinates');
    Http::assertNothingSent();
})->with([['30.0005,31.001', 30.0005, 31.001], [' -30.25, -31.5 ', -30.25, -31.5]]);

test('OSRM coordinate search rejects names and invalid coordinates', function (string $query) {
    $device = Device::factory()->create();
    $this->withToken(pedestrianToken($device))->getJson('/api/device/navigation/search?'.http_build_query(['query' => $query]))
        ->assertUnprocessable()->assertJsonPath('code', 'coordinate_destination_required');
    Http::assertNothingSent();
})->with(['Mansoura Library', '91,31', '30,181', 'NaN,31', 'https://internal.test']);

test('OSRM starts a localized walking session without Google credentials and preserves idempotency', function (string $language, string $instruction) {
    $this->freezeTime();
    $device = pedestrianDevice();
    Http::fake(['https://pedestrian.test/route/v1/foot/*' => Http::response(pedestrianRouteFixture())]);
    $this->withToken(pedestrianToken($device))->withHeader('Accept-Language', $language);
    $search = $this->getJson('/api/device/navigation/search?query=30.0005,31.001')->assertOk();
    $placeId = $search->json('data.0.place_id');

    $response = $this->postJson('/api/device/navigation/start', ['destination_place_id' => $placeId])
        ->assertCreated()->assertJsonPath('data.routing_provider', 'osrm')
        ->assertJsonPath('data.distance_meters', 150)->assertJsonPath('data.estimated_duration_seconds', 111)
        ->assertJsonPath('data.steps.1.instruction', $instruction)
        ->assertJsonPath('data.attribution', '© OpenStreetMap contributors');
    $this->postJson('/api/device/navigation/start', ['destination_place_id' => $placeId])->assertOk()->assertJsonPath('data.session_id', $response->json('data.session_id'));
    $session = NavigationSession::findOrFail($response->json('data.session_id'));
    expect($session->routing_provider)->toBe('osrm')->and($session->steps[0]['end_latitude'])->toBe(30.0005);
    $this->assertDatabaseCount('navigation_sessions', 1);
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && str_contains($request->url(), '/foot/31.0000000,30.0000000;31.0010000,30.0005000')
        && $request['steps'] === 'true' && $request['geometries'] === 'geojson' && $request['overview'] === 'full'
        && $request['radiuses'] === '50;50' && ! $request->hasHeader('X-Goog-Api-Key'));
})->with([['en', 'Turn right.'], ['ar', 'انعطف يميناً.']]);

test('OSRM rejects unconfirmed pedestrian backends before making an HTTP request', function () {
    $device = pedestrianDevice();
    config(['services.osrm.walking_enabled' => false]);
    $this->withToken(pedestrianToken($device))->postJson('/api/device/navigation/start', ['destination_place_id' => 'coordinates_300005000_310010000'])
        ->assertStatus(503)->assertJsonPath('code', 'walking_routing_unavailable');
    $this->assertDatabaseCount('navigation_sessions', 0);
    Http::assertNothingSent();
});

test('OSRM never relabels driving or mixed mode routes as walking even on the foot URL', function (string $mode) {
    $device = pedestrianDevice();
    $data = pedestrianRouteFixture();
    $data['routes'][0]['legs'][0]['steps'][1]['mode'] = $mode;
    Http::fake(['https://pedestrian.test/route/v1/foot/*' => Http::response($data)]);
    $this->withToken(pedestrianToken($device))->postJson('/api/device/navigation/start', ['destination_place_id' => 'coordinates_300005000_310010000'])
        ->assertStatus(503)->assertJsonPath('code', 'walking_routing_unavailable');
    $this->assertDatabaseCount('navigation_sessions', 0);
})->with(['driving', 'cycling', 'ferry']);

test('provider and backend misconfiguration do not silently fall back to another service', function (string $key, mixed $value, string $error) {
    $device = pedestrianDevice();
    config([$key => $value]);
    $this->withToken(pedestrianToken($device))->postJson('/api/device/navigation/start', ['destination_place_id' => 'coordinates_300005000_310010000'])
        ->assertStatus(503)->assertJsonPath('code', $error);
    Http::assertNothingSent();
})->with([
    ['services.navigation.provider', 'unknown', 'navigation_provider_invalid'],
    ['services.osrm.url', null, 'provider_not_configured'],
    ['services.osrm.url', 'file:///tmp/map', 'provider_not_configured'],
    ['services.osrm.profile', '../driving', 'walking_routing_unavailable'],
]);

test('OSRM rejects Google and malformed coordinate place ids without contacting a provider', function (string $placeId) {
    $device = pedestrianDevice();
    $this->withToken(pedestrianToken($device))->postJson('/api/device/navigation/start', ['destination_place_id' => $placeId])
        ->assertUnprocessable()->assertJsonPath('code', 'destination_not_found');
    Http::assertNothingSent();
})->with(['google-place', 'coordinates_910000000_310000000', 'coordinates_300000000_1810000000']);

test('OSRM no-route and snapping failures return 422 without a session', function (string $code, int $status) {
    $device = pedestrianDevice();
    Http::fake(['https://pedestrian.test/route/v1/foot/*' => Http::response(['code' => $code], $status)]);
    $this->withToken(pedestrianToken($device))->postJson('/api/device/navigation/start', ['destination_place_id' => 'coordinates_300005000_310010000'])
        ->assertUnprocessable()->assertJsonPath('code', 'route_not_found');
    $this->assertDatabaseCount('navigation_sessions', 0);
})->with([['NoRoute', 200], ['NoRoute', 400], ['NoSegment', 400]]);

test('OSRM failures and invalid payloads return sanitized provider errors', function (string $failure, int $status) {
    $device = pedestrianDevice();
    $body = pedestrianRouteFixture();
    if ($failure === 'geometry') {
        $body['routes'][0]['geometry']['coordinates'][0][1] = 100;
    }
    if ($failure === 'missing_mode') {
        unset($body['routes'][0]['legs'][0]['steps'][0]['mode']);
    }
    Http::fake(['https://pedestrian.test/route/v1/foot/*' => match ($failure) {
        'timeout' => Http::failedConnection(),
        'server' => Http::response('private provider error', 500),
        'redirect' => Http::response('', 302, ['Location' => 'https://another.test']),
        'json' => Http::response('not json', 200),
        default => Http::response($body),
    }]);
    $this->withToken(pedestrianToken($device))->postJson('/api/device/navigation/start', ['destination_place_id' => 'coordinates_300005000_310010000'])
        ->assertStatus($status)->assertJsonPath('success', false)->assertDontSee('private provider error');
    $this->assertDatabaseCount('navigation_sessions', 0);
})->with([['timeout', 503], ['server', 503], ['redirect', 503], ['json', 502], ['geometry', 502], ['missing_mode', 502]]);

test('OSRM GPS progress uses step endpoints and retains existing broadcasts and completion', function () {
    $this->freezeTime();
    $device = pedestrianDevice();
    Event::fake([LocationUpdated::class]);
    Http::fake(['https://pedestrian.test/route/v1/foot/*' => Http::response(pedestrianRouteFixture())]);
    $this->withToken(pedestrianToken($device))->postJson('/api/device/navigation/start', ['destination_place_id' => 'coordinates_300005000_310010000'])->assertCreated();
    $this->travel(10)->seconds();
    $this->postJson('/api/device/navigation/update', pedestrianFix())->assertOk()->assertJsonPath('data.current_step_index', 1)->assertJsonPath('data.instruction', 'Turn right.');
    $this->travel(10)->seconds();
    $this->postJson('/api/device/navigation/update', pedestrianFix(['lng' => 31.001]))->assertOk()->assertJsonPath('data.status', 'completed');
    $this->assertDatabaseCount('locations', 3);
    Event::assertDispatchedTimes(LocationUpdated::class, 2);
    Http::assertSentCount(1);
    expect(NavigationSession::firstOrFail()->completed_at)->not->toBeNull();
});

test('OSRM sessions retain their provider after configuration switches and reroutes keep the cooldown', function () {
    $this->freezeTime();
    $device = pedestrianDevice();
    Http::fake(['https://pedestrian.test/route/v1/foot/*' => Http::response(pedestrianRouteFixture())]);
    $this->withToken(pedestrianToken($device))->postJson('/api/device/navigation/start', ['destination_place_id' => 'coordinates_300005000_310010000'])->assertCreated();
    config(['services.navigation.provider' => 'google']);
    $this->getJson('/api/device/navigation/active')->assertOk()->assertJsonPath('data.attribution', '© OpenStreetMap contributors');
    $this->travel(61)->seconds();
    $this->postJson('/api/device/navigation/update', pedestrianFix(['lng' => 31.01]))->assertOk()->assertJsonPath('data.routing_provider', 'osrm');
    $this->travel(1)->seconds();
    $this->postJson('/api/device/navigation/update', pedestrianFix(['lng' => 31.01]))->assertOk();
    Http::assertSentCount(2);
    $this->postJson('/api/device/navigation/stop')->assertOk()->assertJsonPath('data.status', 'cancelled');
    expect(NavigationSession::firstOrFail()->cancelled_at)->not->toBeNull();
});

test('existing Google sessions still reroute with Google after selecting OSRM', function () {
    $this->freezeTime();
    $session = NavigationSession::factory()->create();
    config(['services.google_maps.api_key' => 'test-key']);
    Http::fake(['https://routes.googleapis.com/directions/v2:computeRoutes' => Http::response([
        'routes' => [['distanceMeters' => 100, 'duration' => '90s',
            'polyline' => ['geoJsonLinestring' => ['coordinates' => [[31.01, 30.0005], [31, 30.001]]]],
            'legs' => [['steps' => [['distanceMeters' => 100, 'navigationInstruction' => ['instructions' => 'Continue walking'], 'endLocation' => ['latLng' => ['latitude' => 30.001, 'longitude' => 31]]]]]],
        ]],
    ])]);
    $this->travel(61)->seconds();
    $this->withToken(pedestrianToken($session->device))->postJson('/api/device/navigation/update', pedestrianFix(['lng' => 31.01]))
        ->assertOk()->assertJsonPath('data.routing_provider', 'google')->assertJsonPath('data.attribution', 'Google Maps');
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request['travelMode'] === 'WALK' && $request['destination']['placeId'] === 'test-destination');
});

test('failed OSRM rerouting retains history GPS and rate limits subsequent attempts', function () {
    $this->freezeTime();
    $device = pedestrianDevice();
    Http::fake(['https://pedestrian.test/route/v1/foot/*' => Http::sequence()->push(pedestrianRouteFixture())->push([], 503)]);
    $this->withToken(pedestrianToken($device))->postJson('/api/device/navigation/start', ['destination_place_id' => 'coordinates_300005000_310010000'])->assertCreated();
    $this->travel(61)->seconds();
    $this->postJson('/api/device/navigation/update', pedestrianFix(['lng' => 31.01]))->assertStatus(503);
    $this->travel(1)->seconds();
    $this->postJson('/api/device/navigation/update', pedestrianFix(['lng' => 31.01]))->assertOk()->assertJsonPath('data.off_route', true)->assertJsonPath('data.instruction', null);
    expect(NavigationSession::firstOrFail()->status)->toBe(NavigationStatus::Active);
    $this->assertDatabaseCount('locations', 3);
    Http::assertSentCount(2);
});

test('OSRM preserves cross-device isolation and navigation abilities', function () {
    $session = NavigationSession::factory()->create(['routing_provider' => 'osrm']);
    $other = Device::factory()->create();
    $this->withToken($other->createToken('wrong', ['device:location'])->plainTextToken)->getJson('/api/device/navigation/active')->assertForbidden();
    $this->app['auth']->forgetGuards();
    $this->withToken(pedestrianToken($other))->getJson('/api/device/navigation/active')->assertJsonPath('data', null);
    $this->postJson('/api/device/navigation/stop')->assertJsonPath('data', null);
    expect($session->refresh()->status)->toBe(NavigationStatus::Active);
    Http::assertNothingSent();
});

test('OSRM roundabout instructions use the supplied exit and do not invent missing maneuvers', function (bool $hasExit) {
    $device = pedestrianDevice();
    $data = pedestrianRouteFixture();
    $data['routes'][0]['legs'][0]['steps'][1]['maneuver'] = ['type' => 'roundabout', ...($hasExit ? ['exit' => 2] : [])];
    Http::fake(['https://pedestrian.test/route/v1/foot/*' => Http::response($data)]);
    $response = $this->withToken(pedestrianToken($device))->postJson('/api/device/navigation/start', ['destination_place_id' => 'coordinates_300005000_310010000']);
    if ($hasExit) {
        $response->assertCreated()->assertJsonPath('data.steps.1.instruction', 'Follow the pedestrian route around the roundabout to exit 2.');
    } else {
        $response->assertUnprocessable()->assertJsonPath('code', 'route_instructions_unavailable');
        $this->assertDatabaseCount('navigation_sessions', 0);
    }
})->with([true, false]);

test('OSRM rejects routes with no steps or an invalid step geometry', function (string $problem) {
    $device = pedestrianDevice();
    $data = pedestrianRouteFixture();
    if ($problem === 'no_steps') {
        $data['routes'][0]['legs'][0]['steps'] = [];
    } else {
        $data['routes'][0]['legs'][0]['steps'][0]['geometry']['coordinates'] = [];
    }
    Http::fake(['https://pedestrian.test/route/v1/foot/*' => Http::response($data)]);
    $this->withToken(pedestrianToken($device))->postJson('/api/device/navigation/start', ['destination_place_id' => 'coordinates_300005000_310010000'])
        ->assertStatus(502)->assertJsonPath('code', 'invalid_provider_response');
    $this->assertDatabaseCount('navigation_sessions', 0);
})->with(['no_steps', 'empty_geometry']);

test('OSRM malformed associative legs return 502 instead of an internal error', function () {
    $device = pedestrianDevice();
    $data = pedestrianRouteFixture();
    $data['routes'][0]['legs'] = ['unexpected' => $data['routes'][0]['legs'][0]];
    Http::fake(['https://pedestrian.test/route/v1/foot/*' => Http::response($data)]);
    $this->withToken(pedestrianToken($device))->postJson('/api/device/navigation/start', ['destination_place_id' => 'coordinates_300005000_310010000'])
        ->assertStatus(502)->assertJsonPath('code', 'invalid_provider_response');
    $this->assertDatabaseCount('navigation_sessions', 0);
});

test('the additive migration marks existing history as Google even when OSRM is selected', function () {
    $migration = require database_path('migrations/2026_09_21_211514_add_routing_provider_to_navigation_sessions_table.php');
    $migration->down();
    $session = NavigationSession::factory()->cancelled()->create();

    $migration->up();

    expect($session->refresh()->routing_provider)->toBe('google')
        ->and($session->status)->toBe(NavigationStatus::Cancelled)
        ->and($session->destination_place_id)->toBe('test-destination');
});
