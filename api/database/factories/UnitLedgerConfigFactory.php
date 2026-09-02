<?php

namespace Database\Factories;

use App\Models\UnitLedgerConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UnitLedgerConfig>
 */
class UnitLedgerConfigFactory extends Factory
{
    protected $model = UnitLedgerConfig::class;

    public function definition(): array
    {
        return [
            'unit_id'        => null,
            'ledger_id' => null,
            'amount'         => fake()->randomFloat(2, 100, 2000),
            'is_active'      => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withAmount(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
        ]);
    }
}
