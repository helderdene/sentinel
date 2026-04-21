<?php

namespace App\Enums;

enum CameraEnrollmentStatus: string
{
    case Pending = 'pending';
    case Syncing = 'syncing';
    case Done = 'done';
    case Failed = 'failed';

    /**
     * Get the human-readable label for this enrollment status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Syncing => 'Syncing',
            self::Done => 'Done',
            self::Failed => 'Failed',
        };
    }
}
