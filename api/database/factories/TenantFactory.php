<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $companyName = fake()->company();

        return [
            'name'            => $companyName,
            'slug'            => Str::slug($companyName) . '-' . fake()->unique()->numerify('###'),
            'company_name'    => $companyName,
            'company_slogan'  => fake()->catchPhrase(),
            'logo_url'        => null,
            'contact_email'   => fake()->companyEmail(),
            'contact_phone'   => '+267 7' . fake()->numerify('#######'),
            'address'         => fake()->address(),
            'country'         => 'BW',
            'currency'        => 'BWP',
            'primary_color'   => '#1F3A5C',
            'secondary_color' => '#D89B4B',
            'copyright_name'  => $companyName,
            'credentials'     => null,
            'is_active'       => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
