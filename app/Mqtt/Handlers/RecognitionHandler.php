<?php

namespace App\Mqtt\Handlers;

use App\Enums\RecognitionSeverity;
use App\Models\Camera;
use App\Models\RecognitionEvent;
use App\Mqtt\Contracts\MqttHandler;
use App\Services\FrasIncidentFactory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RecognitionHandler implements MqttHandler
{
    public function __construct(
        private FrasIncidentFactory $factory,
    ) {}

    /** Face image size cap in bytes (1 MB) — Pitfall 17. */
    private const FACE_IMAGE_MAX_BYTES = 1_048_576;

    /** Scene image size cap in bytes (2 MB) — Pitfall 17. */
    private const SCENE_IMAGE_MAX_BYTES = 2_097_152;

    /**
     * Parse a RecPush payload, classify severity, persist a recognition_events row
     * idempotently (camera_id, record_id UNIQUE), and write face + scene images to
     * the fras_events private disk under {YYYY-MM-DD}/{faces|scenes}/{event_id}.jpg.
     *
     * FRAS-specific columns (custom_id, camera_person_id, facesluice_id, id_card,
     * is_no_mask, target_bbox, is_real_time) that exist in the Phase 18 schema are
     * left at their defaults here — the full payload survives in `raw_payload`
     * (JSONB) for later Phase 22 DPA extraction.
     */
    public function handle(string $topic, string $message): void
    {
        $payload = json_decode($message, true);

        if (! is_array($payload)) {
            Log::channel('mqtt')->warning('RecPush payload not JSON', ['topic' => $topic]);

            return;
        }

        $deviceId = $payload['deviceId'] ?? null;
        $recordId = $payload['recordId'] ?? null;

        if (! $deviceId || $recordId === null) {
            Log::channel('mqtt')->warning('RecPush missing deviceId or recordId', ['topic' => $topic]);

            return;
        }

        $camera = Camera::where('device_id', $deviceId)->first();

        if (! $camera) {
            Log::channel('mqtt')->warning('RecPush for unknown camera', [
                'device_id' => $deviceId,
                'topic' => $topic,
            ]);

            return;
        }

        // FRAS firmware typo fallback: accept personName OR persionName (D-61).
        $personName = $payload['personName'] ?? $payload['persionName'] ?? null;
        $personType = (int) ($payload['personType'] ?? 0);
        $verifyStatus = (int) ($payload['verifyStatus'] ?? 0);
        $similarity = isset($payload['similarity']) ? (float) $payload['similarity'] : 0.0;

        $severity = RecognitionSeverity::fromEvent($personType, $verifyStatus);
        $capturedAt = isset($payload['capturedAt']) ? Carbon::parse($payload['capturedAt']) : now();

        try {
            // Nested DB::transaction emits a SAVEPOINT on Postgres so a
            // UNIQUE violation here does NOT poison the outer
            // RefreshDatabase test transaction (D-03 idempotency).
            /** @var RecognitionEvent $event */
            $event = DB::transaction(fn () => RecognitionEvent::create([
                'camera_id' => $camera->id,
                'record_id' => (int) $recordId,
                'name_from_camera' => $personName,
                'person_type' => $personType,
                'verify_status' => $verifyStatus,
                'similarity' => $similarity,
                'is_real_time' => true,
                'is_no_mask' => 0,
                'severity' => $severity,
                'raw_payload' => $payload,
                'captured_at' => $capturedAt,
                'received_at' => now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            // D-03: duplicate (camera_id, record_id) → idempotent no-op.
            Log::channel('mqtt')->info('Duplicate RecPush rejected at DB layer', [
                'camera_id' => $camera->id,
                'record_id' => $recordId,
            ]);

            return;
        }

        $datePrefix = $capturedAt->format('Y-m-d');

        $this->persistImage(
            $payload['faceImage'] ?? null,
            "{$datePrefix}/faces/{$event->id}.jpg",
            self::FACE_IMAGE_MAX_BYTES,
            'face',
            $event,
            'face_image_path',
        );

        $this->persistImage(
            $payload['sceneImage'] ?? null,
            "{$datePrefix}/scenes/{$event->id}.jpg",
            self::SCENE_IMAGE_MAX_BYTES,
            'scene',
            $event,
            'scene_image_path',
        );

        // Phase 21 D-07/D-10: hand the persisted event to the factory. The
        // factory owns the 5-gate chain and all downstream broadcasts
        // (IncidentCreated + RecognitionAlertReceived). Return value is
        // informational only — the handler's contract is persist + images.
        $this->factory->createFromRecognition($event);
    }

    /**
     * Decode a base64 image, enforce the size cap, write to the fras_events disk,
     * and persist the resulting path on the given RecognitionEvent column.
     */
    private function persistImage(?string $base64, string $path, int $maxBytes, string $kind, RecognitionEvent $event, string $column): void
    {
        if (! $base64) {
            return;
        }

        $binary = base64_decode($base64, true);

        if ($binary === false || $binary === '') {
            Log::channel('mqtt')->warning("Invalid base64 for {$kind} image", ['event_id' => $event->id]);

            return;
        }

        if (strlen($binary) > $maxBytes) {
            Log::channel('mqtt')->warning("{$kind} image exceeds size cap", [
                'event_id' => $event->id,
                'bytes' => strlen($binary),
                'cap_bytes' => $maxBytes,
            ]);

            return;
        }

        Storage::disk('fras_events')->put($path, $binary);
        $event->update([$column => $path]);
    }
}
