<?php

namespace App\Services;

use App\Exceptions\AssistiveServiceException;
use App\Models\Device;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

class VisionService
{
    public function __construct(private ImageProviderClient $client) {}

    /** @return array{objects: list<array{label: string, confidence: float, position: string}>, message: string} */
    public function detect(Device $device, UploadedFile $image, string $language): array
    {
        return $this->detectWithSettings((array) $device->settings, $image, $language);
    }

    /**
     * Run detection against a plain settings bag so simulator callers reuse the
     * device pipeline without touching a real device record.
     *
     * @param  array<array-key, mixed>  $settings
     * @return array{objects: list<array{label: string, confidence: float, position: string}>, message: string}
     */
    public function detectWithSettings(array $settings, UploadedFile $image, string $language): array
    {
        if (! filter_var(data_get($settings, 'vision_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            throw new AssistiveServiceException('feature_disabled', 403);
        }

        $data = $this->client->send('vision', $image, $language);
        $validator = Validator::make($data, [
            'objects' => ['present', 'array', 'max:100'],
            'objects.*.label' => ['required', 'string', 'max:120'],
            'objects.*.confidence' => ['required', 'numeric', 'between:0,1'],
            'objects.*.position' => ['required', 'in:left,front,right,unknown'],
        ]);
        if ($validator->fails()) {
            throw new AssistiveServiceException('invalid_provider_response', 502);
        }

        $threshold = max(0, min(1, (float) data_get($settings, 'vision_min_confidence', config('services.vision.min_confidence'))));
        $objects = [];
        foreach ($data['objects'] as $object) {
            if ((float) $object['confidence'] >= $threshold) {
                $objects[] = [
                    'label' => trim(strip_tags($object['label'])),
                    'confidence' => (float) $object['confidence'],
                    'position' => $object['position'],
                ];
            }
        }

        return [
            'objects' => $objects,
            'message' => __('assistive.vision_message', ['count' => count($objects)], $language),
        ];
    }
}
