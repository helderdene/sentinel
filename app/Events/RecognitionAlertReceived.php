<?php

namespace App\Events;

use App\Models\Incident;
use App\Models\RecognitionEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class RecognitionAlertReceived implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public RecognitionEvent $event,
        public ?Incident $incident = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('fras.alerts')];
    }

    /**
     * Full denorm payload (D-12) so dispatch map, IntakeStation rail, and
     * Phase 22 /fras/alerts all render without follow-up HTTP calls.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $camera = $this->event->camera;
        $personnel = $this->event->personnel;
        $coords = $camera?->location;

        return [
            'event_id' => $this->event->id,
            'camera_id' => $this->event->camera_id,
            'camera_id_display' => $camera?->camera_id_display,
            'camera_location' => $coords
                ? [$coords->getLongitude(), $coords->getLatitude()]
                : null,
            'severity' => $this->event->severity->value,
            'personnel_id' => $this->event->personnel_id,
            'personnel_name' => $personnel?->name,
            'personnel_category' => $personnel?->category?->value,
            'confidence' => $this->event->similarity,
            'captured_at' => $this->event->captured_at->toIso8601String(),
            'incident_id' => $this->incident?->id,
        ];
    }
}
