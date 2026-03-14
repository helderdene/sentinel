<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Models\User;
use App\Notifications\AckTimeoutNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class CheckAckTimeout implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $incidentId,
        public string $unitId,
        public int $userId,
    ) {}

    /**
     * Check if the assignment is still unacknowledged and send a push notification.
     */
    public function handle(): void
    {
        $stillUnacknowledged = DB::table('incident_unit')
            ->where('incident_id', $this->incidentId)
            ->where('unit_id', $this->unitId)
            ->whereNull('acknowledged_at')
            ->exists();

        if (! $stillUnacknowledged) {
            return;
        }

        $user = User::find($this->userId);
        $incident = Incident::find($this->incidentId);

        if (! $user || ! $incident) {
            return;
        }

        $user->notify(new AckTimeoutNotification($incident, $this->unitId));
    }
}
