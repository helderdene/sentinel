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
        $incidents = Incident::query()
            ->with('incidentType', 'barangay')
            ->where('status', IncidentStatus::Pending)
            ->orderByRaw("CASE priority WHEN 'P1' THEN 1 WHEN 'P2' THEN 2 WHEN 'P3' THEN 3 WHEN 'P4' THEN 4 END")
            ->orderBy('created_at', 'asc')
            ->get();

        $channelCounts = Incident::query()
            ->where('status', IncidentStatus::Pending)
            ->selectRaw('channel, count(*) as count')
            ->groupBy('channel')
            ->pluck('count', 'channel');

        $units = Unit::query()
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
