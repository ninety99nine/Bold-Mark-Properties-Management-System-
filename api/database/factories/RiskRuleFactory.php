<?php

namespace Database\Factories;

use App\Enums\RiskSeverity;
use App\Models\RiskRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RiskRule>
 */
class RiskRuleFactory extends Factory
{
    protected $model = RiskRule::class;

    public function definition(): array
    {
        return [
            'organization_id' => null,
            'name'            => fake()->words(3, true),
            'description'     => fake()->sentence(),
            'severity'        => fake()->randomElement(RiskSeverity::values()),
            'is_active'       => true,
            'sort_order'      => 0,
            'conditions'      => [
                [
                    'type'     => 'overdue_amount',
                    'operator' => '>=',
                    'value'    => 1000,
                ],
            ],
        ];
    }

    public function warning(): static
    {
        return $this->state(fn () => ['severity' => RiskSeverity::WARNING->value]);
    }

    public function critical(): static
    {
        return $this->state(fn () => ['severity' => RiskSeverity::CRITICAL->value]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function withCondition(string $type, float $value): static
    {
        return $this->state(fn () => [
            'conditions' => [
                ['type' => $type, 'operator' => '>=', 'value' => $value],
            ],
        ]);
    }
}
