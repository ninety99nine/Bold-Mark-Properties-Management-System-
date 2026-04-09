<?php

namespace Database\Factories;

use App\Models\UnitTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UnitTenant>
 */
class UnitTenantFactory extends Factory
{
    protected $model = UnitTenant::class;

    public function definition(): array
    {
        return [
            'unit_id'     => null,
            'tenant_id'   => null,
            'full_name'   => fake()->name(),
            'email'       => fake()->unique()->safeEmail(),
            'phone'       => '+267 7' . fake()->numerify('#######'),
            'id_number'   => fake()->numerify('#########'),
            'is_active'   => true,
            'lease_start' => fake()->dateTimeBetween('-2 years', '-1 month')->format('Y-m-d'),
            'lease_end'   => fake()->dateTimeBetween('+1 month', '+2 years')->format('Y-m-d'),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withExpiredLease(): static
    {
        return $this->state(fn (array $attributes) => [
            'lease_start' => fake()->dateTimeBetween('-3 years', '-2 years')->format('Y-m-d'),
            'lease_end'   => fake()->dateTimeBetween('-2 years', '-1 month')->format('Y-m-d'),
            'is_active'   => false,
        ]);
    }

    public function withCurrentLease(): static
    {
        return $this->state(fn (array $attributes) => [
            'lease_start' => fake()->dateTimeBetween('-1 year', '-1 month')->format('Y-m-d'),
            'lease_end'   => fake()->dateTimeBetween('+1 month', '+1 year')->format('Y-m-d'),
            'is_active'   => true,
        ]);
    }
}
