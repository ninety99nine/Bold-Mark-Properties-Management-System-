<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supplier invoices ("GRV" — Goods Received Voucher) mirror WeConnectU's
 * "SUPPLIER TAX INVOICE". Each GRV is raised against a supplier for a community
 * and posts a CREDIT to that supplier's ledger (it increases what the community
 * owes the supplier), aged by invoice_date. The header caches the rolled-up
 * subtotal / discount / vat_amount / total so the ledger and age analysis keep
 * reading the single `total` column, while the individual account lines live on
 * supplier_invoice_items.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('grv_number', 50);            // e.g. "GRV00005"
            $table->string('status')->default('created'); // draft | created
            $table->string('type')->default('adhoc');     // adhoc | fixed_recurring | variable_recurring | invoice_assistant
            $table->string('source_document_number')->nullable();
            $table->string('supplier_reference')->nullable();
            $table->string('our_reference')->nullable();
            $table->string('description')->nullable();    // shown in the ledger Remarks column
            $table->json('attachments')->nullable();      // uploaded source documents

            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->unsignedInteger('financial_year')->nullable();

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('vat_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);

            $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignUuid('community_id')->constrained('communities')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('organization_id');
            $table->index('community_id');
            $table->index('supplier_id');
            $table->index('grv_number');
            $table->index('invoice_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_invoices');
    }
};
