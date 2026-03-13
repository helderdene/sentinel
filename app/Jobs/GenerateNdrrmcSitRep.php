<?php

namespace App\Jobs;

use App\Contracts\NdrrmcReportServiceInterface;
use App\Models\GeneratedReport;
use App\Models\Incident;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateNdrrmcSitRep implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(public Incident $incident) {}

    /**
     * Execute the job.
     */
    public function handle(NdrrmcReportServiceInterface $ndrrmcService): void
    {
        $incident = $this->incident->load([
            'incidentType',
            'barangay',
            'timeline',
            'assignedUnits',
        ]);

        $report = GeneratedReport::create([
            'type' => 'ndrrmc_sitrep',
            'title' => 'NDRRMC SitRep - '.$incident->incident_no,
            'period' => $incident->incident_no,
            'file_path' => '',
            'status' => 'generating',
        ]);

        try {
            $coordinates = null;
            if ($incident->coordinates) {
                $coordinates = [
                    'lat' => $incident->coordinates->getLatitude(),
                    'lng' => $incident->coordinates->getLongitude(),
                ];
            }

            $timelineSummary = $incident->timeline
                ->map(fn ($entry) => $entry->event_type.': '.$entry->created_at->format('Y-m-d H:i:s'))
                ->implode('; ');

            $incidentData = [
                'incident_no' => $incident->incident_no,
                'incident_type' => $incident->incidentType?->name ?? 'Unknown',
                'priority' => $incident->priority?->value ?? 'Unknown',
                'location_text' => $incident->location_text ?? 'Unknown',
                'barangay' => $incident->barangay?->name ?? 'Unknown',
                'coordinates' => $coordinates,
                'outcome' => $incident->outcome ?? 'Pending',
                'units_deployed' => $incident->assignedUnits->count(),
                'timeline_summary' => $timelineSummary ?: 'No timeline entries',
                'created_at' => $incident->created_at->format('Y-m-d H:i:s'),
                'resolved_at' => $incident->resolved_at?->format('Y-m-d H:i:s'),
            ];

            // Submit to NDRRMC stub service
            $result = $ndrrmcService->submitSitRep($incidentData);

            // Generate SitRep PDF
            $pdf = Pdf::loadView('pdf.ndrrmc-sitrep', [
                'incident' => $incident,
                'referenceId' => $result['reference_id'],
            ]);

            $pdfPath = "reports/ndrrmc/{$incident->incident_no}.pdf";
            Storage::disk('local')->put($pdfPath, $pdf->output());

            $report->update([
                'status' => 'ready',
                'file_path' => $pdfPath,
            ]);

            // Create timeline entry on the incident
            $incident->timeline()->create([
                'event_type' => 'ndrrmc_sitrep_generated',
                'event_data' => [
                    'reference_id' => $result['reference_id'],
                    'pdf_path' => $pdfPath,
                ],
                'actor_type' => 'system',
                'actor_id' => null,
            ]);
        } catch (\Throwable $e) {
            $report->update(['status' => 'failed']);
            Log::error('GenerateNdrrmcSitRep failed', [
                'incident_no' => $incident->incident_no,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
