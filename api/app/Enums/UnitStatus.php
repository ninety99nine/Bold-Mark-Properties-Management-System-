<?php

namespace App\Enums;

enum UnitStatus: string
{
    case ACTIVE    = 'active';
    case SUSPENDED = 'suspended';
    case VACATED   = 'vacated';

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
