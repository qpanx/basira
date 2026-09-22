<?php

namespace App\Http\Requests\Api\Device;

class StartNavigationRequest extends NavigationRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'destination_place_id' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/'],
            'language' => ['sometimes', 'required', 'in:en,ar'],
            'lat' => ['prohibited'], 'lng' => ['prohibited'], 'origin' => ['prohibited'],
        ];
    }
}
