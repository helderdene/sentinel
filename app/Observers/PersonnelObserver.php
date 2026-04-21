<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Personnel;
use App\Services\CameraEnrollmentService;

/**
 * Bridges Personnel model mutations to FRAS camera enrollment sync.
 *
 * Per D-13, the `saved` hook is gated on `wasChanged(['photo_hash',
 * 'category'])` — the two fields that materially affect what gets pushed
 * to cameras. Name/phone/address edits are admin metadata and do NOT
 * trigger re-enrollment.
 *
 * The `deleted` hook fires DeletePersons to every online camera so the
 * face database drops the record without waiting for expire-sweep.
 */
class PersonnelObserver
{
    public function __construct(private CameraEnrollmentService $service) {}

    public function saved(Personnel $personnel): void
    {
        if (! $personnel->wasChanged(['photo_hash', 'category'])) {
            return;
        }

        $this->service->enrollPersonnel($personnel);
    }

    public function deleted(Personnel $personnel): void
    {
        $this->service->deleteFromAllCameras($personnel);
    }
}
