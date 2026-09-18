<?php

return [
    'accepted' => 'The :attribute must be accepted.',
    'array' => 'The :attribute must be an array.',
    'boolean' => 'The :attribute field must be true or false.',
    'date' => 'The :attribute is not a valid date.',
    'email' => 'The :attribute must be a valid email address.',
    'required' => 'The :attribute field is required.',
    'required_with' => 'The :attribute field is required when :values is present.',
    'string' => 'The :attribute must be a string.',
    'numeric' => 'The :attribute must be a number.',
    'integer' => 'The :attribute must be an integer.',
    'min' => [
        'numeric' => 'The :attribute must be at least :min.',
        'string' => 'The :attribute must be at least :min characters.',
    ],
    'max' => [
        'numeric' => 'The :attribute may not be greater than :max.',
        'string' => 'The :attribute may not be greater than :max characters.',
    ],
    'between' => [
        'numeric' => 'The :attribute must be between :min and :max.',
        'integer' => 'The :attribute must be between :min and :max.',
    ],
    'uuid' => 'The :attribute must be a valid UUID.',
    'in' => 'The selected :attribute is invalid.',
    'prohibited' => 'The :attribute field is prohibited.',
    'attributes' => [
        'battery_level' => 'battery level', 'firmware_version' => 'firmware version',
        'lat' => 'latitude', 'lng' => 'longitude', 'timestamp' => 'timestamp',
        'type' => 'type', 'severity' => 'severity',
    ],
];
