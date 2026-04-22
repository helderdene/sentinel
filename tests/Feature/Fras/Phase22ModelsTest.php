<?php

use App\Enums\FrasAccessAction;
use App\Enums\FrasAccessSubject;
use App\Enums\FrasDismissReason;
use App\Models\Camera;
use App\Models\FrasAccessLog;
use App\Models\FrasLegalSignoff;
use App\Models\FrasPurgeRun;
use App\Models\RecognitionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

pest()->group('fras', 'phase22');

it('FrasDismissReason enum exposes 4 string-backed cases with human labels', function () {
    expect(FrasDismissReason::FalseMatch->value)->toBe('false_match');
    expect(FrasDismissReason::TestEvent->value)->toBe('test_event');
    expect(FrasDismissReason::Duplicate->value)->toBe('duplicate');
    expect(FrasDismissReason::Other->value)->toBe('other');

    expect(FrasDismissReason::FalseMatch->label())->toBe('False match');
    expect(FrasDismissReason::TestEvent->label())->toBe('Test event');
    expect(FrasDismissReason::Duplicate->label())->toBe('Duplicate alert');
    expect(FrasDismissReason::Other->label())->toBe('Other');
});

it('FrasAccessSubject enum exposes 3 string-backed cases', function () {
    expect(FrasAccessSubject::RecognitionEventFace->value)->toBe('recognition_event_face');
    expect(FrasAccessSubject::RecognitionEventScene->value)->toBe('recognition_event_scene');
    expect(FrasAccessSubject::PersonnelPhoto->value)->toBe('personnel_photo');
});

it('FrasAccessAction enum exposes 2 string-backed cases', function () {
    expect(FrasAccessAction::View->value)->toBe('view');
    expect(FrasAccessAction::Download->value)->toBe('download');
});

it('FrasAccessLog model uses HasUuids + fras_access_log table + enum casts', function () {
    $actor = User::factory()->create();
    $subjectId = (string) Str::uuid();

    $log = FrasAccessLog::create([
        'actor_user_id' => $actor->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Chrome/1.0',
        'subject_type' => FrasAccessSubject::RecognitionEventFace->value,
        'subject_id' => $subjectId,
        'action' => FrasAccessAction::View->value,
        'accessed_at' => now(),
    ]);

    expect($log->id)->toBeString();
    expect(Str::isUuid($log->id))->toBeTrue();
    expect($log->subject_type)->toBeInstanceOf(FrasAccessSubject::class);
    expect($log->subject_type)->toBe(FrasAccessSubject::RecognitionEventFace);
    expect($log->action)->toBe(FrasAccessAction::View);
    expect($log->accessed_at)->not->toBeNull();
    expect($log->actor->id)->toBe($actor->id);
});

it('FrasPurgeRun model uses HasUuids + boolean/integer casts', function () {
    $run = FrasPurgeRun::create([
        'started_at' => now(),
        'finished_at' => now(),
        'dry_run' => true,
        'face_crops_purged' => 12,
        'scene_images_purged' => 4,
        'skipped_for_active_incident' => 1,
        'access_log_rows_purged' => 20,
        'error_summary' => null,
    ]);

    expect(Str::isUuid($run->id))->toBeTrue();
    expect($run->dry_run)->toBeTrue();
    expect($run->face_crops_purged)->toBe(12);
});

it('FrasLegalSignoff model uses HasUuids and datetime cast', function () {
    $signoff = FrasLegalSignoff::create([
        'signed_by_name' => 'Atty. Dela Cruz',
        'contact' => 'dpo@butuan.gov.ph',
        'signed_at' => now(),
        'notes' => 'Initial DPA sign-off',
    ]);

    expect(Str::isUuid($signoff->id))->toBeTrue();
    expect($signoff->signed_by_name)->toBe('Atty. Dela Cruz');
    expect($signoff->signed_at)->not->toBeNull();
});

it('RecognitionEvent has dismissedBy relation + dismiss_reason enum cast', function () {
    $camera = Camera::factory()->create();
    $dismisser = User::factory()->create();

    $event = RecognitionEvent::factory()->create([
        'camera_id' => $camera->id,
        'dismissed_by' => $dismisser->id,
        'dismiss_reason' => FrasDismissReason::FalseMatch->value,
        'dismiss_reason_note' => null,
        'dismissed_at' => now(),
    ]);

    expect($event->dismiss_reason)->toBeInstanceOf(FrasDismissReason::class);
    expect($event->dismiss_reason)->toBe(FrasDismissReason::FalseMatch);
    expect($event->dismissedBy)->not->toBeNull();
    expect($event->dismissedBy->id)->toBe($dismisser->id);
});

it('User model has fras_audio_muted fillable + bool cast', function () {
    $user = User::factory()->create(['fras_audio_muted' => true]);

    expect($user->fras_audio_muted)->toBeTrue();

    $user->refresh();
    expect($user->fras_audio_muted)->toBeTrue();

    $user->update(['fras_audio_muted' => false]);
    $user->refresh();
    expect($user->fras_audio_muted)->toBeFalse();
});

it('FrasAccessLogFactory produces a valid row', function () {
    $log = FrasAccessLog::factory()->create();

    expect($log->id)->toBeString();
    expect($log->actor_user_id)->not->toBeNull();
    expect($log->subject_type)->toBeInstanceOf(FrasAccessSubject::class);
    expect($log->action)->toBeInstanceOf(FrasAccessAction::class);
});
