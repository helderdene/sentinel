<?php

use App\Enums\CameraStatus;
use App\Events\CameraStatusChanged;
use App\Models\Camera;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;

pest()->group('fras');

/**
 * Use a UTC-anchored instant to avoid PG TIMESTAMPTZ / app-timezone round-trip
 * drift. Passing Carbon objects with explicit UTC ensures factory now() == test
 * clock == DB-stored instant (no 8hr Manila offset contamination).
 */
it('keeps camera Online when heartbeat gap is below degraded threshold (default 30s)', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-21 10:00:00', 'UTC'));
    $camera = Camera::factory()->create([
        'status' => CameraStatus::Online,
        'last_seen_at' => now(),
        'decommissioned_at' => null,
    ]);

    Carbon::setTestNow(Carbon::parse('2026-04-21 10:00:20', 'UTC')); // +20s gap — still Online
    Event::fake([CameraStatusChanged::class]);

    Artisan::call('irms:camera-watchdog');

    expect($camera->fresh()->status)->toBe(CameraStatus::Online);
    Event::assertNotDispatched(CameraStatusChanged::class); // no transition
});

it('transitions Online -> Degraded after gap exceeds 30s', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-21 10:00:00', 'UTC'));
    $camera = Camera::factory()->create([
        'status' => CameraStatus::Online,
        'last_seen_at' => now(),
        'decommissioned_at' => null,
    ]);

    Carbon::setTestNow(Carbon::parse('2026-04-21 10:00:45', 'UTC')); // +45s gap — degraded zone
    Event::fake([CameraStatusChanged::class]);

    Artisan::call('irms:camera-watchdog');

    expect($camera->fresh()->status)->toBe(CameraStatus::Degraded);
    Event::assertDispatched(CameraStatusChanged::class, function ($e) use ($camera) {
        return $e->camera->id === $camera->id
            && $e->camera->status === CameraStatus::Degraded;
    });
});

it('transitions Degraded -> Offline after gap exceeds 90s', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-21 10:00:00', 'UTC'));
    $camera = Camera::factory()->create([
        'status' => CameraStatus::Degraded,
        'last_seen_at' => now(),
        'decommissioned_at' => null,
    ]);

    Carbon::setTestNow(Carbon::parse('2026-04-21 10:02:00', 'UTC')); // +120s
    Event::fake([CameraStatusChanged::class]);

    Artisan::call('irms:camera-watchdog');

    expect($camera->fresh()->status)->toBe(CameraStatus::Offline);
    Event::assertDispatched(CameraStatusChanged::class, 1);
});

it('treats null last_seen_at as Offline (never-heartbeated camera)', function () {
    $camera = Camera::factory()->create([
        'status' => CameraStatus::Online,
        'last_seen_at' => null,
        'decommissioned_at' => null,
    ]);

    Event::fake([CameraStatusChanged::class]);
    Artisan::call('irms:camera-watchdog');

    expect($camera->fresh()->status)->toBe(CameraStatus::Offline);
    Event::assertDispatched(CameraStatusChanged::class, 1);
});

it('skips decommissioned cameras entirely', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-21 10:00:00', 'UTC'));
    $camera = Camera::factory()->create([
        'status' => CameraStatus::Online,
        'last_seen_at' => now()->subSeconds(200),
        'decommissioned_at' => now()->subDays(1),
    ]);

    Event::fake([CameraStatusChanged::class]);
    Artisan::call('irms:camera-watchdog');

    // Status unchanged — watchdog skipped this row
    expect($camera->fresh()->status)->toBe(CameraStatus::Online);
    Event::assertNotDispatched(CameraStatusChanged::class);
});

it('does NOT dispatch CameraStatusChanged on steady state (same status tick-to-tick)', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-21 10:00:00', 'UTC'));
    Camera::factory()->count(3)->create([
        'status' => CameraStatus::Online,
        'last_seen_at' => now(),
        'decommissioned_at' => null,
    ]);

    Carbon::setTestNow(Carbon::parse('2026-04-21 10:00:10', 'UTC')); // +10s — still Online
    Event::fake([CameraStatusChanged::class]);
    Artisan::call('irms:camera-watchdog');

    Event::assertNotDispatched(CameraStatusChanged::class);
});
