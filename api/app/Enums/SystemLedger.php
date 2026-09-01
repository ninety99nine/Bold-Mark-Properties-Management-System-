<?php

namespace App\Enums;

enum SystemLedger: string
{
    case ADMIN_LEVY   = 'admin_levy';
    case RESERVE_LEVY = 'reserve_levy';
    case CSOS_LEVY    = 'csos_levy';
    case RENT         = 'rent';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
