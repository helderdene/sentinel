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

];
