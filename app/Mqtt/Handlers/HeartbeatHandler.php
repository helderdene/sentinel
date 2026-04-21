<?php

namespace App\Mqtt\Handlers;

use App\Mqtt\Contracts\MqttHandler;

class HeartbeatHandler implements MqttHandler
{
    public function handle(string $topic, string $message): void
    {
        // TODO Plan 19-03
    }
}
