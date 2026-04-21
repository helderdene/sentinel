<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CameraEnrollmentStatus;
use App\Enums\CameraStatus;
use App\Events\EnrollmentProgressed;
use App\Jobs\EnrollPersonnelBatch;
use App\Models\Camera;
use App\Models\CameraEnrollment;
use App\Models\Personnel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PhpMqtt\Client\Facades\MQTT;

/**
 * Port of FRAS CameraEnrollmentService adapted for IRMS (phase 20, D-11/D-14/D-15):
 *
 *   - FRAS boolean camera flag -> `$camera->status === CameraStatus::Online`
 *   - FRAS legacy config namespace -> `config('fras.*')`
 *   - UUID FKs (IRMS Personnel/Camera use HasUuids)
 *   - Dispatches `EnrollmentProgressed` on every status transition
 *
 * Downstream plans:
 *   - Plan 03 implements `EnrollPersonnelBatch` (currently a stub)
 *   - Plan 04/05 inject this service into admin controllers
 *   - Plan 06 calls `deleteFromAllCameras` in the expire-sweep command
 */
final class CameraEnrollmentService
{
    /**
     * Enroll a single personnel record across every online, non-decommissioned camera.
     * Per-camera: upsert the enrollment row (status=pending), broadcast progress,
     * and dispatch a single-item EnrollPersonnelBatch job.
     */
    public function enrollPersonnel(Personnel $personnel): void
    {
        $cameras = Camera::query()
            ->whereNull('decommissioned_at')
            ->where('status', CameraStatus::Online)
            ->get();

        foreach ($cameras as $camera) {
            $enrollment = CameraEnrollment::updateOrCreate(
                ['camera_id' => $camera->id, 'personnel_id' => $personnel->id],
                ['status' => CameraEnrollmentStatus::Pending, 'last_error' => null],
            );
            EnrollmentProgressed::dispatch($enrollment);

            EnrollPersonnelBatch::dispatch($camera, [$personnel->id])->onQueue('fras');
        }
    }

    /**
     * Enroll every non-decommissioned personnel with a photo_hash to a single camera,
     * chunked by `fras.enrollment.batch_size`. Used when a new camera comes online.
     */
    public function enrollAllToCamera(Camera $camera): void
    {
        if ($camera->status !== CameraStatus::Online || $camera->decommissioned_at !== null) {
            return;
        }

        $personnel = Personnel::query()
            ->whereNull('decommissioned_at')
            ->whereNotNull('photo_hash')
            ->get();

        $batchSize = (int) config('fras.enrollment.batch_size', 10);

        foreach ($personnel->chunk($batchSize) as $chunk) {
            $ids = $chunk->pluck('id')->toArray();

            foreach ($chunk as $p) {
                $enrollment = CameraEnrollment::updateOrCreate(
                    ['camera_id' => $camera->id, 'personnel_id' => $p->id],
                    ['status' => CameraEnrollmentStatus::Pending, 'last_error' => null],
                );
                EnrollmentProgressed::dispatch($enrollment);
            }

            EnrollPersonnelBatch::dispatch($camera, $ids)->onQueue('fras');
        }
    }

    /**
     * Execute an enrollment batch: chunk, build payload, cache correlation, transition
     * rows to `syncing`, broadcast progress, publish MQTT.
     *
     * @param  array<int, string>  $personnelIds  UUID list
     */
    public function upsertBatch(Camera $camera, array $personnelIds): void
    {
        $personnel = Personnel::whereIn('id', $personnelIds)->get();
        $batchSize = (int) config('fras.enrollment.batch_size', 10);
        $ttl = (int) config('fras.enrollment.ack_timeout_minutes', 5) * 60;

        foreach ($personnel->chunk($batchSize) as $chunk) {
            $messageId = 'EditPersonsNew'.now()->format('Y-m-d\TH:i:s').'_'.Str::random(6);

            $payload = $this->buildEditPersonsNewPayload($camera, $chunk, $messageId);

            Cache::put(
                "enrollment-ack:{$camera->id}:{$messageId}",
                [
                    'camera_id' => $camera->id,
                    'personnel_ids' => $chunk->pluck('id')->toArray(),
                    'photo_hashes' => $chunk->pluck('photo_hash', 'custom_id')->toArray(),
                    'dispatched_at' => now()->toIso8601String(),
                ],
                $ttl,
            );

            foreach ($chunk as $p) {
                $enrollment = CameraEnrollment::where('camera_id', $camera->id)
                    ->where('personnel_id', $p->id)
                    ->first();

                if ($enrollment !== null) {
                    $enrollment->update(['status' => CameraEnrollmentStatus::Syncing]);
                    EnrollmentProgressed::dispatch($enrollment->fresh());
                }
            }

            $prefix = (string) config('fras.mqtt.topic_prefix', 'mqtt/face');
            MQTT::connection('publisher')->publish(
                "{$prefix}/{$camera->device_id}",
                json_encode($payload, JSON_UNESCAPED_SLASHES),
            );
        }
    }

    /**
     * Fire-and-forget DeletePersons publish to every online, non-decommissioned camera.
     * No ACK tracking — D-14 (delete is idempotent on the camera side).
     */
    public function deleteFromAllCameras(Personnel $personnel): void
    {
        $cameras = Camera::query()
            ->whereNull('decommissioned_at')
            ->where('status', CameraStatus::Online)
            ->get();

        $prefix = (string) config('fras.mqtt.topic_prefix', 'mqtt/face');

        foreach ($cameras as $camera) {
            $payload = $this->buildDeletePersonsPayload($camera, collect([$personnel]));
            MQTT::connection('publisher')->publish(
                "{$prefix}/{$camera->device_id}",
                json_encode($payload, JSON_UNESCAPED_SLASHES),
            );
        }
    }

    /**
     * Build an EditPersonsNew MQTT payload (FRAS spec §3.5) for a batch of personnel.
     * `picURI` references the token-gated fras.photo.show URL via Personnel::photo_url
     * (Plan 01 accessor). Only personnel with a `photo_path` contribute a picURI.
     *
     * @param  Collection<int, Personnel>  $personnel
     * @return array<string, mixed>
     */
    public function buildEditPersonsNewPayload(Camera $camera, Collection $personnel, string $messageId): array
    {
        $persons = $personnel->map(function (Personnel $p): array {
            $entry = [
                'customId' => $p->custom_id,
                'name' => $p->name,
                'personType' => $p->person_type ?? null,
                'isCheckSimilarity' => 1,
            ];

            if ($p->photo_path !== null) {
                // Personnel::photo_url accessor (plan 01) returns the token-gated URL.
                $entry['picURI'] = $p->photo_url;
            }

            if ($p->gender !== null) {
                $entry['gender'] = $p->gender;
            }

            if ($p->birthday) {
                $entry['birthday'] = $p->birthday->format('Y-m-d');
            }

            if ($p->id_card) {
                $entry['idCard'] = $p->id_card;
            }

            if ($p->phone) {
                $entry['telnum1'] = $p->phone;
            }

            if ($p->address) {
                $entry['address'] = $p->address;
            }

            // Drop nulls (personType is optional in IRMS personnel schema).
            return array_filter($entry, fn ($v) => $v !== null);
        })->values()->toArray();

        return [
            'messageId' => $messageId,
            'DataBegin' => 'BeginFlag',
            'operator' => 'EditPersonsNew',
            'PersonNum' => count($persons),
            'info' => $persons,
            'DataEnd' => 'EndFlag',
        ];
    }

    /**
     * Build a DeletePersons MQTT payload (FRAS spec §3.6).
     *
     * @param  Collection<int, Personnel>  $personnel
     * @return array<string, mixed>
     */
    public function buildDeletePersonsPayload(Camera $camera, Collection $personnel): array
    {
        $messageId = 'DeletePersons'.now()->format('Y-m-d\TH:i:s').'_'.Str::random(6);

        return [
            'messageId' => $messageId,
            'operator' => 'DeletePersons',
            'info' => $personnel->map(fn (Personnel $p) => ['customId' => $p->custom_id])
                ->values()
                ->toArray(),
        ];
    }

    /**
     * Translate a camera enrollment error code (FRAS spec appendix) to an
     * operator-friendly string. Unknown codes surface the numeric value only,
     * no raw firmware strings (T-20-02-06).
     */
    public function translateErrorCode(int $code): string
    {
        return match ($code) {
            461 => 'Camera rejected the enrollment request (invalid payload format).',
            463 => 'Camera database is full — free up face-recognition storage and retry.',
            464 => 'Camera could not resolve the photo URL (transient — will retry).',
            465 => 'Camera could not download the photo (transient — will retry).',
            466 => 'Camera could not read the photo file (transient — will retry).',
            467 => 'Photo is not a valid face image (no face detected).',
            468 => 'Photo face is too small or low-quality for enrollment.',
            474 => 'Camera reported duplicate customId.',
            478 => 'Camera is offline or unreachable.',
            default => 'Camera returned error code '.$code.'.',
        };
    }
}
