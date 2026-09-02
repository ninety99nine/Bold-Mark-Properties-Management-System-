<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * WeConnectU flags each General Ledger account as a "Budget Item" or not — the
     * green bar-chart toggle on the General Ledger page. Income-statement accounts
     * are budget items by default; balance-sheet accounts are not.
     */
    public function up(): void
    {
        Schema::table('ledgers', function (Blueprint $table) {
            $table->boolean('is_budget_item')->default(false)->after('allow_sub_accounts');
        });

        // Income statement sub-accounts (income + expenses) are budget items by default.
        \App\Models\Ledger::query()
            ->where('account_type', 'income_statement')
            ->whereNotNull('parent_id')
            ->update(['is_budget_item' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ledgers', function (Blueprint $table) {
            $table->dropColumn('is_budget_item');
        });
    }
};
