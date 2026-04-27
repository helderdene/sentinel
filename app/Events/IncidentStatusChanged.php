<?php

namespace App\Events;

use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\IncidentOutcome;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IncidentStatusChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Incident $incident,
        public IncidentStatus $oldStatus,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('dispatch.incidents'),
            new PrivateChannel('incident.'.$this->incident->id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $outcomeCode = $this->incident->outcome;
        $outcomeLabel = $outcomeCode
            ? (IncidentOutcome::query()->where('code', $outcomeCode)->value('label') ?? $outcomeCode)
            : null;

        return [
            'id' => $this->incident->id,
            'incident_no' => $this->incident->incident_no,
            'old_status' => $this->oldStatus->value,
            'new_status' => $this->incident->status->value,
            'priority' => $this->incident->priority->value,
            'acknowledged_at' => $this->incident->acknowledged_at?->toIso8601String(),
            'en_route_at' => $this->incident->en_route_at?->toIso8601String(),
            'on_scene_at' => $this->incident->on_scene_at?->toIso8601String(),
            'resolving_at' => $this->incident->resolving_at?->toIso8601String(),
            'resolved_at' => $this->incident->resolved_at?->toIso8601String(),
            'outcome' => $outcomeCode,
            'outcome_label' => $outcomeLabel,
            'hospital' => $this->incident->hospital,
            'checklist_pct' => (int) ($this->incident->checklist_pct ?? 0),
            'checklist_data' => $this->incident->checklist_data,
        ];
    }
}
