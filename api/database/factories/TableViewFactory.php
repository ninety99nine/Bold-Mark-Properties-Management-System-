<?php

namespace Database\Factories;

use App\Models\TableView;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TableView>
 */
class TableViewFactory extends Factory
{
    protected $model = TableView::class;

    public function definition(): array
    {
        return [
            'user_id'         => null,
            'organization_id' => null,
            'context'          => fake()->randomElement(['units', 'invoices', 'cashbook', 'age-analysis', 'users']),
            'name'             => fake()->words(2, true),
            'date_range'       => 'all_time',
            'date_range_start' => null,
            'date_range_end'   => null,
            'filters'          => null,
            'sort_field'       => null,
            'sort_direction'   => 'asc',
        ];
    }
}
