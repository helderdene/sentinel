<?php

namespace App\Console\Commands;

use App\Enums\CameraEnrollmentStatus;
use App\Models\CameraEnrollment;
use App\Models\Personnel;
use App\Services\CameraEnrollmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PersonnelExpireSweepCommand extends Command
{
    protected $signature = 'irms:personnel-expire-sweep';

    protected $description = 'Unenroll personnel whose BOLO expiry has passed and soft-decommission the record';

    public function handle(CameraEnrollmentService $service): int
    {
        $expired = Personnel::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->whereNull('decommissioned_at')
            ->get();

        foreach ($expired as $personnel) {
            $service->deleteFromAllCameras($personnel);

            $personnel->update(['decommissioned_at' => now()]);

            CameraEnrollment::where('personnel_id', $personnel->id)
                ->update(['status' => CameraEnrollmentStatus::Done]);

            Log::channel('mqtt')->info('fras.personnel.expired', [
                'personnel_id' => $personnel->id,
                'name' => $personnel->name,
                'expired_at' => $personnel->expires_at?->toIso8601String(),
            ]);
        }

        return self::SUCCESS;
    }
}
