<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\RecognitionEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class FrasEventFaceController extends Controller
{
    /**
     * Stream the face image crop for a RecognitionEvent.
     *
     * Access-controlled by a temporarySignedRoute URL (5-min TTL, generated
     * by IntakeStationController::show() per D-18) AND by role gate — only
     * operator, supervisor, admin roles reach this endpoint. Responders and
     * dispatchers are denied per DPA preview (D-11 responder exclusion
     * principle). Phase 22 will layer `fras_access_log` writes here
     * (per-fetch audit row).
     */
    public function show(Request $request, RecognitionEvent $event): StreamedResponse
    {
        $user = $request->user();
        $allowedRoles = [UserRole::Operator, UserRole::Supervisor, UserRole::Admin];

        abort_unless($user && in_array($user->role, $allowedRoles, true), 403);
        abort_unless($event->face_image_path, 404);

        // Phase 19/20 convention: face crops stored on the 'fras_events' private disk.
        $disk = Storage::disk('fras_events');
        abort_unless($disk->exists($event->face_image_path), 404);

        // TODO(Phase 22): append row to fras_access_log capturing actor + IP + image ref + timestamp.

        return $disk->response($event->face_image_path, basename($event->face_image_path), [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=60',
        ]);
    }
}
