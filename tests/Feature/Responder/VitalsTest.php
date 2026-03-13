<?php

use App\Enums\IncidentStatus;
use App\Events\IncidentCreated;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\Unit;
use App\Models\User;

beforeEach(function () {
    Event::fake([
        IncidentCreated::class,
    ]);
});

it('saves vitals with valid ranges', function () {
    $unit = Unit::factory()->create();
    $responder = User::factory()->responder()->create(['unit_id' => $unit->id]);
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::OnScene,
        'incident_type_id' => $type->id,
        'vitals' => null,
    ]);

    $incident->assignedUnits()->attach($unit->id, [
        'assigned_at' => now(),
        'assigned_by' => $responder->id,
    ]);

    $this->actingAs($responder)
        ->patchJson(route('responder.update-vitals', $incident), [
            'systolic_bp' => 120,
            'diastolic_bp' => 80,
            'heart_rate' => 72,
            'spo2' => 98,
            'gcs' => 15,
        ])
        ->assertSuccessful();

    $vitals = $incident->fresh()->vitals;
    expect($vitals['systolic_bp'])->toBe(120);
    expect($vitals['diastolic_bp'])->toBe(80);
    expect($vitals['heart_rate'])->toBe(72);
    expect($vitals['spo2'])->toBe(98);
    expect($vitals['gcs'])->toBe(15);
});

it('rejects vitals outside valid ranges', function () {
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
        ->patchJson(route('responder.update-vitals', $incident), [
            'systolic_bp' => 400,
            'heart_rate' => 5,
            'gcs' => 16,
        ])
        ->assertUnprocessable();
});

it('allows partial vitals update', function () {
    $unit = Unit::factory()->create();
    $responder = User::factory()->responder()->create(['unit_id' => $unit->id]);
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::OnScene,
        'incident_type_id' => $type->id,
        'vitals' => null,
    ]);

    $incident->assignedUnits()->attach($unit->id, [
        'assigned_at' => now(),
        'assigned_by' => $responder->id,
    ]);

    $this->actingAs($responder)
        ->patchJson(route('responder.update-vitals', $incident), [
            'heart_rate' => 80,
        ])
        ->assertSuccessful();

    expect($incident->fresh()->vitals['heart_rate'])->toBe(80);
});
