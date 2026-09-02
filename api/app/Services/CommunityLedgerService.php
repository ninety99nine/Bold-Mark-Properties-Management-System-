<?php

namespace App\Services;

use App\Enums\FinancialCategory;
use App\Models\Ledger;
use App\Models\Community;
use Database\Seeders\ChartOfAccountsSeeder;

class CommunityLedgerService
{
    /**
     * Enable the standard WeConnectU income ledgers for a community so the
     * billing engine can resolve them.
     *
     * The chart of accounts is seeded per-organisation by ChartOfAccountsSeeder;
     * every community shares that chart. We simply mark the billable income
     * accounts (main-fund income + the reserve-fund levy — i.e. every SALES
     * sub-account) active on the community_ledgers pivot.
     *
     * @param Community $community
     * @return void
     */
    public function setupDefaultLedgers(Community $community): void
    {
        // Ensure the organisation's chart exists (idempotent).
        $hasLedgers = Ledger::where('organization_id', $community->organization_id)->exists();
        if (!$hasLedgers) {
            (new ChartOfAccountsSeeder())->seedForOrganization($community->organization_id);
        }

        $ledgerIds = Ledger::where('organization_id', $community->organization_id)
            ->where('is_active', true)
            ->whereNotNull('parent_id')
            ->where('financial_category', FinancialCategory::SALES)
            ->pluck('id');

        // Sync with pivot data — sets is_active = true on the junction.
        $community->ledgers()->syncWithPivotValues(
            $ledgerIds->toArray(),
            ['is_active' => true]
        );
    }

    /**
     * Enable a specific ledger for a community.
     *
     * @param Community $community
     * @param Ledger    $ledger
     * @return void
     */
    public function enableLedger(Community $community, Ledger $ledger): void
    {
        $community->ledgers()->syncWithoutDetaching([
            $ledger->id => ['is_active' => true],
        ]);
    }

    /**
     * Disable a specific ledger for a community.
     *
     * @param Community $community
     * @param Ledger    $ledger
     * @return void
     */
    public function disableLedger(Community $community, Ledger $ledger): void
    {
        $community->ledgers()->updateExistingPivot($ledger->id, ['is_active' => false]);
    }
}
