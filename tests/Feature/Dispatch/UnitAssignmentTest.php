<?php

use App\Enums\IncidentStatus;
use App\Enums\UnitStatus;
use App\Events\AssignmentPushed;
use App\Events\IncidentCreated;
use App\Events\IncidentStatusChanged;
use App\Events\UnitStatusChanged;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\Unit;
use App\Models\User;

beforeEach(function () {
    Event::fake([
        IncidentCreated::class,
        AssignmentPushed::class,
        IncidentStatusChanged::class,
        UnitStatusChanged::class,
    ]);
});

it('assigns an available unit to a triaged incident', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    $responder = User::factory()->responder()->create();
    $type = IncidentType::factory()->create();
    $unit = Unit::factory()->create(['status' => UnitStatus::Available]);
    $responder->update(['unit_id' => $unit->id]);

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Triaged,
        'incident_type_id' => $type->id,
    ]);

    $this->actingAs($dispatcher)
        ->postJson(route('dispatch.assign', $incident), [
            'unit_id' => $unit->id,
        ])
        ->assertSuccessful();

    // Verify pivot record
    expect($incident->fresh()->assignedUnits)->toHaveCount(1);

    // Verify incident status changed to DISPATCHED
    expect($incident->fresh()->status)->toBe(IncidentStatus::Dispatched);

    // Verify unit status changed to DISPATCHED
    expect($unit->fresh()->status)->toBe(UnitStatus::Dispatched);

    // Verify timeline entry
    $this->assertDatabaseHas('incident_timeline', [
        'incident_id' => $incident->id,
        'event_type' => 'unit_assigned',
    ]);

    // Verify events dispatched
    Event::assertDispatched(AssignmentPushed::class);
    Event::assertDispatched(UnitStatusChanged::class);
    Event::assertDispatched(IncidentStatusChanged::class);
});

it('assigns second unit to already-dispatched incident keeping status', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    $type = IncidentType::factory()->create();
    $unit1 = Unit::factory()->create(['status' => UnitStatus::Available]);
    $unit2 = Unit::factory()->create(['status' => UnitStatus::Available]);

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Triaged,
        'incident_type_id' => $type->id,
    ]);

    // Assign first unit
    $this->actingAs($dispatcher)
        ->postJson(route('dispatch.assign', $incident), [
            'unit_id' => $unit1->id,
        ])
        ->assertSuccessful();

    // Assign second unit
    $this->actingAs($dispatcher)
        ->postJson(route('dispatch.assign', $incident), [
            'unit_id' => $unit2->id,
        ])
        ->assertSuccessful();

    expect($incident->fresh()->assignedUnits)->toHaveCount(2);
    expect($incident->fresh()->status)->toBe(IncidentStatus::Dispatched);
});

it('rejects assigning non-available unit', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    $type = IncidentType::factory()->create();
    $unit = Unit::factory()->create(['status' => UnitStatus::Dispatched]);

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Triaged,
        'incident_type_id' => $type->id,
    ]);

    $this->actingAs($dispatcher)
        ->postJson(route('dispatch.assign', $incident), [
            'unit_id' => $unit->id,
        ])
        ->assertUnprocessable();
});

it('rejects assigning to resolved incident', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    $type = IncidentType::factory()->create();
    $unit = Unit::factory()->create(['status' => UnitStatus::Available]);

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Resolved,
        'incident_type_id' => $type->id,
    ]);

    $this->actingAs($dispatcher)
        ->postJson(route('dispatch.assign', $incident), [
            'unit_id' => $unit->id,
        ])
        ->assertUnprocessable();
});

it('unassigns a unit from an incident', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    $type = IncidentType::factory()->create();
    $unit = Unit::factory()->create(['status' => UnitStatus::Dispatched]);

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Dispatched,
        'incident_type_id' => $type->id,
    ]);

    // Create the pivot record manually
    $incident->assignedUnits()->attach($unit->id, [
        'assigned_at' => now(),
        'assigned_by' => $dispatcher->id,
    ]);

    $this->actingAs($dispatcher)
        ->postJson(route('dispatch.unassign', $incident), [
            'unit_id' => $unit->id,
        ])
        ->assertSuccessful();

    // Pivot should have unassigned_at set (no longer in active assignments)
    expect($incident->fresh()->assignedUnits)->toHaveCount(0);

    // Unit should be back to AVAILABLE since no other active assignments
    expect($unit->fresh()->status)->toBe(UnitStatus::Available);

    // Timeline entry created
    $this->assertDatabaseHas('incident_timeline', [
        'incident_id' => $incident->id,
        'event_type' => 'unit_unassigned',
    ]);

    Event::assertDispatched(UnitStatusChanged::class);
});
