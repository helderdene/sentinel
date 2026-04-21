<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Camera;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Temporary stub created by plan 20-02 so CameraEnrollmentService dispatches
 * compile without referencing an undefined class. Plan 20-03 Task 1 replaces
 * this stub with the full job implementation (chunked MQTT publish + retry
 * handling). DO NOT add behavior here — the real handler lands in plan 20-03.
 *
 * @internal — removed/replaced by plan 20-03
 */
class EnrollPersonnelBatch implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, string>  $personnelIds  UUID list
     */
    public function __construct(public Camera $camera, public array $personnelIds) {}

    public function handle(): void
    {
        // Stub — real implementation ships in plan 20-03 Task 1.
    }
}
