<?php

namespace App\Enums;

/**
 * The recurring billing roles the "Run Billing" engine posts automatically.
 *
 * Each role maps to a standard WeConnectU chart-of-accounts code (seeded by
 * ChartOfAccountsSeeder). Billing resolves the target ledger by code — there is
 * no longer a bespoke `type` column on the ledger.
 */
enum SystemLedger: string
{
    case ADMIN_LEVY   = 'admin_levy';
    case RESERVE_LEVY = 'reserve_levy';
    case RENT         = 'rent';

    /**
     * The WeConnectU chart-of-accounts code this billing role posts to.
     */
    public function code(): string
    {
        return match ($this) {
            self::ADMIN_LEVY   => '1000/001', // Levies
            self::RESERVE_LEVY => 'RFI/001',  // Reserve Fund Levy (reserve fund)
            self::RENT         => '1000/005', // Rental Income
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
