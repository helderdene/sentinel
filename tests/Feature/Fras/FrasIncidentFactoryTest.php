<?php

use App\Enums\IncidentChannel;
use App\Enums\IncidentPriority;
use App\Enums\PersonnelCategory;
use App\Enums\RecognitionSeverity;
use App\Events\IncidentCreated;
use App\Events\RecognitionAlertReceived;
use App\Models\Camera;
use App\Models\Incident;
use App\Models\IncidentCategory;
use App\Models\IncidentType;
use App\Models\Personnel;
use App\Models\RecognitionEvent;
use App\Services\FrasIncidentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

pest()->group('fras');

beforeEach(function () {
    Event::fake([IncidentCreated::class, RecognitionAlertReceived::class]);

    // Ensure the person_of_interest IncidentType exists for each test (seeder
    // normally wires this; tests invoke the factory directly so we seed inline).
    $category = IncidentCategory::firstOrCreate(
        ['name' => 'Crime / Security'],
        ['icon' => 'Shield', 'is_active' => true, 'sort_order' => 4]
    );

    IncidentType::updateOrCreate(
        ['code' => 'person_of_interest'],
        [
            'incident_category_id' => $category->id,
            'category' => 'Crime / Security',
            'name' => 'Person of Interest',
            'default_priority' => 'P2',
            'is_active' => true,
            'show_in_public_app' => false,
            'sort_order' => 999,
        ]
    );

    // Default thresholds per D-05.
    config([
        'fras.recognition.confidence_threshold' => 0.75,
        'fras.recognition.dedup_window_seconds' => 60,
        'fras.recognition.pulse_duration_seconds' => 3,
        'fras.recognition.priority_map' => [
            'critical' => [
                'block' => 'P2',
                'missing' => 'P2',
                'lost_child' => 'P1',
            ],
        ],
    ]);
});

/**
 * Build a RecognitionEvent wired to a Camera + Personnel with the given
 * severity and similarity score (as a 0..1 float — factory persists it as
 * decimal:2 via cast).
 */
function frasEvent(RecognitionSeverity $severity, float $similarity, PersonnelCategory $category = PersonnelCategory::Block): RecognitionEvent
{
    $camera = Camera::factory()->create();
    $personnel = Personnel::factory()->create(['category' => $category]);

    return RecognitionEvent::factory()
        ->for($camera)
        ->for($personnel)
        ->create([
            'severity' => $severity,
            'similarity' => $similarity,
        ]);
}

it('returns null and stays silent for Info severity', function () {
    $event = frasEvent(RecognitionSeverity::Info, 0.90);

    $result = app(FrasIncidentFactory::class)->createFromRecognition($event);

    expect($result)->toBeNull();
    Event::assertNotDispatched(IncidentCreated::class);
    Event::assertNotDispatched(RecognitionAlertReceived::class);
});

it('broadcasts RecognitionAlertReceived then returns null for Warning severity with incident_id null', function () {
    $event = frasEvent(RecognitionSeverity::Warning, 0.90);

    $result = app(FrasIncidentFactory::class)->createFromRecognition($event);

    expect($result)->toBeNull();
    Event::assertNotDispatched(IncidentCreated::class);
    Event::assertDispatched(RecognitionAlertReceived::class, function ($e) use ($event) {
        return $e->event->id === $event->id && $e->incident === null;
    });
});

it('returns null without broadcast for confidence below threshold', function () {
    config(['fras.recognition.confidence_threshold' => 0.75]);
    $event = frasEvent(RecognitionSeverity::Critical, 0.70);

    $result = app(FrasIncidentFactory::class)->createFromRecognition($event);

    expect($result)->toBeNull();
    Event::assertNotDispatched(IncidentCreated::class);
    Event::assertNotDispatched(RecognitionAlertReceived::class);
});

it('returns null for allow-category personnel', function () {
    $event = frasEvent(RecognitionSeverity::Critical, 0.90, PersonnelCategory::Allow);

    $result = app(FrasIncidentFactory::class)->createFromRecognition($event);

    expect($result)->toBeNull();
    Event::assertNotDispatched(IncidentCreated::class);
});

it('returns null for unknown/null personnel_id', function () {
    $camera = Camera::factory()->create();
    $event = RecognitionEvent::factory()
        ->for($camera)
        ->create([
            'personnel_id' => null,
            'severity' => RecognitionSeverity::Critical,
            'similarity' => 0.90,
        ]);

    $result = app(FrasIncidentFactory::class)->createFromRecognition($event);

    expect($result)->toBeNull();
    Event::assertNotDispatched(IncidentCreated::class);
});

it('returns null when dedup key already present within window', function () {
    $event = frasEvent(RecognitionSeverity::Critical, 0.90);

    // Pre-seed dedup cache matching the factory's key shape (D-08).
    Cache::add(
        "fras:incident-dedup:{$event->camera_id}:{$event->personnel_id}",
        true,
        60
    );

    $result = app(FrasIncidentFactory::class)->createFromRecognition($event);

    expect($result)->toBeNull();
    Event::assertNotDispatched(IncidentCreated::class);
});

it('creates P2 Incident for Critical × block-list personnel', function () {
    $event = frasEvent(RecognitionSeverity::Critical, 0.90, PersonnelCategory::Block);

    $incident = app(FrasIncidentFactory::class)->createFromRecognition($event);

    expect($incident)->toBeInstanceOf(Incident::class);
    expect($incident->channel)->toBe(IncidentChannel::IoT);
    expect($incident->priority)->toBe(IncidentPriority::P2);
    expect($incident->incidentType->code)->toBe('person_of_interest');

    $event->refresh();
    expect($event->incident_id)->toBe($incident->id);

    $timeline = $incident->timeline()->where('event_type', 'incident_created')->first();
    expect($timeline)->not->toBeNull();
    expect($timeline->event_data['source'])->toBe('fras_recognition');
    expect($timeline->event_data['recognition_event_id'])->toBe($event->id);

    Event::assertDispatched(IncidentCreated::class);
    Event::assertDispatched(RecognitionAlertReceived::class, function ($e) use ($incident) {
        return $e->incident?->id === $incident->id;
    });
});

it('creates P1 Incident for Critical × lost_child personnel', function () {
    $event = frasEvent(RecognitionSeverity::Critical, 0.90, PersonnelCategory::LostChild);

    $incident = app(FrasIncidentFactory::class)->createFromRecognition($event);

    expect($incident)->toBeInstanceOf(Incident::class);
    expect($incident->priority)->toBe(IncidentPriority::P1);
    Event::assertDispatched(IncidentCreated::class);
});

it('creates P2 Incident for Critical × missing personnel', function () {
    $event = frasEvent(RecognitionSeverity::Critical, 0.90, PersonnelCategory::Missing);

    $incident = app(FrasIncidentFactory::class)->createFromRecognition($event);

    expect($incident)->toBeInstanceOf(Incident::class);
    expect($incident->priority)->toBe(IncidentPriority::P2);
});

it('respects config overrides for confidence_threshold and dedup_window_seconds', function () {
    config(['fras.recognition.confidence_threshold' => 0.90]);

    // Similarity 0.85 now fails the gate because threshold tightened.
    $event = frasEvent(RecognitionSeverity::Critical, 0.85);

    $result = app(FrasIncidentFactory::class)->createFromRecognition($event);

    expect($result)->toBeNull();
    Event::assertNotDispatched(IncidentCreated::class);
});

it('createFromSensor writes incident via factory with iot_sensor source', function () {
    $incidentType = IncidentType::factory()->create(['code' => 'NAT-002', 'default_priority' => 'P2']);

    $validated = [
        'sensor_type' => 'flood_gauge',
        'sensor_id' => 'FLOOD-42',
        'value' => 6.8,
        'threshold' => 4.0,
        'location_text' => 'Agusan River Bridge',
        'latitude' => 8.9475,
        'longitude' => 125.5406,
    ];

    $mapping = [
        'incident_type_code' => 'NAT-002',
        'priority' => 'P2',
    ];

    $incident = app(FrasIncidentFactory::class)->createFromSensor($validated, $mapping, $incidentType);

    expect($incident)->toBeInstanceOf(Incident::class);
    expect($incident->channel)->toBe(IncidentChannel::IoT);

    $timeline = $incident->timeline()->where('event_type', 'incident_created')->first();
    expect($timeline)->not->toBeNull();
    expect($timeline->event_data['source'])->toBe('iot_sensor');
});
