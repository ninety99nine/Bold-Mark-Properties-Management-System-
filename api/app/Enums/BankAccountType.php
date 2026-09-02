<?php

namespace App\Enums;

enum BankAccountType: string
{
    case CURRENT    = 'current';
    case INVESTMENT = 'investment';

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
