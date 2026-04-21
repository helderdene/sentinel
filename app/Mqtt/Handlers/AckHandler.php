<?php

namespace App\Mqtt\Handlers;

use App\Mqtt\Contracts\MqttHandler;
use Illuminate\Support\Facades\Log;

class AckHandler implements MqttHandler
{
    /**
     * Phase 19 ACK handler is a log-only scaffold. The correlation cache and
     * enrollment state update arrive in Phase 20 (EnrollPersonnelBatch). Here
     * we only record receipt on the mqtt log channel so operators can see
     * device replies in storage/logs/mqtt.log during the Phase 19 smoke test.
     */
    public function handle(string $topic, string $message): void
    {
        $payload = json_decode($message, true);

        Log::channel('mqtt')->info('ACK received', [
            'topic' => $topic,
            'payload' => $payload,
        ]);

        // Phase 20 fills in correlation cache + enrollment state update.
    }
}
