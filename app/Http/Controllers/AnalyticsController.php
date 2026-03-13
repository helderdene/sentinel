<?php

namespace App\Http\Controllers;

use App\Contracts\AnalyticsServiceInterface;
use App\Http\Requests\AnalyticsFilterRequest;
use App\Http\Requests\GenerateReportRequest;
use App\Jobs\GenerateAnnualReport;
use App\Jobs\GenerateQuarterlyReport;
use App\Models\Barangay;
use App\Models\GeneratedReport;
use App\Models\IncidentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function __construct(
        private AnalyticsServiceInterface $analyticsService,
    ) {
        Gate::authorize('view-analytics');
    }

    /**
     * Redirect to the analytics dashboard.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('analytics.dashboard');
    }

    /**
     * Display the KPI dashboard page.
     */
    public function dashboard(AnalyticsFilterRequest $request): Response
    {
        $filters = $request->resolvedFilters();

        $kpis = $this->analyticsService->computeKpis($filters);

        $metrics = ['avg_response_time_min', 'avg_scene_arrival_time_min', 'resolution_rate', 'unit_utilization', 'false_alarm_rate'];
        $timeSeries = [];
        foreach ($metrics as $metric) {
            $timeSeries[$metric] = $this->analyticsService->kpiTimeSeries($metric, $filters);
        }

        return Inertia::render('analytics/Dashboard', [
            'kpis' => $kpis,
            'timeSeries' => $timeSeries,
            'filters' => $filters,
            'filterOptions' => $this->filterOptions(),
        ]);
    }

    /**
     * Display the incident heatmap page.
     */
    public function heatmap(AnalyticsFilterRequest $request): Response
    {
        $filters = $request->resolvedFilters();

        $density = $this->analyticsService->incidentDensityByBarangay($filters);

        $geojson = Cache::rememberForever('barangay-boundaries-geojson', function () {
            $barangays = Barangay::query()
                ->selectRaw('id, name, ST_AsGeoJSON(ST_SimplifyPreserveTopology(boundary::geometry, 0.0005)) as geojson')
                ->whereNotNull('boundary')
                ->get();

            return [
                'type' => 'FeatureCollection',
                'features' => $barangays->map(fn ($b) => [
                    'type' => 'Feature',
                    'id' => $b->id,
                    'geometry' => json_decode($b->geojson),
                    'properties' => ['id' => $b->id, 'name' => $b->name],
                ])->all(),
            ];
        });

        return Inertia::render('analytics/Heatmap', [
            'density' => $density,
            'geojson' => $geojson,
            'filters' => $filters,
            'filterOptions' => $this->filterOptions(),
        ]);
    }

    /**
     * Return detailed breakdown for a specific barangay (JSON).
     */
    public function barangayDetail(AnalyticsFilterRequest $request, Barangay $barangay): JsonResponse
    {
        $filters = $request->resolvedFilters();

        return response()->json(
            $this->analyticsService->barangayDetail($barangay->id, $filters)
        );
    }

    /**
     * Display the reports download center page.
     */
    public function reports(): Response
    {
        $reports = GeneratedReport::query()
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('analytics/Reports', [
            'reports' => $reports,
        ]);
    }

    /**
     * Stream a report file for download.
     */
    public function downloadReport(GeneratedReport $generatedReport): StreamedResponse
    {
        abort_unless(Storage::exists($generatedReport->file_path), 404);

        return Storage::download($generatedReport->file_path, basename($generatedReport->file_path));
    }

    /**
     * Dispatch a report generation job.
     */
    public function generateReport(GenerateReportRequest $request): RedirectResponse
    {
        $type = $request->validated('type');
        $period = $request->validated('period');
        $userId = $request->user()->id;

        $existing = GeneratedReport::query()
            ->where('type', $type)
            ->where('period', $period)
            ->where('status', 'generating')
            ->exists();

        if ($existing) {
            return redirect()->route('analytics.reports')
                ->with('warning', 'A report of this type and period is already being generated.');
        }

        match ($type) {
            'quarterly' => GenerateQuarterlyReport::dispatch($period, $userId),
            'annual' => GenerateAnnualReport::dispatch((int) $period, $userId),
        };

        return redirect()->route('analytics.reports')
            ->with('success', 'Report generation started. It will appear in the list when ready.');
    }

    /**
     * Get filter option lists for incident types and barangays.
     *
     * @return array{incident_types: array<int, array{id: int, name: string}>, barangays: array<int, array{id: int, name: string}>}
     */
    protected function filterOptions(): array
    {
        return [
            'incident_types' => IncidentType::query()
                ->select('id', 'name')
                ->orderBy('name')
                ->get()
                ->toArray(),
            'barangays' => Barangay::query()
                ->select('id', 'name')
                ->orderBy('name')
                ->get()
                ->toArray(),
        ];
    }
}
