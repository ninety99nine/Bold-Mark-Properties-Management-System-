<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WeConnectU Cashbook GL fields: link each bank account to its 8000/00n Bank GL
 * ledger, flag the community's default cashbook + tenant billing account, and
 * record the opening balance of the first bank statement upload.
 *
 * The `integration` column already exists (added in 2026_09_01_000004), so we
 * reuse it rather than adding a duplicate `bank_integration` column.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_active');
            $table->boolean('tenant_billing_account')->default(false)->after('is_default');
            $table->decimal('opening_balance', 15, 2)->default(0)->after('tenant_billing_account');

            $table->foreignUuid('ledger_id')->nullable()->after('community_id')->constrained('ledgers')->nullOnDelete();

            $table->index('ledger_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropIndex(['ledger_id']);
            $table->dropConstrainedForeignId('ledger_id');
            $table->dropColumn(['is_default', 'tenant_billing_account', 'opening_balance']);
        });
    }
};
