<?php

namespace Database\Factories;

use App\Enums\BilledToType;
use App\Enums\FinancialCategory;
use App\Enums\InvoiceStatus;
use App\Models\Ledger;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * Post the invoice's balanced GL batch on creation (Dr Accounts Receivable
     * [unit] / Cr income), so factory invoices move the unit balance exactly like
     * real ones. Skipped when the invoice has no unit (no customer account to
     * post) or the organisation has no chart of accounts yet.
     *
     * @return static
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Invoice $invoice): void {
            if (! $invoice->unit_id || ! $invoice->organization_id) {
                return;
            }

            if (! Ledger::controlAccount($invoice->organization_id, FinancialCategory::ACCOUNTS_RECEIVABLE)) {
                return;
            }

            app(InvoiceService::class)->postInvoiceLedger($invoice);
        });
    }

    public function definition(): array
    {
        return [
            'organization_id'       => null,
            'unit_id'         => null,
            'ledger_id'  => Ledger::factory(),
            'billed_to_type'  => fake()->randomElement(BilledToType::values()),
            'billed_to_id'    => Str::uuid(),
            'invoice_number'  => 'INV-' . date('Y') . '-' . fake()->unique()->numerify('####'),
            'status'          => InvoiceStatus::UNPAID->value,
            'amount'          => fake()->randomFloat(2, 500, 10000),
            'billing_period'  => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-01'),
            'due_date'        => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'sent_at'         => null,
        ];
    }

    public function unpaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'  => InvoiceStatus::UNPAID->value,
            'sent_at' => null,
        ]);
    }

    public function dispatched(): static
    {
        return $this->state(fn (array $attributes) => [
            'sent_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'  => InvoiceStatus::PAID->value,
            'sent_at' => fake()->dateTimeBetween('-60 days', '-1 day'),
        ]);
    }

    public function partiallyPaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'  => InvoiceStatus::PARTIALLY_PAID->value,
            'sent_at' => fake()->dateTimeBetween('-60 days', '-1 day'),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'   => InvoiceStatus::OVERDUE->value,
            'sent_at'  => fake()->dateTimeBetween('-90 days', '-31 days'),
            'due_date' => fake()->dateTimeBetween('-60 days', '-1 day')->format('Y-m-d'),
        ]);
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
