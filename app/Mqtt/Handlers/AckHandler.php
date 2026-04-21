<?php

declare(strict_types=1);

namespace App\Mqtt\Handlers;

use App\Enums\CameraEnrollmentStatus;
use App\Events\EnrollmentProgressed;
use App\Models\Camera;
use App\Models\CameraEnrollment;
use App\Models\Personnel;
use App\Mqtt\Contracts\MqttHandler;
use App\Services\CameraEnrollmentService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Correlates camera ACK responses to the dispatch-side cache entry set by
 * CameraEnrollmentService::upsertBatch, transitions the camera_enrollments
 * row per outcome, and broadcasts EnrollmentProgressed.
 *
 * Port of FRAS AckHandler + IRMS deltas (per 20-PATTERNS.md, D-16):
 *   - event rename: EnrollmentStatusChanged -> EnrollmentProgressed (D-38)
 *   - enum replaces string constants (STATUS_ENROLLED -> Done, STATUS_FAILED)
 *   - mqtt log channel for all warn paths (unknown camera / expired cache)
 *   - translateErrorCode lookup via injected service (consistent with FRAS)
 *
 * Cache::pull is an atomic read+delete — duplicate ACK deliveries see a
 * cache miss on the second call and produce exactly one transition
 * regardless of MQTT QoS re-send behaviour (T-20-03-07).
 */
class AckHandler implements MqttHandler
{
    public function __construct(private CameraEnrollmentService $service) {}

    public function handle(string $topic, string $message): void
    {
        $data = json_decode($message, true);

        if (! is_array($data)) {
            Log::channel('mqtt')->warning('Invalid ACK payload', ['topic' => $topic]);

            return;
        }

        $messageId = $data['messageId'] ?? null;

        if (! is_string($messageId) || $messageId === '') {
            Log::channel('mqtt')->warning('ACK missing messageId', ['topic' => $topic]);

            return;
        }

        // Extract device_id from topic: mqtt/face/{device_id}/Ack
        $segments = explode('/', $topic);
        $deviceId = $segments[2] ?? null;

        if (! is_string($deviceId) || $deviceId === '') {
            Log::channel('mqtt')->warning('ACK topic missing device_id', ['topic' => $topic]);

            return;
        }

        $camera = Camera::where('device_id', $deviceId)->first();

        if (! $camera) {
            Log::channel('mqtt')->warning('ACK for unknown camera', [
                'device_id' => $deviceId,
                'topic' => $topic,
            ]);

            return;
        }

        $cacheKey = "enrollment-ack:{$camera->id}:{$messageId}";
        $pending = Cache::pull($cacheKey);

        if ($pending === null) {
            Log::channel('mqtt')->warning('ACK for unknown or expired messageId', [
                'camera_id' => $camera->id,
                'messageId' => $messageId,
            ]);

            return;
        }

        $info = $data['info'] ?? [];

        $this->processSuccesses($camera, $info['AddSucInfo'] ?? [], $pending);
        $this->processFailures($camera, $info['AddErrInfo'] ?? []);
    }

    /**
     * @param  array<int, array{customId?: string}>  $successes
     * @param  array<string, mixed>  $pending
     */
    private function processSuccesses(Camera $camera, array $successes, array $pending): void
    {
        $photoHashes = $pending['photo_hashes'] ?? [];

        foreach ($successes as $entry) {
            $customId = $entry['customId'] ?? null;

            if (! is_string($customId)) {
                continue;
            }

            $personnel = Personnel::where('custom_id', $customId)->first();

            if (! $personnel) {
                continue;
            }

            $enrollment = CameraEnrollment::where('camera_id', $camera->id)
                ->where('personnel_id', $personnel->id)
                ->first();

            if (! $enrollment) {
                continue;
            }

            $enrollment->update([
                'status' => CameraEnrollmentStatus::Done,
                'enrolled_at' => now(),
                'photo_hash' => $photoHashes[$customId] ?? $enrollment->photo_hash,
                'last_error' => null,
            ]);

            EnrollmentProgressed::dispatch($enrollment->fresh());
        }
    }

    /**
     * @param  array<int, array{customId?: string, errorCode?: int}>  $failures
     */
    private function processFailures(Camera $camera, array $failures): void
    {
        foreach ($failures as $entry) {
            $customId = $entry['customId'] ?? null;
            $code = isset($entry['errorCode']) ? (int) $entry['errorCode'] : 0;

            if (! is_string($customId)) {
                continue;
            }

            $personnel = Personnel::where('custom_id', $customId)->first();

            if (! $personnel) {
                continue;
            }

            $enrollment = CameraEnrollment::where('camera_id', $camera->id)
                ->where('personnel_id', $personnel->id)
                ->first();

            if (! $enrollment) {
                continue;
            }

            $enrollment->update([
                'status' => CameraEnrollmentStatus::Failed,
                'last_error' => $this->service->translateErrorCode($code),
            ]);

            EnrollmentProgressed::dispatch($enrollment->fresh());
        }
    }
}
