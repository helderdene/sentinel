<?php

namespace App\Enums;

enum IncidentChannel: string
{
    case Phone = 'phone';
    case Sms = 'sms';
    case App = 'app';
    case IoT = 'iot';
    case Radio = 'radio';

    /**
     * Get the human-readable label for this channel.
     */
    public function label(): string
    {
        return match ($this) {
            self::Phone => 'Phone',
            self::Sms => 'SMS',
            self::App => 'App (Walk-in/Web)',
            self::IoT => 'IoT Sensor',
            self::Radio => 'Radio',
        };
    }

    /**
     * Get the Lucide icon name for this channel.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Phone => 'Phone',
            self::Sms => 'MessageSquare',
            self::App => 'Globe',
            self::IoT => 'Cpu',
            self::Radio => 'Radio',
        };
    }
}
