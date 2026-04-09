<?php

namespace Database\Factories;

use App\Enums\CashbookEntryType;
use App\Models\CashbookEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CashbookEntry>
 */
class CashbookEntryFactory extends Factory
{
    protected $model = CashbookEntry::class;

    public function definition(): array
    {
        return [
            'estate_id'       => null,
            'tenant_id'       => null,
            'description'     => fake()->sentence(4),
            'amount'          => fake()->randomFloat(2, 500, 15000),
            'type'            => fake()->randomElement(CashbookEntryType::values()),
            'date'            => fake()->dateTimeBetween('-90 days', 'now')->format('Y-m-d'),
            'notes'           => fake()->optional(0.3)->sentence(),
            'unit_id'         => null,
            'invoice_id'      => null,
            'parent_entry_id' => null,
        ];
    }

    public function credit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CashbookEntryType::Credit->value,
        ]);
    }

    public function debit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CashbookEntryType::Debit->value,
        ]);
    }

    public function allocated(): static
    {
        return $this->state(fn (array $attributes) => [
            'invoice_id' => \App\Models\Invoice::factory(),
        ]);
    }

    public function unallocated(): static
    {
        return $this->state(fn (array $attributes) => [
            'invoice_id' => null,
        ]);
    }

    public function withNote(string $note): static
    {
        return $this->state(fn (array $attributes) => [
            'notes' => $note,
        ]);
    }
}
