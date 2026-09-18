<?php

namespace App\Http\Requests\Api\Device;

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAlertRequest extends FormRequest
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
            'type' => ['required', Rule::enum(AlertType::class)],
            'severity' => ['required', Rule::enum(AlertSeverity::class)],
            'distance_cm' => ['nullable', 'integer', 'between:0,65535'],
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
            'timestamp' => ['nullable', 'date'],
            'device_id' => ['prohibited'],
            'blind_user_id' => ['prohibited'],
            'resolved_at' => ['prohibited'],
            'resolved_by' => ['prohibited'],
            'resolution' => ['prohibited'],
        ];
    }
}
