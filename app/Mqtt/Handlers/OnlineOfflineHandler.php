<?php

namespace App\Mqtt\Handlers;

use App\Enums\CameraStatus;
use App\Models\Camera;
use App\Mqtt\Contracts\MqttHandler;
use Illuminate\Support\Facades\Log;

class OnlineOfflineHandler implements MqttHandler
{
    /**
     * Flip `cameras.status` to CameraStatus::Online or ::Offline based on the
     * `operator` field of the payload. Only the literal strings "Online" and
     * "Offline" are accepted per D-08 — Degraded is reserved for Phase 20 and
     * is never written here. Unknown devices and malformed operators emit a
     * warning log on the mqtt channel and return without mutating state.
     */
    public function handle(string $topic, string $message): void
    {
        $payload = json_decode($message, true);

        if (! is_array($payload)) {
            Log::channel('mqtt')->warning('Malformed basic message', ['topic' => $topic]);

            return;
        }

        // Real hardware publishes `facesluiceId` nested under `info`; synthetic
        // tests use the top-level form. Accept both for FRAS-parity (FRAS uses
        // `$data['info']['facesluiceId']` verbatim — see fras reference source).
        $deviceId = $payload['info']['facesluiceId'] ?? $payload['facesluiceId'] ?? null;
        $operator = $payload['operator'] ?? null;

        if (! $deviceId || ! in_array($operator, ['Online', 'Offline'], true)) {
            Log::channel('mqtt')->warning('Malformed basic message', [
                'topic' => $topic,
                'payload' => $payload,
            ]);

            return;
        }

        $status = $operator === 'Online' ? CameraStatus::Online : CameraStatus::Offline;

        $updated = Camera::where('device_id', $deviceId)->update(['status' => $status]);

        if ($updated === 0) {
            Log::channel('mqtt')->warning('basic message for unknown camera', [
                'device_id' => $deviceId,
            ]);
        }
    }
}
