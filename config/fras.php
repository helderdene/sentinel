<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | MQTT Pipeline Settings
    |--------------------------------------------------------------------------
    |
    | FRAS-specific MQTT knobs shared by both the subscriber (config/mqtt-client
    | subscriber block) and the listener command. Kept in a dedicated
    | config/fras.php so Phase 22 retention / DPA settings can join later
    | without touching the vendor-published mqtt-client.php.
    |
    */

    'mqtt' => [
        'topic_prefix' => env('FRAS_MQTT_TOPIC_PREFIX', 'mqtt/face'),
        'keepalive' => (int) env('FRAS_MQTT_KEEPALIVE', 60),
        'reconnect_delay' => (int) env('FRAS_MQTT_RECONNECT_DELAY', 5),
    ],

    'cameras' => [
        'degraded_gap_s' => (int) env('FRAS_CAMERA_DEGRADED_GAP_S', 30),
        'offline_gap_s' => (int) env('FRAS_CAMERA_OFFLINE_GAP_S', 90),
    ],

    'enrollment' => [
        'batch_size' => (int) env('FRAS_ENROLLMENT_BATCH_SIZE', 10),
        'ack_timeout_minutes' => (int) env('FRAS_ENROLLMENT_ACK_TIMEOUT_MINUTES', 5),
    ],

    'photo' => [
        'max_dimension' => (int) env('FRAS_PHOTO_MAX_DIMENSION', 1080),
        'jpeg_quality' => (int) env('FRAS_PHOTO_JPEG_QUALITY', 85),
        'max_size_bytes' => (int) env('FRAS_PHOTO_MAX_SIZE_BYTES', 1_048_576),
    ],

    'recognition' => [
        'confidence_threshold' => (float) env('FRAS_CONFIDENCE_THRESHOLD', 0.75),
        'dedup_window_seconds' => (int) env('FRAS_DEDUP_WINDOW_SECONDS', 60),
        'pulse_duration_seconds' => (int) env('FRAS_PULSE_DURATION_SECONDS', 3),
        'priority_map' => [
            'critical' => [
                'block' => env('FRAS_PRIORITY_CRITICAL_BLOCK', 'P2'),
                'missing' => env('FRAS_PRIORITY_CRITICAL_MISSING', 'P2'),
                'lost_child' => env('FRAS_PRIORITY_CRITICAL_LOST_CHILD', 'P1'),
            ],
        ],
    ],

];
