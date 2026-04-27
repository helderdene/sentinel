<?php

namespace App\Console\Commands;

use App\Enums\IncidentStatus;
use App\Models\FrasAccessLog;
use App\Models\FrasPurgeRun;
use App\Models\RecognitionEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class FrasPurgeExpired extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fras:purge-expired {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge expired FRAS face crops and scene images per DPA retention policy';

    /**
     * Execute the console command.
     *
     * Retention windows (config/fras.php §retention, Plan 22-03):
     * - scene_image_days (default 30)
     * - face_crop_days (default 90)
     * - access_log_retention_days (default 730 ≈ 2 yrs)
     *
     * Active-incident-protection (CONTEXT D-22): events linked to an Incident
     * whose status is NOT IN [Resolved, Cancelled] are skipped and counted
     * separately. This is the DPA legal wall — evidence of an open case may
     * not be destroyed by the scheduled purge. The v1.0 IncidentStatus enum
     * has no Cancelled case yet; Resolved is the sole terminal status today.
     * The protection query uses every enum case name whose presence is
     * guarded (if a future migration adds Cancelled, update the list here).
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $sceneDays = (int) config('fras.retention.scene_image_days', 30);
        $faceDays = (int) config('fras.retention.face_crop_days', 90);
        $logDays = (int) config('fras.retention.access_log_retention_days', 730);
        $unpromotedDays = (int) config('fras.retention.unpromoted_event_days', 90);
        $disk = Storage::disk('fras_events');

        $run = FrasPurgeRun::create([
            'started_at' => now(),
            'dry_run' => $dryRun,
            'face_crops_purged' => 0,
            'scene_images_purged' => 0,
            'skipped_for_active_incident' => 0,
            'access_log_rows_purged' => 0,
            'unpromoted_events_purged' => 0,
        ]);

        $sceneCount = 0;
        $faceCount = 0;
        $skipped = 0;
        $logsPurged = 0;
        $unpromotedCount = 0;
        $errorSummary = null;

        $terminalStatuses = $this->terminalIncidentStatuses();

        try {
            $skipped = $this->countSkippedForActiveIncident($sceneDays, $faceDays, $terminalStatuses);

            $protectQuery = function ($q) use ($terminalStatuses) {
                $q->whereNull('incident_id')
                    ->orWhereHas('incident', function ($i) use ($terminalStatuses) {
                        $i->whereIn('status', $terminalStatuses);
                    });
            };

            /**
             * Scene-image purge pass. Protection query skips events linked to
             * IncidentStatus::Resolved, IncidentStatus::Cancelled (when live)
             * or non-terminal statuses per CONTEXT D-22.
             */
            RecognitionEvent::query()
                ->whereNotNull('scene_image_path')
                ->where('captured_at', '<', now()->subDays($sceneDays))
                ->where($protectQuery)
                ->cursor()
                ->each(function (RecognitionEvent $event) use (&$sceneCount, $dryRun, $disk) {
                    $path = $event->scene_image_path;

                    if ($dryRun) {
                        return;
                    }

                    DB::transaction(function () use ($event, $path, $disk) {
                        $disk->delete($path);
                        $event->update(['scene_image_path' => null]);
                    });

                    $sceneCount++;
                });

            /**
             * Face-crop purge pass — same shape as scene pass with the 90d
             * retention window applied to face_image_path.
             */
            RecognitionEvent::query()
                ->whereNotNull('face_image_path')
                ->where('captured_at', '<', now()->subDays($faceDays))
                ->where($protectQuery)
                ->cursor()
                ->each(function (RecognitionEvent $event) use (&$faceCount, $dryRun, $disk) {
                    $path = $event->face_image_path;

                    if ($dryRun) {
                        return;
                    }

                    DB::transaction(function () use ($event, $path, $disk) {
                        $disk->delete($path);
                        $event->update(['face_image_path' => null]);
                    });

                    $faceCount++;
                });

            if (! $dryRun) {
                $logsPurged = (int) FrasAccessLog::query()
                    ->where('accessed_at', '<', now()->subDays($logDays))
                    ->delete();
            }

            /**
             * Unpromoted-event row purge: events that never linked to an
             * Incident (incident_id IS NULL) past the configured window.
             * Runs after the file passes so face/scene paths are already
             * NULL for events inside the file-retention windows; we still
             * defensively delete any non-null path on each row to avoid
             * orphaning files when unpromoted_event_days < face_crop_days.
             */
            RecognitionEvent::query()
                ->whereNull('incident_id')
                ->where('captured_at', '<', now()->subDays($unpromotedDays))
                ->cursor()
                ->each(function (RecognitionEvent $event) use (&$unpromotedCount, $dryRun, $disk) {
                    if ($dryRun) {
                        $unpromotedCount++;

                        return;
                    }

                    DB::transaction(function () use ($event, $disk) {
                        if ($event->face_image_path) {
                            $disk->delete($event->face_image_path);
                        }

                        if ($event->scene_image_path) {
                            $disk->delete($event->scene_image_path);
                        }

                        $event->delete();
                    });

                    $unpromotedCount++;
                });
        } catch (Throwable $e) {
            $errorSummary = substr($e->getMessage(), 0, 1000);
            report($e);
        }

        $run->update([
            'finished_at' => now(),
            'face_crops_purged' => $faceCount,
            'scene_images_purged' => $sceneCount,
            'skipped_for_active_incident' => $skipped,
            'access_log_rows_purged' => $logsPurged,
            'unpromoted_events_purged' => $unpromotedCount,
            'error_summary' => $errorSummary,
        ]);

        if ($this->output->isVerbose()) {
            $this->info(sprintf(
                'FRAS purge %s face=%d scene=%d skipped=%d logs=%d unpromoted=%d',
                $dryRun ? '(dry-run)' : '',
                $faceCount,
                $sceneCount,
                $skipped,
                $logsPurged,
                $unpromotedCount,
            ));
        }

        return $errorSummary ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Return the list of Incident statuses considered terminal for the
     * active-incident-protection query. Defensively handle the enum not
     * having a Cancelled case in v1.0 — the presence check is a no-op when
     * the case is absent and a live filter value when a future migration
     * adds it.
     *
     * @return array<int, IncidentStatus>
     */
    private function terminalIncidentStatuses(): array
    {
        $statuses = [IncidentStatus::Resolved];

        foreach (IncidentStatus::cases() as $case) {
            if ($case->name === 'Cancelled') {
                $statuses[] = $case;
            }
        }

        return $statuses;
    }

    /**
     * Count events whose captured_at sits inside either retention window AND
     * whose linked Incident is in a non-terminal status. These are the rows
     * that the protection query will skip during the delete passes.
     *
     * @param  array<int, IncidentStatus>  $terminalStatuses
     */
    private function countSkippedForActiveIncident(int $sceneDays, int $faceDays, array $terminalStatuses): int
    {
        return RecognitionEvent::query()
            ->whereNotNull('incident_id')
            ->whereHas('incident', function ($i) use ($terminalStatuses) {
                $i->whereNotIn('status', $terminalStatuses);
            })
            ->where(function ($q) use ($sceneDays, $faceDays) {
                $q->where(function ($s) use ($sceneDays) {
                    $s->whereNotNull('scene_image_path')
                        ->where('captured_at', '<', now()->subDays($sceneDays));
                })->orWhere(function ($f) use ($faceDays) {
                    $f->whereNotNull('face_image_path')
                        ->where('captured_at', '<', now()->subDays($faceDays));
                });
            })
            ->count();
    }
}
