<?php

namespace App\Events;

use App\Models\Incident;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AssignmentPushed implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Incident $incident,
        public string $unitId,
        public int $userId,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->userId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->incident->id,
            'incident_no' => $this->incident->incident_no,
            'priority' => $this->incident->priority->value,
            'status' => $this->incident->status->value,
            'incident_type' => $this->incident->incidentType?->name,
            'location_text' => $this->incident->location_text,
            'barangay' => $this->incident->barangay?->name,
            'coordinates' => $this->incident->coordinates ? [
                'latitude' => $this->incident->coordinates->getLatitude(),
                'longitude' => $this->incident->coordinates->getLongitude(),
            ] : null,
            'notes' => $this->incident->notes,
            'unit_id' => $this->unitId,
        ];
    }
}
