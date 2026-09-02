<?php

namespace Database\Factories;

use App\Models\JournalGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\JournalGroup>
 */
class JournalGroupFactory extends Factory
{
    protected $model = JournalGroup::class;

    public function definition(): array
    {
        return [
            'organization_id' => null,
            'community_id'    => null,
            'name'            => fake()->unique()->words(2, true),
        ];
    }
}
