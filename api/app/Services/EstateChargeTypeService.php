<?php

namespace App\Services;

use App\Enums\ChargeTypeAppliesTo;
use App\Enums\EstateType;
use App\Enums\SystemChargeType;
use App\Models\ChargeType;
use App\Models\Estate;

class EstateChargeTypeService
{
    /**
     * Non-system preset names that apply to each estate type.
     * System types (admin_levy, reserve_levy, csos_levy, rent) are matched separately by 'type'.
     */
    protected array $presetNamesByType = [
        EstateType::SECTIONAL_TITLE->value => [
            'Special Levy',
            'Water Recovery', 'Electricity Recovery', 'Gas Recovery',
            'Sewerage Recovery', 'Refuse Recovery',
            'Late Payment Interest', 'Late Payment Penalty',
            'Insurance Excess', 'Parking Rental', 'Storage Rental',
            'Access Card Fee', 'Gym Access', 'Pool Access',
            'Garden Maintenance', 'Pet Levy', 'Security Contribution', 'Legal Recovery',
        ],
        EstateType::RESIDENTIAL_RENTAL->value => [
            'Key Deposit', 'Damage Deposit',
            'Moving-In Fee', 'Moving-Out Fee',
            'Late Payment Interest', 'Late Payment Penalty',
            'Parking Rental', 'Pet Levy', 'Legal Recovery',
        ],
        EstateType::COMMERCIAL_RENTAL->value => [
            'Key Deposit', 'Damage Deposit',
            'Late Payment Interest', 'Late Payment Penalty',
            'Parking Rental', 'Storage Rental', 'Legal Recovery',
        ],
        EstateType::MIXED->value => [],
    ];

    /**
     * System charge type values that apply to each estate type.
     */
    protected array $systemTypesByEstateType = [
        EstateType::SECTIONAL_TITLE->value    => [SystemChargeType::ADMIN_LEVY->value, SystemChargeType::RESERVE_LEVY->value, SystemChargeType::CSOS_LEVY->value],
        EstateType::RESIDENTIAL_RENTAL->value => [SystemChargeType::RENT->value],
        EstateType::COMMERCIAL_RENTAL->value  => [SystemChargeType::RENT->value],
        EstateType::MIXED->value              => [],
    ];

    /**
     * Auto-configure charge types for a newly created estate based on its type.
     * Syncs all applicable tenant charge types to the estate, marking them active.
     *
     * @param Estate $estate
     * @return void
     */
    public function setupDefaultChargeTypes(Estate $estate): void
    {
        // Seed charge types for this org if they've never been set up
        $hasChargeTypes = ChargeType::where('organization_id', $estate->organization_id)->exists();
        if (!$hasChargeTypes) {
            self::seedDefaultsForOrganization($estate->organization_id);
        }

        $estateTypeValue = $estate->type instanceof EstateType
            ? $estate->type->value
            : (string) $estate->type;

        // For mixed estates, enable ALL active charge types for the tenant
        if ($estateTypeValue === EstateType::MIXED->value) {
            $chargeTypeIds = ChargeType::where('organization_id', $estate->organization_id)
                ->where('is_active', true)
                ->pluck('id');
        } else {
            $systemTypes = $this->systemTypesByEstateType[$estateTypeValue] ?? [];
            $presetNames = $this->presetNamesByType[$estateTypeValue] ?? [];

            $chargeTypeIds = ChargeType::where('organization_id', $estate->organization_id)
                ->where('is_active', true)
                ->where(function ($q) use ($systemTypes, $presetNames) {
                    $q->whereIn('type', $systemTypes)
                      ->orWhereIn('name', $presetNames);
                })
                ->pluck('id');
        }

        // Sync with pivot data — sets is_active = true on the junction
        $estate->chargeTypes()->syncWithPivotValues(
            $chargeTypeIds->toArray(),
            ['is_active' => true]
        );
    }

    /**
     * Enable a specific charge type for an estate.
     *
     * @param Estate     $estate
     * @param ChargeType $chargeType
     * @return void
     */
    public function enableChargeType(Estate $estate, ChargeType $chargeType): void
    {
        $estate->chargeTypes()->syncWithoutDetaching([
            $chargeType->id => ['is_active' => true],
        ]);
    }

    /**
     * Disable a specific charge type for an estate.
     *
     * @param Estate     $estate
     * @param ChargeType $chargeType
     * @return void
     */
    public function disableChargeType(Estate $estate, ChargeType $chargeType): void
    {
        $estate->chargeTypes()->updateExistingPivot($chargeType->id, ['is_active' => false]);
    }

    /**
     * Seed the full default charge type catalogue for an organisation.
     * System types are matched by (organization_id, type); non-system by (organization_id, name).
     * Safe to call multiple times (idempotent via updateOrCreate).
     */
    public static function seedDefaultsForOrganization(string $organizationId): void
    {
        foreach (self::chargeTypeDefaults() as $ct) {
            if (!empty($ct['type'])) {
                $match  = ['organization_id' => $organizationId, 'type' => $ct['type']];
            } else {
                $match  = ['organization_id' => $organizationId, 'name' => $ct['name']];
            }
            ChargeType::updateOrCreate($match, array_merge($ct, ['organization_id' => $organizationId]));
        }
    }

    private static function chargeTypeDefaults(): array
    {
        return [
            ['type' => SystemChargeType::ADMIN_LEVY->value,   'name' => 'Admin Levy',            'description' => 'Monthly body corporate admin fund levy (day-to-day operations)',          'is_system' => true,  'is_active' => true,  'is_recurring' => true,  'applies_to' => ChargeTypeAppliesTo::OWNER,   'sort_order' => 1],
            ['type' => SystemChargeType::RESERVE_LEVY->value, 'name' => 'Reserve Levy',          'description' => 'Monthly body corporate reserve fund levy (capital expenditure)',           'is_system' => true,  'is_active' => true,  'is_recurring' => true,  'applies_to' => ChargeTypeAppliesTo::OWNER,   'sort_order' => 2],
            ['type' => SystemChargeType::CSOS_LEVY->value,    'name' => 'CSOS Levy',             'description' => 'Community Schemes Ombud Service government levy (flat per-unit, SA only)', 'is_system' => true,  'is_active' => true,  'is_recurring' => true,  'applies_to' => ChargeTypeAppliesTo::OWNER,   'sort_order' => 3],
            ['type' => SystemChargeType::RENT->value,         'name' => 'Rent',                  'description' => 'Regular monthly rental payment',                                           'is_system' => true,  'is_active' => true,  'is_recurring' => true,  'applies_to' => ChargeTypeAppliesTo::TENANT,  'sort_order' => 4],
            ['type' => null, 'name' => 'Special Levy',          'description' => 'Once-off body corporate charge approved at AGM or special meeting',  'is_system' => false, 'is_active' => true,  'is_recurring' => false, 'applies_to' => ChargeTypeAppliesTo::OWNER,   'sort_order' => 5],
            ['type' => null, 'name' => 'Water Recovery',        'description' => 'Metered water billed per unit',                                       'is_system' => false, 'is_active' => true,  'is_recurring' => false, 'applies_to' => ChargeTypeAppliesTo::EITHER,  'sort_order' => 6],
            ['type' => null, 'name' => 'Electricity Recovery',  'description' => 'Metered electricity billed per unit',                                 'is_system' => false, 'is_active' => true,  'is_recurring' => false, 'applies_to' => ChargeTypeAppliesTo::EITHER,  'sort_order' => 7],
            ['type' => null, 'name' => 'Gas Recovery',          'description' => 'Metered gas billed per unit',                                         'is_system' => false, 'is_active' => true,  'is_recurring' => false, 'applies_to' => ChargeTypeAppliesTo::EITHER,  'sort_order' => 8],
            ['type' => null, 'name' => 'Sewerage Recovery',     'description' => 'Sewerage charges billed per unit',                                    'is_system' => false, 'is_active' => true,  'is_recurring' => true,  'applies_to' => ChargeTypeAppliesTo::EITHER,  'sort_order' => 9],
            ['type' => null, 'name' => 'Refuse Recovery',       'description' => 'Refuse/waste collection billed per unit',                             'is_system' => false, 'is_active' => true,  'is_recurring' => true,  'applies_to' => ChargeTypeAppliesTo::EITHER,  'sort_order' => 10],
            ['type' => null, 'name' => 'Late Payment Interest',  'description' => 'Interest charged on overdue balances',                               'is_system' => false, 'is_active' => true,  'is_recurring' => false, 'applies_to' => ChargeTypeAppliesTo::EITHER,  'sort_order' => 11],
            ['type' => null, 'name' => 'Late Payment Penalty',   'description' => 'Flat penalty fee for late payment',                                  'is_system' => false, 'is_active' => true,  'is_recurring' => false, 'applies_to' => ChargeTypeAppliesTo::EITHER,  'sort_order' => 12],
            ['type' => null, 'name' => 'Insurance Excess',       'description' => 'Damage-related excess billed back to a unit',                        'is_system' => false, 'is_active' => true,  'is_recurring' => false, 'applies_to' => ChargeTypeAppliesTo::OWNER,   'sort_order' => 13],
            ['type' => null, 'name' => 'Key Deposit',            'description' => 'Deposit for keys or access devices',                                  'is_system' => false, 'is_active' => true,  'is_recurring' => false, 'applies_to' => ChargeTypeAppliesTo::TENANT,  'sort_order' => 14],
            ['type' => null, 'name' => 'Damage Deposit',         'description' => 'Security/damage deposit held against the unit',                      'is_system' => false, 'is_active' => true,  'is_recurring' => false, 'applies_to' => ChargeTypeAppliesTo::TENANT,  'sort_order' => 15],
            ['type' => null, 'name' => 'Parking Rental',         'description' => 'Monthly parking bay rental',                                         'is_system' => false, 'is_active' => true,  'is_recurring' => true,  'applies_to' => ChargeTypeAppliesTo::EITHER,  'sort_order' => 16],
            ['type' => null, 'name' => 'Storage Rental',         'description' => 'Monthly storage unit rental',                                        'is_system' => false, 'is_active' => true,  'is_recurring' => true,  'applies_to' => ChargeTypeAppliesTo::EITHER,  'sort_order' => 17],
            ['type' => null, 'name' => 'Moving-In Fee',          'description' => 'Once-off fee charged when a tenant moves in',                        'is_system' => false, 'is_active' => true,  'is_recurring' => false, 'applies_to' => ChargeTypeAppliesTo::TENANT,  'sort_order' => 18],
            ['type' => null, 'name' => 'Moving-Out Fee',         'description' => 'Once-off fee charged when a tenant moves out',                       'is_system' => false, 'is_active' => true,  'is_recurring' => false, 'applies_to' => ChargeTypeAppliesTo::TENANT,  'sort_order' => 19],
            ['type' => null, 'name' => 'Access Card Fee',        'description' => 'Once-off or replacement fee for access cards/remotes',               'is_system' => false, 'is_active' => true,  'is_recurring' => false, 'applies_to' => ChargeTypeAppliesTo::EITHER,  'sort_order' => 20],
            ['type' => null, 'name' => 'Gym Access',             'description' => 'Recurring fee for gym or fitness facility',                           'is_system' => false, 'is_active' => true,  'is_recurring' => true,  'applies_to' => ChargeTypeAppliesTo::EITHER,  'sort_order' => 21],
            ['type' => null, 'name' => 'Pool Access',            'description' => 'Recurring fee for pool facility',                                     'is_system' => false, 'is_active' => true,  'is_recurring' => true,  'applies_to' => ChargeTypeAppliesTo::EITHER,  'sort_order' => 22],
            ['type' => null, 'name' => 'Garden Maintenance',     'description' => 'Individual garden maintenance charge for units with private gardens', 'is_system' => false, 'is_active' => true,  'is_recurring' => true,  'applies_to' => ChargeTypeAppliesTo::OWNER,   'sort_order' => 23],
            ['type' => null, 'name' => 'Pet Levy',               'description' => 'Recurring monthly charge for pet-owning residents',                  'is_system' => false, 'is_active' => true,  'is_recurring' => true,  'applies_to' => ChargeTypeAppliesTo::EITHER,  'sort_order' => 24],
            ['type' => null, 'name' => 'Security Contribution',  'description' => 'Additional security charge beyond the standard levy',                'is_system' => false, 'is_active' => true,  'is_recurring' => true,  'applies_to' => ChargeTypeAppliesTo::OWNER,   'sort_order' => 25],
            ['type' => null, 'name' => 'Legal Recovery',         'description' => 'Recovery of legal costs incurred in collections',                    'is_system' => false, 'is_active' => true,  'is_recurring' => false, 'applies_to' => ChargeTypeAppliesTo::EITHER,  'sort_order' => 26],
        ];
    }
}
