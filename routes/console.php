<?php

use App\Jobs\GenerateDilgMonthlyReport;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new GenerateDilgMonthlyReport)->monthlyOn(1, '00:00')
    ->timezone('Asia/Manila')
    ->description('Generate DILG monthly incident report');

Schedule::command('irms:mqtt-listener-watchdog')
    ->everyThirtySeconds()
    ->withoutOverlapping()
    ->description('Detect MQTT listener silence and broadcast health transitions');

Schedule::command('irms:camera-watchdog')
    ->everyMinute()
    ->withoutOverlapping()
    ->description('Flip camera status between online/degraded/offline based on heartbeat gap');

Schedule::command('irms:personnel-expire-sweep')
    ->hourly()
    ->withoutOverlapping()
    ->description('Unenroll personnel whose BOLO expiry has passed');

Schedule::command('fras:purge-expired')
    ->dailyAt((string) config('fras.retention.purge_run_schedule', '02:00'))
    ->timezone('Asia/Manila')
    ->withoutOverlapping()
    ->onFailure(fn () => Log::error('FRAS retention purge failed'))
    ->description('Purge expired FRAS face/scene images per DPA retention policy');
