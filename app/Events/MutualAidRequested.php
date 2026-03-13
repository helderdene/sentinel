<?php

namespace App\Events;

use App\Models\Agency;
use App\Models\Incident;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MutualAidRequested implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Incident $incident,
        public Agency $agency,
        public ?string $notes,
        public string $requestedBy,
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
            'agency' => [
                'name' => $this->agency->name,
                'code' => $this->agency->code,
            ],
            'notes' => $this->notes,
            'requested_by' => $this->requestedBy,
            'timestamp' => now()->toISOString(),
        ];
    }
}
