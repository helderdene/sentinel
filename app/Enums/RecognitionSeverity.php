<?php

namespace App\Enums;

enum RecognitionSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';

    /**
     * Get the human-readable label for this severity.
     */
    public function label(): string
    {
        return match ($this) {
            self::Info => 'Info',
            self::Warning => 'Warning',
            self::Critical => 'Critical',
        };
    }

    /**
     * Determine if this severity is critical (blocklist / high-risk match).
     */
    public function isCritical(): bool
    {
        return $this === self::Critical;
    }
}
