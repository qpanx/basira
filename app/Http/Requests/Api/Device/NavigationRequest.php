<?php

namespace App\Http\Requests\Api\Device;

use Illuminate\Foundation\Http\FormRequest;

class NavigationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'device_id' => ['prohibited'], 'blind_user_id' => ['prohibited'],
            'session_id' => ['prohibited'], 'received_at' => ['prohibited'],
        ];
    }
}
