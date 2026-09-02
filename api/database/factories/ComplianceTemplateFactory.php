<?php

namespace Database\Factories;

use App\Models\ComplianceTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ComplianceTemplate>
 */
class ComplianceTemplateFactory extends Factory
{
    protected $model = ComplianceTemplate::class;

    public function definition(): array
    {
        return [
            'organization_id' => null,
            'name'            => fake()->words(3, true) . ' Template',
            'description'     => null,
            'country'         => null,
            'is_default'      => false,
            'is_system'       => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}
