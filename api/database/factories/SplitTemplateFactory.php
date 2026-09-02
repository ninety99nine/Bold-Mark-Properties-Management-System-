<?php

namespace Database\Factories;

use App\Enums\JournalLineType;
use App\Models\SplitTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SplitTemplate>
 */
class SplitTemplateFactory extends Factory
{
    protected $model = SplitTemplate::class;

    public function definition(): array
    {
        return [
            'community_id'    => null,
            'organization_id' => null,
            'name'            => fake()->words(2, true) . ' Split',
            'lines'           => [
                ['ledger_type' => JournalLineType::GENERAL->value, 'amount' => 100.0],
                ['ledger_type' => JournalLineType::GENERAL->value, 'amount' => 50.0],
            ],
        ];
    }
}
