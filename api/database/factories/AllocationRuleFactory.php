<?php

namespace Database\Factories;

use App\Enums\JournalLineType;
use App\Models\AllocationRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AllocationRule>
 */
class AllocationRuleFactory extends Factory
{
    protected $model = AllocationRule::class;

    public function definition(): array
    {
        return [
            'community_id'            => null,
            'organization_id'         => null,
            'description_starts_with' => null,
            'description_contains'    => null,
            'ledger_type'             => JournalLineType::GENERAL->value,
            'ledger_id'               => null,
            'unit_id'                 => null,
            'supplier_id'             => null,
            'vat_type'                => null,
            'remarks'                 => null,
            'apply_to_positive'       => true,
            'apply_to_negative'       => true,
            'bank_account_ids'        => null,
            'sort_order'              => fake()->numberBetween(0, 100),
        ];
    }
}
