<?php

namespace App\Enums;

enum RiskSeverity: string
{
    case WARNING  = 'warning';
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
