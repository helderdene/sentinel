<?php

use App\Enums\IncidentStatus;
use App\Events\IncidentCreated;
use App\Events\MessageSent;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\Unit;
use App\Models\User;

beforeEach(function () {
    Event::fake([
        IncidentCreated::class,
        MessageSent::class,
    ]);
});

it('sends a message to dispatch', function () {
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
        ->postJson(route('responder.send-message', $incident), [
            'body' => 'Patient stabilized, requesting transport.',
        ])
        ->assertSuccessful();

    expect($incident->messages()->count())->toBe(1);
    expect($incident->messages()->first()->body)->toBe('Patient stabilized, requesting transport.');
    expect($incident->messages()->first()->sender_type)->toBe(User::class);
    expect($incident->messages()->first()->sender_id)->toBe($responder->id);
});

it('sends a quick reply message', function () {
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
        ->postJson(route('responder.send-message', $incident), [
            'body' => 'Copy that',
            'is_quick_reply' => true,
        ])
        ->assertSuccessful();

    expect($incident->messages()->first()->is_quick_reply)->toBeTrue();
});

it('validates message body is required', function () {
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
        ->postJson(route('responder.send-message', $incident), [])
        ->assertUnprocessable();
});
