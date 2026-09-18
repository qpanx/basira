<?php

namespace App\Http\Requests\Api\Device;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreArrivalRequest extends FormRequest
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
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
            'timestamp' => ['nullable', 'date'],
            'device_id' => ['prohibited'],
            'blind_user_id' => ['prohibited'],
            'confirmed_at' => ['prohibited'],
            'received_at' => ['prohibited'],
        ];
    }
}
