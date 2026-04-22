<?php

namespace App\Http\Controllers;

use App\Enums\FrasAccessAction;
use App\Enums\FrasAccessSubject;
use App\Enums\UserRole;
use App\Models\FrasAccessLog;
use App\Models\RecognitionEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class FrasEventSceneController extends Controller
{
    /**
     * Stream the wide scene image for a RecognitionEvent.
     *
     * Access-controlled by a temporarySignedRoute URL (5-min TTL) AND by
     * role gate — operator, supervisor, admin only. Responders and
     * dispatchers are denied here; this is defense-in-depth layer 1 of the
     * three-layer responder exclusion per Phase 22 CONTEXT D-26 (layer 2 is
     * ResponderController prop-strip, layer 3 is routes/channels.php).
     *
     * Every successful fetch appends a fras_access_log row inside a
     * DB::transaction BEFORE the stream is returned — any DB error throws
     * out of the transaction and the stream never reaches the caller
     * (D-16 sync-audit guarantee).
     */
    public function show(Request $request, RecognitionEvent $event): StreamedResponse
    {
        $user = $request->user();
        $allowedRoles = [UserRole::Operator, UserRole::Supervisor, UserRole::Admin];

        abort_unless($user && in_array($user->role, $allowedRoles, true), 403);
        abort_unless($event->scene_image_path, 404);

        $disk = Storage::disk('fras_events');
        abort_unless($disk->exists($event->scene_image_path), 404);

        DB::transaction(function () use ($request, $user, $event) {
            FrasAccessLog::create([
                'actor_user_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'subject_type' => FrasAccessSubject::RecognitionEventScene->value,
                'subject_id' => $event->id,
                'action' => FrasAccessAction::View->value,
                'accessed_at' => now(),
            ]);
        });

        return $disk->response($event->scene_image_path, basename($event->scene_image_path), [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
