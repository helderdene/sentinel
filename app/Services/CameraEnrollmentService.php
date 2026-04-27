<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CameraEnrollmentStatus;
use App\Enums\CameraStatus;
use App\Enums\PersonnelCategory;
use App\Events\EnrollmentProgressed;
use App\Jobs\EnrollPersonnelBatch;
use App\Models\Camera;
use App\Models\CameraEnrollment;
use App\Models\Personnel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpMqtt\Client\Facades\MQTT;
use RuntimeException;

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
     * Execute an enrollment: send one `EditPerson` MQTT publish per person with
     * the photo embedded as base64 (spec §4.1). Cache correlation, transition
     * rows to `syncing`, broadcast progress, publish MQTT. Per spec, EditPerson
     * calls must be separated by ≥1s when sending base64 image data.
     *
     * @param  array<int, string>  $personnelIds  UUID list
     */
    public function upsertBatch(Camera $camera, array $personnelIds): void
    {
        $personnel = Personnel::whereIn('id', $personnelIds)->get();
        $ttl = (int) config('fras.enrollment.ack_timeout_minutes', 5) * 60;
        $prefix = (string) config('fras.mqtt.topic_prefix', 'mqtt/face');
        $interval = (int) config('fras.enrollment.edit_person_interval_ms', 1100);

        $first = true;

        foreach ($personnel as $p) {
            if (! $first && $interval > 0) {
                usleep($interval * 1000);
            }
            $first = false;

            $messageId = 'EditPerson'.now()->format('Y-m-d\TH:i:s').'_'.Str::random(6);
            $payload = $this->buildEditPersonPayload($p, $messageId);

            Cache::put(
                "enrollment-ack:{$camera->id}:{$messageId}",
                [
                    'camera_id' => $camera->id,
                    'personnel_ids' => [$p->id],
                    'photo_hashes' => [$p->custom_id => $p->photo_hash],
                    'dispatched_at' => now()->toIso8601String(),
                ],
                $ttl,
            );

            $enrollment = CameraEnrollment::where('camera_id', $camera->id)
                ->where('personnel_id', $p->id)
                ->first();

            if ($enrollment !== null) {
                $enrollment->update(['status' => CameraEnrollmentStatus::Syncing]);
                EnrollmentProgressed::dispatch($enrollment->fresh());
            }

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
     * Build an EditPerson MQTT payload (spec §4.1) for a single person with the
     * photo embedded as a base64 data URI in the `pic` field. Bypasses the
     * picURI approach so cameras that cannot reach our HTTP host still enroll.
     *
     * @return array<string, mixed>
     */
    public function buildEditPersonPayload(Personnel $p, string $messageId): array
    {
        // personType per spec §4.1: 0 = Allow list, 1 = Block list.
        // IRMS has four categories — only Allow maps to 0; Block/Missing/LostChild
        // all mean "alert when seen", which is what the camera's block list does.
        $personType = $p->category === PersonnelCategory::Allow ? 0 : 1;

        $info = [
            'customId' => $p->custom_id,
            'name' => $p->name,
            'personType' => $personType,
        ];

        if ($p->photo_path !== null) {
            $info['pic'] = $this->readPhotoAsBase64DataUri($p);
        }

        if ($p->gender !== null) {
            $info['gender'] = $p->gender;
        }

        if ($p->birthday) {
            $info['birthday'] = $p->birthday->format('Y-m-d');
        }

        if ($p->id_card) {
            $info['idCard'] = $p->id_card;
        }

        if ($p->phone) {
            $info['telnum1'] = $p->phone;
        }

        if ($p->address) {
            $info['address'] = $p->address;
        }

        return [
            'messageId' => $messageId,
            'operator' => 'EditPerson',
            'info' => $info,
        ];
    }

    /**
     * Read the personnel photo from the `fras_photos` disk and encode it as a
     * data URI for the EditPerson `pic` field. Throws if the file is missing
     * or exceeds the 1 MB post-encoding limit set by the camera firmware.
     */
    private function readPhotoAsBase64DataUri(Personnel $p): string
    {
        $disk = Storage::disk('fras_photos');
        $path = 'personnel/'.$p->id.'.jpg';

        if (! $disk->exists($path)) {
            throw new RuntimeException("Personnel photo missing: {$path}");
        }

        $binary = $disk->get($path);
        $encoded = base64_encode($binary);

        if (strlen($encoded) > 1024 * 1024) {
            throw new RuntimeException('Personnel photo exceeds 1 MB limit after base64 encoding.');
        }

        $mime = str_ends_with(strtolower($path), '.png') ? 'image/png' : 'image/jpeg';

        return "data:{$mime};base64,{$encoded}";
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
     * Translate a camera enrollment error code to an operator-friendly string.
     * Codes mapped per MQTT Protocol of Intelligent Network Camera v1.25
     * §29.6 (batch add URI) and §29.8 (batch add/modify URI).
     * Unknown codes surface the numeric value only, no raw firmware strings
     * (T-20-02-06).
     */
    public function translateErrorCode(int $code): string
    {
        return match ($code) {
            // Command-level errors (code field)
            410 => 'Camera is still processing the previous enrollment batch — retrying shortly.',
            412, 413, 414, 415, 418 => 'Camera rejected the enrollment request (malformed payload).',
            416 => 'Enrollment batch exceeded the 1000-person limit.',
            417 => 'Enrollment batch count mismatch — rebuild and retry.',
            460 => 'Enrollment payload exceeds the 1 MB limit.',

            // Per-person errors (errcode field)
            461 => 'Camera already has a person enrolled with this customId.',
            462 => 'Camera already has a person with this RFID card.',
            463 => 'Photo URL missing from enrollment payload.',
            464 => 'Camera could not resolve our server address (check the camera\'s DNS).',
            465 => 'Camera could not download the photo (timeout or DNS issue — check camera network).',
            466 => 'Camera could not read the downloaded photo file.',
            467 => 'Photo is too large — must be under 1 MB and 1080p.',
            468 => 'No face could be detected in the photo.',
            469 => 'Camera failed to write the photo to its database.',
            470 => 'Camera failed to write the person record to its database.',
            478 => 'A similar face is already enrolled on this camera (possible duplicate).',

            default => 'Camera returned error code '.$code.'.',
        };
    }
}
