<?php

use App\Enums\IncidentStatus;
use App\Events\IncidentCreated;
use App\Events\ResourceRequested;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\Unit;
use App\Models\User;

beforeEach(function () {
    Event::fake([
        IncidentCreated::class,
        ResourceRequested::class,
    ]);
});

it('requests an additional resource', function () {
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
        ->postJson(route('responder.request-resource', $incident), [
            'type' => 'ADDITIONAL_AMBULANCE',
            'notes' => 'Multiple casualties, need additional transport.',
        ])
        ->assertSuccessful();

    expect($incident->timeline()->where('event_type', 'resource_requested')->count())->toBe(1);

    Event::assertDispatched(ResourceRequested::class);
});

it('validates resource type is required and valid', function () {
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
        ->postJson(route('responder.request-resource', $incident), [])
        ->assertUnprocessable();

    $this->actingAs($responder)
        ->postJson(route('responder.request-resource', $incident), [
            'type' => 'INVALID_TYPE',
        ])
        ->assertUnprocessable();
});
