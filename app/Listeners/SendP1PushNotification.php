<?php

namespace App\Listeners;

use App\Enums\IncidentPriority;
use App\Enums\UserRole;
use App\Events\IncidentCreated;
use App\Models\User;
use App\Notifications\P1IncidentNotification;
use Illuminate\Support\Facades\Notification;

class SendP1PushNotification
{
    /**
     * Handle the IncidentCreated event for P1 priority incidents.
     */
    public function handle(IncidentCreated $event): void
    {
        if ($event->incident->priority !== IncidentPriority::P1) {
            return;
        }

        $users = User::query()
            ->whereIn('role', [UserRole::Dispatcher, UserRole::Operator, UserRole::Supervisor])
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new P1IncidentNotification($event->incident));
    }
}
