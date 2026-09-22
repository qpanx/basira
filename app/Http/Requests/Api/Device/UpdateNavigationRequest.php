<?php

namespace App\Http\Requests\Api\Device;

class UpdateNavigationRequest extends StoreLocationRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'session_id' => ['prohibited'],
            'accuracy' => ['required', 'numeric', 'min:0', 'max:'.config('smart-cane.navigation.max_accuracy_meters')],
            'timestamp' => ['required', 'date', 'before_or_equal:'.now()->toISOString(), 'after_or_equal:'.now()->subSeconds(config('smart-cane.navigation.location_max_age_seconds'))->toISOString()],
        ];
    }
}
