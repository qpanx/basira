<?php

return [

    'navigation' => [
        'provider' => env('NAVIGATION_PROVIDER', 'google'),
    ],

    'osrm' => [
        'url' => env('OSRM_SERVICE_URL'),
        'profile' => env('OSRM_PROFILE', 'foot'),
        'walking_enabled' => (bool) env('OSRM_WALKING_ENABLED', false),
        'timeout' => (int) env('OSRM_TIMEOUT', 10),
        'max_snap_distance_meters' => (int) env('OSRM_MAX_SNAP_DISTANCE_METERS', 50),
    ],

    'vision' => [
        'url' => env('VISION_SERVICE_URL'),
        'api_key' => env('VISION_SERVICE_API_KEY'),
        'timeout' => (int) env('VISION_SERVICE_TIMEOUT', 10),
        'max_image_kb' => (int) env('VISION_MAX_IMAGE_KB', 5120),
        'min_confidence' => (float) env('VISION_MIN_CONFIDENCE', 0.5),
    ],

    'ocr' => [
        'url' => env('OCR_SERVICE_URL'),
        'api_key' => env('OCR_SERVICE_API_KEY'),
        'timeout' => (int) env('OCR_SERVICE_TIMEOUT', 10),
        'max_image_kb' => (int) env('OCR_MAX_IMAGE_KB', 5120),
    ],

    'google_maps' => [
        'api_key' => env('GOOGLE_MAPS_API_KEY'),
        'timeout' => (int) env('GOOGLE_MAPS_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'sms_from' => env('TWILIO_SMS_FROM'),
        'voice_from' => env('TWILIO_VOICE_FROM'),
        'status_callback' => env('TWILIO_STATUS_CALLBACK'),
    ],

];
