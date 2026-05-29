<?php

namespace App\Services;

use App\Enums\EstateType;
use App\Enums\SystemChargeType;
use App\Models\ChargeType;
use App\Models\Estate;
use Database\Seeders\DefaultChargeTypesSeeder;

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
            (new DefaultChargeTypesSeeder())->seedForTenant($estate->organization_id);
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
}
