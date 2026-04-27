<?php

namespace App\Listeners;

use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Events\IncidentStatusChanged;
use App\Jobs\GenerateIncidentReport;
use App\Jobs\GenerateNdrrmcSitRep;

class GenerateReportsOnIncidentResolution
{
    /**
     * Auto-generate the per-incident PDF (and NDRRMC SitRep for P1) on the
     * Pending/Triaged/.../Resolving → Resolved transition, regardless of which
     * controller path triggered it.
     */
    public function handle(IncidentStatusChanged $event): void
    {
        if ($event->incident->status !== IncidentStatus::Resolved) {
            return;
        }

        if ($event->oldStatus === IncidentStatus::Resolved) {
            return;
        }

        GenerateIncidentReport::dispatch($event->incident);

        if ($event->incident->priority === IncidentPriority::P1) {
            GenerateNdrrmcSitRep::dispatch($event->incident);
        }
    }
}
