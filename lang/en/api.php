<?php

return [
    'auth' => [
        'device_token_required' => 'A valid device token is required.',
        'device_disabled' => 'This device is disabled.',
        'device_unassigned' => 'This device is not assigned to a blind user.',
        'missing_ability' => 'This token is not allowed to perform that action.',
    ],
    'heartbeat' => ['recorded' => 'Heartbeat recorded.'],
    'location' => ['recorded' => 'Location recorded.'],
    'alert' => ['recorded' => 'Alert recorded.'],
    'arrival' => ['recorded' => 'Arrival confirmation recorded.'],
    'config' => ['loaded' => 'Device configuration loaded.'],
    'notifications' => [
        'sos_message' => 'SOS emergency from :name. Please check the Smart Assistive Cane dashboard immediately.',
    ],
    'errors' => [
        'not_found' => 'The requested resource was not found.',
    ],
];
