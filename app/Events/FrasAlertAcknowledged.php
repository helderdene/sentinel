<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Cross-operator broadcast fired when a FRAS alert is acknowledged or
 * dismissed. Reuses the Phase 21 `fras.alerts` private channel so other
 * operators see the new state without a fresh HTTP fetch. Scalar-only
 * constructor keeps the payload Eloquent-free — the RecognitionEvent row
 * has already been updated and committed by the time this fires.
 */
final class FrasAlertAcknowledged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $eventId,
        public string $action,
        public int $actorUserId,
        public string $actorName,
        public ?string $reason = null,
        public ?string $reasonNote = null,
        public ?string $actedAt = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('fras.alerts')];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->eventId,
            'action' => $this->action,
            'actor_user_id' => $this->actorUserId,
            'actor_name' => $this->actorName,
            'reason' => $this->reason,
            'reason_note' => $this->reasonNote,
            'acted_at' => $this->actedAt ?? now()->toIso8601String(),
        ];
    }
}
