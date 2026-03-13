<?php

namespace App\Events;

use App\Models\Incident;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IncidentCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Incident $incident) {}

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
        $coordinates = $this->incident->coordinates;

        return [
            'id' => $this->incident->id,
            'incident_no' => $this->incident->incident_no,
            'priority' => $this->incident->priority->value,
            'status' => $this->incident->status->value,
            'incident_type_id' => $this->incident->incident_type_id,
            'incident_type' => $this->incident->incidentType?->name,
            'location_text' => $this->incident->location_text,
            'barangay' => $this->incident->barangay?->name,
            'channel' => $this->incident->channel->value,
            'coordinates' => $coordinates ? [
                'lat' => $coordinates->getLatitude(),
                'lng' => $coordinates->getLongitude(),
            ] : null,
            'caller_name' => $this->incident->caller_name,
            'caller_contact' => $this->incident->caller_contact,
            'notes' => $this->incident->notes,
            'created_at' => $this->incident->created_at->toISOString(),
        ];
    }
}
