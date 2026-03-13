<?php

namespace App\Events;

use App\Enums\ResourceType;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ResourceRequested implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Incident $incident,
        public ResourceType $resourceType,
        public ?string $notes,
        public User $requester,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('dispatch.incidents'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'incident_id' => $this->incident->id,
            'incident_no' => $this->incident->incident_no,
            'resource_type' => $this->resourceType->value,
            'resource_label' => $this->resourceType->label(),
            'notes' => $this->notes,
            'requested_by' => $this->requester->name,
            'timestamp' => now()->toISOString(),
        ];
    }
}
