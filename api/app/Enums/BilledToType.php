<?php

namespace App\Enums;

enum BilledToType: string
{
    case OWNER  = 'owner';
    case TENANT = 'tenant';

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
