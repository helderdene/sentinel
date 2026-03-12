<?php

namespace App\Enums;

enum IncidentPriority: string
{
    case P1 = 'P1';
    case P2 = 'P2';
    case P3 = 'P3';
    case P4 = 'P4';

    /**
     * Get the human-readable label for this priority.
     */
    public function label(): string
    {
        return match ($this) {
            self::P1 => 'Critical / Life-Threatening',
            self::P2 => 'Urgent / Serious',
            self::P3 => 'Standard / Non-Urgent',
            self::P4 => 'Low / Informational',
        };
    }

    /**
     * Get the display color for this priority.
     */
    public function color(): string
    {
        return match ($this) {
            self::P1 => 'red',
            self::P2 => 'orange',
            self::P3 => 'amber',
            self::P4 => 'green',
        };
    }
}
