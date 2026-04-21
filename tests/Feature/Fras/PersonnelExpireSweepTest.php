<?php

use App\Enums\CameraEnrollmentStatus;
use App\Enums\CameraStatus;
use App\Models\Camera;
use App\Models\CameraEnrollment;
use App\Models\Personnel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use PhpMqtt\Client\Facades\MQTT;

pest()->group('fras');

it('unenrolls personnel whose expires_at has passed and sets decommissioned_at', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-21 10:00:00', 'UTC'));

    $expired = Personnel::factory()->create([
        'expires_at' => now()->subHour(),
        'decommissioned_at' => null,
    ]);
    Camera::factory()->count(2)->create(['status' => CameraStatus::Online, 'decommissioned_at' => null]);

    MQTT::shouldReceive('connection->publish')->atLeast()->times(2);

    Artisan::call('irms:personnel-expire-sweep');

    expect($expired->fresh()->decommissioned_at)->not->toBeNull();
});

it('marks all enrollment rows for the expired personnel as Done', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-21 10:00:00', 'UTC'));

    $expired = Personnel::factory()->create(['expires_at' => now()->subMinute(), 'decommissioned_at' => null]);
    $camera = Camera::factory()->create();
    $enrollment = CameraEnrollment::factory()->create([
        'personnel_id' => $expired->id,
        'camera_id' => $camera->id,
        'status' => CameraEnrollmentStatus::Syncing,
    ]);

    MQTT::shouldReceive('connection->publish')->zeroOrMoreTimes();

    Artisan::call('irms:personnel-expire-sweep');

    expect($enrollment->fresh()->status)->toBe(CameraEnrollmentStatus::Done);
});

it('does NOT touch personnel whose expires_at is still in the future', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-21 10:00:00', 'UTC'));

    $future = Personnel::factory()->create([
        'expires_at' => now()->addDays(30),
        'decommissioned_at' => null,
    ]);

    MQTT::shouldReceive('connection->publish')->zeroOrMoreTimes();
    Artisan::call('irms:personnel-expire-sweep');

    expect($future->fresh()->decommissioned_at)->toBeNull();
});

it('does NOT touch personnel with null expires_at', function () {
    $permanent = Personnel::factory()->create([
        'expires_at' => null,
        'decommissioned_at' => null,
    ]);

    MQTT::shouldReceive('connection->publish')->zeroOrMoreTimes();
    Artisan::call('irms:personnel-expire-sweep');

    expect($permanent->fresh()->decommissioned_at)->toBeNull();
});

it('skips personnel that are already decommissioned (no double-process)', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-21 10:00:00', 'UTC'));
    MQTT::shouldReceive('connection->publish')->never();

    $alreadyDone = Personnel::factory()->create([
        'expires_at' => now()->subDays(10),
        'decommissioned_at' => now()->subDay(),
    ]);

    Artisan::call('irms:personnel-expire-sweep');

    // Row untouched — decommissioned_at is still yesterday's value, not re-set
    $snapshot = $alreadyDone->decommissioned_at;
    expect($alreadyDone->fresh()->decommissioned_at->format('Y-m-d H:i:s'))
        ->toBe($snapshot->format('Y-m-d H:i:s'));
});
