<?php

namespace Database\Factories;

use App\Enums\EstateType;
use App\Models\Estate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Estate>
 */
class EstateFactory extends Factory
{
    protected $model = Estate::class;

    public function definition(): array
    {
        return [
            'tenant_id'            => null,
            'name'                 => fake()->company() . ' Estate',
            'address'              => fake()->streetAddress(),
            'type'                 => fake()->randomElement(EstateType::values()),
            'default_levy_amount'  => fake()->randomFloat(2, 500, 5000),
            'default_rent_amount'  => fake()->randomFloat(2, 1000, 20000),
            'billing_day'          => fake()->numberBetween(1, 28),
            'is_active'            => true,
        ];
    }

    public function sectionalTitle(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => EstateType::SectionalTitle->value,
        ]);
    }

    public function residentialRental(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => EstateType::ResidentialRental->value,
        ]);
    }

    public function commercialRental(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => EstateType::CommercialRental->value,
        ]);
    }

    public function mixed(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => EstateType::Mixed->value,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
