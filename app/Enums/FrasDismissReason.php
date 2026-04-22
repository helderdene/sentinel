<?php

namespace App\Enums;

enum FrasDismissReason: string
{
    case FalseMatch = 'false_match';
    case TestEvent = 'test_event';
    case Duplicate = 'duplicate';
    case Other = 'other';

    /**
     * Get the human-readable label for this dismiss reason
     * (copy per Phase 22 UI-SPEC §DismissReasonModal).
     */
    public function label(): string
    {
        return match ($this) {
            self::FalseMatch => 'False match',
            self::TestEvent => 'Test event',
            self::Duplicate => 'Duplicate alert',
            self::Other => 'Other',
        };
    }
}
