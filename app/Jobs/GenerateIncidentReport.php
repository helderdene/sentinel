<?php

namespace App\Jobs;

use App\Models\Incident;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class GenerateIncidentReport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public Incident $incident) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $incident = $this->incident->load([
            'incidentType',
            'barangay',
            'assignedUnits',
            'timeline',
        ]);

        $pdf = Pdf::loadView('pdf.incident-report', [
            'incident' => $incident,
        ]);

        $path = "incident-reports/{$incident->incident_no}.pdf";

        Storage::disk('local')->put($path, $pdf->output());

        $incident->update(['report_pdf_url' => $path]);
    }
}
