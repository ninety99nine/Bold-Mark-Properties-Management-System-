<?php

namespace App\Services;

use App\Enums\EstateType;
use App\Models\ChargeType;
use App\Models\Estate;

class EstateChargeTypeService
{
    /**
     * Charge type codes that apply to each estate type.
     * Used when auto-configuring an estate on creation.
     */
    protected array $presetsByType = [
        EstateType::SECTIONAL_TITLE->value => [
            'LEVY', 'SPECIAL_LEVY',
            'WATER_RECOVERY', 'ELECTRICITY_RECOVERY', 'GAS_RECOVERY',
            'SEWERAGE_RECOVERY', 'REFUSE_RECOVERY',
            'LATE_INTEREST', 'LATE_PENALTY',
            'INSURANCE_EXCESS', 'PARKING_RENTAL', 'STORAGE_RENTAL',
            'ACCESS_CARD', 'GYM_ACCESS', 'POOL_ACCESS',
            'GARDEN_MAINT', 'PET_LEVY', 'SECURITY_CONTRIB', 'LEGAL_RECOVERY',
        ],
        EstateType::RESIDENTIAL_RENTAL->value => [
            'RENT', 'KEY_DEPOSIT', 'DAMAGE_DEPOSIT',
            'MOVING_IN', 'MOVING_OUT',
            'LATE_INTEREST', 'LATE_PENALTY',
            'PARKING_RENTAL', 'PET_LEVY', 'LEGAL_RECOVERY',
        ],
        EstateType::COMMERCIAL_RENTAL->value => [
            'RENT', 'KEY_DEPOSIT', 'DAMAGE_DEPOSIT',
            'LATE_INTEREST', 'LATE_PENALTY',
            'PARKING_RENTAL', 'STORAGE_RENTAL', 'LEGAL_RECOVERY',
        ],
        EstateType::MIXED->value => [],  // All charge types enabled for mixed
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
        $estateTypeValue = $estate->type instanceof EstateType
            ? $estate->type->value
            : (string) $estate->type;

        // For mixed estates, enable ALL active charge types for the tenant
        if ($estateTypeValue === EstateType::MIXED->value) {
            $chargeTypeIds = ChargeType::where('tenant_id', $estate->tenant_id)
                ->where('is_active', true)
                ->pluck('id');
        } else {
            $codes = $this->presetsByType[$estateTypeValue] ?? [];

            $chargeTypeIds = ChargeType::where('tenant_id', $estate->tenant_id)
                ->where('is_active', true)
                ->whereIn('code', $codes)
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
     * Disable a specific charge type for an estate (without removing the pivot row).
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
