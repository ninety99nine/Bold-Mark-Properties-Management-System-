<?php

namespace Database\Factories;

use App\Enums\OccupancyType;
use App\Enums\UnitStatus;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        return [
            'estate_id'      => null,
            'tenant_id'      => null,
            'unit_number'    => strtoupper(fake()->lexify('?')) . fake()->numberBetween(1, 50),
            'address'        => fake()->streetAddress(),
            'occupancy_type' => fake()->randomElement(OccupancyType::values()),
            'status'         => UnitStatus::Active->value,
            'levy_override'  => null,
            'rent_amount'    => fake()->randomFloat(2, 3000, 15000),
        ];
    }

    public function ownerOccupied(): static
    {
        return $this->state(fn (array $attributes) => [
            'occupancy_type' => OccupancyType::OwnerOccupied->value,
        ]);
    }

    public function tenantOccupied(): static
    {
        return $this->state(fn (array $attributes) => [
            'occupancy_type' => OccupancyType::TenantOccupied->value,
        ]);
    }

    public function vacant(): static
    {
        return $this->state(fn (array $attributes) => [
            'occupancy_type' => OccupancyType::Vacant->value,
            'rent_amount'    => null,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UnitStatus::Suspended->value,
        ]);
    }

    public function withLevyOverride(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'levy_override' => $amount,
        ]);
    }
}
