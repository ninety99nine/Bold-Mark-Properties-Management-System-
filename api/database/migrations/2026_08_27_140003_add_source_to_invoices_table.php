<?php

use App\Enums\InvoiceSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Marks each invoice as either 'system' (recurring engine / single-charge manual
     * entry) or 'manual' (a WeConnectU-style multi-line customer invoice). This makes
     * the distinction explicit instead of inferring it from a NULL header ledger, and
     * lets reporting attribute manual-invoice revenue via its line items.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->enum('source', InvoiceSource::values())
                ->default(InvoiceSource::SYSTEM->value)
                ->after('status');

            $table->index('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });
    }
};
