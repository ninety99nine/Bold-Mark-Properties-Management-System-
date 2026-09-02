<?php

namespace Database\Factories;

use App\Enums\SupplierAccountType;
use App\Enums\SupplierPaymentType;
use App\Enums\SupplierStatus;
use App\Enums\SupplierType;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'organization_id'     => null,
            'community_id'        => null,
            'supplier_code'       => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name) . 'XXX', 0, 3))
                                     . str_pad((string) fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'name'                => $name,
            'supplier_type'       => fake()->randomElement(SupplierType::values()),
            'status'              => SupplierStatus::VERIFIED->value,
            'payment_type'        => SupplierPaymentType::NOT_SPECIFIED->value,
            'reference'           => strtoupper(fake()->bothify('REF-####')),
            'email'               => fake()->companyEmail(),
            'alt_email'           => fake()->safeEmail(),
            'phone'               => fake()->numerify('0## ### ####'),
            'alt_phone'           => fake()->numerify('0## ### ####'),
            'bank_name'           => fake()->randomElement(['Standard Bank', 'ABSA', 'Nedbank', 'First National Bank']),
            'account_type'        => fake()->randomElement(SupplierAccountType::values()),
            'account_number'      => (string) fake()->numberBetween(100000000, 999999999),
            'branch_code'         => (string) fake()->numberBetween(100000, 999999),
            'branch_name'         => fake()->city(),
            'vat_number'          => (string) fake()->numberBetween(4000000000, 4999999999),
            'registration_number' => fake()->numerify('####/######/##'),
            'address'             => fake()->address(),
            'address_line_1'      => fake()->streetAddress(),
            'suburb'              => fake()->citySuffix(),
            'town'                => fake()->city(),
            'postal_code'         => fake()->postcode(),
            'is_active'           => true,
            'balance'             => fake()->randomFloat(2, 0, 50000),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
