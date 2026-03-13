<?php

use App\Enums\IncidentStatus;
use App\Events\IncidentCreated;
use App\Events\MessageSent;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\User;

beforeEach(function () {
    Event::fake([
        IncidentCreated::class,
        MessageSent::class,
    ]);
});

it('dispatcher can send a message to an incident', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    $type = IncidentType::factory()->create();
    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Dispatched,
        'incident_type_id' => $type->id,
    ]);

    $this->actingAs($dispatcher)
        ->postJson(route('dispatch.send-message', $incident), [
            'body' => 'Proceed to location.',
            'is_quick_reply' => true,
        ])
        ->assertSuccessful();
});

it('creates message in incident_messages table with correct data', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    $type = IncidentType::factory()->create();
    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Dispatched,
        'incident_type_id' => $type->id,
    ]);

    $this->actingAs($dispatcher)
        ->postJson(route('dispatch.send-message', $incident), [
            'body' => 'Stand by for update.',
        ])
        ->assertSuccessful();

    $message = $incident->messages()->first();

    expect($message)->not->toBeNull();
    expect($message->sender_type)->toBe(User::class);
    expect($message->sender_id)->toBe($dispatcher->id);
    expect($message->body)->toBe('Stand by for update.');
    expect($message->message_type)->toBe('text');
});

it('dispatches MessageSent event with correct parameters', function () {
    $dispatcher = User::factory()->dispatcher()->create();
    $type = IncidentType::factory()->create();
    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Dispatched,
        'incident_type_id' => $type->id,
    ]);

    $this->actingAs($dispatcher)
        ->postJson(route('dispatch.send-message', $incident), [
            'body' => 'Copy that.',
            'is_quick_reply' => true,
        ])
        ->assertSuccessful();

    Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($dispatcher, $incident) {
        return $event->incidentId === $incident->id
            && $event->senderId === $dispatcher->id
            && $event->senderName === $dispatcher->name
            && $event->senderRole === 'dispatcher'
            && $event->senderUnitCallsign === null
            && $event->body === 'Copy that.'
            && $event->isQuickReply === true;
    });
});

it('denies non-dispatch role from sending dispatch message', function () {
    $responder = User::factory()->responder()->create();
    $type = IncidentType::factory()->create();
    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Dispatched,
        'incident_type_id' => $type->id,
    ]);

    $this->actingAs($responder)
        ->postJson(route('dispatch.send-message', $incident), [
            'body' => 'Should not work.',
        ])
        ->assertForbidden();
});
