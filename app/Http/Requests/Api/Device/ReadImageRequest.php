<?php

namespace App\Http\Requests\Api\Device;

use Illuminate\Foundation\Http\FormRequest;

class ReadImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $provider = $this->routeIs('api.device.vision.detect') ? 'vision' : 'ocr';

        return [
            'image' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:'.config("services.{$provider}.max_image_kb"), 'dimensions:max_width=8192,max_height=8192'],
            'language' => ['sometimes', 'required', 'in:en,ar'],
            'device_id' => ['prohibited'],
            'blind_user_id' => ['prohibited'],
        ];
    }
}
