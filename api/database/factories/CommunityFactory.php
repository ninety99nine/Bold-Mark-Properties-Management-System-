<?php

namespace Database\Factories;

use App\Enums\CommunityEntityType;
use App\Enums\CommunityStatus;
use App\Models\Community;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Community>
 */
class CommunityFactory extends Factory
{
    protected $model = Community::class;

    public function definition(): array
    {
        return [
            'organization_id'            => null,
            'name'                 => fake()->company() . ' Community',
            'address'              => fake()->streetAddress(),
            'entity_type'          => fake()->randomElement(CommunityEntityType::values()),
            'admin_fund_amount'    => fake()->randomFloat(2, 500, 5000),
            'default_rent_amount'  => fake()->randomFloat(2, 1000, 20000),
            'billing_day'          => fake()->numberBetween(1, 28),
            'is_active'            => true,
            'status'               => CommunityStatus::ACTIVE->value,
        ];
    }

    public function takeOn(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CommunityStatus::TAKE_ON->value,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'status'    => CommunityStatus::SUSPENDED->value,
        ]);
    }

    public function sectionalTitle(): static
    {
        return $this->state(fn (array $attributes) => [
            'entity_type' => CommunityEntityType::BODY_CORPORATE->value,
        ]);
    }

    public function residentialRental(): static
    {
        return $this->state(fn (array $attributes) => [
            'entity_type' => CommunityEntityType::RESIDENTIAL_RENTAL->value,
        ]);
    }

    public function commercialRental(): static
    {
        return $this->state(fn (array $attributes) => [
            'entity_type' => CommunityEntityType::COMMERCIAL_RENTAL->value,
        ]);
    }

    public function mixed(): static
    {
        return $this->state(fn (array $attributes) => [
            'entity_type' => CommunityEntityType::MIXED->value,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'status'    => CommunityStatus::SUSPENDED->value,
        ]);
    }
}
