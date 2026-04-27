<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\RecognitionEvent;
use Illuminate\Support\Facades\URL;

/**
 * Resolve the responder-facing Person-of-Interest payload for a FRAS
 * recognition-born incident. Used by both the Inertia page-load
 * (ResponderController) and the real-time AssignmentPushed broadcast so
 * responders see the same accordion regardless of how they receive the
 * assignment.
 *
 * CRITICAL (D-26 layer 3): the returned payload MUST NEVER include any
 * scene-image URL — only a 5-minute signed face URL plus personnel/camera
 * metadata. Responders ARE allowed to fetch the face crop (CDRRMO override
 * of the original D-27 exclusion) so they can identify the subject on scene;
 * scene imagery remains denied to responders by FrasEventSceneController.
 */
final class PersonOfInterestHydrator
{
    /**
     * @return array<string, string|null>|null
     */
    public function hydrate(Incident $incident): ?array
    {
        $firstTimeline = $incident->timeline->first()
            ?? $incident->timeline()->orderBy('created_at')->first();

        if (! $firstTimeline || ($firstTimeline->event_data['source'] ?? null) !== 'fras_recognition') {
            return null;
        }

        $recognitionEventId = $firstTimeline->event_data['recognition_event_id'] ?? null;

        if (! $recognitionEventId) {
            return null;
        }

        $rec = RecognitionEvent::query()
            ->with(['camera:id,camera_id_display,name', 'personnel:id,name,category'])
            ->find($recognitionEventId);

        if (! $rec || ! $rec->face_image_path || ! $rec->personnel_id) {
            return null;
        }

        return [
            'face_image_url' => URL::temporarySignedRoute(
                'fras.event.face',
                now()->addMinutes(5),
                ['event' => $rec->id],
            ),
            'personnel_name' => $rec->personnel?->name,
            'personnel_category' => $rec->personnel?->category?->value,
            'camera_label' => $rec->camera?->camera_id_display,
            'camera_name' => $rec->camera?->name,
            'captured_at' => $rec->captured_at?->toIso8601String(),
        ];
    }
}
