<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $incidentId,
        public int $senderId,
        public string $senderName,
        public string $senderRole,
        public ?string $senderUnitCallsign,
        public string $body,
        public bool $isQuickReply,
        public int $messageId,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('incident.'.$this->incidentId.'.messages'),
            new PrivateChannel('dispatch.incidents'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->messageId,
            'incident_id' => $this->incidentId,
            'sender_id' => $this->senderId,
            'sender_name' => $this->senderName,
            'sender_role' => $this->senderRole,
            'sender_unit_callsign' => $this->senderUnitCallsign,
            'body' => $this->body,
            'is_quick_reply' => $this->isQuickReply,
            'sent_at' => now()->toISOString(),
        ];
    }
}
