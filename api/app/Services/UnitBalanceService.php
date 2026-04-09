<?php

namespace App\Services;

use App\Models\CashbookEntry;
use App\Models\Invoice;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

class UnitBalanceService
{
    /**
     * Recompute and persist the stored balance for a single unit.
     *
     * Formula: balance = unallocated_credits − outstanding_amount
     *
     *   outstanding_amount  = sum of (invoice.amount − payments already allocated to it)
     *                         for all invoices in unpaid / overdue / partially_paid status.
     *                         GREATEST(0, …) prevents a rounding edge case where allocated
     *                         payments exceed the invoice amount producing a negative term.
     *
     *   unallocated_credits = sum of cashbook credit entries that have no invoice_id,
     *                         i.e. advance payments / overpayment remainders on account.
     *
     * Result semantics:
     *   balance < 0  → unit is in arrears
     *   balance = 0  → clear
     *   balance > 0  → credit on account
     *
     * This method is called from InvoiceService and CashbookEntryService after every
     * write operation that can move money into or out of a unit's ledger position.
     *
     * @param Unit $unit
     * @return void
     */
    public function recalculate(Unit $unit): void
    {
        $unitId = $unit->id;

        // Outstanding: net amount still owed on open invoices.
        $outstandingRows = Invoice::where('unit_id', $unitId)
            ->whereIn('status', ['unpaid', 'overdue', 'partially_paid'])
            ->get(['id', 'amount']);

        $outstandingAmount = 0.0;
        foreach ($outstandingRows as $invoice) {
            $paid             = (float) CashbookEntry::where('invoice_id', $invoice->id)->sum('amount');
            $outstandingAmount += max(0, (float) $invoice->amount - $paid);
        }

        // Unallocated credits: money received but not yet matched to an invoice.
        $unallocatedCredits = (float) CashbookEntry::where('unit_id', $unitId)
            ->whereNull('invoice_id')
            ->where('type', 'credit')
            ->sum('amount');

        $balance = $unallocatedCredits - $outstandingAmount;

        DB::table('units')
            ->where('id', $unitId)
            ->update(['balance' => $balance]);
    }

    /**
     * Bulk recalculate balances for every unit in the system (or a scoped subset).
     *
     * Uses a single raw SQL UPDATE per unit to minimise round-trips. Called by the
     * units:recalculate-balances Artisan command to backfill after the migration.
     *
     * @param string|null $tenantId  When provided, only units for that tenant are updated.
     * @return int  Number of units updated.
     */
    public function recalculateAll(?string $tenantId = null): int
    {
        $query = Unit::query();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $count = 0;

        $query->select('id')->lazyById(200)->each(function (Unit $unit) use (&$count) {
            $this->recalculate($unit);
            $count++;
        });

        return $count;
    }
}
