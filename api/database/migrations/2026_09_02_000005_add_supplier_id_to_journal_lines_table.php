<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the supplier account column to journal lines so a line_type of "supplier"
 * can post against a supplier ledger — completing the four WeConnectU ledger
 * types (general → ledger_id, customer → unit_id, supplier → supplier_id,
 * reserve_fund → ledger_id).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->foreignUuid('supplier_id')->nullable()->after('unit_id')->constrained('suppliers')->nullOnDelete();

            $table->index('supplier_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn('supplier_id');
        });
    }
};
