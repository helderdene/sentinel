<?php

use App\Enums\FrasAccessAction;
use App\Enums\FrasAccessSubject;
use App\Enums\IncidentStatus;
use App\Models\FrasAccessLog;
use App\Models\FrasPurgeRun;
use App\Models\Incident;
use App\Models\RecognitionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

pest()->group('fras');

beforeEach(function () {
    Storage::fake('fras_events');

    // Default retention config (Plan 22-03 supplies these in config/fras.php;
    // tests pin them explicitly so this plan is independent of config/fras.php MOD).
    config([
        'fras.retention.scene_image_days' => 30,
        'fras.retention.face_crop_days' => 90,
        'fras.retention.purge_run_schedule' => '02:00',
        'fras.retention.access_log_retention_days' => 730,
        'fras.retention.unpromoted_event_days' => 90,
    ]);
});

it('purges face crops older than retention window', function () {
    // Attach to a Resolved incident so the unpromoted-event row purge does
    // NOT also delete the row — this test focuses on the file pass only.
    $incident = Incident::factory()->create(['status' => IncidentStatus::Resolved]);
    $event = RecognitionEvent::factory()->create([
        'incident_id' => $incident->id,
        'face_image_path' => 'face/old.jpg',
        'captured_at' => now()->subDays(100),
    ]);
    Storage::disk('fras_events')->put('face/old.jpg', 'x');

    Artisan::call('fras:purge-expired');

    expect($event->fresh()->face_image_path)->toBeNull();
    expect(Storage::disk('fras_events')->exists('face/old.jpg'))->toBeFalse();
    expect(FrasPurgeRun::latest('started_at')->first()->face_crops_purged)->toBe(1);
});

it('purges scene images older than retention window', function () {
    $event = RecognitionEvent::factory()->create([
        'scene_image_path' => 'scene/old.jpg',
        'captured_at' => now()->subDays(45),
    ]);
    Storage::disk('fras_events')->put('scene/old.jpg', 'x');

    Artisan::call('fras:purge-expired');

    expect($event->fresh()->scene_image_path)->toBeNull();
    expect(Storage::disk('fras_events')->exists('scene/old.jpg'))->toBeFalse();
    expect(FrasPurgeRun::latest('started_at')->first()->scene_images_purged)->toBe(1);
});

it('keeps face crop younger than retention window', function () {
    $event = RecognitionEvent::factory()->create([
        'face_image_path' => 'face/young.jpg',
        'captured_at' => now()->subDays(89),
    ]);
    Storage::disk('fras_events')->put('face/young.jpg', 'x');

    Artisan::call('fras:purge-expired');

    expect($event->fresh()->face_image_path)->toBe('face/young.jpg');
    expect(Storage::disk('fras_events')->exists('face/young.jpg'))->toBeTrue();
    expect(FrasPurgeRun::latest('started_at')->first()->face_crops_purged)->toBe(0);
});

it('survives expired scene image when linked Incident is still Dispatched', function () {
    $incident = Incident::factory()->create(['status' => IncidentStatus::Dispatched]);
    $event = RecognitionEvent::factory()->create([
        'incident_id' => $incident->id,
        'scene_image_path' => 'scene/protected.jpg',
        'captured_at' => now()->subDays(45),
    ]);
    Storage::disk('fras_events')->put('scene/protected.jpg', 'x');

    Artisan::call('fras:purge-expired');

    // Protected — neither column nor file should be touched.
    expect($event->fresh()->scene_image_path)->toBe('scene/protected.jpg');
    expect(Storage::disk('fras_events')->exists('scene/protected.jpg'))->toBeTrue();

    $run = FrasPurgeRun::latest('started_at')->first();
    expect($run->scene_images_purged)->toBe(0);
    expect($run->skipped_for_active_incident)->toBeGreaterThanOrEqual(1);
});

it('--dry-run performs no deletes but writes summary row', function () {
    $event = RecognitionEvent::factory()->create([
        'face_image_path' => 'face/dryrun.jpg',
        'captured_at' => now()->subDays(120),
    ]);
    Storage::disk('fras_events')->put('face/dryrun.jpg', 'x');

    Artisan::call('fras:purge-expired', ['--dry-run' => true]);

    expect($event->fresh()->face_image_path)->toBe('face/dryrun.jpg');
    expect(Storage::disk('fras_events')->exists('face/dryrun.jpg'))->toBeTrue();

    $run = FrasPurgeRun::latest('started_at')->first();
    expect($run)->not->toBeNull();
    expect($run->dry_run)->toBeTrue();
    expect($run->face_crops_purged)->toBe(0);
    expect($run->scene_images_purged)->toBe(0);
    expect($run->access_log_rows_purged)->toBe(0);
});

it('purges fras_access_log rows older than retention', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-22 10:00:00', 'UTC'));

    $user = User::factory()->create();
    $event = RecognitionEvent::factory()->create();

    // Seed an old log row (800d ago) and a recent one (10d ago).
    $oldLog = FrasAccessLog::create([
        'actor_user_id' => $user->id,
        'ip_address' => '10.0.0.1',
        'user_agent' => 'curl/8.0',
        'subject_type' => FrasAccessSubject::RecognitionEventFace,
        'subject_id' => $event->id,
        'action' => FrasAccessAction::View,
        'accessed_at' => now()->subDays(800),
    ]);
    $recentLog = FrasAccessLog::create([
        'actor_user_id' => $user->id,
        'ip_address' => '10.0.0.1',
        'user_agent' => 'curl/8.0',
        'subject_type' => FrasAccessSubject::RecognitionEventFace,
        'subject_id' => $event->id,
        'action' => FrasAccessAction::View,
        'accessed_at' => now()->subDays(10),
    ]);

    Artisan::call('fras:purge-expired');

    expect(FrasAccessLog::find($oldLog->id))->toBeNull();
    expect(FrasAccessLog::find($recentLog->id))->not->toBeNull();

    $run = FrasPurgeRun::latest('started_at')->first();
    expect($run->access_log_rows_purged)->toBe(1);

    Carbon::setTestNow();
});

it('respects env override for face_crop_days', function () {
    config(['fras.retention.face_crop_days' => 1]);

    $event = RecognitionEvent::factory()->create([
        'face_image_path' => 'face/override.jpg',
        'captured_at' => now()->subDays(2),
    ]);
    Storage::disk('fras_events')->put('face/override.jpg', 'x');

    Artisan::call('fras:purge-expired');

    expect($event->fresh()->face_image_path)->toBeNull();
    expect(Storage::disk('fras_events')->exists('face/override.jpg'))->toBeFalse();
    expect(FrasPurgeRun::latest('started_at')->first()->face_crops_purged)->toBe(1);
});

it('row-deletes unpromoted recognition events past retention window', function () {
    $event = RecognitionEvent::factory()->create([
        'incident_id' => null,
        'captured_at' => now()->subDays(100),
    ]);

    Artisan::call('fras:purge-expired');

    expect(RecognitionEvent::find($event->id))->toBeNull();
    expect(FrasPurgeRun::latest('started_at')->first()->unpromoted_events_purged)->toBe(1);
});

it('keeps unpromoted recognition event younger than retention window', function () {
    $event = RecognitionEvent::factory()->create([
        'incident_id' => null,
        'captured_at' => now()->subDays(89),
    ]);

    Artisan::call('fras:purge-expired');

    expect(RecognitionEvent::find($event->id))->not->toBeNull();
    expect(FrasPurgeRun::latest('started_at')->first()->unpromoted_events_purged)->toBe(0);
});

it('preserves promoted events past retention window (incident_id is set)', function () {
    $incident = Incident::factory()->create(['status' => IncidentStatus::Resolved]);
    $event = RecognitionEvent::factory()->create([
        'incident_id' => $incident->id,
        'captured_at' => now()->subDays(365),
    ]);

    Artisan::call('fras:purge-expired');

    expect(RecognitionEvent::find($event->id))->not->toBeNull();
    expect(FrasPurgeRun::latest('started_at')->first()->unpromoted_events_purged)->toBe(0);
});

it('cleans up straggler image files when row-deleting an unpromoted event', function () {
    config(['fras.retention.unpromoted_event_days' => 30]);

    $event = RecognitionEvent::factory()->create([
        'incident_id' => null,
        'face_image_path' => 'face/straggler.jpg',
        'scene_image_path' => 'scene/straggler.jpg',
        'captured_at' => now()->subDays(45),
    ]);
    Storage::disk('fras_events')->put('face/straggler.jpg', 'x');
    Storage::disk('fras_events')->put('scene/straggler.jpg', 'x');

    Artisan::call('fras:purge-expired');

    expect(RecognitionEvent::find($event->id))->toBeNull();
    expect(Storage::disk('fras_events')->exists('face/straggler.jpg'))->toBeFalse();
    expect(Storage::disk('fras_events')->exists('scene/straggler.jpg'))->toBeFalse();
});

it('--dry-run counts unpromoted events but does not delete them', function () {
    $event = RecognitionEvent::factory()->create([
        'incident_id' => null,
        'captured_at' => now()->subDays(120),
    ]);

    Artisan::call('fras:purge-expired', ['--dry-run' => true]);

    expect(RecognitionEvent::find($event->id))->not->toBeNull();

    $run = FrasPurgeRun::latest('started_at')->first();
    expect($run->dry_run)->toBeTrue();
    expect($run->unpromoted_events_purged)->toBe(1);
});
