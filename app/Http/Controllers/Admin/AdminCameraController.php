<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CameraStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCameraRequest;
use App\Http\Requests\Admin\UpdateCameraRequest;
use App\Models\Camera;
use App\Services\BarangayLookupService;
use App\Services\CameraEnrollmentService;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminCameraController extends Controller
{
    public function __construct(
        private CameraEnrollmentService $enrollmentService,
        private BarangayLookupService $barangayLookup,
    ) {}

    /**
     * Display a listing of cameras (including decommissioned — D-08 UI filters client-side).
     */
    public function index(): Response
    {
        $cameras = Camera::query()
            ->withCount(['enrollments as total_enrollments'])
            ->with('barangay:id,name')
            ->orderBy('camera_id_display')
            ->get();

        return Inertia::render('admin/Cameras', [
            'cameras' => $cameras,
            'statuses' => CameraStatus::cases(),
        ]);
    }

    /**
     * Show the form for creating a new camera.
     */
    public function create(): Response
    {
        return Inertia::render('admin/CameraForm', [
            'statuses' => CameraStatus::cases(),
        ]);
    }

    /**
     * Store a newly created camera.
     *
     * D-09 auto-sequence + D-14 barangay lookup + enrollAllToCamera fan-out.
     */
    public function store(StoreCameraRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $maxSequence = Camera::query()
            ->selectRaw("MAX(CAST(SUBSTRING(camera_id_display FROM '[0-9]+$') AS INTEGER)) as max_seq")
            ->value('max_seq');
        $cameraIdDisplay = sprintf('CAM-%02d', ((int) ($maxSequence ?? 0)) + 1);

        $barangay = $this->barangayLookup->findByCoordinates(
            (float) $validated['latitude'],
            (float) $validated['longitude'],
        );

        $camera = Camera::query()->create([
            'name' => $validated['name'],
            'device_id' => $validated['device_id'],
            'camera_id_display' => $cameraIdDisplay,
            'status' => CameraStatus::Offline, // freshly registered — watchdog flips to online on first heartbeat
            'location' => Point::makeGeodetic((float) $validated['latitude'], (float) $validated['longitude']),
            'location_label' => $validated['location_label'] ?? null,
            'barangay_id' => $barangay?->id,
            'notes' => $validated['notes'] ?? null,
        ]);

        $this->enrollmentService->enrollAllToCamera($camera);

        return redirect()
            ->route('admin.cameras.index')
            ->with('success', "Camera {$camera->camera_id_display} created.");
    }

    /**
     * Show the form for editing the specified camera.
     */
    public function edit(Camera $camera): Response
    {
        $camera->load('barangay:id,name');

        return Inertia::render('admin/CameraForm', [
            'camera' => $camera,
            'statuses' => CameraStatus::cases(),
        ]);
    }

    /**
     * Update the specified camera — re-runs barangay lookup on coord change.
     */
    public function update(UpdateCameraRequest $request, Camera $camera): RedirectResponse
    {
        $validated = $request->validated();

        $barangay = $this->barangayLookup->findByCoordinates(
            (float) $validated['latitude'],
            (float) $validated['longitude'],
        );

        $camera->update([
            'name' => $validated['name'],
            'device_id' => $validated['device_id'],
            'location' => Point::makeGeodetic((float) $validated['latitude'], (float) $validated['longitude']),
            'location_label' => $validated['location_label'] ?? null,
            'barangay_id' => $barangay?->id,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('admin.cameras.index')
            ->with('success', "Camera {$camera->camera_id_display} updated.");
    }

    /**
     * Soft-decommission the specified camera.
     *
     * D-28 guard: blocks when any enrollment row is pending/syncing.
     */
    public function destroy(Camera $camera): RedirectResponse
    {
        $inFlight = $camera->enrollments()
            ->whereIn('status', ['pending', 'syncing'])
            ->count();

        if ($inFlight > 0) {
            $message = "This camera has {$inFlight} in-flight enrollment"
                .($inFlight === 1 ? '' : 's')
                .'. Wait for them to complete, or open the person\'s edit page and retry any failed enrollments, then try again.';

            return redirect()
                ->route('admin.cameras.index')
                ->withErrors(['camera' => $message]);
        }

        $camera->update(['decommissioned_at' => now()]);

        return redirect()
            ->route('admin.cameras.index')
            ->with('success', "Camera {$camera->camera_id_display} decommissioned.");
    }

    /**
     * Recommission a decommissioned camera (D-29).
     */
    public function recommission(Camera $camera): RedirectResponse
    {
        $camera->update(['decommissioned_at' => null]);

        return redirect()
            ->route('admin.cameras.index')
            ->with('success', "Camera {$camera->camera_id_display} recommissioned.");
    }
}
