<?php

namespace App\Enums;

enum OwnerEntityType: string
{
    case INDIVIDUAL        = 'individual';
    case COMPANY           = 'company';
    case TRUST             = 'trust';
    case CLOSE_CORPORATION = 'close_corporation';
    case OTHER             = 'other';

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
