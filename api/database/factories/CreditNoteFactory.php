<?php

namespace Database\Factories;

use App\Enums\BilledToType;
use App\Models\CreditNote;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CreditNote>
 */
class CreditNoteFactory extends Factory
{
    protected $model = CreditNote::class;

    public function definition(): array
    {
        return [
            'organization_id'    => null,
            'unit_id'            => null,
            'applied_invoice_id' => null,
            'billed_to_type'     => fake()->randomElement(BilledToType::values()),
            'billed_to_id'       => Str::uuid(),
            'credit_note_number' => 'CN-' . date('Y') . '-' . fake()->unique()->numerify('####'),
            'reason'             => fake()->optional()->sentence(3),
            'reference'          => fake()->optional()->numerify('ORD-####'),
            'amount'             => fake()->randomFloat(2, 100, 5000),
            'subtotal'           => fake()->randomFloat(2, 100, 5000),
            'vat_amount'         => 0,
            'credit_note_date'   => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'sent_at'            => null,
        ];
    }

    public function billedToOwner(): static
    {
        return $this->state(fn (array $attributes) => [
            'billed_to_type' => BilledToType::OWNER->value,
        ]);
    }

    public function billedToOccupant(): static
    {
        return $this->state(fn (array $attributes) => [
            'billed_to_type' => BilledToType::OCCUPANT->value,
        ]);
    }
}
