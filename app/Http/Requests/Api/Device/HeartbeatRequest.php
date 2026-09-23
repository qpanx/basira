<?php

namespace App\Http\Requests\Api\Device;

use App\Enums\PowerState;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class HeartbeatRequest extends CloudDeviceRequest
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
                'online' => $this->isStatusPayload() ? ['required', 'boolean'] : ['prohibited'],
                'percentage' => $this->isStatusPayload() ? ['prohibited'] : ['required', 'integer', 'between:0,100'],
                'voltage' => $this->isStatusPayload() ? ['prohibited'] : ['required', 'numeric', 'between:0,99.999', 'decimal:0,3'],
                'battery_level' => ['prohibited'],
                'firmware_version' => ['prohibited'],
                'power_state' => ['prohibited'],
                'recorded_at' => ['prohibited'],
            ];
        }

        return [
            'message_id' => ['nullable', 'uuid'],
            'battery_level' => ['required', 'integer', 'between:0,100'],
            'firmware_version' => ['required', 'string', 'max:64'],
            'power_state' => ['nullable', Rule::enum(PowerState::class)],
            'recorded_at' => ['nullable', 'date'],
            'device_id' => ['prohibited'],
            'blind_user_id' => ['prohibited'],
            'status' => ['prohibited'],
            'last_seen_at' => ['prohibited'],
        ];
    }

    public function isCloudPayload(): bool
    {
        return $this->hasAny(['device_id', 'percentage', 'voltage', 'online']);
    }

    public function isStatusPayload(): bool
    {
        return $this->has('online');
    }

    /** @return array<string, mixed> */
    public function ingestionData(): array
    {
        if (! $this->isCloudPayload() || $this->isStatusPayload()) {
            return $this->validated();
        }

        $values = [
            'battery_level' => (int) $this->validated('percentage'),
            'voltage' => (float) $this->validated('voltage'),
        ];

        return [
            ...$values,
            'message_id' => $this->cloudMessageId('battery', $values),
            'recorded_at' => $this->validated('timestamp'),
        ];
    }
}
