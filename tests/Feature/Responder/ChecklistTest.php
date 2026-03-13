<?php

use App\Enums\IncidentStatus;
use App\Events\ChecklistUpdated;
use App\Events\IncidentCreated;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\Unit;
use App\Models\User;

beforeEach(function () {
    Event::fake([
        IncidentCreated::class,
        ChecklistUpdated::class,
    ]);
});

it('updates checklist items and computes percentage', function () {
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
        ->patchJson(route('responder.update-checklist', $incident), [
            'items' => [
                'scene_secured' => true,
                'patient_assessed' => true,
                'vitals_recorded' => false,
                'transport_ready' => false,
            ],
        ])
        ->assertSuccessful();

    $fresh = $incident->fresh();
    expect($fresh->checklist_pct)->toBe(50);

    Event::assertDispatched(ChecklistUpdated::class);
});

it('computes 100% when all items complete', function () {
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
        ->patchJson(route('responder.update-checklist', $incident), [
            'items' => [
                'scene_secured' => true,
                'patient_assessed' => true,
            ],
        ])
        ->assertSuccessful();

    expect($incident->fresh()->checklist_pct)->toBe(100);
});

it('validates checklist items are required', function () {
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
        ->patchJson(route('responder.update-checklist', $incident), [])
        ->assertUnprocessable();
});
