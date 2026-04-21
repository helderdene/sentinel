<?php

namespace App\Console\Commands;

use App\Enums\CameraStatus;
use App\Events\CameraStatusChanged;
use App\Models\Camera;
use Illuminate\Console\Command;

class CameraWatchdogCommand extends Command
{
    protected $signature = 'irms:camera-watchdog';

    protected $description = 'Flip camera status between online/degraded/offline based on heartbeat gap';

    public function handle(): int
    {
        $degradedGap = (int) config('fras.cameras.degraded_gap_s', 30);
        $offlineGap = (int) config('fras.cameras.offline_gap_s', 90);
        $now = now();

        Camera::query()
            ->whereNull('decommissioned_at')
            ->get()
            ->each(function (Camera $camera) use ($now, $degradedGap, $offlineGap) {
                $gap = $camera->last_seen_at
                    ? (int) $now->diffInSeconds($camera->last_seen_at, absolute: true)
                    : PHP_INT_MAX;

                $newStatus = match (true) {
                    $gap <= $degradedGap => CameraStatus::Online,
                    $gap <= $offlineGap => CameraStatus::Degraded,
                    default => CameraStatus::Offline,
                };

                if ($camera->status !== $newStatus) {
                    $camera->update(['status' => $newStatus]);
                    CameraStatusChanged::dispatch($camera->fresh());
                }
            });

        return self::SUCCESS;
    }
}
