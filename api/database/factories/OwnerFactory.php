<?php

namespace Database\Factories;

use App\Models\Owner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Owner>
 */
class OwnerFactory extends Factory
{
    protected $model = Owner::class;

    public function definition(): array
    {
        return [
            'unit_id'   => null,
            'tenant_id' => null,
            'full_name' => fake()->name(),
            'email'     => fake()->unique()->safeEmail(),
            'phone'     => '+267 7' . fake()->numerify('#######'),
            'id_number' => fake()->numerify('#########'),
            'address'   => fake()->streetAddress(),
        ];
    }
}
