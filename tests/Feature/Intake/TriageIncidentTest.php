<?php

use App\Enums\IncidentStatus;
use App\Events\IncidentCreated;
use App\Events\IncidentStatusChanged;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([IncidentCreated::class, IncidentStatusChanged::class]);
});

// --- Triage action tests ---

it('transitions pending incident to triaged on triage', function () {
    $operator = User::factory()->operator()->create();
    $type = IncidentType::factory()->create();
    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Pending,
    ]);

    $this->actingAs($operator)
        ->post(route('intake.triage', $incident), [
            'incident_type_id' => $type->id,
            'priority' => 'P2',
            'location_text' => 'Rizal Park, Butuan City',
        ])
        ->assertRedirect();

    $incident->refresh();
    expect($incident->status)->toBe(IncidentStatus::Triaged);
});

it('creates timeline entry on triage', function () {
    $operator = User::factory()->operator()->create();
    $type = IncidentType::factory()->create();
    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Pending,
    ]);

    $this->actingAs($operator)
        ->post(route('intake.triage', $incident), [
            'incident_type_id' => $type->id,
            'priority' => 'P2',
            'location_text' => 'Rizal Park, Butuan City',
        ]);

    $this->assertDatabaseHas('incident_timeline', [
        'incident_id' => $incident->id,
        'event_type' => 'incident_triaged',
    ]);
});

it('fires IncidentStatusChanged event on triage', function () {
    $operator = User::factory()->operator()->create();
    $type = IncidentType::factory()->create();
    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Pending,
    ]);

    $this->actingAs($operator)
        ->post(route('intake.triage', $incident), [
            'incident_type_id' => $type->id,
            'priority' => 'P2',
            'location_text' => 'Rizal Park, Butuan City',
        ]);

    Event::assertDispatched(IncidentStatusChanged::class, function ($event) use ($incident) {
        return $event->incident->id === $incident->id
            && $event->oldStatus === IncidentStatus::Pending;
    });
});

it('validates required fields on triage', function () {
    $operator = User::factory()->operator()->create();
    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Pending,
    ]);

    $this->actingAs($operator)
        ->post(route('intake.triage', $incident), [])
        ->assertSessionHasErrors(['incident_type_id', 'priority', 'location_text']);
});

it('returns 403 for responder on triage', function () {
    $responder = User::factory()->responder()->create();
    $type = IncidentType::factory()->create();
    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Pending,
    ]);

    $this->actingAs($responder)
        ->post(route('intake.triage', $incident), [
            'incident_type_id' => $type->id,
            'priority' => 'P2',
            'location_text' => 'Some location',
        ])
        ->assertForbidden();
});

// --- Manual entry tests ---

it('creates a new incident directly as triaged on manual entry', function () {
    $operator = User::factory()->operator()->create();
    $type = IncidentType::factory()->create();

    $this->actingAs($operator)
        ->post(route('intake.store-and-triage'), [
            'incident_type_id' => $type->id,
            'priority' => 'P3',
            'location_text' => 'City Hall, Butuan City',
            'channel' => 'phone',
            'caller_name' => 'Maria Santos',
            'caller_contact' => '09171234567',
        ])
        ->assertRedirect();

    $incident = Incident::latest()->first();
    expect($incident->status)->toBe(IncidentStatus::Triaged)
        ->and($incident->incident_no)->toMatch('/^INC-\d{4}-\d{5}$/')
        ->and($incident->location_text)->toBe('City Hall, Butuan City');
});

it('creates both timeline entries on manual entry', function () {
    $operator = User::factory()->operator()->create();
    $type = IncidentType::factory()->create();

    $this->actingAs($operator)
        ->post(route('intake.store-and-triage'), [
            'incident_type_id' => $type->id,
            'priority' => 'P2',
            'location_text' => 'Agusan del Norte Capitol',
            'channel' => 'phone',
        ]);

    $incident = Incident::latest()->first();

    $this->assertDatabaseHas('incident_timeline', [
        'incident_id' => $incident->id,
        'event_type' => 'incident_created',
    ]);

    $this->assertDatabaseHas('incident_timeline', [
        'incident_id' => $incident->id,
        'event_type' => 'incident_triaged',
    ]);
});

it('fires both events on manual entry', function () {
    $operator = User::factory()->operator()->create();
    $type = IncidentType::factory()->create();

    $this->actingAs($operator)
        ->post(route('intake.store-and-triage'), [
            'incident_type_id' => $type->id,
            'priority' => 'P1',
            'location_text' => 'Butuan Wharf',
            'channel' => 'radio',
        ]);

    Event::assertDispatched(IncidentCreated::class);
    Event::assertDispatched(IncidentStatusChanged::class);
});

it('validates required fields on manual entry', function () {
    $operator = User::factory()->operator()->create();

    $this->actingAs($operator)
        ->post(route('intake.store-and-triage'), [])
        ->assertSessionHasErrors(['incident_type_id', 'priority', 'location_text', 'channel']);
});

it('returns 403 for responder on manual entry', function () {
    $responder = User::factory()->responder()->create();
    $type = IncidentType::factory()->create();

    $this->actingAs($responder)
        ->post(route('intake.store-and-triage'), [
            'incident_type_id' => $type->id,
            'priority' => 'P2',
            'location_text' => 'Some location',
            'channel' => 'phone',
        ])
        ->assertForbidden();
});
