<?php

use App\Enums\RecognitionSeverity;
use App\Models\Camera;
use App\Models\Personnel;
use App\Models\RecognitionEvent;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

pest()->group('fras');

beforeEach(function () {
    Storage::fake('fras_events');
});

it('filters by severity=critical', function () {
    $operator = User::factory()->operator()->create();
    $camera = Camera::factory()->create();
    $personnel = Personnel::factory()->create();

    RecognitionEvent::factory()->for($camera)->for($personnel)->create([
        'severity' => RecognitionSeverity::Critical,
    ]);
    RecognitionEvent::factory()->for($camera)->for($personnel)->create([
        'severity' => RecognitionSeverity::Warning,
    ]);

    $this->actingAs($operator)
        ->get(route('fras.events.index', ['severity' => ['critical']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('fras/Events')
            ->has('events.data', 1)
            ->where('events.data.0.severity', 'critical')
        );
});

it('filters by camera_id', function () {
    $operator = User::factory()->operator()->create();
    $camA = Camera::factory()->create(['camera_id_display' => 'CAM-A']);
    $camB = Camera::factory()->create(['camera_id_display' => 'CAM-B']);

    RecognitionEvent::factory()->for($camA)->create([
        'severity' => RecognitionSeverity::Critical,
    ]);
    RecognitionEvent::factory()->for($camB)->create([
        'severity' => RecognitionSeverity::Critical,
    ]);

    $this->actingAs($operator)
        ->get(route('fras.events.index', ['camera_id' => $camA->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('events.data', 1)
            ->where('events.data.0.camera_id', $camA->id)
        );
});

it('filters by from date', function () {
    $operator = User::factory()->operator()->create();
    $camera = Camera::factory()->create();

    RecognitionEvent::factory()->for($camera)->create([
        'captured_at' => now()->subDays(5),
    ]);
    RecognitionEvent::factory()->for($camera)->create([
        'captured_at' => now()->subHour(),
    ]);

    $this->actingAs($operator)
        ->get(route('fras.events.index', ['from' => now()->subDay()->toDateString()]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('events.data', 1));
});

it('filters by q on personnel.name (ILIKE)', function () {
    $operator = User::factory()->operator()->create();
    $camera = Camera::factory()->create();
    $match = Personnel::factory()->create(['name' => 'Juan Dela Cruz']);
    $other = Personnel::factory()->create(['name' => 'Maria Santos']);

    RecognitionEvent::factory()->for($camera)->for($match)->create();
    RecognitionEvent::factory()->for($camera)->for($other)->create();

    $this->actingAs($operator)
        ->get(route('fras.events.index', ['q' => 'dela cruz']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('events.data', 1)
            ->where('events.data.0.personnel_id', $match->id)
        );
});

it('filters by q on camera.camera_id_display (ILIKE)', function () {
    $operator = User::factory()->operator()->create();
    $match = Camera::factory()->create(['camera_id_display' => 'GATE-07']);
    $other = Camera::factory()->create(['camera_id_display' => 'PARK-03']);

    RecognitionEvent::factory()->for($match)->create();
    RecognitionEvent::factory()->for($other)->create();

    $this->actingAs($operator)
        ->get(route('fras.events.index', ['q' => 'gate']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('events.data', 1)
            ->where('events.data.0.camera_id', $match->id)
        );
});

it('paginates at 25 per page', function () {
    $operator = User::factory()->operator()->create();
    $camera = Camera::factory()->create();
    RecognitionEvent::factory()->count(30)->for($camera)->create([
        'severity' => RecognitionSeverity::Critical,
    ]);

    $this->actingAs($operator)
        ->get(route('fras.events.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('events.data', 25)
            ->where('events.per_page', 25)
            ->where('events.total', 30)
        );
});

it('computes replay count >= N for repeated camera+personnel pairs in last 24h', function () {
    $operator = User::factory()->operator()->create();
    $camera = Camera::factory()->create();
    $personnel = Personnel::factory()->create();

    RecognitionEvent::factory()->count(3)->for($camera)->for($personnel)->create([
        'severity' => RecognitionSeverity::Warning,
        'captured_at' => now()->subHours(2),
    ]);

    $key = "{$camera->id}:{$personnel->id}";

    $this->actingAs($operator)
        ->get(route('fras.events.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('replayCounts')
            ->where("replayCounts.{$key}", 3)
        );
});

it('skips replay count entries for events with null personnel_id', function () {
    $operator = User::factory()->operator()->create();
    $camera = Camera::factory()->create();

    RecognitionEvent::factory()->count(2)->for($camera)->create([
        'personnel_id' => null,
        'severity' => RecognitionSeverity::Info,
        'captured_at' => now()->subHours(2),
    ]);

    $this->actingAs($operator)
        ->get(route('fras.events.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('replayCounts', [])
        );
});

it('responder gets 403 on GET /fras/events (role middleware)', function () {
    $responder = User::factory()->responder()->create();

    $this->actingAs($responder)
        ->get(route('fras.events.index'))
        ->assertForbidden();
});

it('operator reaches the scene stream via a valid signed URL', function () {
    $operator = User::factory()->operator()->create();
    $event = RecognitionEvent::factory()->create([
        'scene_image_path' => 'scene/ok.jpg',
    ]);
    Storage::disk('fras_events')->put('scene/ok.jpg', 'fake-jpeg');

    $url = URL::temporarySignedRoute(
        'fras.events.scene.show',
        now()->addMinutes(5),
        ['event' => $event->id],
    );

    $this->actingAs($operator)->get($url)->assertOk();
});

it('responder gets 403 on scene fetch even with a valid signed URL', function () {
    $responder = User::factory()->responder()->create();
    $event = RecognitionEvent::factory()->create([
        'scene_image_path' => 'scene/blocked.jpg',
    ]);
    Storage::disk('fras_events')->put('scene/blocked.jpg', 'fake-jpeg');

    $url = URL::temporarySignedRoute(
        'fras.events.scene.show',
        now()->addMinutes(5),
        ['event' => $event->id],
    );

    $this->actingAs($responder)->get($url)->assertForbidden();
});
