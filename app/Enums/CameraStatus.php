<?php

namespace App\Enums;

enum CameraStatus: string
{
    case Online = 'online';
    case Offline = 'offline';
    case Degraded = 'degraded';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::Offline => 'Offline',
            self::Degraded => 'Degraded',
        };
    }
}
