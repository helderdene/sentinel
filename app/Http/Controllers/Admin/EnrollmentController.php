<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CameraEnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Jobs\EnrollPersonnelBatch;
use App\Models\Camera;
use App\Models\CameraEnrollment;
use App\Models\Personnel;
use App\Services\CameraEnrollmentService;
use Illuminate\Http\RedirectResponse;

class EnrollmentController extends Controller
{
    public function __construct(private CameraEnrollmentService $service) {}

    public function retry(Personnel $personnel, Camera $camera): RedirectResponse
    {
        $enrollment = CameraEnrollment::where('camera_id', $camera->id)
            ->where('personnel_id', $personnel->id)
            ->firstOrFail();

        $enrollment->update([
            'status' => CameraEnrollmentStatus::Pending,
            'last_error' => null,
        ]);

        EnrollPersonnelBatch::dispatch($camera, [$personnel->id])->onQueue('fras');

        return redirect()
            ->back()
            ->with('success', "Retrying enrollment on {$camera->camera_id_display}.");
    }

    public function resyncAll(Personnel $personnel): RedirectResponse
    {
        $this->service->enrollPersonnel($personnel);

        return redirect()
            ->back()
            ->with('success', 'Resyncing across all active cameras.');
    }
}
