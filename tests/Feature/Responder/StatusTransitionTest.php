<?php

use App\Enums\IncidentStatus;
use App\Events\IncidentCreated;
use App\Events\IncidentStatusChanged;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\Unit;
use App\Models\User;

beforeEach(function () {
    Event::fake([
        IncidentCreated::class,
        IncidentStatusChanged::class,
    ]);
});

it('advances from ACKNOWLEDGED to EN_ROUTE', function () {
    $unit = Unit::factory()->create();
    $responder = User::factory()->responder()->create(['unit_id' => $unit->id]);
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Acknowledged,
        'incident_type_id' => $type->id,
    ]);

    $incident->assignedUnits()->attach($unit->id, [
        'assigned_at' => now(),
        'assigned_by' => $responder->id,
    ]);

    $this->actingAs($responder)
        ->postJson(route('responder.advance-status', $incident), [
            'status' => 'EN_ROUTE',
        ])
        ->assertSuccessful();

    expect($incident->fresh()->status)->toBe(IncidentStatus::EnRoute);
    expect($incident->fresh()->en_route_at)->not->toBeNull();

    Event::assertDispatched(IncidentStatusChanged::class);
});

it('advances from EN_ROUTE to ON_SCENE', function () {
    $unit = Unit::factory()->create();
    $responder = User::factory()->responder()->create(['unit_id' => $unit->id]);
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::EnRoute,
        'incident_type_id' => $type->id,
    ]);

    $incident->assignedUnits()->attach($unit->id, [
        'assigned_at' => now(),
        'assigned_by' => $responder->id,
    ]);

    $this->actingAs($responder)
        ->postJson(route('responder.advance-status', $incident), [
            'status' => 'ON_SCENE',
        ])
        ->assertSuccessful();

    expect($incident->fresh()->status)->toBe(IncidentStatus::OnScene);
    expect($incident->fresh()->on_scene_at)->not->toBeNull();
});

it('advances from ON_SCENE to RESOLVING', function () {
    $unit = Unit::factory()->create();
    $responder = User::factory()->responder()->create(['unit_id' => $unit->id]);
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::OnScene,
        'incident_type_id' => $type->id,
    ]);

    $incident->assignedUnits()->attach($unit->id, [
        'assigned_at' => now(),
        'assigned_by' => $responder->id,
    ]);

    $this->actingAs($responder)
        ->postJson(route('responder.advance-status', $incident), [
            'status' => 'RESOLVING',
        ])
        ->assertSuccessful();

    expect($incident->fresh()->status)->toBe(IncidentStatus::Resolving);
});

it('rejects backward status transition from responder', function () {
    $unit = Unit::factory()->create();
    $responder = User::factory()->responder()->create(['unit_id' => $unit->id]);
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::OnScene,
        'incident_type_id' => $type->id,
    ]);

    $incident->assignedUnits()->attach($unit->id, [
        'assigned_at' => now(),
        'assigned_by' => $responder->id,
    ]);

    $this->actingAs($responder)
        ->postJson(route('responder.advance-status', $incident), [
            'status' => 'EN_ROUTE',
        ])
        ->assertUnprocessable();
});

it('rejects RESOLVED status via advance-status endpoint', function () {
    $unit = Unit::factory()->create();
    $responder = User::factory()->responder()->create(['unit_id' => $unit->id]);
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Resolving,
        'incident_type_id' => $type->id,
    ]);

    $incident->assignedUnits()->attach($unit->id, [
        'assigned_at' => now(),
        'assigned_by' => $responder->id,
    ]);

    $this->actingAs($responder)
        ->postJson(route('responder.advance-status', $incident), [
            'status' => 'RESOLVED',
        ])
        ->assertUnprocessable();
});

it('creates timeline entry on status advance', function () {
    $unit = Unit::factory()->create();
    $responder = User::factory()->responder()->create(['unit_id' => $unit->id]);
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Acknowledged,
        'incident_type_id' => $type->id,
    ]);

    $incident->assignedUnits()->attach($unit->id, [
        'assigned_at' => now(),
        'assigned_by' => $responder->id,
    ]);

    $this->actingAs($responder)
        ->postJson(route('responder.advance-status', $incident), [
            'status' => 'EN_ROUTE',
        ])
        ->assertSuccessful();

    expect($incident->timeline()->where('event_type', 'status_changed')->count())->toBe(1);
});
