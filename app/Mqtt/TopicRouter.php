<?php

namespace App\Mqtt;

use App\Mqtt\Contracts\MqttHandler;
use App\Mqtt\Handlers\AckHandler;
use App\Mqtt\Handlers\HeartbeatHandler;
use App\Mqtt\Handlers\OnlineOfflineHandler;
use App\Mqtt\Handlers\RecognitionHandler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TopicRouter
{
    /** @var array<string, class-string<MqttHandler>> */
    private array $routes;

    public function __construct()
    {
        $prefix = preg_quote(config('fras.mqtt.topic_prefix'), '#');

        $this->routes = [
            '#^'.$prefix.'/[^/]+/Rec$#' => RecognitionHandler::class,
            '#^'.$prefix.'/[^/]+/Ack$#' => AckHandler::class,
            '#^'.$prefix.'/basic$#' => OnlineOfflineHandler::class,
            '#^'.$prefix.'/heartbeat$#' => HeartbeatHandler::class,
        ];
    }

    /**
     * Dispatch an MQTT message to the appropriate handler based on topic pattern.
     */
    public function dispatch(string $topic, string $message): void
    {
        // Bump liveness BEFORE routing: any arriving message proves broker connectivity,
        // even unmatched topics (FRAS-parity, D-05 intent).
        Cache::put(
            'mqtt:listener:last_message_received_at',
            now()->toIso8601String(),
            now()->addSeconds(120),
        );

        foreach ($this->routes as $pattern => $handlerClass) {
            if (preg_match($pattern, $topic)) {
                app($handlerClass)->handle($topic, $message);

                return;
            }
        }

        Log::channel('mqtt')->warning('Unmatched MQTT topic', ['topic' => $topic]);
    }
}
