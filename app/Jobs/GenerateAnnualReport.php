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

class GenerateAnnualReport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $year,
        public ?int $userId = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AnalyticsServiceInterface $analyticsService): void
    {
        $period = (string) $this->year;

        // Prevent duplicate reports
        $existing = GeneratedReport::query()
            ->where('type', 'annual')
            ->where('period', $period)
            ->where('status', 'ready')
            ->first();

        if ($existing) {
            return;
        }

        $report = GeneratedReport::firstOrCreate(
            [
                'type' => 'annual',
                'period' => $period,
                'status' => 'generating',
            ],
            [
                'title' => 'Annual Statistical Summary - '.$this->year,
                'file_path' => '',
                'generated_by' => $this->userId,
            ],
        );

        try {
            $startDate = CarbonImmutable::create($this->year, 1, 1)->startOfDay();
            $endDate = CarbonImmutable::create($this->year, 12, 31)->endOfDay();

            $prevStartDate = CarbonImmutable::create($this->year - 1, 1, 1)->startOfDay();
            $prevEndDate = CarbonImmutable::create($this->year - 1, 12, 31)->endOfDay();

            $currentFilters = [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ];

            $prevFilters = [
                'start_date' => $prevStartDate->toDateString(),
                'end_date' => $prevEndDate->toDateString(),
            ];

            // KPIs for current and previous year
            $currentKpis = $analyticsService->computeKpis($currentFilters);
            $prevKpis = $analyticsService->computeKpis($prevFilters);

            // Monthly incident volume
            $monthlyVolume = Incident::query()
                ->selectRaw("date_trunc('month', created_at) as month_start, count(*) as count")
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupByRaw("date_trunc('month', created_at)")
                ->orderByRaw("date_trunc('month', created_at)")
                ->get()
                ->map(fn ($row) => [
                    'month' => CarbonImmutable::parse($row->month_start)->format('F'),
                    'count' => (int) $row->count,
                ])
                ->toArray();

            // Type distribution
            $typeDistribution = Incident::query()
                ->join('incident_types', 'incidents.incident_type_id', '=', 'incident_types.id')
                ->selectRaw('incident_types.name, count(*) as count')
                ->whereBetween('incidents.created_at', [$startDate, $endDate])
                ->groupBy('incident_types.name')
                ->orderByDesc('count')
                ->get()
                ->toArray();

            // Priority distribution
            $priorityDistribution = Incident::query()
                ->selectRaw('priority, count(*) as count')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('priority')
                ->orderBy('priority')
                ->get()
                ->toArray();

            $totalCurrent = Incident::query()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();

            $totalPrev = Incident::query()
                ->whereBetween('created_at', [$prevStartDate, $prevEndDate])
                ->count();

            // Generate PDF
            $pdf = Pdf::loadView('pdf.annual-summary', [
                'year' => $this->year,
                'currentKpis' => $currentKpis,
                'prevKpis' => $prevKpis,
                'monthlyVolume' => $monthlyVolume,
                'typeDistribution' => $typeDistribution,
                'priorityDistribution' => $priorityDistribution,
                'totalCurrent' => $totalCurrent,
                'totalPrev' => $totalPrev,
            ]);

            $pdfPath = "reports/annual/{$period}.pdf";
            Storage::disk('local')->put($pdfPath, $pdf->output());

            $report->update([
                'status' => 'ready',
                'file_path' => $pdfPath,
            ]);
        } catch (\Throwable $e) {
            $report->update(['status' => 'failed']);
            Log::error('GenerateAnnualReport failed', [
                'year' => $this->year,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
