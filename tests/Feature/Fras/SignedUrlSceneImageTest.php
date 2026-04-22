<?php

use App\Enums\FrasAccessSubject;
use App\Enums\UserRole;
use App\Models\FrasAccessLog;
use App\Models\RecognitionEvent;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

pest()->group('fras');

beforeEach(function () {
    Storage::fake('fras_events');
});

it('streams the scene image and writes one log row for an operator', function () {
    $event = RecognitionEvent::factory()->create([
        'scene_image_path' => 'scene/op-view.jpg',
    ]);
    Storage::disk('fras_events')->put('scene/op-view.jpg', 'fake-jpeg');
    $user = User::factory()->create(['role' => UserRole::Operator]);

    $url = URL::temporarySignedRoute(
        'fras.events.scene.show',
        now()->addMinutes(5),
        ['event' => $event->id],
    );

    $response = $this->actingAs($user)->get($url);

    $response->assertOk();
    $directives = array_map('trim', explode(',', (string) $response->headers->get('Cache-Control')));
    expect($directives)->toContain('private', 'no-store', 'max-age=0');

    expect(FrasAccessLog::count())->toBe(1);
    expect(FrasAccessLog::first()->subject_type)->toBe(FrasAccessSubject::RecognitionEventScene);
    expect(FrasAccessLog::first()->subject_id)->toBe($event->id);
});

it('returns 403 for a responder with a valid signed URL (layer 1 exclusion)', function () {
    $event = RecognitionEvent::factory()->create([
        'scene_image_path' => 'scene/resp-block.jpg',
    ]);
    Storage::disk('fras_events')->put('scene/resp-block.jpg', 'fake');
    $user = User::factory()->create(['role' => UserRole::Responder]);

    $url = URL::temporarySignedRoute(
        'fras.events.scene.show',
        now()->addMinutes(5),
        ['event' => $event->id],
    );

    $this->actingAs($user)->get($url)->assertForbidden();
    expect(FrasAccessLog::count())->toBe(0);
});

it('returns 403 for a dispatcher with a valid signed URL', function () {
    $event = RecognitionEvent::factory()->create([
        'scene_image_path' => 'scene/disp-block.jpg',
    ]);
    Storage::disk('fras_events')->put('scene/disp-block.jpg', 'fake');
    $user = User::factory()->create(['role' => UserRole::Dispatcher]);

    $url = URL::temporarySignedRoute(
        'fras.events.scene.show',
        now()->addMinutes(5),
        ['event' => $event->id],
    );

    $this->actingAs($user)->get($url)->assertForbidden();
    expect(FrasAccessLog::count())->toBe(0);
});

it('returns 403 when the scene signed URL has expired', function () {
    $event = RecognitionEvent::factory()->create([
        'scene_image_path' => 'scene/expired.jpg',
    ]);
    Storage::disk('fras_events')->put('scene/expired.jpg', 'fake');
    $user = User::factory()->create(['role' => UserRole::Operator]);

    $url = URL::temporarySignedRoute(
        'fras.events.scene.show',
        now()->subMinute(),
        ['event' => $event->id],
    );

    $this->actingAs($user)->get($url)->assertForbidden();
    expect(FrasAccessLog::count())->toBe(0);
});

it('returns 404 when scene_image_path is missing', function () {
    $event = RecognitionEvent::factory()->create([
        'scene_image_path' => null,
    ]);
    $user = User::factory()->create(['role' => UserRole::Operator]);

    $url = URL::temporarySignedRoute(
        'fras.events.scene.show',
        now()->addMinutes(5),
        ['event' => $event->id],
    );

    $this->actingAs($user)->get($url)->assertNotFound();
    expect(FrasAccessLog::count())->toBe(0);
});
