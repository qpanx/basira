<?php

namespace App\Http\Requests\Api\Device;

class SearchDestinationRequest extends NavigationRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'query' => ['required', 'string', 'min:2', 'max:200'],
            'language' => ['sometimes', 'required', 'in:en,ar'],
        ];
    }
}
