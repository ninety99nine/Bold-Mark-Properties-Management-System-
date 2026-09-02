<?php

namespace Database\Factories;

use App\Models\SupplierGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SupplierGroup>
 */
class SupplierGroupFactory extends Factory
{
    protected $model = SupplierGroup::class;

    public function definition(): array
    {
        return [
            'organization_id' => null,
            'community_id'    => null,
            'name'            => fake()->unique()->words(2, true),
        ];
    }
}
