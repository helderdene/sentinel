<?php

namespace App\Notifications;

use App\Models\Incident;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class AssignmentPushedNotification extends Notification
{
    public function __construct(
        public Incident $incident,
        public string $unitId,
    ) {}

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

        return (new WebPushMessage)
            ->title('New Assignment')
            ->body("{$this->incident->incident_no} - {$typeName}")
            ->icon('/pwa-192x192.png')
            ->badge('/pwa-192x192.png')
            ->tag("assignment-{$this->incident->id}")
            ->data([
                'url' => '/assignment',
                'incident_id' => $this->incident->id,
            ])
            ->options(['TTL' => 300, 'urgency' => 'high']);
    }
}
