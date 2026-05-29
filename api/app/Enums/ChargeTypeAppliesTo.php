<?php

namespace App\Enums;

enum ChargeTypeAppliesTo: string
{
    case OWNER  = 'owner';
    case TENANT = 'tenant';
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
