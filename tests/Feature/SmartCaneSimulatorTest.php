<?php

use App\Enums\NavigationStatus;
use App\Filament\Pages\SmartCaneSimulator;
use App\Models\BlindUser;
use App\Models\Device;
use App\Models\Location;
use App\Models\NavigationSession;
use App\Models\Ngo;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Http::preventStrayRequests();
    config([
        'services.vision.url' => 'https://vision.test/detect',
        'services.ocr.url' => 'https://ocr.test/read',
        'services.osrm.url' => 'https://pedestrian.test',
        'services.osrm.profile' => 'foot',
        'services.osrm.walking_enabled' => true,
    ]);
    filament()->setCurrentPanel(filament()->getPanel('admin'));
});

function simulatorPedestrianRoute(): array
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

function fakeOsrmRoute(): void
{
    Http::fake(['https://pedestrian.test/route/v1/foot/*' => Http::response(simulatorPedestrianRoute())]);
}

function simulateAs(User $user): Testable
{
    test()->actingAs($user);

    return Livewire::test(SmartCaneSimulator::class);
}

test('guests are redirected to login and family caregivers cannot open the simulator', function () {
    $this->get('/admin/smart-cane-simulator')->assertRedirect();

    $family = User::factory()->family()->create();
    $this->actingAs($family)->get('/admin/smart-cane-simulator')->assertForbidden();
});

test('super admins and NGO staff can open the simulator page', function (string $state) {
    $user = User::factory()->{$state}()->create();

    $this->actingAs($user)->get('/admin/smart-cane-simulator')
        ->assertOk()
        ->assertSee('Smart Cane Simulator')
        ->assertSee('Detect objects')
        ->assertSee('Extract text');
})->with(['superAdmin', 'ngoStaff']);

test('vision upload validation rejects missing oversized unsupported and spoofed images', function (string $kind) {
    $user = User::factory()->superAdmin()->create();
    $file = match ($kind) {
        'missing' => null,
        'oversized' => UploadedFile::fake()->image('big.jpg')->size((int) config('services.vision.max_image_kb') + 1),
        'unsupported' => UploadedFile::fake()->image('animated.gif'),
        'spoofed' => UploadedFile::fake()->createWithContent('photo.jpg', 'not an image'),
        'dimensions' => UploadedFile::fake()->image('wide.png', 8193, 1),
    };

    simulateAs($user)
        ->set('visionImage', $file)
        ->call('detectVision')
        ->assertHasErrors('visionImage');

    Http::assertNothingSent();
})->with(['missing', 'oversized', 'unsupported', 'spoofed', 'dimensions']);

test('vision detection returns normalized objects and a localized speech description', function (string $language, string $expectedSpeech) {
    $user = User::factory()->superAdmin()->create();
    config(['services.vision.api_key' => 'private-vision-key']);
    Http::fake(['https://vision.test/detect' => Http::response(['objects' => [
        ['label' => 'car', 'confidence' => 0.9, 'position' => 'front', 'private' => 'discard'],
        ['label' => 'person', 'confidence' => 0.85, 'position' => 'left'],
    ]])]);

    simulateAs($user)
        ->set('visionLanguage', $language)
        ->set('visionImage', UploadedFile::fake()->image('scene.jpg'))
        ->call('detectVision')
        ->assertHasNoErrors()
        ->assertSet('visionObjects', [
            ['label' => 'car', 'confidence' => 0.9, 'position' => 'front'],
            ['label' => 'person', 'confidence' => 0.85, 'position' => 'left'],
        ])
        ->assertSet('visionSpeech', $expectedSpeech)
        ->assertSee('data-tts-text=')
        ->assertDontSee('private-vision-key');

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer private-vision-key') && $request->hasFile('image'));
})->with([
    ['en', 'A car was detected in the center of the image. A person was detected on the left side of the image.'],
    ['ar', 'تم التعرف على سيارة في منتصف الصورة. تم التعرف على شخص في الجهة اليسرى من الصورة.'],
]);

test('vision speech uses an indefinite article and image-relative positions only', function () {
    $user = User::factory()->superAdmin()->create();
    Http::fake(['https://vision.test/detect' => Http::response(['objects' => [
        ['label' => 'umbrella', 'confidence' => 0.7, 'position' => 'right'],
    ]])]);

    simulateAs($user)
        ->set('visionImage', UploadedFile::fake()->image('scene.png'))
        ->call('detectVision')
        ->assertSet('visionSpeech', 'An umbrella was detected on the right side of the image.');
});

test('vision provider failures are bounded and never leak provider secrets', function () {
    $user = User::factory()->superAdmin()->create();
    config(['services.vision.api_key' => 'private-vision-key']);
    Http::fake(['https://vision.test/detect' => Http::response(['secret' => 'sensitive-vision-data'], 500)]);

    simulateAs($user)
        ->set('visionImage', UploadedFile::fake()->image('scene.jpg'))
        ->call('detectVision')
        ->assertSet('visionObjects', null)
        ->assertSet('visionError', __('assistive.errors.provider_unavailable'))
        ->assertDontSee('sensitive-vision-data')
        ->assertDontSee('private-vision-key');
});

test('empty vision results produce the no-objects description', function () {
    $user = User::factory()->superAdmin()->create();
    Http::fake(['https://vision.test/detect' => Http::response(['objects' => []])]);

    simulateAs($user)
        ->set('visionImage', UploadedFile::fake()->image('scene.jpg'))
        ->call('detectVision')
        ->assertSet('visionObjects', [])
        ->assertSet('visionSpeech', 'No objects were detected in the image.');
});

test('OCR preserves the exact extracted text and exposes it for read-aloud', function (string $language, string $providerText, string $expected) {
    $user = User::factory()->superAdmin()->create();
    Http::fake(['https://ocr.test/read' => Http::response(['text' => $providerText, 'language' => $language, 'summary' => 'fabricated'])]);

    simulateAs($user)
        ->set('ocrLanguage', $language)
        ->set('ocrImage', UploadedFile::fake()->image('sign.png'))
        ->call('extractText')
        ->assertHasNoErrors()
        ->assertSet('ocrText', $expected)
        ->assertSet('ocrDetectedLanguage', $language)
        ->assertSee('data-tts-text=')
        ->assertSee('data-tts-lang="'.$language.'"', false);
})->with([
    ['en', "  Printed text\r\nLine two  ", "Printed text\nLine two"],
    ['ar', "  جامعة المنصورة\r\nسطر ثانٍ  ", "جامعة المنصورة\nسطر ثانٍ"],
]);

test('empty OCR returns a helpful message without fabricated text', function () {
    $user = User::factory()->superAdmin()->create();
    Http::fake(['https://ocr.test/read' => Http::response(['text' => '   ', 'language' => null])]);

    simulateAs($user)
        ->set('ocrImage', UploadedFile::fake()->image('blank.jpg'))
        ->call('extractText')
        ->assertSet('ocrText', null)
        ->assertSet('ocrError', __('assistive.errors.no_text_detected'))
        ->assertSee(__('assistive.errors.no_text_detected'));
});

test('OCR provider failures are bounded and never leak provider secrets', function () {
    $user = User::factory()->superAdmin()->create();
    config(['services.ocr.api_key' => 'private-ocr-key']);
    Http::fake(['https://ocr.test/read' => Http::response(['secret' => 'sensitive-ocr-data'], 500)]);

    simulateAs($user)
        ->set('ocrImage', UploadedFile::fake()->image('sign.jpg'))
        ->call('extractText')
        ->assertSet('ocrText', null)
        ->assertSet('ocrError', __('assistive.errors.provider_unavailable'))
        ->assertDontSee('sensitive-ocr-data')
        ->assertDontSee('private-ocr-key');
});

test('navigation validation rejects invalid coordinates before any provider call', function () {
    $user = User::factory()->superAdmin()->create();
    $device = Device::factory()->create();

    simulateAs($user)
        ->set('selectedDeviceId', $device->id)
        ->set('originLatitude', 'not-a-number')
        ->call('startSimulation')
        ->assertHasErrors('originLatitude')
        ->assertSet('simulation', null);

    Http::assertNothingSent();
});

test('the simulator refuses devices outside the dashboard user scope', function () {
    $ngo = Ngo::factory()->create();
    $staff = User::factory()->ngoStaff($ngo)->create();
    $foreignDevice = Device::factory()->create();

    simulateAs($staff)
        ->set('selectedDeviceId', $foreignDevice->id)
        ->call('startSimulation')
        ->assertHasErrors('selectedDeviceId')
        ->assertSet('simulation', null);

    Http::assertNothingSent();
});

test('device select options are scoped to the dashboard user', function () {
    $ngo = Ngo::factory()->create();
    $staff = User::factory()->ngoStaff($ngo)->create();
    $own = Device::factory()->create(['blind_user_id' => BlindUser::factory()->create(['ngo_id' => $ngo->id])->id]);
    $foreign = Device::factory()->create();

    simulateAs($staff)
        ->assertSee($own->serial_number)
        ->assertDontSee($foreign->serial_number);
});

test('navigation simulation starts a localized OSRM session without writing device telemetry', function () {
    $user = User::factory()->superAdmin()->create();
    $device = Device::factory()->create();
    fakeOsrmRoute();

    simulateAs($user)
        ->set('selectedDeviceId', $device->id)
        ->call('startSimulation')
        ->assertHasNoErrors()
        ->assertSet('simulation.status', 'active')
        ->assertSet('simulation.routing_provider', 'osrm')
        ->assertSet('simulation.simulated', true)
        ->assertSet('simulation.device_id', $device->id)
        ->assertSet('simulation.spoken_count', 1)
        ->assertDispatched('basira-speak');

    $this->assertDatabaseCount('locations', 0);
    $this->assertDatabaseCount('navigation_sessions', 0);
    Http::assertSentCount(1);
});

test('GPS updates never persist and the same instruction is spoken only when it changes', function () {
    $user = User::factory()->superAdmin()->create();
    $device = Device::factory()->create();
    fakeOsrmRoute();

    $component = simulateAs($user)
        ->set('selectedDeviceId', $device->id)
        ->call('startSimulation')
        ->assertSet('simulation.spoken_count', 1);

    // Same step as the start: no repeat speech, no telemetry writes.
    $component->set('currentLatitude', '30')->set('currentLongitude', '31')
        ->call('updateSimulatedGps')
        ->assertSet('simulation.spoken_count', 1)
        ->assertSet('simulation.current_step_index', 0)
        ->assertNotDispatched('basira-speak');

    // Reaching the first step endpoint advances the step and speaks once.
    $component->set('currentLatitude', '30.0005')->set('currentLongitude', '31')
        ->call('updateSimulatedGps')
        ->assertSet('simulation.current_step_index', 1)
        ->assertSet('simulation.spoken_count', 2)
        ->assertDispatched('basira-speak');

    $this->assertDatabaseCount('locations', 0);
    $this->assertDatabaseCount('navigation_sessions', 0);
});

test('navigation simulation never touches an existing production session', function () {
    $user = User::factory()->superAdmin()->create();
    $device = Device::factory()->create();
    $session = NavigationSession::factory()->for($device)->create();
    $locationCount = Location::query()->count();
    fakeOsrmRoute();

    simulateAs($user)
        ->set('selectedDeviceId', $device->id)
        ->call('startSimulation')
        ->assertSet('simulation.production_session_active', true)
        ->set('currentLatitude', '30.0005')
        ->set('currentLongitude', '31')
        ->call('updateSimulatedGps')
        ->assertSet('simulation.status', 'active');

    $session->refresh();
    expect($session->status)->toBe(NavigationStatus::Active)
        ->and($session->current_step_index)->toBe(0)
        ->and(Location::query()->count())->toBe($locationCount)
        ->and(NavigationSession::query()->count())->toBe(1);
});

test('reaching the destination completes the simulation and speaks arrival once', function () {
    $user = User::factory()->superAdmin()->create();
    $device = Device::factory()->create();
    fakeOsrmRoute();

    $component = simulateAs($user)
        ->set('selectedDeviceId', $device->id)
        ->call('startSimulation');

    $component->set('currentLatitude', '30.0005')->set('currentLongitude', '31')
        ->call('updateSimulatedGps')
        ->assertSet('simulation.spoken_count', 2);

    $component->set('currentLatitude', '30.0005')->set('currentLongitude', '31.001')
        ->call('updateSimulatedGps')
        ->assertSet('simulation.status', 'completed')
        ->assertSet('simulation.spoken_count', 3)
        ->assertSet('simulation.last_spoken_instruction', __('filament.simulator.messages.arrived'));

    $this->assertDatabaseCount('navigation_sessions', 0);
});

test('off-route fixes clear the instruction without repeating speech', function () {
    $user = User::factory()->superAdmin()->create();
    $device = Device::factory()->create();
    fakeOsrmRoute();

    simulateAs($user)
        ->set('selectedDeviceId', $device->id)
        ->call('startSimulation')
        ->set('currentLatitude', '35')
        ->set('currentLongitude', '35')
        ->call('updateSimulatedGps')
        ->assertSet('simulation.off_route', true)
        ->assertSet('simulation.current_instruction', null)
        ->assertSet('simulation.spoken_count', 1)
        ->assertNotDispatched('basira-speak');
});

test('a simulation cannot continue once its device becomes inaccessible', function () {
    $ngo = Ngo::factory()->create();
    $staff = User::factory()->ngoStaff($ngo)->create();
    $device = Device::factory()->create(['blind_user_id' => BlindUser::factory()->create(['ngo_id' => $ngo->id])->id]);
    fakeOsrmRoute();

    $component = simulateAs($staff)
        ->set('selectedDeviceId', $device->id)
        ->call('startSimulation')
        ->assertSet('simulation.status', 'active');

    $device->update(['blind_user_id' => null]);

    $component->set('currentLatitude', '30.0005')->set('currentLongitude', '31')
        ->call('updateSimulatedGps')
        ->assertHasErrors('selectedDeviceId')
        ->assertSet('simulation.status', 'active')
        ->assertSet('simulation.current_step_index', 0);
});

test('stopping the simulation cancels it and asks the browser to stop speech', function () {
    $user = User::factory()->superAdmin()->create();
    $device = Device::factory()->create();
    fakeOsrmRoute();

    simulateAs($user)
        ->set('selectedDeviceId', $device->id)
        ->call('startSimulation')
        ->call('stopSimulation')
        ->assertSet('simulation.status', 'cancelled')
        ->assertDispatched('basira-stop-speech');

    $this->assertDatabaseCount('navigation_sessions', 0);
});

test('unconfigured navigation providers fail with a bounded localized error', function () {
    $user = User::factory()->superAdmin()->create();
    $device = Device::factory()->create();
    config(['services.osrm.url' => null]);

    simulateAs($user)
        ->set('selectedDeviceId', $device->id)
        ->call('startSimulation')
        ->assertSet('simulation', null)
        ->assertSet('navigationError', __('assistive.errors.provider_not_configured'));

    Http::assertNothingSent();
});
