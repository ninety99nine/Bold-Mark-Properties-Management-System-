<?php

namespace Database\Factories;

use App\Models\Organization;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    /**
     * Every organisation needs the full WeConnectU chart of accounts so the GL
     * posting engine can resolve control accounts (Accounts Receivable, VAT
     * Control, Suspense, …) when documents post balanced journal batches.
     *
     * @return static
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Organization $organization): void {
            (new ChartOfAccountsSeeder)->seedForOrganization($organization->id);
        });
    }

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
