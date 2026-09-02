<?php

namespace App\Services;

use App\Models\Unit;
use Illuminate\Support\Facades\DB;

class UnitBalanceService
{
    /**
     * Recompute and persist the stored balance for a single unit.
     *
     * The balance is now purely the GL customer-control position (WeConnectU
     * balance-forward): it is Σ(credit − debit) over the unit's Accounts
     * Receivable journal lines. Every customer document — invoices (Dr → −),
     * receipts and credit notes (Cr → +) — posts a balanced GL batch, so the GL
     * is the single source of truth. The document tables are no longer read here.
     *
     * Result semantics:
     *   balance < 0  → unit is in arrears
     *   balance = 0  → clear
     *   balance > 0  → credit on account
     *
     * @param Unit $unit
     * @return void
     */
    public function recalculate(Unit $unit): void
    {
        $balance = (new JournalPostingService())->balanceEffect($unit->id);

        DB::table('units')
            ->where('id', $unit->id)
            ->update(['balance' => $balance]);
    }

    /**
     * Bulk recalculate balances for every unit in the system (or a scoped subset).
     *
     * Uses a single raw SQL UPDATE per unit to minimise round-trips. Called by the
     * units:recalculate-balances Artisan command to backfill after the migration.
     *
     * @param string|null $organizationId  When provided, only units for that occupant are updated.
     * @return int  Number of units updated.
     */
    public function recalculateAll(?string $organizationId = null): int
    {
        $query = Unit::query();

        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        $count = 0;

        $query->select('id')->lazyById(200)->each(function (Unit $unit) use (&$count) {
            $this->recalculate($unit);
            $count++;
        });

        return $count;
    }
}
