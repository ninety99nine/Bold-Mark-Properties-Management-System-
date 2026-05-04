<?php

namespace App\Enums;

enum ComplianceItemPriority: string
{
    case LOW      = 'low';
    case MEDIUM   = 'medium';
    case HIGH     = 'high';
    case CRITICAL = 'critical';

    /**
     * Return all enum values as a plain array (used in migrations).
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
