<?php

namespace Database\Factories;

use App\Models\UnitChargeConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UnitChargeConfig>
 */
class UnitChargeConfigFactory extends Factory
{
    protected $model = UnitChargeConfig::class;

    public function definition(): array
    {
        return [
            'unit_id'        => null,
            'tenant_id'      => null,
            'charge_type_id' => null,
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
