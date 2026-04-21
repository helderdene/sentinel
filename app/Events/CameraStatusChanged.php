<?php

namespace App\Events;

use App\Models\Camera;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CameraStatusChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Camera $camera) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('fras.cameras')];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'camera_id' => $this->camera->id,
            'camera_id_display' => $this->camera->camera_id_display,
            'status' => $this->camera->status->value,
            'last_seen_at' => $this->camera->last_seen_at?->toIso8601String(),
            'location' => $this->camera->location
                ? [
                    'lat' => $this->camera->location->getLatitude(),
                    'lng' => $this->camera->location->getLongitude(),
                ]
                : null,
        ];
    }
}
