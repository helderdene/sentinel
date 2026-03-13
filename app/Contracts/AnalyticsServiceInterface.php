<?php

namespace App\Contracts;

interface AnalyticsServiceInterface
{
    /**
     * Compute the 5 KPI metrics for the given filter period.
     *
     * @param  array{start_date: string, end_date: string, incident_type_id?: int, priority?: string, barangay_id?: int}  $filters
     * @return array{avg_response_time_min: float|null, avg_scene_arrival_time_min: float|null, resolution_rate: float, unit_utilization: float, false_alarm_rate: float}
     */
    public function computeKpis(array $filters): array;

    /**
     * Compute daily time-series data for a specific KPI metric.
     *
     * @param  array{start_date: string, end_date: string, incident_type_id?: int, priority?: string, barangay_id?: int}  $filters
     * @return array<int, array{date: string, value: float}>
     */
    public function kpiTimeSeries(string $metric, array $filters, string $interval = 'day'): array;

    /**
     * Get incident density counts for all barangays in the filter period.
     *
     * @param  array{start_date: string, end_date: string, incident_type_id?: int, priority?: string, barangay_id?: int}  $filters
     * @return array<int, array{barangay_id: int, name: string, incident_count: int}>
     */
    public function incidentDensityByBarangay(array $filters): array;

    /**
     * Get detailed breakdown for a specific barangay.
     *
     * @param  array{start_date: string, end_date: string, incident_type_id?: int, priority?: string, barangay_id?: int}  $filters
     * @return array{top_types: array<int, array{name: string, count: int}>, priority_breakdown: array{P1: int, P2: int, P3: int, P4: int}}
     */
    public function barangayDetail(int $barangayId, array $filters): array;
}
