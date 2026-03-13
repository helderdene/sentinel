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

it('responder sendMessage dispatches MessageSent with updated constructor', function () {
    $unit = Unit::factory()->create(['callsign' => 'AMB 01']);
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
            'body' => 'Patient stable.',
            'is_quick_reply' => false,
        ])
        ->assertSuccessful();

    Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($responder, $incident) {
        return $event->incidentId === $incident->id
            && $event->senderId === $responder->id
            && $event->senderName === $responder->name
            && $event->senderRole === 'responder'
            && $event->body === 'Patient stable.';
    });
});

it('responder sendMessage includes unit callsign in the event payload', function () {
    $unit = Unit::factory()->create(['callsign' => 'FIRE 03']);
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
            'body' => 'Need backup.',
            'is_quick_reply' => true,
        ])
        ->assertSuccessful();

    Event::assertDispatched(MessageSent::class, function (MessageSent $event) {
        return $event->senderUnitCallsign === 'FIRE 03';
    });
});
