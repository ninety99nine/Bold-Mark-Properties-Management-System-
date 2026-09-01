<?php

namespace Database\Seeders;

use App\Enums\CollectionStatus;
use App\Models\Organization;
use App\Models\Unit;
use Illuminate\Database\Seeder;

/**
 * Sprinkle a realistic WeConnectU-style spread of collection statuses, debit-order
 * flags and active-transfer flags across the demo units — so the Age Analysis
 * customer column shows the full range of WeConnectU status markers (1st / 2nd /
 * letter-of-demand dots, the letter-of-demand-sent document, payment-arrangement
 * + handed-over flags, the debit-order clock and the transfer arrows). No
 * reminder/paid markers — WeConnectU has neither.
 *
 * Non-destructive: only touches collection_status / debit_order / transfer_active
 * and is deterministic per unit number, so re-running is idempotent. Runs on its
 * own after DemoSeeder, and can also be run standalone to backfill without a full
 * reseed.
 */
class DemoCollectionStatusSeeder extends Seeder
{
    /**
     * Status spread applied per unit (indexed by unit-number % 9). Keeps a chunk
     * of customers with no marker and covers every collection status so the Age
     * Analysis column shows the full WeConnectU range regardless of demo balances.
     */
    private const SPREAD = [
        0 => CollectionStatus::NONE,
        1 => CollectionStatus::NONE,
        2 => CollectionStatus::FIRST_NOTICE,
        3 => CollectionStatus::SECOND_NOTICE,
        4 => CollectionStatus::FINAL_NOTICE,
        5 => CollectionStatus::LETTER_OF_DEMAND,
        6 => CollectionStatus::PAYMENT_ARRANGEMENT,
        7 => CollectionStatus::HANDED_OVER,
        8 => CollectionStatus::NONE,
    ];

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

        Unit::where('organization_id', $organizationId)->each(function (Unit $unit) {
            $n = (int) preg_replace('/[^0-9]/', '', (string) $unit->unit_number);

            $unit->collection_status = self::SPREAD[$n % 9]->value;
            $unit->debit_order       = ($n % 5 === 0);
            $unit->transfer_active   = ($n % 13 === 0);
            $unit->save();
        });
    }
}
