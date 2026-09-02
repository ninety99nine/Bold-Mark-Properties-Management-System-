<?php

namespace App\Enums;

enum LedgerAppliesTo: string
{
    case OWNER  = 'owner';
    case OCCUPANT = 'occupant';
    case EITHER = 'either';

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
