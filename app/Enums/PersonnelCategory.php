<?php

namespace App\Enums;

enum PersonnelCategory: string
{
    case Allow = 'allow';
    case Block = 'block';
    case Missing = 'missing';
    case LostChild = 'lost_child';

    /**
     * Get the human-readable label for this category.
     */
    public function label(): string
    {
        return match ($this) {
            self::Allow => 'Allow',
            self::Block => 'BOLO (Block)',
            self::Missing => 'Missing Person',
            self::LostChild => 'Lost Child',
        };
    }
}
