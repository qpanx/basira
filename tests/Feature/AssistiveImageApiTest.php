<?php

use App\Models\Device;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Http::preventStrayRequests();
    config(['services.vision.url' => 'https://vision.test/detect', 'services.ocr.url' => 'https://ocr.test/read']);
});

test('image endpoints return 401 without device authentication', function (string $endpoint) {
    $this->postJson($endpoint)->assertUnauthorized()->assertJsonPath('success', false);
    Http::assertNothingSent();
})->with(['/api/device/vision/detect', '/api/device/ocr/read']);

test('image endpoints reject missing abilities and dashboard tokens', function (string $endpoint) {
    $device = Device::factory()->create();
    $this->withToken($device->createToken('wrong', ['device:location'])->plainTextToken)
        ->postJson($endpoint)->assertForbidden()->assertJsonPath('code', 'missing_token_ability');
    $this->app['auth']->forgetGuards();
    $user = User::factory()->superAdmin()->create();
    $this->withToken($user->createToken('dashboard', ['*'])->plainTextToken)->postJson($endpoint)->assertUnauthorized();
    Http::assertNothingSent();
})->with(['/api/device/vision/detect', '/api/device/ocr/read']);

test('disabled and unassigned devices cannot use image providers', function (string $endpoint, string $ability, string $state) {
    $device = Device::factory()->create($state === 'disabled' ? ['is_enabled' => false] : ['blind_user_id' => null]);
    $this->withToken($device->createToken('test', [$ability])->plainTextToken)->postJson($endpoint)->assertForbidden();
    Http::assertNothingSent();
})->with([
    ['/api/device/vision/detect', 'device:vision', 'disabled'],
    ['/api/device/ocr/read', 'device:ocr', 'unassigned'],
]);

test('image validation rejects missing oversized unsupported and spoofed uploads', function (string $kind, string $provider) {
    $device = Device::factory()->create();
    $file = match ($kind) {
        'missing' => null,
        'oversized' => UploadedFile::fake()->image('image.jpg')->size(5121),
        'unsupported' => UploadedFile::fake()->image('image.gif'),
        'spoofed' => UploadedFile::fake()->createWithContent('image.jpg', 'not an image'),
        'dimensions' => UploadedFile::fake()->image('image.png', 8193, 1),
    };
    $endpoint = $provider === 'vision' ? 'vision/detect' : 'ocr/read';

    $this->withToken($device->createToken('test', ['device:'.$provider])->plainTextToken)
        ->postJson('/api/device/'.$endpoint, ['image' => $file])->assertUnprocessable()->assertJsonValidationErrors('image');
    Http::assertNothingSent();
})->with(['missing', 'oversized', 'unsupported', 'spoofed', 'dimensions'])->with(['vision', 'ocr']);

test('vision filters confidence and returns normalized objects without storing images', function () {
    Storage::fake('local');
    $device = Device::factory()->create(['settings' => ['vision_min_confidence' => 0.8]]);
    config(['services.vision.api_key' => 'private-provider-key']);
    Http::fake(['https://vision.test/detect' => Http::response(['objects' => [
        ['label' => 'person', 'confidence' => 0.91, 'position' => 'front', 'private' => 'discard'],
        ['label' => 'car', 'confidence' => 0.7, 'position' => 'left'],
    ]])]);

    $this->withToken($device->createToken('test', ['device:vision'])->plainTextToken)
        ->postJson('/api/device/vision/detect', ['image' => UploadedFile::fake()->image('camera.jpg')])
        ->assertOk()->assertJsonPath('success', true)
        ->assertJsonPath('data.objects', [['label' => 'person', 'confidence' => 0.91, 'position' => 'front']])
        ->assertDontSee('private-provider-key');
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer private-provider-key') && $request->hasFile('image'));
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('device vision configuration can disable inference', function () {
    $device = Device::factory()->create(['settings' => ['vision_enabled' => false]]);
    $this->withToken($device->createToken('test', ['device:vision'])->plainTextToken)
        ->postJson('/api/device/vision/detect', ['image' => UploadedFile::fake()->image('camera.jpg')])
        ->assertForbidden()->assertJsonPath('code', 'feature_disabled');
    Http::assertNothingSent();
});

test('OCR preserves Arabic and English extracted text separately from provider summaries', function (string $language, string $text) {
    $device = Device::factory()->create();
    Http::fake(['https://ocr.test/read' => Http::response(['text' => "  {$text}\r\nLine 2  ", 'language' => $language, 'summary' => 'fabricated'])]);

    $this->withToken($device->createToken('test', ['device:ocr'])->plainTextToken)
        ->withHeader('Accept-Language', $language)
        ->postJson('/api/device/ocr/read', ['image' => UploadedFile::fake()->image('camera.png')])
        ->assertOk()->assertExactJson(['success' => true, 'data' => ['text' => "{$text}\nLine 2", 'language' => $language]]);
    Http::assertSent(fn (Request $request): bool => str_contains($request->body(), $language));
})->with([['en', 'Printed text'], ['ar', 'جامعة المنصورة']]);

test('empty OCR returns 422 without fabricated text', function () {
    $device = Device::factory()->create();
    Http::fake(['https://ocr.test/read' => Http::response(['text' => '', 'language' => null])]);
    $this->withToken($device->createToken('test', ['device:ocr'])->plainTextToken)
        ->postJson('/api/device/ocr/read', ['image' => UploadedFile::fake()->image('camera.jpg')])
        ->assertUnprocessable()->assertJsonPath('code', 'no_text_detected')->assertJsonMissingPath('data.text');
});

test('image provider errors are bounded and do not expose sensitive provider content', function (string $provider, string $failure) {
    $device = Device::factory()->create();
    $url = $provider === 'vision' ? 'https://vision.test/detect' : 'https://ocr.test/read';
    $fake = match ($failure) {
        'server' => Http::response(['secret' => 'sensitive-image-data'], 500),
        'timeout' => Http::failedConnection(),
        'malformed' => Http::response(['unexpected' => 'sensitive-image-data']),
    };
    Http::fake([$url => $fake]);
    $endpoint = $provider === 'vision' ? 'vision/detect' : 'ocr/read';
    $this->withToken($device->createToken('test', ['device:'.$provider])->plainTextToken)
        ->postJson('/api/device/'.$endpoint, ['image' => UploadedFile::fake()->image('camera.jpg')])
        ->assertStatus($failure === 'malformed' ? 502 : 503)->assertJsonPath('success', false)->assertDontSee('sensitive-image-data');
})->with(['vision', 'ocr'])->with(['server', 'timeout', 'malformed']);

test('unconfigured providers return localized 503 responses without HTTP calls', function (string $provider) {
    $device = Device::factory()->create(['preferred_language' => 'ar']);
    config(['services.'.$provider.'.url' => null]);
    $this->withServerVariables(['HTTP_ACCEPT_LANGUAGE' => null]);
    $endpoint = $provider === 'vision' ? 'vision/detect' : 'ocr/read';
    $this->withToken($device->createToken('test', ['device:'.$provider])->plainTextToken)
        ->postJson('/api/device/'.$endpoint, ['image' => UploadedFile::fake()->image('camera.jpg')])
        ->assertStatus(503)->assertJsonPath('message', 'لم يتم إعداد الخدمة الخارجية.');
    Http::assertNothingSent();
})->with(['vision', 'ocr']);

test('image endpoints enforce device rate limits', function (string $provider) {
    config(['smart-cane.rate_limits.'.$provider => 1]);
    $device = Device::factory()->create();
    $endpoint = $provider === 'vision' ? 'vision/detect' : 'ocr/read';
    $this->withToken($device->createToken('test', ['device:'.$provider])->plainTextToken);
    $this->postJson('/api/device/'.$endpoint)->assertUnprocessable();
    $this->postJson('/api/device/'.$endpoint)->assertTooManyRequests()->assertJsonPath('code', 'rate_limited')->assertJsonPath('success', false);
    Http::assertNothingSent();
})->with(['vision', 'ocr']);

test('vision rejects malformed detections rather than inventing scene data', function (array $object) {
    $device = Device::factory()->create();
    Http::fake(['https://vision.test/detect' => Http::response(['objects' => [$object]])]);
    $this->withToken($device->createToken('test', ['device:vision'])->plainTextToken)
        ->postJson('/api/device/vision/detect', ['image' => UploadedFile::fake()->image('camera.jpg')])
        ->assertStatus(502)->assertJsonPath('code', 'invalid_provider_response');
})->with([
    [['label' => 'car', 'confidence' => 1.4, 'position' => 'front']],
    [['label' => 'car', 'confidence' => 0.9, 'position' => 'nowhere']],
    [['confidence' => 0.9, 'position' => 'left']],
]);

test('image endpoints prohibit caller supplied identities', function (string $provider) {
    $device = Device::factory()->create();
    $endpoint = $provider === 'vision' ? 'vision/detect' : 'ocr/read';
    $this->withToken($device->createToken('test', ['device:'.$provider])->plainTextToken)
        ->postJson('/api/device/'.$endpoint, ['image' => UploadedFile::fake()->image('camera.jpg'), 'device_id' => 999, 'blind_user_id' => 999])
        ->assertUnprocessable()->assertJsonValidationErrors(['device_id', 'blind_user_id']);
    Http::assertNothingSent();
})->with(['vision', 'ocr']);

test('invalid image errors are localized in Arabic with a consistent envelope', function () {
    $device = Device::factory()->create();
    $this->withToken($device->createToken('test', ['device:ocr'])->plainTextToken)
        ->withHeader('Accept-Language', 'ar')
        ->postJson('/api/device/ocr/read', ['image' => UploadedFile::fake()->createWithContent('image.jpg', 'invalid image')])
        ->assertUnprocessable()->assertJsonPath('success', false)->assertJsonPath('code', 'validation_error')
        ->assertJsonPath('errors.image.0', 'أبعاد الصورة غير صالحة.');
    Http::assertNothingSent();
});
