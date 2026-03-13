<?php

namespace App\Jobs;

use App\Contracts\AnalyticsServiceInterface;
use App\Models\GeneratedReport;
use App\Models\Incident;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateQuarterlyReport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $period,
        public ?int $userId = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AnalyticsServiceInterface $analyticsService): void
    {
        // Prevent duplicate reports
        $existing = GeneratedReport::query()
            ->where('type', 'quarterly')
            ->where('period', $this->period)
            ->where('status', 'ready')
            ->first();

        if ($existing) {
            return;
        }

        $report = GeneratedReport::create([
            'type' => 'quarterly',
            'title' => 'Quarterly Performance Report - '.str_replace('-', ' ', $this->period),
            'period' => $this->period,
            'file_path' => '',
            'status' => 'generating',
            'generated_by' => $this->userId,
        ]);

        try {
            // Parse period: Q1-2026 -> quarter 1, year 2026
            [$quarterStr, $yearStr] = explode('-', $this->period);
            $quarter = (int) substr($quarterStr, 1);
            $year = (int) $yearStr;

            $startMonth = ($quarter - 1) * 3 + 1;
            $startDate = CarbonImmutable::create($year, $startMonth, 1)->startOfDay();
            $endDate = $startDate->addMonths(3)->subDay()->endOfDay();

            // Previous quarter
            $prevStartDate = $startDate->subMonths(3);
            $prevEndDate = $startDate->subDay()->endOfDay();

            $currentFilters = [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ];

            $prevFilters = [
                'start_date' => $prevStartDate->toDateString(),
                'end_date' => $prevEndDate->toDateString(),
            ];

            // KPIs for current and previous quarter
            $currentKpis = $analyticsService->computeKpis($currentFilters);
            $prevKpis = $analyticsService->computeKpis($prevFilters);

            // Weekly incident volume for current quarter
            $weeklyVolume = Incident::query()
                ->selectRaw("date_trunc('week', created_at) as week_start, count(*) as count")
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupByRaw("date_trunc('week', created_at)")
                ->orderByRaw("date_trunc('week', created_at)")
                ->get()
                ->map(fn ($row) => [
                    'week_start' => CarbonImmutable::parse($row->week_start)->format('Y-m-d'),
                    'count' => (int) $row->count,
                ])
                ->toArray();

            // Top 10 barangays
            $topBarangays = Incident::query()
                ->join('barangays', 'incidents.barangay_id', '=', 'barangays.id')
                ->selectRaw('barangays.name, count(*) as count')
                ->whereBetween('incidents.created_at', [$startDate, $endDate])
                ->whereNotNull('incidents.barangay_id')
                ->groupBy('barangays.name')
                ->orderByDesc('count')
                ->limit(10)
                ->get()
                ->toArray();

            // Type breakdown
            $typeBreakdown = Incident::query()
                ->join('incident_types', 'incidents.incident_type_id', '=', 'incident_types.id')
                ->selectRaw('incident_types.name, count(*) as count')
                ->whereBetween('incidents.created_at', [$startDate, $endDate])
                ->groupBy('incident_types.name')
                ->orderByDesc('count')
                ->get()
                ->toArray();

            // Priority breakdown
            $priorityBreakdown = Incident::query()
                ->selectRaw('priority, count(*) as count')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('priority')
                ->orderBy('priority')
                ->get()
                ->toArray();

            $totalCurrent = Incident::query()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();

            // Generate PDF
            $pdf = Pdf::loadView('pdf.quarterly-report', [
                'period' => $this->period,
                'periodLabel' => str_replace('-', ' ', $this->period),
                'currentKpis' => $currentKpis,
                'prevKpis' => $prevKpis,
                'weeklyVolume' => $weeklyVolume,
                'topBarangays' => $topBarangays,
                'typeBreakdown' => $typeBreakdown,
                'priorityBreakdown' => $priorityBreakdown,
                'totalCurrent' => $totalCurrent,
            ]);

            $pdfPath = "reports/quarterly/{$this->period}.pdf";
            Storage::disk('local')->put($pdfPath, $pdf->output());

            $report->update([
                'status' => 'ready',
                'file_path' => $pdfPath,
            ]);
        } catch (\Throwable $e) {
            $report->update(['status' => 'failed']);
            Log::error('GenerateQuarterlyReport failed', [
                'period' => $this->period,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
