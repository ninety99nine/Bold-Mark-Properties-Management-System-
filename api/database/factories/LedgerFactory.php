<?php

namespace Database\Factories;

use App\Enums\LedgerAppliesTo;
use App\Models\Ledger;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ledger>
 */
class LedgerFactory extends Factory
{
    protected $model = Ledger::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'type'            => null,
            'name'            => fake()->words(2, true),
            'description'     => fake()->sentence(),
            'is_system'       => false,
            'is_active'       => true,
            'applies_to'      => fake()->randomElement(LedgerAppliesTo::values()),
            'is_recurring'    => fake()->boolean(),
            'sort_order'      => fake()->numberBetween(1, 100),
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
            'applies_to' => LedgerAppliesTo::Owner->value,
        ]);
    }

    public function forOrganization(): static
    {
        return $this->state(fn (array $attributes) => [
            'applies_to' => LedgerAppliesTo::Occupant->value,
        ]);
    }

    public function forEither(): static
    {
        return $this->state(fn (array $attributes) => [
            'applies_to' => LedgerAppliesTo::Either->value,
        ]);
    }
}
