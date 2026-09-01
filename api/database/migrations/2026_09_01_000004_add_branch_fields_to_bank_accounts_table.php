<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Branch + integration fields backing the "Bank Details" tab of the
 * community-info modal (WeConnectU parity).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->string('branch_code', 30)->nullable()->after('account_number');
            $table->string('branch_name')->nullable()->after('branch_code');
            $table->string('integration')->nullable()->after('branch_name');
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropColumn(['branch_code', 'branch_name', 'integration']);
        });
    }
};
