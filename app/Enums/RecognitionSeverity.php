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

    /**
     * Classify severity from MQTT RecPush payload fields.
     *
     * Ported from FRAS AlertSeverity::fromEvent() (Wave 0 gap A1). IRMS lacks
     * an `Ignored` case — the Phase 18 recognition_events CHECK constraint only
     * accepts info/warning/critical — so FRAS's Ignored branch collapses into
     * Info. Collapsed events stay in recognition_events history but never
     * broadcast to browsers (Phase 22 DPA).
     *
     * Priority order:
     * 1. personType === 1 (block-list match) -> Critical
     * 2. verifyStatus === 2 (refused) -> Warning
     * 3. everything else (allow-list hit, stranger, unknown) -> Info
     */
    public static function fromEvent(int $personType, int $verifyStatus): self
    {
        if ($personType === 1) {
            return self::Critical;
        }

        if ($verifyStatus === 2) {
            return self::Warning;
        }

        return self::Info;
    }
}
