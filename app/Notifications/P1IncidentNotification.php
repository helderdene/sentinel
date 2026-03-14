<?php

namespace App\Notifications;

use App\Models\Incident;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class P1IncidentNotification extends Notification
{
    public function __construct(public Incident $incident) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        $typeName = $this->incident->incidentType?->name ?? 'Unknown';
        $location = $this->incident->location_text ?? 'Unknown location';

        return (new WebPushMessage)
            ->title('P1 CRITICAL')
            ->body("{$this->incident->incident_no} - {$typeName} at {$location}")
            ->icon('/pwa-192x192.png')
            ->tag("p1-{$this->incident->id}")
            ->data([
                'url' => '/dispatch',
                'incident_id' => $this->incident->id,
            ])
            ->options(['TTL' => 300, 'urgency' => 'high']);
    }
}
