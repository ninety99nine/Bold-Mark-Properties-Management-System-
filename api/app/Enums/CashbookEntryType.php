<?php

namespace App\Enums;

enum CashbookEntryType: string
{
    case CREDIT = 'credit';
    case DEBIT  = 'debit';

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
