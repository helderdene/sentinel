<?php

use App\Events\MqttListenerHealthChanged;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

pest()->group('mqtt');

it('broadcasts the exact D-11 payload shape (status, last_message_received_at, since, active_camera_count)', function () {
    $event = new MqttListenerHealthChanged(
        status: 'SILENT',
        lastMessageReceivedAt: '2026-04-21T00:00:00+00:00',
        since: '2026-04-21T00:01:30+00:00',
        activeCameraCount: 3,
    );

    $payload = $event->broadcastWith();

    expect(array_keys($payload))->toEqualCanonicalizing([
        'status',
        'last_message_received_at',
        'since',
        'active_camera_count',
    ]);
    expect($payload['status'])->toBe('SILENT');
    expect($payload['last_message_received_at'])->toBe('2026-04-21T00:00:00+00:00');
    expect($payload['since'])->toBe('2026-04-21T00:01:30+00:00');
    expect($payload['active_camera_count'])->toBe(3);
});

it('broadcasts on PrivateChannel(dispatch.incidents) per D-10', function () {
    $event = new MqttListenerHealthChanged('HEALTHY', null, now()->toIso8601String(), 1);
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
    expect($channels[0]->name)->toBe('private-dispatch.incidents');
});

it('implements ShouldBroadcast and ShouldDispatchAfterCommit', function () {
    $interfaces = class_implements(MqttListenerHealthChanged::class);

    expect($interfaces)->toContain(ShouldBroadcast::class);
    expect($interfaces)->toContain(ShouldDispatchAfterCommit::class);
});

it('accepts all four D-11 status enum values', function (string $status) {
    $event = new MqttListenerHealthChanged($status, null, now()->toIso8601String(), 0);
    expect($event->broadcastWith()['status'])->toBe($status);
})->with(['HEALTHY', 'SILENT', 'DISCONNECTED', 'NO_ACTIVE_CAMERAS']);
