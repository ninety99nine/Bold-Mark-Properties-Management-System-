<?php

namespace App\Enums;

/**
 * Whether a journal line is a debit or a credit. A balanced batch has
 * total debits === total credits.
 */
enum JournalEntryType: string
{
    case DEBIT  = 'debit';
    case CREDIT = 'credit';

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
