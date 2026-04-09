<?php

namespace Database\Factories;

use App\Enums\ChargeTypeAppliesTo;
use App\Models\ChargeType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChargeType>
 */
class ChargeTypeFactory extends Factory
{
    protected $model = ChargeType::class;

    public function definition(): array
    {
        return [
            'tenant_id'    => null,
            'code'         => 'CHARGE_' . strtoupper(fake()->unique()->lexify('?????')),
            'name'         => fake()->words(2, true),
            'description'  => fake()->sentence(),
            'is_system'    => false,
            'is_active'    => true,
            'applies_to'   => fake()->randomElement(ChargeTypeAppliesTo::values()),
            'is_recurring' => fake()->boolean(),
            'sort_order'   => fake()->numberBetween(1, 100),
        ];
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function recurring(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_recurring' => true,
        ]);
    }

    public function adHoc(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_recurring' => false,
        ]);
    }

    public function forOwner(): static
    {
        return $this->state(fn (array $attributes) => [
            'applies_to' => ChargeTypeAppliesTo::Owner->value,
        ]);
    }

    public function forTenant(): static
    {
        return $this->state(fn (array $attributes) => [
            'applies_to' => ChargeTypeAppliesTo::Tenant->value,
        ]);
    }

    public function forEither(): static
    {
        return $this->state(fn (array $attributes) => [
            'applies_to' => ChargeTypeAppliesTo::Either->value,
        ]);
    }
}
