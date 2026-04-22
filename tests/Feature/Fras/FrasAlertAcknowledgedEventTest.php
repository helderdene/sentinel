<?php

use App\Events\FrasAlertAcknowledged;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Support\Facades\Event;

pest()->group('fras');

it('broadcasts on the fras.alerts private channel', function () {
    $event = new FrasAlertAcknowledged('uuid-x', 'ack', 42, 'Op A');

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
    expect($channels[0]->name)->toBe('private-fras.alerts');
});

it('broadcasts under the default FQN (no custom broadcastAs)', function () {
    // Echo default namespace is 'App.Events', so client listeners via
    // useEcho('fras.alerts', 'FrasAlertAcknowledged', ...) resolve to the
    // FQN after namespace prepending. Defining broadcastAs() would emit
    // the short name and silently break all client handlers.
    $event = new FrasAlertAcknowledged('uuid-x', 'ack', 42, 'Op A');

    expect(method_exists($event, 'broadcastAs'))->toBeFalse();
});

it('implements ShouldBroadcast and ShouldDispatchAfterCommit', function () {
    $event = new FrasAlertAcknowledged('uuid-x', 'ack', 42, 'Op A');

    expect($event)->toBeInstanceOf(ShouldBroadcast::class);
    expect($event)->toBeInstanceOf(ShouldDispatchAfterCommit::class);
});

it('broadcastWith returns the 7-key payload (ack default)', function () {
    $event = new FrasAlertAcknowledged('ev-1', 'ack', 42, 'Op A');

    $payload = $event->broadcastWith();

    expect($payload)->toHaveKeys([
        'event_id',
        'action',
        'actor_user_id',
        'actor_name',
        'reason',
        'reason_note',
        'acted_at',
    ]);

    expect($payload['event_id'])->toBe('ev-1');
    expect($payload['action'])->toBe('ack');
    expect($payload['actor_user_id'])->toBe(42);
    expect($payload['actor_name'])->toBe('Op A');
    expect($payload['reason'])->toBeNull();
    expect($payload['reason_note'])->toBeNull();
    expect($payload['acted_at'])->toBeString(); // now()->toIso8601String() default
});

it('broadcastWith returns the 7-key payload (dismiss with reason + note + explicit actedAt)', function () {
    $event = new FrasAlertAcknowledged(
        'ev-2',
        'dismiss',
        42,
        'Op A',
        'other',
        'test note',
        '2026-04-22T00:00:00Z',
    );

    $payload = $event->broadcastWith();

    expect($payload['event_id'])->toBe('ev-2');
    expect($payload['action'])->toBe('dismiss');
    expect($payload['reason'])->toBe('other');
    expect($payload['reason_note'])->toBe('test note');
    expect($payload['acted_at'])->toBe('2026-04-22T00:00:00Z');
});

it('is dispatchable via the Dispatchable trait', function () {
    Event::fake([FrasAlertAcknowledged::class]);

    FrasAlertAcknowledged::dispatch('uuid-x', 'ack', 42, 'Op A');

    Event::assertDispatched(
        FrasAlertAcknowledged::class,
        fn ($e) => $e->eventId === 'uuid-x'
            && $e->action === 'ack'
            && $e->actorUserId === 42
            && $e->actorName === 'Op A',
    );
});
