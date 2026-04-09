<?php

namespace App\Enums;

enum OccupancyType: string
{
    case OWNER_OCCUPIED  = 'owner_occupied';
    case TENANT_OCCUPIED = 'tenant_occupied';
    case VACANT          = 'vacant';

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
