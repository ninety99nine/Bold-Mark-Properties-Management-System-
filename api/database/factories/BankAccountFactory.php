<?php

namespace Database\Factories;

use App\Enums\BankAccountType;
use App\Models\BankAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BankAccount>
 */
class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    public function definition(): array
    {
        return [
            'organization_id' => null,
            'community_id'    => null,
            'name'            => fake()->randomElement(['Standard Bank Current', 'FNB Cheque', 'ABSA Current']),
            'bank_name'       => fake()->randomElement(['Standard Bank', 'FNB', 'ABSA', 'Nedbank']),
            'account_number'  => (string) fake()->numberBetween(100000000, 999999999),
            'type'            => BankAccountType::CURRENT->value,
            'balance'         => fake()->randomFloat(2, 5000, 250000),
            'balance_as_at'   => now()->subDays(fake()->numberBetween(0, 20))->toDateString(),
            'is_active'       => true,
        ];
    }

    public function current(): static
    {
        return $this->state(fn () => ['type' => BankAccountType::CURRENT->value]);
    }

    public function investment(): static
    {
        return $this->state(fn () => [
            'type'    => BankAccountType::INVESTMENT->value,
            'name'    => fake()->randomElement(['Money Market Investment', 'Reserve Fund Investment', 'Fixed Deposit']),
            'balance' => fake()->randomFloat(2, 0, 700000),
        ]);
    }
}
