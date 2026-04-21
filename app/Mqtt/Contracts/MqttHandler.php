<?php

namespace App\Mqtt\Contracts;

interface MqttHandler
{
    /**
     * Handle an incoming MQTT message for a matched topic.
     */
    public function handle(string $topic, string $message): void;
}
