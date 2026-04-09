<?php

namespace Database\Seeders;

use App\Enums\ChargeTypeAppliesTo;
use App\Models\ChargeType;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class DefaultChargeTypesSeeder extends Seeder
{
    /**
     * The 24 default charge types (2 locked + 22 presets).
     * Seeded for every tenant on creation.
     */
    private array $defaults = [
        // --- Locked defaults (is_system = true) ---
        [
            'code'         => 'LEVY',
            'name'         => 'Levy',
            'description'  => 'Regular monthly body corporate levy',
            'is_system'    => true,
            'is_active'    => true,
            'is_recurring' => true,
            'applies_to'   => ChargeTypeAppliesTo::OWNER,
            'sort_order'   => 1,
        ],
        [
            'code'         => 'RENT',
            'name'         => 'Rent',
            'description'  => 'Regular monthly rental payment',
            'is_system'    => true,
            'is_active'    => true,
            'is_recurring' => true,
            'applies_to'   => ChargeTypeAppliesTo::TENANT,
            'sort_order'   => 2,
        ],

        // --- Common presets (is_system = false) ---
        [
            'code'         => 'SPECIAL_LEVY',
            'name'         => 'Special Levy',
            'description'  => 'Once-off body corporate charge approved at AGM or special meeting',
            'is_system'    => false,
            'is_active'    => true,
            'is_recurring' => false,
            'applies_to'   => ChargeTypeAppliesTo::OWNER,
            'sort_order'   => 3,
        ],
        [
            'code'         => 'WATER_RECOVERY',
            'name'         => 'Water Recovery',
            'description'  => 'Metered water billed per unit',
            'is_system'    => false,
            'is_active'    => true,
            'is_recurring' => true,
            'applies_to'   => ChargeTypeAppliesTo::EITHER,
            'sort_order'   => 4,
        ],
        [
            'code'         => 'ELECTRICITY_RECOVERY',
            'name'         => 'Electricity Recovery',
            'description'  => 'Metered electricity billed per unit',
            'is_system'    => false,
            'is_active'    => true,
            'is_recurring' => true,
            'applies_to'   => ChargeTypeAppliesTo::EITHER,
            'sort_order'   => 5,
        ],
        [
            'code'         => 'GAS_RECOVERY',
            'name'         => 'Gas Recovery',
            'description'  => 'Metered gas billed per unit',
            'is_system'    => false,
            'is_active'    => true,
            'is_recurring' => true,
            'applies_to'   => ChargeTypeAppliesTo::EITHER,
            'sort_order'   => 6,
        ],
        [
            'code'         => 'SEWERAGE_RECOVERY',
            'name'         => 'Sewerage Recovery',
            'description'  => 'Sewerage charges billed per unit',
            'is_system'    => false,
            'is_active'    => true,
            'is_recurring' => true,
            'applies_to'   => ChargeTypeAppliesTo::EITHER,
            'sort_order'   => 7,
        ],
        [
            'code'         => 'REFUSE_RECOVERY',
            'name'         => 'Refuse Recovery',
            'description'  => 'Refuse/waste collection billed per unit',
            'is_system'    => false,
            'is_active'    => true,
            'is_recurring' => true,
            'applies_to'   => ChargeTypeAppliesTo::EITHER,
            'sort_order'   => 8,
        ],
        [
            'code'         => 'LATE_INTEREST',
            'name'         => 'Late Payment Interest',
            'description'  => 'Interest charged on overdue balances',
            'is_system'    => false,
            'is_active'    => true,
            'is_recurring' => false,
            'applies_to'   => ChargeTypeAppliesTo::EITHER,
            'sort_order'   => 9,
        ],
        [
            'code'         => 'LATE_PENALTY',
            'name'         => 'Late Payment Penalty',
            'description'  => 'Flat penalty fee for late payment',
            'is_system'    => false,
            'is_active'    => true,
            'is_recurring' => false,
            'applies_to'   => ChargeTypeAppliesTo::EITHER,
            'sort_order'   => 10,
        ],
        [
            'code'         => 'INSURANCE_EXCESS',
            'name'         => 'Insurance Excess',
            'description'  => 'Damage-related excess billed back to a unit',
            'is_system'    => false,
            'is_active'    => false,
            'is_recurring' => false,
            'applies_to'   => ChargeTypeAppliesTo::OWNER,
            'sort_order'   => 11,
        ],
        [
            'code'         => 'KEY_DEPOSIT',
            'name'         => 'Key Deposit',
            'description'  => 'Deposit for keys or access devices',
            'is_system'    => false,
            'is_active'    => true,
            'is_recurring' => false,
            'applies_to'   => ChargeTypeAppliesTo::TENANT,
            'sort_order'   => 12,
        ],
        [
            'code'         => 'DAMAGE_DEPOSIT',
            'name'         => 'Damage Deposit',
            'description'  => 'Security/damage deposit held against the unit',
            'is_system'    => false,
            'is_active'    => true,
            'is_recurring' => false,
            'applies_to'   => ChargeTypeAppliesTo::TENANT,
            'sort_order'   => 13,
        ],
        [
            'code'         => 'PARKING_RENTAL',
            'name'         => 'Parking Rental',
            'description'  => 'Monthly parking bay rental',
            'is_system'    => false,
            'is_active'    => true,
            'is_recurring' => true,
            'applies_to'   => ChargeTypeAppliesTo::EITHER,
            'sort_order'   => 14,
        ],
        [
            'code'         => 'STORAGE_RENTAL',
            'name'         => 'Storage Rental',
            'description'  => 'Monthly storage unit rental',
            'is_system'    => false,
            'is_active'    => true,
            'is_recurring' => true,
            'applies_to'   => ChargeTypeAppliesTo::EITHER,
            'sort_order'   => 15,
        ],
        [
            'code'         => 'MOVING_IN',
            'name'         => 'Moving-In Fee',
            'description'  => 'Once-off fee charged when a tenant moves in',
            'is_system'    => false,
            'is_active'    => true,
            'is_recurring' => false,
            'applies_to'   => ChargeTypeAppliesTo::TENANT,
            'sort_order'   => 16,
        ],
        [
            'code'         => 'MOVING_OUT',
            'name'         => 'Moving-Out Fee',
            'description'  => 'Once-off fee charged when a tenant moves out',
            'is_system'    => false,
            'is_active'    => true,
            'is_recurring' => false,
            'applies_to'   => ChargeTypeAppliesTo::TENANT,
            'sort_order'   => 17,
        ],
        [
            'code'         => 'ACCESS_CARD',
            'name'         => 'Access Card Fee',
            'description'  => 'Once-off or replacement fee for access cards/remotes',
            'is_system'    => false,
            'is_active'    => true,
            'is_recurring' => false,
            'applies_to'   => ChargeTypeAppliesTo::EITHER,
            'sort_order'   => 18,
        ],
        [
            'code'         => 'GYM_ACCESS',
            'name'         => 'Gym Access',
            'description'  => 'Recurring fee for gym or fitness facility',
            'is_system'    => false,
            'is_active'    => true,
            'is_recurring' => true,
            'applies_to'   => ChargeTypeAppliesTo::EITHER,
            'sort_order'   => 19,
        ],
        [
            'code'         => 'POOL_ACCESS',
            'name'         => 'Pool Access',
            'description'  => 'Recurring fee for pool facility',
            'is_system'    => false,
            'is_active'    => true,
            'is_recurring' => true,
            'applies_to'   => ChargeTypeAppliesTo::EITHER,
            'sort_order'   => 20,
        ],
        [
            'code'         => 'GARDEN_MAINT',
            'name'         => 'Garden Maintenance',
            'description'  => 'Individual garden maintenance charge for units with private gardens',
            'is_system'    => false,
            'is_active'    => true,
            'is_recurring' => true,
            'applies_to'   => ChargeTypeAppliesTo::OWNER,
            'sort_order'   => 21,
        ],
        [
            'code'         => 'PET_LEVY',
            'name'         => 'Pet Levy',
            'description'  => 'Recurring monthly charge for pet-owning residents',
            'is_system'    => false,
            'is_active'    => true,
            'is_recurring' => true,
            'applies_to'   => ChargeTypeAppliesTo::EITHER,
            'sort_order'   => 22,
        ],
        [
            'code'         => 'SECURITY_CONTRIB',
            'name'         => 'Security Contribution',
            'description'  => 'Additional security charge beyond the standard levy',
            'is_system'    => false,
            'is_active'    => true,
            'is_recurring' => true,
            'applies_to'   => ChargeTypeAppliesTo::OWNER,
            'sort_order'   => 23,
        ],
        [
            'code'         => 'LEGAL_RECOVERY',
            'name'         => 'Legal Recovery',
            'description'  => 'Recovery of legal costs incurred in collections',
            'is_system'    => false,
            'is_active'    => false,
            'is_recurring' => false,
            'applies_to'   => ChargeTypeAppliesTo::EITHER,
            'sort_order'   => 24,
        ],
    ];

    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $this->seedForTenant($tenant->id);
        }
    }

    public function seedForTenant(string $tenantId): void
    {
        foreach ($this->defaults as $chargeType) {
            ChargeType::updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => $chargeType['code']],
                array_merge($chargeType, ['tenant_id' => $tenantId])
            );
        }
    }
}
