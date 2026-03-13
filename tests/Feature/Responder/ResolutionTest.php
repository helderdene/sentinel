<?php

use App\Enums\IncidentStatus;
use App\Enums\UnitStatus;
use App\Events\IncidentCreated;
use App\Events\IncidentStatusChanged;
use App\Events\UnitStatusChanged;
use App\Jobs\GenerateIncidentReport;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\Unit;
use App\Models\User;

beforeEach(function () {
    Event::fake([
        IncidentCreated::class,
        IncidentStatusChanged::class,
        UnitStatusChanged::class,
    ]);
    Queue::fake();
});

it('resolves an incident with outcome', function () {
    $unit = Unit::factory()->create(['status' => UnitStatus::OnScene]);
    $responder = User::factory()->responder()->create(['unit_id' => $unit->id]);
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Resolving,
        'incident_type_id' => $type->id,
        'on_scene_at' => now()->subMinutes(30),
    ]);

    $incident->assignedUnits()->attach($unit->id, [
        'assigned_at' => now()->subHour(),
        'assigned_by' => $responder->id,
    ]);

    $this->actingAs($responder)
        ->postJson(route('responder.resolve', $incident), [
            'outcome' => 'FALSE_ALARM',
            'closure_notes' => 'No emergency found at location.',
        ])
        ->assertSuccessful();

    $fresh = $incident->fresh();
    expect($fresh->status)->toBe(IncidentStatus::Resolved);
    expect($fresh->resolved_at)->not->toBeNull();
    expect($fresh->outcome)->toBe('FALSE_ALARM');
    expect($fresh->scene_time_sec)->toBeGreaterThan(0);

    expect($unit->fresh()->status)->toBe(UnitStatus::Available);

    Event::assertDispatched(IncidentStatusChanged::class);
    Event::assertDispatched(UnitStatusChanged::class);
    Queue::assertPushed(GenerateIncidentReport::class);
});

it('requires hospital when outcome is TRANSPORTED_TO_HOSPITAL', function () {
    $unit = Unit::factory()->create(['status' => UnitStatus::OnScene]);
    $responder = User::factory()->responder()->create(['unit_id' => $unit->id]);
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Resolving,
        'incident_type_id' => $type->id,
        'on_scene_at' => now()->subMinutes(30),
        'vitals' => ['systolic_bp' => 120, 'diastolic_bp' => 80, 'heart_rate' => 72, 'spo2' => 98, 'gcs' => 15],
    ]);

    $incident->assignedUnits()->attach($unit->id, [
        'assigned_at' => now()->subHour(),
        'assigned_by' => $responder->id,
    ]);

    $this->actingAs($responder)
        ->postJson(route('responder.resolve', $incident), [
            'outcome' => 'TRANSPORTED_TO_HOSPITAL',
        ])
        ->assertUnprocessable();

    $this->actingAs($responder)
        ->postJson(route('responder.resolve', $incident), [
            'outcome' => 'TRANSPORTED_TO_HOSPITAL',
            'hospital' => 'Butuan City District Hospital (BCDH)',
        ])
        ->assertSuccessful();
});

it('requires vitals for medical outcomes', function () {
    $unit = Unit::factory()->create(['status' => UnitStatus::OnScene]);
    $responder = User::factory()->responder()->create(['unit_id' => $unit->id]);
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Resolving,
        'incident_type_id' => $type->id,
        'on_scene_at' => now()->subMinutes(30),
        'vitals' => null,
    ]);

    $incident->assignedUnits()->attach($unit->id, [
        'assigned_at' => now()->subHour(),
        'assigned_by' => $responder->id,
    ]);

    $this->actingAs($responder)
        ->postJson(route('responder.resolve', $incident), [
            'outcome' => 'TREATED_ON_SCENE',
        ])
        ->assertUnprocessable()
        ->assertJsonFragment(['message' => 'Vitals must be recorded before resolving with a medical outcome.']);
});

it('does not require vitals for non-medical outcomes', function () {
    $unit = Unit::factory()->create(['status' => UnitStatus::OnScene]);
    $responder = User::factory()->responder()->create(['unit_id' => $unit->id]);
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Resolving,
        'incident_type_id' => $type->id,
        'on_scene_at' => now()->subMinutes(30),
        'vitals' => null,
    ]);

    $incident->assignedUnits()->attach($unit->id, [
        'assigned_at' => now()->subHour(),
        'assigned_by' => $responder->id,
    ]);

    $this->actingAs($responder)
        ->postJson(route('responder.resolve', $incident), [
            'outcome' => 'FALSE_ALARM',
        ])
        ->assertSuccessful();
});

it('requires outcome to resolve', function () {
    $unit = Unit::factory()->create(['status' => UnitStatus::OnScene]);
    $responder = User::factory()->responder()->create(['unit_id' => $unit->id]);
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Resolving,
        'incident_type_id' => $type->id,
    ]);

    $incident->assignedUnits()->attach($unit->id, [
        'assigned_at' => now()->subHour(),
        'assigned_by' => $responder->id,
    ]);

    $this->actingAs($responder)
        ->postJson(route('responder.resolve', $incident), [])
        ->assertUnprocessable();
});

it('creates timeline entry when resolving', function () {
    $unit = Unit::factory()->create(['status' => UnitStatus::OnScene]);
    $responder = User::factory()->responder()->create(['unit_id' => $unit->id]);
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Resolving,
        'incident_type_id' => $type->id,
        'on_scene_at' => now()->subMinutes(30),
    ]);

    $incident->assignedUnits()->attach($unit->id, [
        'assigned_at' => now()->subHour(),
        'assigned_by' => $responder->id,
    ]);

    $this->actingAs($responder)
        ->postJson(route('responder.resolve', $incident), [
            'outcome' => 'REFUSED_TREATMENT',
        ])
        ->assertSuccessful();

    expect($incident->timeline()->where('event_type', 'status_changed')->count())->toBe(1);
});
