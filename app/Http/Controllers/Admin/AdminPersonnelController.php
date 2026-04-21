<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PersonnelCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePersonnelRequest;
use App\Http\Requests\Admin\UpdatePersonnelRequest;
use App\Models\Personnel;
use App\Services\CameraEnrollmentService;
use App\Services\FrasPhotoProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminPersonnelController extends Controller
{
    public function __construct(
        private FrasPhotoProcessor $photoProcessor,
        private CameraEnrollmentService $enrollmentService,
    ) {}

    public function index(): Response
    {
        $personnel = Personnel::query()
            ->withCount([
                'enrollments as total_enrollments',
                'enrollments as done_enrollments' => fn ($q) => $q->where('status', 'done'),
                'enrollments as failed_enrollments' => fn ($q) => $q->where('status', 'failed'),
            ])
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/Personnel', [
            'personnel' => $personnel,
            'categories' => PersonnelCategory::cases(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/PersonnelForm', [
            'categories' => PersonnelCategory::cases(),
        ]);
    }

    public function store(StorePersonnelRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['photo']);

        // Create with a placeholder token; custom_id derived from the UUID below.
        $personnel = Personnel::create(array_merge($data, [
            'photo_access_token' => Str::uuid()->toString(),
            'custom_id' => null,
        ]));

        $personnel->update([
            'custom_id' => str_replace('-', '', (string) $personnel->id),
        ]);

        if ($request->hasFile('photo')) {
            $result = $this->photoProcessor->process($request->file('photo'), $personnel);
            $personnel->update([
                'photo_path' => $result['photo_path'],
                'photo_hash' => $result['photo_hash'],
            ]);
            // PersonnelObserver fires on the above update (photo_hash changed)
            // → enrolls across every online camera.
        }

        return redirect()
            ->route('admin.personnel.index')
            ->with('success', "Personnel {$personnel->name} created.");
    }

    public function edit(Personnel $personnel): Response
    {
        $personnel->load('enrollments.camera:id,camera_id_display,name');

        $photoSignedUrl = $personnel->photo_path
            ? URL::temporarySignedRoute(
                'admin.personnel.photo',
                now()->addMinutes(5),
                ['personnel' => $personnel->id],
            )
            : null;

        return Inertia::render('admin/PersonnelForm', [
            'personnel' => $personnel,
            'categories' => PersonnelCategory::cases(),
            'photo_signed_url' => $photoSignedUrl,
            'enrollment_rows' => $personnel->enrollments->map(fn ($e) => [
                'camera_id' => $e->camera_id,
                'camera_id_display' => $e->camera?->camera_id_display,
                'camera_name' => $e->camera?->name,
                'status' => $e->status?->value,
                'last_error' => $e->last_error,
                'enrolled_at' => $e->enrolled_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function update(UpdatePersonnelRequest $request, Personnel $personnel): RedirectResponse
    {
        $data = $request->safe()->except(['photo']);

        if ($request->hasFile('photo')) {
            $oldPath = $personnel->photo_path;
            $result = $this->photoProcessor->process($request->file('photo'), $personnel);
            // Only delete the old file if it differs — process() writes to
            // personnel/{id}.jpg, so the new upload already overwrote the old
            // file if the paths were identical.
            if ($oldPath !== null && $oldPath !== $result['photo_path']) {
                $this->photoProcessor->delete($oldPath);
            }

            $data['photo_path'] = $result['photo_path'];
            $data['photo_hash'] = $result['photo_hash'];
            $data['photo_access_token'] = Str::uuid()->toString(); // D-23 rotation
        }

        $personnel->update($data);
        // Observer re-enrolls if photo_hash or category mutated.

        return redirect()
            ->route('admin.personnel.index')
            ->with('success', "Personnel {$personnel->name} updated.");
    }

    public function destroy(Personnel $personnel): RedirectResponse
    {
        // D-33: preserve the row, decommission only.
        $personnel->update(['decommissioned_at' => now()]);
        $this->enrollmentService->deleteFromAllCameras($personnel);

        return redirect()
            ->route('admin.personnel.index')
            ->with('success', "Personnel {$personnel->name} removed from watch-list.");
    }

    public function recommission(Personnel $personnel): RedirectResponse
    {
        $personnel->update(['decommissioned_at' => null]);

        return redirect()
            ->route('admin.personnel.index')
            ->with('success', "Personnel {$personnel->name} restored.");
    }
}
