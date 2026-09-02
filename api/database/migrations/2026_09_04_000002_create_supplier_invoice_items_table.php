<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Line-item breakdown for a supplier invoice (GRV). Each row maps to one account
 * (an expense ledger, or a free-text account name) with a quantity, unit price,
 * discount and VAT. The parent GRV caches the rolled-up subtotal / discount /
 * vat_amount / total, so downstream finance code (supplier ledger, supplier age
 * analysis) keeps reading the single `total` column on the header unchanged.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('supplier_invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('account_name')->nullable();   // "Account" column when no ledger is linked
            $table->string('description')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);

            $table->foreignUuid('supplier_invoice_id')->constrained('supplier_invoices')->cascadeOnDelete();
            $table->foreignUuid('ledger_id')->nullable()->constrained('ledgers')->nullOnDelete();

            $table->timestamps();

            $table->index('supplier_invoice_id');
            $table->index('ledger_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_invoice_items');
    }
};
