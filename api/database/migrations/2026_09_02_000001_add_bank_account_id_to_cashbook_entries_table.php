<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * WeConnectU's Cashbook is scoped to a selected bank account ("cashbook"),
     * so each transaction line belongs to a specific bank account. The FK is
     * added in a second Schema::table() call — mirroring the parent_entry_id
     * approach in the create migration — because PostgreSQL cannot verify the
     * constraint inline on an existing table.
     */
    public function up(): void
    {
        Schema::table('cashbook_entries', function (Blueprint $table) {
            $table->uuid('bank_account_id')->nullable()->after('invoice_id');

            $table->index('bank_account_id');
            $table->index(['bank_account_id', 'date']);
        });

        Schema::table('cashbook_entries', function (Blueprint $table) {
            $table->foreign('bank_account_id')
                  ->references('id')
                  ->on('bank_accounts')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cashbook_entries', function (Blueprint $table) {
            $table->dropForeign(['bank_account_id']);
            $table->dropColumn('bank_account_id');
        });
    }
};
