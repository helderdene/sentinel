<?php

namespace App\Events;

use App\Models\CameraEnrollment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class EnrollmentProgressed implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public CameraEnrollment $enrollment) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('fras.enrollments')];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'personnel_id' => $this->enrollment->personnel_id,
            'camera_id' => $this->enrollment->camera_id,
            'camera_id_display' => $this->enrollment->camera?->camera_id_display,
            'status' => $this->enrollment->status->value,
            'last_error' => $this->enrollment->last_error,
        ];
    }
}
