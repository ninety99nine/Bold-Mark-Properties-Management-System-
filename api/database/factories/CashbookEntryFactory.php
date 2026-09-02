<?php

namespace Database\Factories;

use App\Enums\CashbookEntryType;
use App\Models\CashbookEntry;
use App\Services\AllocationPostingService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CashbookEntry>
 */
class CashbookEntryFactory extends Factory
{
    protected $model = CashbookEntry::class;

    /**
     * Post the entry's bank GL batch on creation (Dr/Cr Bank vs Suspense for an
     * unallocated line), mirroring the real create path. Requires a community and
     * a bank account with a GL ledger; child (split) rows are skipped by the
     * posting service itself.
     *
     * @return static
     */
    public function configure(): static
    {
        return $this->afterCreating(function (CashbookEntry $entry): void {
            if ($entry->community_id && $entry->bank_account_id) {
                app(AllocationPostingService::class)->postEntryLedger($entry);
            }
        });
    }

    public function definition(): array
    {
        return [
            'community_id'       => null,
            'organization_id'       => null,
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
            'type' => CashbookEntryType::CREDIT->value,
        ]);
    }

    public function debit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CashbookEntryType::DEBIT->value,
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
