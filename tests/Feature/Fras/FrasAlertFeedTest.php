<?php

use App\Enums\RecognitionSeverity;
use App\Events\FrasAlertAcknowledged;
use App\Models\Camera;
use App\Models\Personnel;
use App\Models\RecognitionEvent;
use App\Models\User;
use Illuminate\Support\Facades\Event;

pest()->group('fras');

beforeEach(function () {
    // The fras/Alerts + fras/Events Vue pages land in Plans 22-06/22-07.
    // Plan 22-05 asserts on the Inertia prop contract only.
    config()->set('inertia.testing.ensure_pages_exist', false);
});

it('operator ACK of an unacknowledged event sets ack columns + fires FrasAlertAcknowledged', function () {
    Event::fake([FrasAlertAcknowledged::class]);

    $operator = User::factory()->operator()->create(['name' => 'Op Alpha']);
    $event = RecognitionEvent::factory()
        ->warning()
        ->for(Camera::factory())
        ->for(Personnel::factory())
        ->create();

    $this->actingAs($operator)
        ->post(route('fras.alerts.ack', ['event' => $event->id]))
        ->assertRedirect();

    $event->refresh();
    expect($event->acknowledged_by)->toBe($operator->id);
    expect($event->acknowledged_at)->not->toBeNull();
    expect($event->dismissed_at)->toBeNull();

    Event::assertDispatched(
        FrasAlertAcknowledged::class,
        fn ($e) => $e->eventId === $event->id
            && $e->action === 'ack'
            && $e->actorUserId === $operator->id
            && $e->actorName === 'Op Alpha',
    );
});

it('operator dismiss with reason sets dismiss columns + fires FrasAlertAcknowledged', function () {
    Event::fake([FrasAlertAcknowledged::class]);

    $operator = User::factory()->operator()->create(['name' => 'Op Alpha']);
    $event = RecognitionEvent::factory()
        ->warning()
        ->for(Camera::factory())
        ->for(Personnel::factory())
        ->create();

    $this->actingAs($operator)
        ->post(route('fras.alerts.dismiss', ['event' => $event->id]), [
            'reason' => 'false_match',
        ])
        ->assertRedirect();

    $event->refresh();
    expect($event->dismissed_by)->toBe($operator->id);
    expect($event->dismissed_at)->not->toBeNull();
    expect($event->dismiss_reason?->value)->toBe('false_match');
    expect($event->dismiss_reason_note)->toBeNull();

    Event::assertDispatched(
        FrasAlertAcknowledged::class,
        fn ($e) => $e->eventId === $event->id
            && $e->action === 'dismiss'
            && $e->reason === 'false_match',
    );
});

it('responder receives 403 on ACK (role middleware)', function () {
    $responder = User::factory()->responder()->create();
    $event = RecognitionEvent::factory()->warning()->create();

    $this->actingAs($responder)
        ->post(route('fras.alerts.ack', ['event' => $event->id]))
        ->assertForbidden();
});

it('dispatcher receives 403 on ACK (role middleware)', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    $event = RecognitionEvent::factory()->warning()->create();

    $this->actingAs($dispatcher)
        ->post(route('fras.alerts.ack', ['event' => $event->id]))
        ->assertForbidden();
});

it('dismiss requires a reason', function () {
    $operator = User::factory()->operator()->create();
    $event = RecognitionEvent::factory()->warning()->create();

    $this->actingAs($operator)
        ->post(route('fras.alerts.dismiss', ['event' => $event->id]), [])
        ->assertInvalid(['reason']);
});

it('dismiss with reason=other requires reason_note', function () {
    $operator = User::factory()->operator()->create();
    $event = RecognitionEvent::factory()->warning()->create();

    $this->actingAs($operator)
        ->post(route('fras.alerts.dismiss', ['event' => $event->id]), [
            'reason' => 'other',
        ])
        ->assertInvalid(['reason_note']);
});

it('ACK on an already-acknowledged event returns 409', function () {
    $operator = User::factory()->operator()->create();
    $event = RecognitionEvent::factory()
        ->warning()
        ->create([
            'acknowledged_by' => $operator->id,
            'acknowledged_at' => now()->subMinute(),
        ]);

    $this->actingAs($operator)
        ->post(route('fras.alerts.ack', ['event' => $event->id]))
        ->assertStatus(409);
});

it('index hydrates Critical+Warning non-ack non-dismiss events with signed face URLs', function () {
    $operator = User::factory()->operator()->create();
    $camera = Camera::factory()->create(['camera_id_display' => 'CAM-A']);
    $personnel = Personnel::factory()->create(['name' => 'Jane Doe']);

    // Eligible: Critical with face image
    $eligible = RecognitionEvent::factory()->for($camera)->for($personnel)->create([
        'severity' => RecognitionSeverity::Critical,
        'face_image_path' => 'faces/x.jpg',
    ]);

    // Excluded: already acknowledged
    RecognitionEvent::factory()->for($camera)->for($personnel)->create([
        'severity' => RecognitionSeverity::Warning,
        'acknowledged_at' => now(),
        'acknowledged_by' => $operator->id,
    ]);

    // Excluded: already dismissed
    RecognitionEvent::factory()->for($camera)->for($personnel)->create([
        'severity' => RecognitionSeverity::Warning,
        'dismissed_at' => now(),
        'dismissed_by' => $operator->id,
        'dismiss_reason' => 'false_match',
    ]);

    // Excluded: Info severity
    RecognitionEvent::factory()->for($camera)->for($personnel)->create([
        'severity' => RecognitionSeverity::Info,
    ]);

    $this->actingAs($operator)
        ->get(route('fras.alerts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('fras/Alerts')
            ->has('initialAlerts', 1)
            ->where('initialAlerts.0.event_id', $eligible->id)
            ->where('initialAlerts.0.severity', 'critical')
            ->has('initialAlerts.0.personnel')
            ->has('initialAlerts.0.camera')
            ->has('initialAlerts.0.face_image_url')
            ->has('audioMuted')
            ->where('frasConfig.audioEnabled', true)
        );
});

it('index excludes events where personnel_id is null (unmatched face detections)', function () {
    $operator = User::factory()->operator()->create();
    $camera = Camera::factory()->create(['camera_id_display' => 'CAM-A']);
    $personnel = Personnel::factory()->create();

    // Eligible: fully matched
    $matched = RecognitionEvent::factory()->for($camera)->for($personnel)->create([
        'severity' => RecognitionSeverity::Critical,
    ]);

    // Excluded: no personnel match (anonymous face detection — crashes Vue
    // if surfaced, since AlertCard assumes a non-null personnel object)
    RecognitionEvent::factory()->for($camera)->create([
        'severity' => RecognitionSeverity::Warning,
        'personnel_id' => null,
    ]);

    $this->actingAs($operator)
        ->get(route('fras.alerts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('initialAlerts', 1)
            ->where('initialAlerts.0.event_id', $matched->id)
        );
});
