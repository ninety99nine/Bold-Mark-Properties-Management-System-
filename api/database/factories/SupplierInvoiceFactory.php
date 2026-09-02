<?php

namespace Database\Factories;

use App\Enums\FinancialCategory;
use App\Enums\SupplierInvoiceStatus;
use App\Enums\SupplierInvoiceType;
use App\Models\Ledger;
use App\Models\SupplierInvoice;
use App\Services\SupplierInvoiceService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SupplierInvoice>
 */
class SupplierInvoiceFactory extends Factory
{
    protected $model = SupplierInvoice::class;

    /**
     * Ensure a factory-created supplier invoice always has at least one line item
     * (mirroring the roll-up header totals) and posts its balanced GL batch (Dr
     * expense/input-VAT, Cr Accounts Payable [supplier]) when it is "created", so
     * factory invoices move the supplier balance exactly like real ones. GL
     * posting is skipped when the invoice has no supplier or the organisation has
     * no chart of accounts yet.
     *
     * @return static
     */
    public function configure(): static
    {
        return $this->afterCreating(function (SupplierInvoice $invoice): void {
            if ($invoice->items()->count() === 0) {
                $net = round((float) $invoice->subtotal, 2);
                $tax = round((float) $invoice->vat_amount, 2);

                $invoice->items()->create([
                    'account_name' => 'Expense',
                    'description'  => $invoice->description ?: 'Goods received',
                    'quantity'     => 1,
                    'unit_price'   => round($net + $tax, 2),
                    'discount'     => 0,
                    'tax_rate'     => $net > 0 && $tax > 0 ? round($tax / $net * 100, 2) : 0,
                    'tax_amount'   => $tax,
                    'line_total'   => round($net + $tax, 2),
                    'sort_order'   => 0,
                    'ledger_id'    => null,
                ]);
            }

            if ($invoice->status !== SupplierInvoiceStatus::CREATED || ! $invoice->supplier_id || ! $invoice->organization_id) {
                return;
            }

            if (! Ledger::controlAccount($invoice->organization_id, FinancialCategory::ACCOUNTS_PAYABLE)) {
                return;
            }

            app(SupplierInvoiceService::class)->postSupplierInvoiceLedger($invoice);
        });
    }

    /**
     * @return array
     */
    public function definition(): array
    {
        $total = fake()->randomFloat(2, 500, 20000);

        return [
            'grv_number'             => 'GRV' . str_pad((string) fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'status'                 => SupplierInvoiceStatus::CREATED->value,
            'type'                   => SupplierInvoiceType::ADHOC->value,
            'source_document_number' => null,
            'supplier_reference'     => fake()->bothify('INV####'),
            'our_reference'          => null,
            'description'            => fake()->sentence(3),
            'attachments'            => null,
            'invoice_date'           => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'due_date'               => null,
            'financial_year'         => (int) date('Y'),
            'subtotal'               => $total,
            'discount'               => 0,
            'vat_amount'             => 0,
            'total'                  => $total,
            'organization_id'        => null,
            'community_id'           => null,
            'supplier_id'            => null,
            'created_by_user_id'     => null,
        ];
    }

    /**
     * A draft supplier invoice (posts nothing to the GL).
     *
     * @return static
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SupplierInvoiceStatus::DRAFT->value,
        ]);
    }
}
