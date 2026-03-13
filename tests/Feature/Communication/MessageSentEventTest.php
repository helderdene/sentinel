<?php

use App\Events\MessageSent;
use Illuminate\Broadcasting\PrivateChannel;

it('broadcasts on incident-level message channel', function () {
    $event = new MessageSent(
        incidentId: 'abc-123',
        senderId: 1,
        senderName: 'John Doe',
        senderRole: 'dispatcher',
        senderUnitCallsign: null,
        body: 'Test message',
        isQuickReply: false,
        messageId: 42,
    );

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(2);
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
    expect($channels[0]->name)->toBe('private-incident.abc-123.messages');
});

it('broadcasts on dispatch.incidents channel', function () {
    $event = new MessageSent(
        incidentId: 'abc-123',
        senderId: 1,
        senderName: 'John Doe',
        senderRole: 'dispatcher',
        senderUnitCallsign: null,
        body: 'Test message',
        isQuickReply: false,
        messageId: 42,
    );

    $channels = $event->broadcastOn();

    expect($channels[1])->toBeInstanceOf(PrivateChannel::class);
    expect($channels[1]->name)->toBe('private-dispatch.incidents');
});

it('includes full sender context in broadcastWith payload', function () {
    $event = new MessageSent(
        incidentId: 'inc-456',
        senderId: 5,
        senderName: 'Jane Smith',
        senderRole: 'responder',
        senderUnitCallsign: 'AMB-01',
        body: 'Patient stable',
        isQuickReply: true,
        messageId: 99,
    );

    $payload = $event->broadcastWith();

    expect($payload)->toHaveKeys([
        'id', 'incident_id', 'sender_id', 'sender_name',
        'sender_role', 'sender_unit_callsign', 'body',
        'is_quick_reply', 'sent_at',
    ]);
    expect($payload['id'])->toBe(99);
    expect($payload['incident_id'])->toBe('inc-456');
    expect($payload['sender_id'])->toBe(5);
    expect($payload['sender_name'])->toBe('Jane Smith');
    expect($payload['sender_role'])->toBe('responder');
    expect($payload['sender_unit_callsign'])->toBe('AMB-01');
    expect($payload['body'])->toBe('Patient stable');
    expect($payload['is_quick_reply'])->toBeTrue();
    expect($payload['sent_at'])->toBeString();
});
