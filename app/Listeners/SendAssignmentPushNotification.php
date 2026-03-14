<?php

namespace App\Listeners;

use App\Events\AssignmentPushed;
use App\Jobs\CheckAckTimeout;
use App\Models\User;
use App\Notifications\AssignmentPushedNotification;

class SendAssignmentPushNotification
{
    /**
     * Handle the AssignmentPushed event.
     */
    public function handle(AssignmentPushed $event): void
    {
        $user = User::find($event->userId);

        if (! $user) {
            return;
        }

        $user->notify(new AssignmentPushedNotification($event->incident, $event->unitId));

        CheckAckTimeout::dispatch($event->incident->id, $event->unitId, $event->userId)
            ->delay(now()->addSeconds(90));
    }
}
