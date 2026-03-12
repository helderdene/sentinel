<?php

namespace App\Http\Controllers;

use App\Enums\IncidentChannel;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Events\IncidentCreated;
use App\Events\IncidentStatusChanged;
use App\Http\Requests\ManualEntryRequest;
use App\Http\Requests\TriageIncidentRequest;
use App\Models\Incident;
use App\Models\IncidentTimeline;
use App\Models\IncidentType;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class IntakeStationController extends Controller
{
    /**
     * Display the intake station with pending and triaged incidents.
     */
    public function show(): Response
    {
        $incidentTypes = IncidentType::query()
            ->active()
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('category');

        $priorityOrder = "CASE priority WHEN 'P1' THEN 1 WHEN 'P2' THEN 2 WHEN 'P3' THEN 3 WHEN 'P4' THEN 4 END";

        $pendingIncidents = Incident::query()
            ->with('incidentType', 'barangay')
            ->where('status', IncidentStatus::Pending)
            ->orderByRaw($priorityOrder)
            ->orderBy('created_at', 'asc')
            ->get();

        $triagedIncidents = Incident::query()
            ->with('incidentType', 'barangay')
            ->where('status', IncidentStatus::Triaged)
            ->orderByRaw($priorityOrder)
            ->orderBy('created_at', 'asc')
            ->get();

        return Inertia::render('intake/IntakeStation', [
            'incidentTypes' => $incidentTypes,
            'channels' => IncidentChannel::cases(),
            'priorities' => IncidentPriority::cases(),
            'pendingIncidents' => $pendingIncidents,
            'triagedIncidents' => $triagedIncidents,
            'priorityConfig' => config('priority'),
        ]);
    }

    /**
     * Triage an existing PENDING incident, transitioning it to TRIAGED.
     */
    public function triage(TriageIncidentRequest $request, Incident $incident): RedirectResponse
    {
        $validated = $request->validated();

        $oldStatus = $incident->status;

        $updateData = [
            'status' => IncidentStatus::Triaged,
            'incident_type_id' => $validated['incident_type_id'],
            'priority' => $validated['priority'],
            'location_text' => $validated['location_text'],
            'caller_name' => $validated['caller_name'] ?? $incident->caller_name,
            'caller_contact' => $validated['caller_contact'] ?? $incident->caller_contact,
            'notes' => $validated['notes'] ?? $incident->notes,
        ];

        if (isset($validated['barangay_id'])) {
            $updateData['barangay_id'] = $validated['barangay_id'];
        }

        $latitude = $validated['latitude'] ?? null;
        $longitude = $validated['longitude'] ?? null;

        if ($latitude !== null && $longitude !== null) {
            $updateData['coordinates'] = Point::makeGeodetic((float) $latitude, (float) $longitude);
        }

        $incident->update($updateData);

        $incidentType = IncidentType::find($validated['incident_type_id']);

        IncidentTimeline::query()->create([
            'incident_id' => $incident->id,
            'event_type' => 'incident_triaged',
            'event_data' => [
                'priority' => $validated['priority'],
                'incident_type' => $incidentType?->name,
                'location' => $validated['location_text'],
                'previous_status' => $oldStatus->value,
            ],
            'actor_type' => User::class,
            'actor_id' => $request->user()->id,
            'notes' => "Incident triaged by operator — priority {$validated['priority']}",
        ]);

        IncidentStatusChanged::dispatch($incident, $oldStatus);

        return back()->with('success', "Incident {$incident->incident_no} triaged successfully.");
    }

    /**
     * Create a new incident directly as TRIAGED (manual entry workflow).
     */
    public function storeAndTriage(ManualEntryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $data = [
            'incident_type_id' => $validated['incident_type_id'],
            'priority' => $validated['priority'],
            'channel' => $validated['channel'],
            'location_text' => $validated['location_text'],
            'caller_name' => $validated['caller_name'] ?? null,
            'caller_contact' => $validated['caller_contact'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => IncidentStatus::Triaged,
            'created_by' => $request->user()->id,
        ];

        $latitude = $validated['latitude'] ?? null;
        $longitude = $validated['longitude'] ?? null;

        if ($latitude !== null && $longitude !== null) {
            $data['coordinates'] = Point::makeGeodetic((float) $latitude, (float) $longitude);
        }

        if (isset($validated['barangay_id'])) {
            $data['barangay_id'] = $validated['barangay_id'];
        }

        $incident = Incident::query()->create($data);
        $incident->load('incidentType', 'barangay');

        $incidentType = IncidentType::find($validated['incident_type_id']);

        IncidentTimeline::query()->create([
            'incident_id' => $incident->id,
            'event_type' => 'incident_created',
            'event_data' => [
                'channel' => $validated['channel'],
                'priority' => $validated['priority'],
                'incident_type' => $incidentType?->name,
                'location' => $validated['location_text'],
                'caller_name' => $validated['caller_name'] ?? null,
            ],
            'actor_type' => User::class,
            'actor_id' => $request->user()->id,
            'notes' => "Incident manually created via {$validated['channel']} channel",
        ]);

        IncidentTimeline::query()->create([
            'incident_id' => $incident->id,
            'event_type' => 'incident_triaged',
            'event_data' => [
                'priority' => $validated['priority'],
                'incident_type' => $incidentType?->name,
                'location' => $validated['location_text'],
                'previous_status' => IncidentStatus::Pending->value,
            ],
            'actor_type' => User::class,
            'actor_id' => $request->user()->id,
            'notes' => "Incident triaged on creation — priority {$validated['priority']}",
        ]);

        IncidentCreated::dispatch($incident);
        IncidentStatusChanged::dispatch($incident, IncidentStatus::Pending);

        return back()->with('success', "Incident {$incident->incident_no} created and triaged.");
    }

    /**
     * Override the priority of a triaged incident (supervisor/admin only).
     */
    public function overridePriority(Request $request, Incident $incident): RedirectResponse
    {
        Gate::authorize('override-priority');

        $validated = $request->validate([
            'priority' => ['required', 'in:P1,P2,P3,P4'],
        ]);

        $oldPriority = $incident->priority->value;

        $incident->update([
            'priority' => $validated['priority'],
        ]);

        IncidentTimeline::query()->create([
            'incident_id' => $incident->id,
            'event_type' => 'priority_override',
            'event_data' => [
                'old_priority' => $oldPriority,
                'new_priority' => $validated['priority'],
            ],
            'actor_type' => User::class,
            'actor_id' => $request->user()->id,
            'notes' => "Priority overridden from {$oldPriority} to {$validated['priority']}",
        ]);

        IncidentStatusChanged::dispatch($incident, $incident->status);

        return back()->with('success', "Priority updated to {$validated['priority']}.");
    }

    /**
     * Recall a triaged incident back to pending status (supervisor/admin only).
     */
    public function recall(Incident $incident): RedirectResponse
    {
        Gate::authorize('recall-incident');

        $oldStatus = $incident->status;

        $incident->update([
            'status' => IncidentStatus::Pending,
        ]);

        IncidentTimeline::query()->create([
            'incident_id' => $incident->id,
            'event_type' => 'incident_recalled',
            'event_data' => [
                'previous_status' => $oldStatus->value,
            ],
            'actor_type' => User::class,
            'actor_id' => request()->user()->id,
            'notes' => "Incident recalled from {$oldStatus->value} to PENDING",
        ]);

        IncidentStatusChanged::dispatch($incident, $oldStatus);

        return back()->with('success', "Incident {$incident->incident_no} recalled.");
    }
}
