<?php

namespace App\Services;

use App\Enums\LedgerAppliesTo;
use App\Enums\CommunityEntityType;
use App\Enums\SystemLedger;
use App\Models\Ledger;
use App\Models\Community;

class CommunityLedgerService
{
    /**
     * Non-system preset names that apply to each billing basis (derived from the
     * community's entity type). System ledgers (admin_levy, reserve_levy,
     * csos_levy, rent) are matched separately by 'type'.
     */
    protected array $presetNamesByBasis = [
        'levy' => [
            'Special Levy',
            'Water Recovery', 'Electricity Recovery', 'Gas Recovery',
            'Sewerage Recovery', 'Refuse Recovery',
            'Late Payment Interest', 'Late Payment Penalty',
            'Insurance Excess', 'Parking Rental', 'Storage Rental',
            'Access Card Fee', 'Gym Access', 'Pool Access',
            'Garden Maintenance', 'Pet Levy', 'Security Contribution', 'Legal Recovery',
        ],
        'rent' => [
            'Key Deposit', 'Damage Deposit',
            'Moving-In Fee', 'Moving-Out Fee',
            'Late Payment Interest', 'Late Payment Penalty',
            'Parking Rental', 'Storage Rental', 'Pet Levy', 'Legal Recovery',
        ],
        'mixed' => [],
    ];

    /**
     * System ledger values that apply to each billing basis.
     */
    protected array $systemLedgersByBasis = [
        'levy'  => [SystemLedger::ADMIN_LEVY->value, SystemLedger::RESERVE_LEVY->value, SystemLedger::CSOS_LEVY->value],
        'rent'  => [SystemLedger::RENT->value],
        'mixed' => [],
    ];

    /**
     * Auto-configure ledgers for a newly created community based on its type.
     * Syncs all applicable occupant ledgers to the community, marking them active.
     *
     * @param Community $community
     * @return void
     */
    public function setupDefaultLedgers(Community $community): void
    {
        // Seed ledgers for this org if they've never been set up
        $hasLedgers = Ledger::where('organization_id', $community->organization_id)->exists();
        if (!$hasLedgers) {
            self::seedDefaultsForOrganization($community->organization_id);
        }

        $basis = $community->entity_type instanceof CommunityEntityType
            ? $community->entity_type->billingBasis()
            : 'levy';

        // For mixed communities, enable ALL active ledgers for the occupant
        if ($basis === 'mixed') {
            $ledgerIds = Ledger::where('organization_id', $community->organization_id)
                ->where('is_active', true)
                ->pluck('id');
        } else {
            $systemTypes = $this->systemLedgersByBasis[$basis] ?? [];
            $presetNames = $this->presetNamesByBasis[$basis] ?? [];

            $ledgerIds = Ledger::where('organization_id', $community->organization_id)
                ->where('is_active', true)
                ->where(function ($q) use ($systemTypes, $presetNames) {
                    $q->whereIn('type', $systemTypes)
                      ->orWhereIn('name', $presetNames);
                })
                ->pluck('id');
        }

        // Sync with pivot data — sets is_active = true on the junction
        $community->ledgers()->syncWithPivotValues(
            $ledgerIds->toArray(),
            ['is_active' => true]
        );
    }

    /**
     * Enable a specific ledger for an community.
     *
     * @param Community     $community
     * @param Ledger $ledger
     * @return void
     */
    public function enableLedger(Community $community, Ledger $ledger): void
    {
        $community->ledgers()->syncWithoutDetaching([
            $ledger->id => ['is_active' => true],
        ]);
    }

    /**
     * Disable a specific ledger for an community.
     *
     * @param Community     $community
     * @param Ledger $ledger
     * @return void
     */
    public function disableLedger(Community $community, Ledger $ledger): void
    {
        $community->ledgers()->updateExistingPivot($ledger->id, ['is_active' => false]);
    }

    /**
     * Seed the full default ledger catalogue for an organisation.
     * System types are matched by (organization_id, type); non-system by (organization_id, name).
     * Safe to call multiple times (idempotent via updateOrCreate).
     */
    public static function seedDefaultsForOrganization(string $organizationId): void
    {
        foreach (self::ledgerDefaults() as $ct) {
            if (!empty($ct['type'])) {
                $match  = ['organization_id' => $organizationId, 'type' => $ct['type']];
            } else {
                $match  = ['organization_id' => $organizationId, 'name' => $ct['name']];
            }
            Ledger::updateOrCreate($match, array_merge($ct, ['organization_id' => $organizationId]));
        }
    }

    private static function ledgerDefaults(): array
    {
        return [
            ['type' => SystemLedger::ADMIN_LEVY->value,   'name' => 'Admin Levy',            'description' => 'Monthly body corporate admin fund levy (day-to-day operations)',          'is_system' => true,  'is_active' => true,  'is_recurring' => true,  'applies_to' => LedgerAppliesTo::OWNER,   'sort_order' => 1],
            ['type' => SystemLedger::RESERVE_LEVY->value, 'name' => 'Reserve Levy',          'description' => 'Monthly body corporate reserve fund levy (capital expenditure)',           'is_system' => true,  'is_active' => true,  'is_recurring' => true,  'applies_to' => LedgerAppliesTo::OWNER,   'sort_order' => 2],
            ['type' => SystemLedger::CSOS_LEVY->value,    'name' => 'CSOS Levy',             'description' => 'Community Schemes Ombud Service government levy (flat per-unit, SA only)', 'is_system' => true,  'is_active' => true,  'is_recurring' => true,  'applies_to' => LedgerAppliesTo::OWNER,   'sort_order' => 3],
            ['type' => SystemLedger::RENT->value,         'name' => 'Rent',                  'description' => 'Regular monthly rental payment',                                           'is_system' => true,  'is_active' => true,  'is_recurring' => true,  'applies_to' => LedgerAppliesTo::OCCUPANT,  'sort_order' => 4],
            ['type' => null, 'name' => 'Special Levy',          'description' => 'Once-off body corporate charge approved at AGM or special meeting',  'is_system' => false, 'is_active' => true,  'is_recurring' => false, 'applies_to' => LedgerAppliesTo::OWNER,   'sort_order' => 5],
            ['type' => null, 'name' => 'Water Recovery',        'description' => 'Metered water billed per unit',                                       'is_system' => false, 'is_active' => true,  'is_recurring' => false, 'applies_to' => LedgerAppliesTo::EITHER,  'sort_order' => 6],
            ['type' => null, 'name' => 'Electricity Recovery',  'description' => 'Metered electricity billed per unit',                                 'is_system' => false, 'is_active' => true,  'is_recurring' => false, 'applies_to' => LedgerAppliesTo::EITHER,  'sort_order' => 7],
            ['type' => null, 'name' => 'Gas Recovery',          'description' => 'Metered gas billed per unit',                                         'is_system' => false, 'is_active' => true,  'is_recurring' => false, 'applies_to' => LedgerAppliesTo::EITHER,  'sort_order' => 8],
            ['type' => null, 'name' => 'Sewerage Recovery',     'description' => 'Sewerage charges billed per unit',                                    'is_system' => false, 'is_active' => true,  'is_recurring' => true,  'applies_to' => LedgerAppliesTo::EITHER,  'sort_order' => 9],
            ['type' => null, 'name' => 'Refuse Recovery',       'description' => 'Refuse/waste collection billed per unit',                             'is_system' => false, 'is_active' => true,  'is_recurring' => true,  'applies_to' => LedgerAppliesTo::EITHER,  'sort_order' => 10],
            ['type' => null, 'name' => 'Late Payment Interest',  'description' => 'Interest charged on overdue balances',                               'is_system' => false, 'is_active' => true,  'is_recurring' => false, 'applies_to' => LedgerAppliesTo::EITHER,  'sort_order' => 11],
            ['type' => null, 'name' => 'Late Payment Penalty',   'description' => 'Flat penalty fee for late payment',                                  'is_system' => false, 'is_active' => true,  'is_recurring' => false, 'applies_to' => LedgerAppliesTo::EITHER,  'sort_order' => 12],
            ['type' => null, 'name' => 'Insurance Excess',       'description' => 'Damage-related excess billed back to a unit',                        'is_system' => false, 'is_active' => true,  'is_recurring' => false, 'applies_to' => LedgerAppliesTo::OWNER,   'sort_order' => 13],
            ['type' => null, 'name' => 'Key Deposit',            'description' => 'Deposit for keys or access devices',                                  'is_system' => false, 'is_active' => true,  'is_recurring' => false, 'applies_to' => LedgerAppliesTo::OCCUPANT,  'sort_order' => 14],
            ['type' => null, 'name' => 'Damage Deposit',         'description' => 'Security/damage deposit held against the unit',                      'is_system' => false, 'is_active' => true,  'is_recurring' => false, 'applies_to' => LedgerAppliesTo::OCCUPANT,  'sort_order' => 15],
            ['type' => null, 'name' => 'Parking Rental',         'description' => 'Monthly parking bay rental',                                         'is_system' => false, 'is_active' => true,  'is_recurring' => true,  'applies_to' => LedgerAppliesTo::EITHER,  'sort_order' => 16],
            ['type' => null, 'name' => 'Storage Rental',         'description' => 'Monthly storage unit rental',                                        'is_system' => false, 'is_active' => true,  'is_recurring' => true,  'applies_to' => LedgerAppliesTo::EITHER,  'sort_order' => 17],
            ['type' => null, 'name' => 'Moving-In Fee',          'description' => 'Once-off fee charged when a occupant moves in',                        'is_system' => false, 'is_active' => true,  'is_recurring' => false, 'applies_to' => LedgerAppliesTo::OCCUPANT,  'sort_order' => 18],
            ['type' => null, 'name' => 'Moving-Out Fee',         'description' => 'Once-off fee charged when a occupant moves out',                       'is_system' => false, 'is_active' => true,  'is_recurring' => false, 'applies_to' => LedgerAppliesTo::OCCUPANT,  'sort_order' => 19],
            ['type' => null, 'name' => 'Access Card Fee',        'description' => 'Once-off or replacement fee for access cards/remotes',               'is_system' => false, 'is_active' => true,  'is_recurring' => false, 'applies_to' => LedgerAppliesTo::EITHER,  'sort_order' => 20],
            ['type' => null, 'name' => 'Gym Access',             'description' => 'Recurring fee for gym or fitness facility',                           'is_system' => false, 'is_active' => true,  'is_recurring' => true,  'applies_to' => LedgerAppliesTo::EITHER,  'sort_order' => 21],
            ['type' => null, 'name' => 'Pool Access',            'description' => 'Recurring fee for pool facility',                                     'is_system' => false, 'is_active' => true,  'is_recurring' => true,  'applies_to' => LedgerAppliesTo::EITHER,  'sort_order' => 22],
            ['type' => null, 'name' => 'Garden Maintenance',     'description' => 'Individual garden maintenance charge for units with private gardens', 'is_system' => false, 'is_active' => true,  'is_recurring' => true,  'applies_to' => LedgerAppliesTo::OWNER,   'sort_order' => 23],
            ['type' => null, 'name' => 'Pet Levy',               'description' => 'Recurring monthly charge for pet-owning residents',                  'is_system' => false, 'is_active' => true,  'is_recurring' => true,  'applies_to' => LedgerAppliesTo::EITHER,  'sort_order' => 24],
            ['type' => null, 'name' => 'Security Contribution',  'description' => 'Additional security charge beyond the standard levy',                'is_system' => false, 'is_active' => true,  'is_recurring' => true,  'applies_to' => LedgerAppliesTo::OWNER,   'sort_order' => 25],
            ['type' => null, 'name' => 'Legal Recovery',         'description' => 'Recovery of legal costs incurred in collections',                    'is_system' => false, 'is_active' => true,  'is_recurring' => false, 'applies_to' => LedgerAppliesTo::EITHER,  'sort_order' => 26],
        ];
    }
}
