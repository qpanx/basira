<?php

return [
    'supported_locales' => ['en', 'ar'],

    'offline_after_minutes' => (int) env('DEVICE_OFFLINE_AFTER_MINUTES', 10),

    'navigation' => [
        'location_max_age_seconds' => (int) env('NAVIGATION_LOCATION_MAX_AGE_SECONDS', 120),
        'max_accuracy_meters' => (float) env('NAVIGATION_MAX_ACCURACY_METERS', 50),
        'deviation_meters' => (float) env('NAVIGATION_DEVIATION_METERS', 50),
        'reroute_cooldown_seconds' => (int) env('NAVIGATION_REROUTE_COOLDOWN_SECONDS', 60),
        'arrival_radius_meters' => (float) env('NAVIGATION_ARRIVAL_RADIUS_METERS', 20),
    ],

    'rate_limits' => [
        'vision' => (int) env('DEVICE_VISION_RATE_LIMIT', 12),
        'ocr' => (int) env('DEVICE_OCR_RATE_LIMIT', 12),
        'navigation-search' => (int) env('DEVICE_NAVIGATION_SEARCH_RATE_LIMIT', 10),
        'navigation-start' => (int) env('DEVICE_NAVIGATION_START_RATE_LIMIT', 5),
        'navigation-update' => (int) env('DEVICE_NAVIGATION_UPDATE_RATE_LIMIT', 60),
        'navigation-read' => (int) env('DEVICE_NAVIGATION_READ_RATE_LIMIT', 30),
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
