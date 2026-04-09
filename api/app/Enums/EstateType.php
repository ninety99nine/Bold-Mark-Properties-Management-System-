<?php

namespace App\Enums;

enum EstateType: string
{
    case SECTIONAL_TITLE     = 'sectional_title';
    case RESIDENTIAL_RENTAL  = 'residential_rental';
    case COMMERCIAL_RENTAL   = 'commercial_rental';
    case MIXED               = 'mixed';

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
