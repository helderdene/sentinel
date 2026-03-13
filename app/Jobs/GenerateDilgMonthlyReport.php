<?php

namespace App\Jobs;

use App\Models\GeneratedReport;
use App\Models\Incident;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Csv\Writer;
use SplTempFileObject;

class GenerateDilgMonthlyReport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(public ?CarbonImmutable $month = null)
    {
        $this->month = $month ?? CarbonImmutable::now()->subMonth();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $period = $this->month->format('Y-m');

        // Prevent duplicate reports
        $existing = GeneratedReport::query()
            ->where('type', 'dilg_monthly')
            ->where('period', $period)
            ->where('status', 'ready')
            ->first();

        if ($existing) {
            return;
        }

        $report = GeneratedReport::create([
            'type' => 'dilg_monthly',
            'title' => 'DILG Monthly Report - '.$this->month->format('F Y'),
            'period' => $period,
            'file_path' => '',
            'status' => 'generating',
        ]);

        try {
            $startOfMonth = $this->month->startOfMonth();
            $endOfMonth = $this->month->endOfMonth();

            $incidents = Incident::query()
                ->with(['incidentType', 'barangay'])
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->get();

            $total = $incidents->count();

            // Priority breakdown
            $byPriority = $incidents->groupBy(fn (Incident $i) => $i->priority?->value ?? 'Unknown')
                ->map->count()
                ->toArray();

            // Incident type breakdown (sorted desc by count)
            $byType = $incidents->groupBy(fn (Incident $i) => $i->incidentType?->name ?? 'Unknown')
                ->map->count()
                ->sortDesc()
                ->toArray();

            // Barangay breakdown (top 20, sorted desc)
            $byBarangay = $incidents->groupBy(fn (Incident $i) => $i->barangay?->name ?? 'Unknown')
                ->map->count()
                ->sortDesc()
                ->take(20)
                ->toArray();

            // Outcome breakdown
            $byOutcome = $incidents->groupBy(fn (Incident $i) => $i->outcome ?? 'Pending')
                ->map->count()
                ->toArray();

            // Generate PDF
            $pdf = Pdf::loadView('pdf.dilg-monthly', [
                'month' => $this->month,
                'total' => $total,
                'byPriority' => $byPriority,
                'byType' => $byType,
                'byBarangay' => $byBarangay,
                'byOutcome' => $byOutcome,
            ]);

            $pdfPath = "reports/dilg/{$period}.pdf";
            Storage::disk('local')->put($pdfPath, $pdf->output());

            // Generate CSV
            $csv = Writer::createFromFileObject(new SplTempFileObject);
            $csv->insertOne(['incident_no', 'type', 'priority', 'barangay', 'outcome', 'created_at', 'resolved_at']);

            foreach ($incidents as $incident) {
                $csv->insertOne([
                    $incident->incident_no,
                    $incident->incidentType?->name ?? 'Unknown',
                    $incident->priority?->value ?? 'Unknown',
                    $incident->barangay?->name ?? 'Unknown',
                    $incident->outcome ?? 'Pending',
                    $incident->created_at?->format('Y-m-d H:i:s'),
                    $incident->resolved_at?->format('Y-m-d H:i:s') ?? '',
                ]);
            }

            $csvPath = "reports/dilg/{$period}.csv";
            Storage::disk('local')->put($csvPath, $csv->toString());

            $report->update([
                'status' => 'ready',
                'file_path' => $pdfPath,
                'csv_path' => $csvPath,
            ]);
        } catch (\Throwable $e) {
            $report->update(['status' => 'failed']);
            Log::error('GenerateDilgMonthlyReport failed', [
                'period' => $period,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
