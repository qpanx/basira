<?php

namespace App\Http\Requests\Api\Device;

use App\Enums\PowerState;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HeartbeatRequest extends FormRequest
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
}
