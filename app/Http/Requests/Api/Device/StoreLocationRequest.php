<?php

namespace App\Http\Requests\Api\Device;

use Illuminate\Contracts\Validation\ValidationRule;

class StoreLocationRequest extends CloudDeviceRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->isCloudPayload()) {
            return [
                ...$this->cloudRules(),
                'lat' => ['required', 'numeric', 'between:-90,90'],
                'lng' => ['required', 'numeric', 'between:-180,180'],
                'gps_valid' => ['required', 'boolean'],
                'accuracy' => ['prohibited'],
            ];
        }

        return [
            'message_id' => ['nullable', 'uuid'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'timestamp' => ['required', 'date'],
            'device_id' => ['prohibited'],
            'blind_user_id' => ['prohibited'],
            'received_at' => ['prohibited'],
        ];
    }

    public function isCloudPayload(): bool
    {
        return $this->hasAny(['device_id', 'gps_valid']);
    }

    /** @return array<string, mixed> */
    public function ingestionData(): array
    {
        if (! $this->isCloudPayload()) {
            return $this->validated();
        }

        $values = [
            'lat' => (float) $this->validated('lat'),
            'lng' => (float) $this->validated('lng'),
            'gps_valid' => (bool) $this->validated('gps_valid'),
        ];

        return [
            ...$values,
            'timestamp' => $this->validated('timestamp'),
            'message_id' => $this->cloudMessageId('location', $values),
        ];
    }
}
