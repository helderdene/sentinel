<?php

namespace App\Http\Controllers;

use App\Contracts\DirectionsServiceInterface;
use App\Contracts\ProximityServiceInterface;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Enums\UnitStatus;
use App\Events\AssignmentPushed;
use App\Events\IncidentStatusChanged;
use App\Events\MutualAidRequested;
use App\Events\UnitStatusChanged;
use App\Http\Requests\AdvanceStatusRequest;
use App\Http\Requests\AssignUnitRequest;
use App\Http\Requests\MutualAidRequest;
use App\Http\Requests\UnassignUnitRequest;
use App\Models\Agency;
use App\Models\Incident;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class DispatchConsoleController extends Controller
{
    public function __construct(
        private ProximityServiceInterface $proximityService,
        private DirectionsServiceInterface $directionsService,
    ) {}

    /**
     * Display the dispatch console.
     */
    public function show(): Response
    {
        $dispatchStatuses = [
            IncidentStatus::Triaged,
            IncidentStatus::Dispatched,
            IncidentStatus::EnRoute,
            IncidentStatus::OnScene,
        ];

        $incidents = Incident::query()
            ->whereIn('status', $dispatchStatuses)
            ->with(['incidentType', 'barangay', 'assignedUnits'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Incident $incident) {
                $data = $incident->toArray();
                $data['assigned_units'] = $incident->assignedUnits->map(fn ($unit) => [
                    'unit_id' => $unit->id,
                    'callsign' => $unit->callsign,
                    'assigned_at' => $unit->pivot->assigned_at,
                    'acknowledged_at' => $unit->pivot->acknowledged_at,
                ]);

                return $data;
            });

        $units = Unit::all();
        $agencies = Agency::with('incidentTypes')->get();

        $activeStatuses = [
            IncidentStatus::Dispatched,
            IncidentStatus::EnRoute,
            IncidentStatus::OnScene,
        ];

        $averageHandleTime = Incident::query()
            ->where('status', IncidentStatus::Resolved)
            ->whereDate('resolved_at', today())
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (resolved_at - created_at)) / 60) as avg_handle_time')
            ->value('avg_handle_time');

        $metrics = [
            'totalIncidents' => Incident::query()
                ->where('status', '!=', IncidentStatus::Resolved)
                ->count(),
            'activeIncidents' => Incident::query()
                ->whereIn('status', $activeStatuses)
                ->count(),
            'criticalIncidents' => Incident::query()
                ->whereIn('status', $activeStatuses)
                ->where('priority', IncidentPriority::P1)
                ->count(),
            'unitsAvailable' => Unit::where('status', UnitStatus::Available)->count(),
            'unitsTotal' => Unit::where('status', '!=', UnitStatus::Offline)->count(),
            'averageHandleTime' => $averageHandleTime !== null
                ? round((float) $averageHandleTime, 1)
                : null,
        ];

        return Inertia::render('dispatch/Console', [
            'incidents' => $incidents,
            'units' => $units,
            'agencies' => $agencies,
            'metrics' => $metrics,
        ]);
    }

    /**
     * Assign a unit to an incident.
     */
    public function assignUnit(AssignUnitRequest $request, Incident $incident): JsonResponse
    {
        $unit = Unit::findOrFail($request->validated('unit_id'));

        if ($unit->status !== UnitStatus::Available) {
            return response()->json(['message' => 'Unit is not available.'], 422);
        }

        if ($incident->status === IncidentStatus::Resolved) {
            return response()->json(['message' => 'Cannot assign to a resolved incident.'], 422);
        }

        $oldIncidentStatus = $incident->status;
        $oldUnitStatus = $unit->status;

        // Create pivot record
        $incident->assignedUnits()->attach($unit->id, [
            'assigned_at' => now(),
            'assigned_by' => $request->user()->id,
        ]);

        // Update unit status
        $unit->update(['status' => UnitStatus::Dispatched]);

        // Update incident status if TRIAGED
        if ($incident->status === IncidentStatus::Triaged) {
            $incident->update([
                'status' => IncidentStatus::Dispatched,
                'dispatched_at' => now(),
            ]);
        }

        // Create timeline entry
        $incident->timeline()->create([
            'event_type' => 'unit_assigned',
            'event_data' => [
                'unit_id' => $unit->id,
                'unit_callsign' => $unit->callsign,
            ],
            'actor_type' => get_class($request->user()),
            'actor_id' => $request->user()->id,
        ]);

        // Dispatch events
        foreach ($unit->users as $user) {
            AssignmentPushed::dispatch($incident->fresh(), $unit->id, $user->id);
        }

        UnitStatusChanged::dispatch($unit->fresh(), $oldUnitStatus);

        if ($oldIncidentStatus !== $incident->fresh()->status) {
            IncidentStatusChanged::dispatch($incident->fresh(), $oldIncidentStatus);
        }

        return response()->json(['message' => 'Unit assigned successfully.']);
    }

    /**
     * Unassign a unit from an incident.
     */
    public function unassignUnit(UnassignUnitRequest $request, Incident $incident): JsonResponse
    {
        $unit = Unit::findOrFail($request->validated('unit_id'));
        $oldUnitStatus = $unit->status;

        // Set unassigned_at on the pivot
        $incident->assignedUnits()->updateExistingPivot($unit->id, [
            'unassigned_at' => now(),
        ]);

        // If unit has no other active assignments, set back to AVAILABLE
        $otherActiveAssignments = $unit->activeIncidents()
            ->where('incidents.id', '!=', $incident->id)
            ->count();

        if ($otherActiveAssignments === 0) {
            $unit->update(['status' => UnitStatus::Available]);
        }

        // Create timeline entry
        $incident->timeline()->create([
            'event_type' => 'unit_unassigned',
            'event_data' => [
                'unit_id' => $unit->id,
                'unit_callsign' => $unit->callsign,
            ],
            'actor_type' => get_class($request->user()),
            'actor_id' => $request->user()->id,
        ]);

        UnitStatusChanged::dispatch($unit->fresh(), $oldUnitStatus);

        return response()->json(['message' => 'Unit unassigned successfully.']);
    }

    /**
     * Advance the incident status forward.
     */
    public function advanceStatus(AdvanceStatusRequest $request, Incident $incident): JsonResponse
    {
        $newStatus = IncidentStatus::from($request->validated('status'));
        $oldStatus = $incident->status;

        // Define allowed forward transitions from dispatch
        $allowedTransitions = [
            IncidentStatus::Dispatched->value => [
                IncidentStatus::Acknowledged,
                IncidentStatus::EnRoute,
            ],
            IncidentStatus::Acknowledged->value => [
                IncidentStatus::EnRoute,
            ],
            IncidentStatus::EnRoute->value => [
                IncidentStatus::OnScene,
            ],
            IncidentStatus::OnScene->value => [
                IncidentStatus::Resolving,
                IncidentStatus::Resolved,
            ],
            IncidentStatus::Resolving->value => [
                IncidentStatus::Resolved,
            ],
        ];

        $allowedNext = $allowedTransitions[$oldStatus->value] ?? [];

        if (! in_array($newStatus, $allowedNext, true)) {
            return response()->json([
                'message' => "Cannot transition from {$oldStatus->value} to {$newStatus->value}.",
            ], 422);
        }

        // Update status and corresponding timestamp
        $updateData = ['status' => $newStatus];

        match ($newStatus) {
            IncidentStatus::Acknowledged => $updateData['acknowledged_at'] = now(),
            IncidentStatus::EnRoute => $updateData['en_route_at'] = now(),
            IncidentStatus::OnScene => $updateData['on_scene_at'] = now(),
            IncidentStatus::Resolved => $updateData['resolved_at'] = now(),
            default => null,
        };

        $incident->update($updateData);

        // Create timeline entry
        $incident->timeline()->create([
            'event_type' => 'status_changed',
            'event_data' => [
                'old_status' => $oldStatus->value,
                'new_status' => $newStatus->value,
            ],
            'actor_type' => get_class($request->user()),
            'actor_id' => $request->user()->id,
        ]);

        IncidentStatusChanged::dispatch($incident->fresh(), $oldStatus);

        return response()->json(['message' => 'Status updated successfully.']);
    }

    /**
     * Request mutual aid from an agency.
     */
    public function requestMutualAid(MutualAidRequest $request, Incident $incident): JsonResponse
    {
        $agency = Agency::findOrFail($request->validated('agency_id'));

        $incident->timeline()->create([
            'event_type' => 'mutual_aid_requested',
            'event_data' => [
                'agency_id' => $agency->id,
                'agency_name' => $agency->name,
                'notes' => $request->validated('notes'),
            ],
            'actor_type' => get_class($request->user()),
            'actor_id' => $request->user()->id,
        ]);

        MutualAidRequested::dispatch(
            $incident,
            $agency,
            $request->validated('notes'),
            $request->user()->name,
        );

        return response()->json(['message' => 'Mutual aid request sent.']);
    }

    /**
     * Get nearby available units ranked by proximity to an incident.
     */
    public function nearbyUnits(Incident $incident): JsonResponse
    {
        $coordinates = $incident->coordinates;

        if (! $coordinates) {
            return response()->json([]);
        }

        $units = $this->proximityService->rankNearbyUnits(
            $coordinates->getLatitude(),
            $coordinates->getLongitude(),
        );

        $incidentLat = $coordinates->getLatitude();
        $incidentLng = $coordinates->getLongitude();

        $result = array_map(function ($unit) use ($incidentLat, $incidentLng) {
            $distanceKm = round($unit->distance_meters / 1000, 2);

            try {
                $route = $this->directionsService->route(
                    $unit->latitude,
                    $unit->longitude,
                    $incidentLat,
                    $incidentLng,
                );
                $etaMinutes = round($route['duration_seconds'] / 60, 1);
            } catch (\Throwable) {
                // Fallback to straight-line calculation at 30km/h
                $etaMinutes = round(($distanceKm / 30) * 60, 1);
            }

            return [
                'id' => $unit->id,
                'callsign' => $unit->callsign,
                'type' => $unit->type,
                'agency' => $unit->agency,
                'crew_capacity' => $unit->crew_capacity,
                'distance_km' => $distanceKm,
                'eta_minutes' => $etaMinutes,
            ];
        }, $units);

        return response()->json($result);
    }
}
