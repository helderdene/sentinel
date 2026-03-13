<?php

namespace App\Http\Controllers;

use App\Enums\IncidentOutcome;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Enums\ResourceType;
use App\Enums\UnitStatus;
use App\Events\ChecklistUpdated;
use App\Events\IncidentStatusChanged;
use App\Events\MessageSent;
use App\Events\ResourceRequested;
use App\Events\UnitLocationUpdated;
use App\Events\UnitStatusChanged;
use App\Http\Requests\AcknowledgeAssignmentRequest;
use App\Http\Requests\AdvanceResponderStatusRequest;
use App\Http\Requests\RequestResourceRequest;
use App\Http\Requests\ResolveIncidentRequest;
use App\Http\Requests\SendMessageRequest;
use App\Http\Requests\UpdateAssessmentTagsRequest;
use App\Http\Requests\UpdateChecklistRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Http\Requests\UpdateVitalsRequest;
use App\Jobs\GenerateIncidentReport;
use App\Jobs\GenerateNdrrmcSitRep;
use App\Models\Incident;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class ResponderController extends Controller
{
    /**
     * Display the responder station page.
     */
    public function show(): Response
    {
        /** @var User $user */
        $user = auth()->user();
        $unit = $user->unit;

        $activeIncident = null;

        if ($unit) {
            $activeIncident = $unit->activeIncidents()
                ->with(['incidentType', 'barangay', 'timeline', 'messages', 'assignedUnits'])
                ->first();

            if ($activeIncident) {
                $activeIncident = $activeIncident->toArray();
            }
        }

        return Inertia::render('responder/Station', [
            'incident' => $activeIncident,
            'unit' => $unit,
            'hospitals' => config('hospitals'),
            'userId' => $user->id,
            'messages' => $activeIncident
                ? Incident::find($activeIncident['id'])?->messages()->orderBy('created_at')->get()
                : [],
        ]);
    }

    /**
     * Acknowledge a dispatch assignment.
     */
    public function acknowledge(AcknowledgeAssignmentRequest $request, Incident $incident): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $unit = $user->unit;

        if (! $unit) {
            return response()->json(['message' => 'You are not assigned to a unit.'], 422);
        }

        $pivot = $incident->assignedUnits()
            ->where('unit_id', $unit->id)
            ->first();

        if (! $pivot) {
            return response()->json(['message' => 'Your unit is not assigned to this incident.'], 403);
        }

        $oldStatus = $incident->status;

        $incident->assignedUnits()->updateExistingPivot($unit->id, [
            'acknowledged_at' => now(),
        ]);

        $incident->update([
            'status' => IncidentStatus::Acknowledged,
            'acknowledged_at' => now(),
        ]);

        $incident->timeline()->create([
            'event_type' => 'status_changed',
            'event_data' => [
                'old_status' => $oldStatus->value,
                'new_status' => IncidentStatus::Acknowledged->value,
            ],
            'actor_type' => get_class($user),
            'actor_id' => $user->id,
        ]);

        IncidentStatusChanged::dispatch($incident->fresh(), $oldStatus);

        return response()->json(['message' => 'Assignment acknowledged.']);
    }

    /**
     * Advance the incident status forward (responder transitions only).
     */
    public function advanceStatus(AdvanceResponderStatusRequest $request, Incident $incident): JsonResponse
    {
        $newStatus = IncidentStatus::from($request->validated('status'));
        $oldStatus = $incident->status;

        $allowedTransitions = [
            IncidentStatus::Acknowledged->value => [
                IncidentStatus::EnRoute,
            ],
            IncidentStatus::EnRoute->value => [
                IncidentStatus::OnScene,
            ],
            IncidentStatus::OnScene->value => [
                IncidentStatus::Resolving,
            ],
        ];

        $allowedNext = $allowedTransitions[$oldStatus->value] ?? [];

        if (! in_array($newStatus, $allowedNext, true)) {
            return response()->json([
                'message' => "Cannot transition from {$oldStatus->value} to {$newStatus->value}.",
            ], 422);
        }

        $updateData = ['status' => $newStatus];

        match ($newStatus) {
            IncidentStatus::EnRoute => $updateData['en_route_at'] = now(),
            IncidentStatus::OnScene => $updateData['on_scene_at'] = now(),
            IncidentStatus::Resolving => $updateData['resolving_at'] = now(),
            default => null,
        };

        $incident->update($updateData);

        /** @var User $user */
        $user = $request->user();

        $incident->timeline()->create([
            'event_type' => 'status_changed',
            'event_data' => [
                'old_status' => $oldStatus->value,
                'new_status' => $newStatus->value,
            ],
            'actor_type' => get_class($user),
            'actor_id' => $user->id,
        ]);

        IncidentStatusChanged::dispatch($incident->fresh(), $oldStatus);

        return response()->json(['message' => 'Status updated successfully.']);
    }

    /**
     * Update the responder's unit GPS location.
     */
    public function updateLocation(UpdateLocationRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $unit = $user->unit;

        if (! $unit) {
            return response()->json(['message' => 'You are not assigned to a unit.'], 422);
        }

        $unit->update([
            'coordinates' => Point::makeGeodetic(
                $request->validated('latitude'),
                $request->validated('longitude'),
            ),
        ]);

        UnitLocationUpdated::dispatch($unit->fresh());

        return response()->json(['message' => 'Location updated.']);
    }

    /**
     * Send a message from the responder to dispatch.
     */
    public function sendMessage(SendMessageRequest $request, Incident $incident): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $message = $incident->messages()->create([
            'sender_type' => User::class,
            'sender_id' => $user->id,
            'body' => $request->validated('body'),
            'message_type' => 'text',
            'is_quick_reply' => $request->validated('is_quick_reply', false),
        ]);

        MessageSent::dispatch(
            0,
            $incident->id,
            $user->id,
            $user->name,
            $message->body,
        );

        return response()->json(['message' => 'Message sent.']);
    }

    /**
     * Update the incident checklist and compute completion percentage.
     */
    public function updateChecklist(UpdateChecklistRequest $request, Incident $incident): JsonResponse
    {
        $items = $request->validated('items');
        $total = count($items);
        $completed = count(array_filter($items));
        $pct = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

        $incident->update([
            'checklist_data' => $items,
            'checklist_pct' => $pct,
        ]);

        ChecklistUpdated::dispatch($incident->fresh());

        return response()->json(['message' => 'Checklist updated.', 'checklist_pct' => $pct]);
    }

    /**
     * Update patient vitals on the incident.
     */
    public function updateVitals(UpdateVitalsRequest $request, Incident $incident): JsonResponse
    {
        $vitals = array_filter($request->validated(), fn ($v) => $v !== null);

        $incident->update([
            'vitals' => $vitals,
        ]);

        return response()->json(['message' => 'Vitals updated.']);
    }

    /**
     * Update assessment tags on the incident.
     */
    public function updateAssessmentTags(UpdateAssessmentTagsRequest $request, Incident $incident): JsonResponse
    {
        $incident->update([
            'assessment_tags' => $request->validated('assessment_tags'),
        ]);

        IncidentStatusChanged::dispatch($incident->fresh(), $incident->status);

        return response()->json(['message' => 'Assessment tags updated.']);
    }

    /**
     * Resolve the incident with an outcome.
     */
    public function resolve(ResolveIncidentRequest $request, Incident $incident): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $unit = $user->unit;
        $outcome = IncidentOutcome::from($request->validated('outcome'));
        $oldStatus = $incident->status;

        if ($outcome->isMedical() && empty($incident->vitals)) {
            return response()->json([
                'message' => 'Vitals must be recorded before resolving with a medical outcome.',
            ], 422);
        }

        $sceneTimeSec = $incident->on_scene_at
            ? (int) abs(now()->diffInSeconds($incident->on_scene_at))
            : null;

        $incident->update([
            'status' => IncidentStatus::Resolved,
            'resolved_at' => now(),
            'outcome' => $outcome->value,
            'hospital' => $request->validated('hospital'),
            'closure_notes' => $request->validated('closure_notes'),
            'scene_time_sec' => $sceneTimeSec,
        ]);

        $incident->timeline()->create([
            'event_type' => 'status_changed',
            'event_data' => [
                'old_status' => $oldStatus->value,
                'new_status' => IncidentStatus::Resolved->value,
                'outcome' => $outcome->value,
            ],
            'actor_type' => get_class($user),
            'actor_id' => $user->id,
        ]);

        if ($unit) {
            $oldUnitStatus = $unit->status;

            $incident->assignedUnits()->updateExistingPivot($unit->id, [
                'unassigned_at' => now(),
            ]);

            $otherActive = $unit->activeIncidents()
                ->where('incidents.id', '!=', $incident->id)
                ->count();

            if ($otherActive === 0) {
                $unit->update(['status' => UnitStatus::Available]);
            }

            UnitStatusChanged::dispatch($unit->fresh(), $oldUnitStatus);
        }

        IncidentStatusChanged::dispatch($incident->fresh(), $oldStatus);

        GenerateIncidentReport::dispatch($incident);

        if ($incident->priority === IncidentPriority::P1) {
            GenerateNdrrmcSitRep::dispatch($incident);
        }

        return response()->json(['message' => 'Incident resolved.']);
    }

    /**
     * Request additional resources from the field.
     */
    public function requestResource(RequestResourceRequest $request, Incident $incident): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $resourceType = ResourceType::from($request->validated('type'));

        $incident->timeline()->create([
            'event_type' => 'resource_requested',
            'event_data' => [
                'type' => $resourceType->value,
                'label' => $resourceType->label(),
                'notes' => $request->validated('notes'),
            ],
            'actor_type' => get_class($user),
            'actor_id' => $user->id,
        ]);

        ResourceRequested::dispatch(
            $incident,
            $resourceType,
            $request->validated('notes'),
            $user,
        );

        return response()->json(['message' => 'Resource requested.']);
    }
}
