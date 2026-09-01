<?php

namespace App\Enums;

enum OffenceStatus: string
{
    case WARNING      = 'warning';
    case FIRST_NOTICE = 'first_notice';
    case FINAL_NOTICE = 'final_notice';
    case FINE         = 'fine';
    case RESOLVED     = 'resolved';

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
