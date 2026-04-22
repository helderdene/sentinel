<?php

namespace App\Http\Controllers;

use App\Enums\PersonnelCategory;
use App\Enums\RecognitionSeverity;
use App\Events\FrasAlertAcknowledged;
use App\Http\Requests\Fras\AcknowledgeFrasAlertRequest;
use App\Http\Requests\Fras\DismissFrasAlertRequest;
use App\Models\RecognitionEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

final class FrasAlertFeedController extends Controller
{
    /**
     * Render the /fras/alerts live feed page.
     *
     * Hydrates up to 100 non-ack non-dismissed Critical + Warning events,
     * mapping each into the AlertsPageProps shape per UI-SPEC §1.  Face-image
     * URLs are pre-signed (5-min TTL) at prop-build time so the Vue layer
     * never reaches back to the server for a URL. The Echo subscription on
     * the page keeps the buffer fresh; this initial payload seeds it on SSR.
     */
    public function index(): Response
    {
        $user = request()->user();

        $alerts = RecognitionEvent::query()
            ->with([
                'camera:id,camera_id_display,name',
                'personnel:id,name,category',
            ])
            ->whereIn('severity', [RecognitionSeverity::Critical, RecognitionSeverity::Warning])
            ->whereNull('acknowledged_at')
            ->whereNull('dismissed_at')
            ->whereNotNull('personnel_id')
            ->whereHas('personnel', fn ($q) => $q->whereIn('category', [
                PersonnelCategory::Block,
                PersonnelCategory::Missing,
                PersonnelCategory::LostChild,
            ]))
            ->orderByDesc('captured_at')
            ->limit(100)
            ->get()
            ->map(function (RecognitionEvent $event) use ($user) {
                $faceImageUrl = $event->face_image_path
                    ? URL::temporarySignedRoute(
                        'fras.event.face',
                        now()->addMinutes(5),
                        ['event' => $event->id],
                    )
                    : null;

                return [
                    'event_id' => $event->id,
                    'severity' => $event->severity->value,
                    'personnel' => [
                        'id' => $event->personnel->id,
                        'name' => $event->personnel->name,
                        'category' => $event->personnel->category->value,
                    ],
                    'camera' => [
                        'id' => $event->camera->id,
                        'camera_id_display' => $event->camera->camera_id_display,
                        'name' => $event->camera->name,
                    ],
                    'captured_at' => $event->captured_at?->toIso8601String(),
                    'face_image_url' => $faceImageUrl,
                    'can_promote' => $event->severity !== RecognitionSeverity::Critical
                        && $user?->can('view-fras-alerts'),
                ];
            });

        return Inertia::render('fras/Alerts', [
            'initialAlerts' => $alerts,
            'audioMuted' => (bool) ($user?->fras_audio_muted ?? false),
            'frasConfig' => ['audioEnabled' => true],
        ]);
    }

    /**
     * Acknowledge a FRAS recognition event.
     *
     * Guards against double-ack via a 409 Conflict when the event already
     * has acknowledged_at or dismissed_at set — Plan 22-06 Vue layer handles
     * the 409 silently (removes the card without an error toast).
     */
    public function acknowledge(AcknowledgeFrasAlertRequest $request, RecognitionEvent $event): RedirectResponse
    {
        Gate::authorize('view-fras-alerts');
        abort_if($event->acknowledged_at || $event->dismissed_at, 409);

        $user = $request->user();
        $now = now();

        $event->update([
            'acknowledged_by' => $user->id,
            'acknowledged_at' => $now,
        ]);

        FrasAlertAcknowledged::dispatch(
            $event->id,
            'ack',
            $user->id,
            $user->name,
            null,
            null,
            $now->toIso8601String(),
        );

        return back();
    }

    /**
     * Dismiss a FRAS recognition event with an audit-trail reason.
     */
    public function dismiss(DismissFrasAlertRequest $request, RecognitionEvent $event): RedirectResponse
    {
        Gate::authorize('view-fras-alerts');
        abort_if($event->acknowledged_at || $event->dismissed_at, 409);

        $user = $request->user();
        $now = now();
        $reason = $request->validated('reason');
        $reasonNote = $request->validated('reason_note');

        $event->update([
            'dismissed_by' => $user->id,
            'dismissed_at' => $now,
            'dismiss_reason' => $reason,
            'dismiss_reason_note' => $reasonNote,
        ]);

        FrasAlertAcknowledged::dispatch(
            $event->id,
            'dismiss',
            $user->id,
            $user->name,
            $reason,
            $reasonNote,
            $now->toIso8601String(),
        );

        return back();
    }
}
