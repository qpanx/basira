<?php

return [
    'supported_locales' => ['en', 'ar'],

    'offline_after_minutes' => (int) env('DEVICE_OFFLINE_AFTER_MINUTES', 10),

    'rate_limits' => [
        'heartbeat' => (int) env('DEVICE_HEARTBEAT_RATE_LIMIT', 30),
        'location' => (int) env('DEVICE_LOCATION_RATE_LIMIT', 120),
        'alert' => (int) env('DEVICE_ALERT_RATE_LIMIT', 30),
        'sos' => (int) env('DEVICE_SOS_RATE_LIMIT', 10),
        'arrival' => (int) env('DEVICE_ARRIVAL_RATE_LIMIT', 10),
        'config' => (int) env('DEVICE_CONFIG_RATE_LIMIT', 12),
    ],

    'emergency' => [
        'driver' => env('EMERGENCY_GATEWAY', 'log'),
        'voice_calls_enabled' => (bool) env('EMERGENCY_VOICE_CALLS_ENABLED', true),
    ],
];
