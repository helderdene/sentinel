<?php

namespace App\Services;

use App\Contracts\AnalyticsServiceInterface;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Models\Barangay;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AnalyticsService implements AnalyticsServiceInterface
{
    /**
     * {@inheritDoc}
     */
    public function computeKpis(array $filters): array
    {
        $query = Incident::query()
            ->whereBetween('created_at', [$filters['start_date'], $filters['end_date']]);

        $this->applyFilters($query, $filters);

        $totalInPeriod = (clone $query)->count();

        $resolvedQuery = (clone $query)->where('status', IncidentStatus::Resolved->value);
        $resolvedCount = $resolvedQuery->count();

        // Average response time: dispatched_at - created_at
        $avgResponseTimeSec = (clone $query)
            ->whereNotNull('dispatched_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (dispatched_at - created_at))) as avg_val')
            ->value('avg_val');

        // Average scene arrival time: on_scene_at - dispatched_at
        $avgSceneArrivalSec = (clone $query)
            ->whereNotNull('on_scene_at')
            ->whereNotNull('dispatched_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (on_scene_at - dispatched_at))) as avg_val')
            ->value('avg_val');

        // Resolution rate: resolved / total
        $resolutionRate = $totalInPeriod > 0
            ? round(($resolvedCount / $totalInPeriod) * 100, 1)
            : 0.0;

        // Unit utilization: units with at least 1 assignment in period / total units
        $totalUnits = Unit::count();
        $activeUnits = DB::table('incident_unit')
            ->whereBetween('assigned_at', [$filters['start_date'], $filters['end_date']])
            ->distinct('unit_id')
            ->count('unit_id');

        $unitUtilization = $totalUnits > 0
            ? round(($activeUnits / $totalUnits) * 100, 1)
            : 0.0;

        // False alarm rate: outcome=FALSE_ALARM / total resolved. Code is
        // a literal here (rather than a model lookup) to avoid an extra
        // query on every KPI tick — IncidentOutcome row with this code is
        // seeded as universal and is_active by IncidentOutcomeSeeder.
        $falseAlarmCount = (clone $resolvedQuery)
            ->where('outcome', 'FALSE_ALARM')
            ->count();

        $falseAlarmRate = $resolvedCount > 0
            ? round(($falseAlarmCount / $resolvedCount) * 100, 1)
            : 0.0;

        return [
            'avg_response_time_min' => $avgResponseTimeSec !== null ? round((float) $avgResponseTimeSec / 60.0, 1) : null,
            'avg_scene_arrival_time_min' => $avgSceneArrivalSec !== null ? round((float) $avgSceneArrivalSec / 60.0, 1) : null,
            'resolution_rate' => $resolutionRate,
            'unit_utilization' => $unitUtilization,
            'false_alarm_rate' => $falseAlarmRate,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function kpiTimeSeries(string $metric, array $filters, string $interval = 'day'): array
    {
        $selectExpression = match ($metric) {
            'avg_response_time_min' => 'AVG(EXTRACT(EPOCH FROM (dispatched_at - created_at))) / 60.0',
            'avg_scene_arrival_time_min' => 'AVG(EXTRACT(EPOCH FROM (on_scene_at - dispatched_at))) / 60.0',
            'resolution_rate' => 'CASE WHEN COUNT(*) > 0 THEN COUNT(CASE WHEN status = \'RESOLVED\' THEN 1 END)::float / COUNT(*)::float * 100.0 ELSE 0 END',
            'unit_utilization' => '0',
            'false_alarm_rate' => 'CASE WHEN COUNT(CASE WHEN status = \'RESOLVED\' THEN 1 END) > 0 THEN COUNT(CASE WHEN outcome = \'FALSE_ALARM\' THEN 1 END)::float / COUNT(CASE WHEN status = \'RESOLVED\' THEN 1 END)::float * 100.0 ELSE 0 END',
            default => '0',
        };

        $query = Incident::query()
            ->selectRaw("DATE_TRUNC('{$interval}', created_at) as date")
            ->selectRaw("{$selectExpression} as value")
            ->whereBetween('created_at', [$filters['start_date'], $filters['end_date']]);

        $this->applyFilters($query, $filters);

        if (in_array($metric, ['avg_response_time_min'])) {
            $query->whereNotNull('dispatched_at');
        }

        if (in_array($metric, ['avg_scene_arrival_time_min'])) {
            $query->whereNotNull('dispatched_at')->whereNotNull('on_scene_at');
        }

        return $query
            ->groupByRaw("DATE_TRUNC('{$interval}', created_at)")
            ->orderByRaw("DATE_TRUNC('{$interval}', created_at)")
            ->get()
            ->map(fn ($row) => [
                'date' => substr((string) $row->date, 0, 10),
                'value' => round((float) $row->value, 1),
            ])
            ->values()
            ->all();
    }

    /**
     * {@inheritDoc}
     */
    public function incidentDensityByBarangay(array $filters): array
    {
        return Barangay::query()
            ->select('barangays.id as barangay_id', 'barangays.name')
            ->selectRaw('COUNT(incidents.id) as incident_count')
            ->leftJoin('incidents', function ($join) use ($filters) {
                $join->on('barangays.id', '=', 'incidents.barangay_id')
                    ->whereBetween('incidents.created_at', [$filters['start_date'], $filters['end_date']]);

                if (isset($filters['incident_type_id'])) {
                    $join->where('incidents.incident_type_id', $filters['incident_type_id']);
                }
                if (isset($filters['priority'])) {
                    $join->where('incidents.priority', $filters['priority']);
                }
            })
            ->groupBy('barangays.id', 'barangays.name')
            ->orderBy('barangays.name')
            ->get()
            ->map(fn ($row) => [
                'barangay_id' => $row->barangay_id,
                'name' => $row->name,
                'incident_count' => (int) $row->incident_count,
            ])
            ->all();
    }

    /**
     * {@inheritDoc}
     */
    public function barangayDetail(int $barangayId, array $filters): array
    {
        $query = Incident::query()
            ->where('barangay_id', $barangayId)
            ->whereBetween('created_at', [$filters['start_date'], $filters['end_date']]);

        $this->applyFilters($query, $filters);

        // Top 5 incident types by count
        $topTypes = (clone $query)
            ->select('incident_type_id')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('incident_type_id')
            ->orderByDesc('count')
            ->limit(5)
            ->with([])
            ->get()
            ->map(function ($row) {
                $type = IncidentType::find($row->incident_type_id);

                return [
                    'name' => $type?->name ?? 'Unknown',
                    'count' => (int) $row->count,
                ];
            })
            ->all();

        // Priority breakdown
        $priorityBreakdown = [];
        foreach (IncidentPriority::cases() as $priority) {
            $priorityBreakdown[$priority->value] = (clone $query)
                ->where('priority', $priority->value)
                ->count();
        }

        return [
            'top_types' => $topTypes,
            'priority_breakdown' => $priorityBreakdown,
        ];
    }

    /**
     * Apply common filters to an incident query.
     *
     * @param  Builder<Incident>  $query
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters($query, array $filters): void
    {
        if (isset($filters['incident_type_id'])) {
            $query->where('incident_type_id', $filters['incident_type_id']);
        }

        if (isset($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (isset($filters['barangay_id'])) {
            $query->where('barangay_id', $filters['barangay_id']);
        }
    }
}
