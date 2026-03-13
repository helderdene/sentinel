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

it('acknowledges an assignment and updates pivot and incident status', function () {
    $unit = Unit::factory()->create();
    $responder = User::factory()->responder()->create(['unit_id' => $unit->id]);
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Dispatched,
        'incident_type_id' => $type->id,
    ]);

    $incident->assignedUnits()->attach($unit->id, [
        'assigned_at' => now(),
        'assigned_by' => $responder->id,
    ]);

    $this->actingAs($responder)
        ->postJson(route('responder.acknowledge', $incident))
        ->assertSuccessful();

    expect($incident->fresh()->status)->toBe(IncidentStatus::Acknowledged);
    expect($incident->fresh()->acknowledged_at)->not->toBeNull();

    $pivot = $incident->assignedUnits()->where('unit_id', $unit->id)->first();
    expect($pivot->pivot->acknowledged_at)->not->toBeNull();

    Event::assertDispatched(IncidentStatusChanged::class);
});

it('returns 422 if responder has no unit', function () {
    $responder = User::factory()->responder()->create(['unit_id' => null]);
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Dispatched,
        'incident_type_id' => $type->id,
    ]);

    $this->actingAs($responder)
        ->postJson(route('responder.acknowledge', $incident))
        ->assertUnprocessable();
});

it('returns 403 if unit is not assigned to incident', function () {
    $unit = Unit::factory()->create();
    $responder = User::factory()->responder()->create(['unit_id' => $unit->id]);
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Dispatched,
        'incident_type_id' => $type->id,
    ]);

    $this->actingAs($responder)
        ->postJson(route('responder.acknowledge', $incident))
        ->assertForbidden();
});

it('creates a timeline entry when acknowledging', function () {
    $unit = Unit::factory()->create();
    $responder = User::factory()->responder()->create(['unit_id' => $unit->id]);
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Dispatched,
        'incident_type_id' => $type->id,
    ]);

    $incident->assignedUnits()->attach($unit->id, [
        'assigned_at' => now(),
        'assigned_by' => $responder->id,
    ]);

    $this->actingAs($responder)
        ->postJson(route('responder.acknowledge', $incident))
        ->assertSuccessful();

    expect($incident->timeline()->where('event_type', 'status_changed')->count())->toBe(1);
});

it('requires authentication for acknowledge', function () {
    $type = IncidentType::factory()->create();
    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Dispatched,
        'incident_type_id' => $type->id,
    ]);

    $this->postJson(route('responder.acknowledge', $incident))
        ->assertUnauthorized();
});

it('requires responder role for acknowledge', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Dispatched,
        'incident_type_id' => $type->id,
    ]);

    $this->actingAs($dispatcher)
        ->postJson(route('responder.acknowledge', $incident))
        ->assertForbidden();
});
