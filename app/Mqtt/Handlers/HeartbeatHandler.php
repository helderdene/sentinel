<?php

namespace App\Mqtt\Handlers;

use App\Models\Camera;
use App\Mqtt\Contracts\MqttHandler;
use Illuminate\Support\Facades\Log;

class HeartbeatHandler implements MqttHandler
{
    /**
     * Bump `cameras.last_seen_at = now()` for a known device_id (resolved via
     * the payload's `info.facesluiceId` field — FRAS-parity wire shape; the
     * `info` wrapper is how real hardware publishes). Top-level `facesluiceId`
     * is accepted as a compatibility fallback for synthetic test payloads.
     * Unknown devices log a warning on the mqtt channel and return without
     * mutating state.
     *
     * Note: IRMS uses `last_seen_at` rather than FRAS's `last_heartbeat_at` —
     * the column is defined in the cameras migration as a nullable TIMESTAMPTZ.
     */
    public function handle(string $topic, string $message): void
    {
        $payload = json_decode($message, true);
        $deviceId = is_array($payload)
            ? ($payload['info']['facesluiceId'] ?? $payload['facesluiceId'] ?? null)
            : null;

        if (! $deviceId) {
            Log::channel('mqtt')->warning('Heartbeat missing facesluiceId', ['topic' => $topic]);

            return;
        }

        $updated = Camera::where('device_id', $deviceId)->update(['last_seen_at' => now()]);

        if ($updated === 0) {
            Log::channel('mqtt')->warning('Heartbeat for unknown camera', [
                'device_id' => $deviceId,
                'topic' => $topic,
            ]);
        }
    }
}
