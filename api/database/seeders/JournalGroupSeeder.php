<?php

namespace Database\Seeders;

use App\Models\Community;
use App\Models\JournalGroup;
use Illuminate\Database\Seeder;

/**
 * Seeds the WeConnectU-style default journal groups for every community.
 *
 * WeConnectU ships each community with a fixed managed list of 11 journal
 * groups used to tag journal batches. Idempotent: keyed on
 * (community_id, name) via updateOrCreate.
 */
class JournalGroupSeeder extends Seeder
{
    /**
     * The default journal groups seeded per community, matching WeConnectU.
     *
     * @var array<string>
     */
    public const DEFAULTS = [
        'Accrual',
        'Audit',
        'Customer Recovery',
        'Insurance',
        'Interest on Arrears',
        'Legal Fees',
        'Levy',
        'Opening Balances',
        'Petty Cash',
        'Transfer',
        'Water and Sewerage',
    ];

    public function run(): void
    {
        Community::query()->each(function (Community $community) {
            $this->seedForCommunity($community);
        });
    }

    /**
     * Seed the default journal groups for a single community.
     *
     * @param Community $community
     * @return void
     */
    public function seedForCommunity(Community $community): void
    {
        foreach (self::DEFAULTS as $name) {
            JournalGroup::updateOrCreate(
                ['community_id' => $community->id, 'name' => $name],
                ['organization_id' => $community->organization_id]
            );
        }
    }
}
