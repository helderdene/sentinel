<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\CameraEnrollmentStatus;
use App\Events\EnrollmentProgressed;
use App\Models\Camera;
use App\Models\CameraEnrollment;
use App\Services\CameraEnrollmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

/**
 * Chunked MQTT enrollment publish for a single camera. Runs on the `fras`
 * queue (Phase 19 Horizon supervisor). Mutex-serialized per camera via
 * WithoutOverlapping so two concurrent admin actions (e.g. editing a
 * Personnel photo twice in quick succession) do not produce an interleaved
 * MQTT payload stream to the same camera.
 *
 * Port of FRAS EnrollPersonnelBatch + IRMS deltas:
 *   - typed `$tries` (was 3, same)
 *   - explicit `$queue = 'fras'` so dispatch defaults do not leak into v1.0 queues
 *   - `failed()` handler transitions rows to failed + broadcasts
 *     `EnrollmentProgressed` so the admin progress panel reflects broker-
 *     unreachable scenarios without waiting for the 5-min ACK cache TTL.
 */
class EnrollPersonnelBatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<int, string>  $personnelIds  UUID list
     */
    public function __construct(
        public Camera $camera,
        public array $personnelIds,
    ) {
        // Queueable trait declares `public $queue` — set it via constructor
        // (cannot re-declare with typed property, causes trait-conflict fatal).
        $this->queue = 'fras';
    }

    /**
     * @return array<int, WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('enrollment-camera-'.$this->camera->id))
                ->releaseAfter(30)
                ->expireAfter(300),
        ];
    }

    public function handle(CameraEnrollmentService $service): void
    {
        $service->upsertBatch($this->camera, $this->personnelIds);
    }

    /**
     * Marks each referenced enrollment row failed with `last_error` from the
     * throwable and broadcasts progress per row. Covers the
     * broker-unreachable / upsertBatch-exception path (D-17).
     */
    public function failed(Throwable $e): void
    {
        $message = $e->getMessage() !== '' ? $e->getMessage() : 'Unknown error.';

        foreach ($this->personnelIds as $personnelId) {
            $enrollment = CameraEnrollment::where('camera_id', $this->camera->id)
                ->where('personnel_id', $personnelId)
                ->first();

            if ($enrollment === null) {
                continue;
            }

            $enrollment->update([
                'status' => CameraEnrollmentStatus::Failed,
                'last_error' => $message,
            ]);

            EnrollmentProgressed::dispatch($enrollment->fresh());
        }
    }
}
