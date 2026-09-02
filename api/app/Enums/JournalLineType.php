<?php

namespace App\Enums;

/**
 * The four "Ledger Type" options on a journal line, mirroring WeConnectU.
 *
 *   general      → a chart-of-accounts (General Ledger) account, e.g. "1000/001 - Levies"
 *   customer     → a customer's account (a unit / owner), e.g. "AMM001-U80 - A M Morule"
 *   supplier     → a supplier / creditor account (not yet modelled — selectable but empty)
 *   reserve_fund → a Reserve Fund ledger account, e.g. "RFI/001 - Reserve Fund Levy"
 */
enum JournalLineType: string
{
    case GENERAL      = 'general';
    case CUSTOMER     = 'customer';
    case SUPPLIER     = 'supplier';
    case RESERVE_FUND = 'reserve_fund';

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
