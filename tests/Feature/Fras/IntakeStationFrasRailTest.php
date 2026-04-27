<?php

use App\Enums\PersonnelCategory;
use App\Enums\RecognitionSeverity;
use App\Enums\UserRole;
use App\Models\Camera;
use App\Models\Personnel;
use App\Models\RecognitionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

pest()->group('fras');

it('renders IntakeStation with recentFrasEvents prop carrying shaped payload', function () {
    $operator = User::factory()->operator()->create();

    $camera = Camera::factory()->create(['camera_id_display' => 'CAM-07']);
    $personnel = Personnel::factory()->create([
        'name' => 'Maria Santos',
        'category' => PersonnelCategory::Block,
    ]);

    // Eligible rail events (Critical + Warning).
    RecognitionEvent::factory()->for($camera)->for($personnel)->create([
        'severity' => RecognitionSeverity::Critical,
        'similarity' => 0.88,
    ]);
    RecognitionEvent::factory()->for($camera)->for($personnel)->create([
        'severity' => RecognitionSeverity::Warning,
        'similarity' => 0.60,
    ]);

    // Excluded: Info severity never reaches the rail.
    RecognitionEvent::factory()->for($camera)->for($personnel)->create([
        'severity' => RecognitionSeverity::Info,
        'similarity' => 0.40,
    ]);

    $this->actingAs($operator)
        ->get(route('intake.station'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('intake/IntakeStation')
            ->has('recentFrasEvents', 2)
            ->has('recentFrasEvents.0.event_id')
            ->has('recentFrasEvents.0.severity')
            ->has('recentFrasEvents.0.camera_label')
            ->has('recentFrasEvents.0.personnel_name')
            ->has('recentFrasEvents.0.personnel_category')
            ->has('recentFrasEvents.0.confidence')
            ->has('recentFrasEvents.0.captured_at')
            ->has('recentFrasEvents.0.incident_id')
            ->has('recentFrasEvents.0.face_image_path')
        );
});

it('renders recentFrasEvents as empty array when no recognition events exist', function () {
    $operator = User::factory()->operator()->create();

    $this->actingAs($operator)
        ->get(route('intake.station'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('intake/IntakeStation')
            ->has('recentFrasEvents', 0)
            ->has('frasConfig.pulseDurationSeconds')
        );
});

it('shares frasConfig Inertia prop with pulseDurationSeconds', function () {
    $operator = User::factory()->operator()->create();

    $this->actingAs($operator)
        ->get(route('intake.station'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('frasConfig.pulseDurationSeconds')
        );
});

/**
 * Build a signed fras.event.face URL with a valid face_image_path and a
 * stubbed byte-string on the fras_events disk so the controller's
 * Storage::exists check passes.
 */
function signedFaceUrl(RecognitionEvent $event, int $minutes = 5): string
{
    return URL::temporarySignedRoute(
        'fras.event.face',
        now()->addMinutes($minutes),
        ['event' => $event->id],
    );
}

it('fras.event.face route allows operator and responder, denies dispatcher', function () {
    Storage::fake('fras_events');

    $camera = Camera::factory()->create();
    $event = RecognitionEvent::factory()->for($camera)->create([
        'face_image_path' => 'events/2026/04/abc.jpg',
    ]);
    Storage::disk('fras_events')->put('events/2026/04/abc.jpg', 'fake-jpeg-bytes');

    $url = signedFaceUrl($event);

    // Operator gets image
    $operator = User::factory()->create(['role' => UserRole::Operator]);
    $this->actingAs($operator)
        ->get($url)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');

    // Dispatcher denied — face controller never exposed to dispatch console
    $dispatcher = User::factory()->create(['role' => UserRole::Dispatcher]);
    $this->actingAs($dispatcher)
        ->get($url)
        ->assertForbidden();

    // Responder allowed (CDRRMO override) — needed for the PoI accordion on
    // /responder. Scene imagery remains denied via the separate scene route.
    $responder = User::factory()->create(['role' => UserRole::Responder]);
    $this->actingAs($responder)
        ->get($url)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');
});

it('fras.event.face returns 404 when event face_image_path is null', function () {
    Storage::fake('fras_events');

    $camera = Camera::factory()->create();
    $event = RecognitionEvent::factory()->for($camera)->create([
        'face_image_path' => null,
    ]);

    $operator = User::factory()->create(['role' => UserRole::Operator]);
    $url = signedFaceUrl($event);

    $this->actingAs($operator)
        ->get($url)
        ->assertNotFound();
});

it('fras.event.face returns 403 when URL signature is missing', function () {
    Storage::fake('fras_events');

    $camera = Camera::factory()->create();
    $event = RecognitionEvent::factory()->for($camera)->create([
        'face_image_path' => 'events/2026/04/abc.jpg',
    ]);
    Storage::disk('fras_events')->put('events/2026/04/abc.jpg', 'fake-jpeg-bytes');

    $operator = User::factory()->create(['role' => UserRole::Operator]);

    // Unsigned URL (no signature query param) → 403 from signed middleware.
    $this->actingAs($operator)
        ->get(route('fras.event.face', ['event' => $event->id]))
        ->assertForbidden();
});
