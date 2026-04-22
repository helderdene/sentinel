<?php

use App\Enums\FrasAccessAction;
use App\Enums\FrasAccessSubject;
use App\Enums\UserRole;
use App\Models\FrasAccessLog;
use App\Models\RecognitionEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

pest()->group('fras');

beforeEach(function () {
    Storage::fake('fras_events');
});

it('writes one fras_access_log row before streaming the face crop', function () {
    $event = RecognitionEvent::factory()->create([
        'face_image_path' => 'face/op-fetch.jpg',
    ]);
    Storage::disk('fras_events')->put('face/op-fetch.jpg', 'fake-jpeg-bytes');
    $user = User::factory()->create(['role' => UserRole::Operator]);

    $url = URL::temporarySignedRoute(
        'fras.event.face',
        now()->addMinutes(5),
        ['event' => $event->id],
    );

    $response = $this->actingAs($user)->get($url);

    $response->assertOk();
    // Symfony normalises/sorts Cache-Control directives — compare as a set.
    $directives = array_map('trim', explode(',', (string) $response->headers->get('Cache-Control')));
    expect($directives)->toContain('private', 'no-store', 'max-age=0');

    expect(FrasAccessLog::count())->toBe(1);

    $row = FrasAccessLog::first();
    expect($row->actor_user_id)->toBe($user->id);
    expect($row->subject_type)->toBe(FrasAccessSubject::RecognitionEventFace);
    expect($row->subject_id)->toBe($event->id);
    expect($row->action)->toBe(FrasAccessAction::View);
    expect($row->ip_address)->not->toBeNull();
    expect($row->accessed_at)->not->toBeNull();
});

it('aborts the stream and persists zero log rows when the audit write fails', function () {
    $event = RecognitionEvent::factory()->create([
        'face_image_path' => 'face/txfail.jpg',
    ]);
    Storage::disk('fras_events')->put('face/txfail.jpg', 'fake-jpeg-bytes');
    $user = User::factory()->create(['role' => UserRole::Operator]);

    $url = URL::temporarySignedRoute(
        'fras.event.face',
        now()->addMinutes(5),
        ['event' => $event->id],
    );

    // Force the DB::transaction wrap to throw — proves the stream is NEVER
    // returned if the audit row can't be written. No DB::transaction partial
    // should persist.
    DB::shouldReceive('transaction')->once()->andThrow(new RuntimeException('db down'));

    $this->actingAs($user)
        ->withoutExceptionHandling()
        ->get($url);
})->throws(RuntimeException::class, 'db down');

it('returns 403 when the signed URL is expired', function () {
    $event = RecognitionEvent::factory()->create([
        'face_image_path' => 'face/expired.jpg',
    ]);
    Storage::disk('fras_events')->put('face/expired.jpg', 'fake');
    $user = User::factory()->create(['role' => UserRole::Operator]);

    $url = URL::temporarySignedRoute(
        'fras.event.face',
        now()->subMinute(),
        ['event' => $event->id],
    );

    $this->actingAs($user)->get($url)->assertForbidden();
    expect(FrasAccessLog::count())->toBe(0);
});

it('returns 403 for a responder even with a valid signed URL', function () {
    $event = RecognitionEvent::factory()->create([
        'face_image_path' => 'face/resp-block.jpg',
    ]);
    Storage::disk('fras_events')->put('face/resp-block.jpg', 'fake');
    $user = User::factory()->create(['role' => UserRole::Responder]);

    $url = URL::temporarySignedRoute(
        'fras.event.face',
        now()->addMinutes(5),
        ['event' => $event->id],
    );

    $this->actingAs($user)->get($url)->assertForbidden();
    expect(FrasAccessLog::count())->toBe(0);
});

it('appends one row per consecutive fetch — log is append-only', function () {
    $event = RecognitionEvent::factory()->create([
        'face_image_path' => 'face/consec.jpg',
    ]);
    Storage::disk('fras_events')->put('face/consec.jpg', 'fake');
    $user = User::factory()->create(['role' => UserRole::Operator]);

    $url = URL::temporarySignedRoute(
        'fras.event.face',
        now()->addMinutes(5),
        ['event' => $event->id],
    );

    $this->actingAs($user)->get($url)->assertOk();
    $this->actingAs($user)->get($url)->assertOk();

    expect(FrasAccessLog::count())->toBe(2);
});
