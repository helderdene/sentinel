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

it('stores assessment tags on the incident', function () {
    $unit = Unit::factory()->create();
    $responder = User::factory()->responder()->create(['unit_id' => $unit->id]);
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'status' => IncidentStatus::OnScene,
        'incident_type_id' => $type->id,
        'assessment_tags' => null,
    ]);

    $incident->assignedUnits()->attach($unit->id, [
        'assigned_at' => now(),
        'assigned_by' => $responder->id,
    ]);

    $tags = ['conscious', 'breathing', 'no_visible_injury'];

    $this->actingAs($responder)
        ->patchJson(route('responder.update-assessment-tags', $incident), [
            'assessment_tags' => $tags,
        ])
        ->assertSuccessful();

    expect($incident->fresh()->assessment_tags)->toBe($tags);
});

it('validates assessment_tags is a required array', function () {
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
        ->patchJson(route('responder.update-assessment-tags', $incident), [])
        ->assertUnprocessable();
});
