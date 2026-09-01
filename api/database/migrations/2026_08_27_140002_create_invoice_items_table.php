<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Line-item breakdown for a customer invoice. Each row maps to one account
     * (ledger) with a quantity, unit amount, and VAT. The parent invoice caches
     * the rolled-up subtotal / vat_amount / amount so downstream finance code
     * (unit balances, age analysis, cashbook allocation) keeps reading the single
     * `amount` column unchanged.
     */
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('description')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('amount', 12, 2)->default(0);       // net unit amount
            $table->decimal('tax_rate', 5, 2)->default(0);      // VAT %
            $table->decimal('tax_amount', 12, 2)->default(0);   // computed VAT
            $table->decimal('line_total', 12, 2)->default(0);   // quantity * amount + tax_amount
            $table->unsignedInteger('sort_order')->default(0);

            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignUuid('ledger_id')->nullable()->constrained('ledgers')->nullOnDelete();

            $table->timestamps();

            $table->index('invoice_id');
            $table->index('ledger_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
