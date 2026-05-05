<?php

namespace Database\Factories;

use App\Models\ComplianceChecklist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ComplianceChecklist>
 */
class ComplianceChecklistFactory extends Factory
{
    protected $model = ComplianceChecklist::class;

    public function definition(): array
    {
        $year  = fake()->numberBetween(2022, 2027);
        $start = $year . '-03-01';
        $end   = ($year + 1) . '-02-28';

        return [
            'organization_id'      => null,
            'estate_id'            => null,
            'created_by_id'        => null,
            'financial_year_label' => $year . '/' . ($year + 1),
            'financial_year_start' => $start,
            'financial_year_end'   => $end,
            'notes'                => null,
        ];
    }
}
