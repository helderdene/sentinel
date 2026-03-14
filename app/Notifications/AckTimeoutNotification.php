<?php

namespace App\Notifications;

use App\Models\Incident;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class AckTimeoutNotification extends Notification
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
        return (new WebPushMessage)
            ->title('Assignment Not Acknowledged')
            ->body("{$this->incident->incident_no} requires acknowledgement")
            ->icon('/pwa-192x192.png')
            ->tag("ack-timeout-{$this->incident->id}")
            ->data([
                'url' => '/assignment',
                'incident_id' => $this->incident->id,
            ])
            ->options(['TTL' => 120, 'urgency' => 'high']);
    }
}
