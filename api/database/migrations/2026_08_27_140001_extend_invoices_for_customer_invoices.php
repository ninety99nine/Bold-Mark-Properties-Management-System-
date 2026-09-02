<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Extends the single-line invoice header so it can also back a WeConnectU-style
     * multi-line customer invoice: a nominated bank account, an explicit invoice date
     * (distinct from the billing_period the recurring engine keys off), cached
     * subtotal/VAT amounts, and an optional uploaded source document.
     *
     * `ledger_id` becomes nullable: a multi-line customer invoice has no single header
     * account (its accounts live on invoice_items), so its header ledger_id is stored
     * NULL. The existing (unit_id, ledger_id, billing_period) unique index is preserved
     * — NULLs are treated as distinct in a unique index (both MySQL and SQLite), so
     * customer invoices never collide, while the recurring billing engine keeps its
     * duplicate backstop for real (non-null) ledger invoices.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->uuid('ledger_id')->nullable()->change();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('subtotal', 12, 2)->nullable()->after('amount');
            $table->decimal('vat_amount', 12, 2)->nullable()->after('subtotal');
            $table->date('invoice_date')->nullable()->after('billing_period');
            $table->string('attachment_path')->nullable()->after('reminder_sent_at');

            $table->foreignUuid('bank_account_id')->nullable()->after('ledger_id')
                ->constrained('bank_accounts')->nullOnDelete();

            $table->index('bank_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
            $table->dropColumn(['subtotal', 'vat_amount', 'invoice_date', 'attachment_path']);
        });
    }
};
