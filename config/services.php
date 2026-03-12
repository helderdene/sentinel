<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    'iot' => [
        'webhook_secret' => env('IOT_WEBHOOK_SECRET'),
        'sensor_mappings' => [
            'flood_gauge' => ['incident_type_code' => 'NAT-002', 'priority' => 'P2'],
            'fire_alarm' => ['incident_type_code' => 'FIR-001', 'priority' => 'P1'],
            'weather' => ['incident_type_code' => 'NAT-004', 'priority' => 'P2'],
            'seismic' => ['incident_type_code' => 'NAT-001', 'priority' => 'P1'],
            'cctv_analytics' => ['incident_type_code' => 'PUB-001', 'priority' => 'P3'],
        ],
    ],

];
