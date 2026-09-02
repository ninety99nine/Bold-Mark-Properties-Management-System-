<?php

namespace Database\Seeders;

use App\Enums\CollectionStatus;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Unit;
use Illuminate\Database\Seeder;

/**
 * Assign each demo unit a WeConnectU-style collection status that MATCHES its
 * real arrears — so the Age Analysis status markers (1st / 2nd / final-notice
 * dots, letter-of-demand, handed-over, payment-arrangement) always line up with
 * an actual outstanding balance. Units that are paid up carry no marker. A
 * deterministic slice of paying members gets the debit-order clock and a couple
 * of debtors an active transfer, purely for visual variety.
 *
 * Status is derived from the number of overdue invoices on the unit, which grows
 * with how long the customer has been in arrears:
 *
 *   0 overdue  → none                4 overdue  → letter of demand
 *   1 overdue  → 1st notice          5+ overdue → handed over
 *   2 overdue  → 2nd notice          (a slice of mid-arrears → payment arrangement)
 *   3 overdue  → final notice
 *
 * Non-destructive: only touches collection_status / debit_order / transfer_active
 * and is deterministic per unit, so re-running is idempotent. Runs on its own
 * after DemoSeeder, and can also be run standalone to backfill.
 */
class DemoCollectionStatusSeeder extends Seeder
{
    /**
     * Run the seeder.
     *
     * @return void
     */
    public function run(): void
    {
        $organizationId = Organization::where('slug', 'boldmark')->value('id');

        if (!$organizationId) {
            return;
        }

        // Overdue-invoice count per unit → drives the arrears depth.
        $overdueByUnit = Invoice::where('organization_id', $organizationId)
            ->where('status', 'overdue')
            ->selectRaw('unit_id, COUNT(*) AS c')
            ->groupBy('unit_id')
            ->pluck('c', 'unit_id');

        Unit::where('organization_id', $organizationId)->each(function (Unit $unit) use ($overdueByUnit) {
            $n       = (int) preg_replace('/[^0-9]/', '', (string) $unit->unit_number);
            $overdue = (int) ($overdueByUnit[$unit->id] ?? 0);

            $status = match (true) {
                $overdue <= 0 => CollectionStatus::NONE,
                // A believable slice of mid-arrears debtors have arranged to pay.
                $overdue >= 2 && $overdue <= 3 && $n % 4 === 0 => CollectionStatus::PAYMENT_ARRANGEMENT,
                $overdue === 1 => CollectionStatus::FIRST_NOTICE,
                $overdue === 2 => CollectionStatus::SECOND_NOTICE,
                $overdue === 3 => CollectionStatus::FINAL_NOTICE,
                $overdue === 4 => CollectionStatus::LETTER_OF_DEMAND,
                default        => CollectionStatus::HANDED_OVER,
            };

            $unit->collection_status = $status->value;
            // Debit order: paid-up members only (an on-order member is never in arrears).
            $unit->debit_order     = $overdue === 0 && ($n % 5 === 0);
            // A couple of deep-arrears units are mid-transfer (sale in progress).
            $unit->transfer_active = $overdue >= 4 && ($n % 2 === 0);
            $unit->save();
        });
    }
}
