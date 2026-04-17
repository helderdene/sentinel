<?php

namespace App\Http\Controllers;

use App\Enums\IncidentStatus;
use App\Enums\UnitStatus;
use App\Models\Incident;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;

class StateSyncController extends Controller
{
    /**
     * Return current state for WebSocket reconnection.
     */
    public function __invoke(): JsonResponse
    {
        $dispatchVisibleStatuses = [
            IncidentStatus::Pending,
            IncidentStatus::Triaged,
            IncidentStatus::Dispatched,
            IncidentStatus::Acknowledged,
            IncidentStatus::EnRoute,
            IncidentStatus::OnScene,
            IncidentStatus::Resolving,
        ];

        $incidents = Incident::query()
            ->with(['incidentType', 'barangay', 'timeline' => function ($query) {
                $query->where('event_type', 'resource_requested')
                    ->orderByDesc('created_at');
            }, 'timeline.actor'])
            ->whereIn('status', $dispatchVisibleStatuses)
            ->orderByRaw("CASE priority WHEN 'P1' THEN 1 WHEN 'P2' THEN 2 WHEN 'P3' THEN 3 WHEN 'P4' THEN 4 END")
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function (Incident $inc) {
                $data = $inc->toArray();
                $data['resource_requests'] = $inc->timeline
                    ->map(fn ($t) => [
                        'resource_type' => $t->event_data['type'] ?? null,
                        'resource_label' => $t->event_data['label'] ?? null,
                        'notes' => $t->event_data['notes'] ?? null,
                        'requested_by' => $t->actor?->name ?? 'Unknown',
                        'timestamp' => $t->created_at->toISOString(),
                    ])
                    ->values()
                    ->all();
                unset($data['timeline']);

                return $data;
            });

        $channelCounts = Incident::query()
            ->where('status', IncidentStatus::Pending)
            ->selectRaw('channel, count(*) as count')
            ->groupBy('channel')
            ->pluck('count', 'channel');

        $units = Unit::query()
            ->active()
            ->where('status', '!=', UnitStatus::Offline)
            ->select('id', 'callsign', 'type', 'status', 'coordinates')
            ->get();

        return response()->json([
            'incidents' => $incidents,
            'channelCounts' => $channelCounts,
            'units' => $units,
        ]);
    }
}
