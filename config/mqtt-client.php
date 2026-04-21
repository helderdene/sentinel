<?php

declare(strict_types=1);

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\Repositories\MemoryRepository;

return [

    /*
    |--------------------------------------------------------------------------
    | Default MQTT Connection
    |--------------------------------------------------------------------------
    |
    | This option selects the MQTT connection block returned when resolving
    | the facade without a name. IRMS subscribes by default, so the listener
    | command runs against the `subscriber` block unless overridden via
    | MQTT_CONNECTION.
    |
    */

    'default' => env('MQTT_CONNECTION', 'subscriber'),

    /*
    |--------------------------------------------------------------------------
    | MQTT Connections
    |--------------------------------------------------------------------------
    |
    | IRMS declares two independent MQTT connections:
    |
    | - `subscriber` — long-lived listener (use_clean_session=false, persistent
    |   client id so broker-queued messages survive restarts). MQTT-04 requires
    |   auto_reconnect=true so the listener recovers from broker bounces.
    | - `publisher` — short-lived enrollment / control publisher
    |   (use_clean_session=true, independent client id so publishes do not
    |   race the subscriber session).
    |
    | MQTT-06 mandates these be SEPARATE top-level connection entries rather
    | than a single shared connection. The top-level `auto_reconnect` key on
    | each connection is the IRMS-specific MQTT-04 marker asserted by
    | MqttClientConfigTest; the vendor php-mqtt/laravel-client reads the
    | nested `connection_settings.auto_reconnect` array at runtime.
    |
    */

    'connections' => [

        'subscriber' => [

            'host' => env('MQTT_HOST', '127.0.0.1'),
            'port' => (int) env('MQTT_PORT', 1883),
            'protocol' => MqttClient::MQTT_3_1_1,
            'client_id' => env('MQTT_SUBSCRIBER_CLIENT_ID', 'irms-mqtt-sub-'.gethostname()),
            'use_clean_session' => false,
            'enable_logging' => env('MQTT_ENABLE_LOGGING', true),
            'log_channel' => env('MQTT_LOG_CHANNEL', 'mqtt'),
            'repository' => MemoryRepository::class,

            'auto_reconnect' => true,

            'connection_settings' => [

                'tls' => [
                    'enabled' => env('MQTT_TLS_ENABLED', false),
                    'allow_self_signed_certificate' => env('MQTT_TLS_ALLOW_SELF_SIGNED_CERT', false),
                    'verify_peer' => env('MQTT_TLS_VERIFY_PEER', true),
                    'verify_peer_name' => env('MQTT_TLS_VERIFY_PEER_NAME', true),
                    'ca_file' => env('MQTT_TLS_CA_FILE'),
                    'ca_path' => env('MQTT_TLS_CA_PATH'),
                    'client_certificate_file' => env('MQTT_TLS_CLIENT_CERT_FILE'),
                    'client_certificate_key_file' => env('MQTT_TLS_CLIENT_CERT_KEY_FILE'),
                    'client_certificate_key_passphrase' => env('MQTT_TLS_CLIENT_CERT_KEY_PASSPHRASE'),
                    'alpn' => env('MQTT_TLS_ALPN'),
                ],

                'auth' => [
                    'username' => env('MQTT_USERNAME') ?: null,
                    'password' => env('MQTT_PASSWORD') ?: null,
                ],

                'last_will' => [
                    'topic' => env('MQTT_LAST_WILL_TOPIC'),
                    'message' => env('MQTT_LAST_WILL_MESSAGE'),
                    'quality_of_service' => (int) env('MQTT_LAST_WILL_QUALITY_OF_SERVICE', 0),
                    'retain' => env('MQTT_LAST_WILL_RETAIN', false),
                ],

                'connect_timeout' => (int) env('MQTT_CONNECT_TIMEOUT', 60),
                'socket_timeout' => (int) env('MQTT_SOCKET_TIMEOUT', 5),
                'resend_timeout' => (int) env('MQTT_RESEND_TIMEOUT', 10),

                'keep_alive_interval' => (int) (config('fras.mqtt.keepalive') ?? env('FRAS_MQTT_KEEPALIVE', 60)),

                'auto_reconnect' => [
                    'enabled' => true,
                    'max_reconnect_attempts' => (int) env('MQTT_MAX_RECONNECT_ATTEMPTS', 10),
                    'delay_between_reconnect_attempts' => (int) (config('fras.mqtt.reconnect_delay') ?? env('FRAS_MQTT_RECONNECT_DELAY', 5)),
                ],

            ],

        ],

        'publisher' => [

            'host' => env('MQTT_HOST', '127.0.0.1'),
            'port' => (int) env('MQTT_PORT', 1883),
            'protocol' => MqttClient::MQTT_3_1_1,
            'client_id' => env('MQTT_PUBLISHER_CLIENT_ID', 'irms-mqtt-pub-'.gethostname()),
            'use_clean_session' => true,
            'enable_logging' => env('MQTT_ENABLE_LOGGING', true),
            'log_channel' => env('MQTT_LOG_CHANNEL', 'mqtt'),
            'repository' => MemoryRepository::class,

            'auto_reconnect' => true,

            'connection_settings' => [

                'tls' => [
                    'enabled' => env('MQTT_TLS_ENABLED', false),
                    'allow_self_signed_certificate' => env('MQTT_TLS_ALLOW_SELF_SIGNED_CERT', false),
                    'verify_peer' => env('MQTT_TLS_VERIFY_PEER', true),
                    'verify_peer_name' => env('MQTT_TLS_VERIFY_PEER_NAME', true),
                    'ca_file' => env('MQTT_TLS_CA_FILE'),
                    'ca_path' => env('MQTT_TLS_CA_PATH'),
                    'client_certificate_file' => env('MQTT_TLS_CLIENT_CERT_FILE'),
                    'client_certificate_key_file' => env('MQTT_TLS_CLIENT_CERT_KEY_FILE'),
                    'client_certificate_key_passphrase' => env('MQTT_TLS_CLIENT_CERT_KEY_PASSPHRASE'),
                    'alpn' => env('MQTT_TLS_ALPN'),
                ],

                'auth' => [
                    'username' => env('MQTT_USERNAME') ?: null,
                    'password' => env('MQTT_PASSWORD') ?: null,
                ],

                'connect_timeout' => (int) env('MQTT_CONNECT_TIMEOUT', 60),
                'socket_timeout' => (int) env('MQTT_SOCKET_TIMEOUT', 5),
                'resend_timeout' => (int) env('MQTT_RESEND_TIMEOUT', 10),

                'keep_alive_interval' => (int) (config('fras.mqtt.keepalive') ?? env('FRAS_MQTT_KEEPALIVE', 60)),

                'auto_reconnect' => [
                    'enabled' => true,
                    'max_reconnect_attempts' => (int) env('MQTT_MAX_RECONNECT_ATTEMPTS', 10),
                    'delay_between_reconnect_attempts' => (int) (config('fras.mqtt.reconnect_delay') ?? env('FRAS_MQTT_RECONNECT_DELAY', 5)),
                ],

            ],

        ],

    ],

];
