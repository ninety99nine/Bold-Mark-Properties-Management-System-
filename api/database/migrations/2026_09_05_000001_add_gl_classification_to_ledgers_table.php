<?php

use App\Enums\FinancialCategory;
use App\Enums\VatType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Enriches the chart of accounts with WeConnectU General Ledger classification:
     * where each account sits (income_statement vs balance_sheet), its Financial
     * Category (used by the GL posting engine to resolve control accounts), its
     * default VAT treatment, which fund it belongs to (main vs reserve), and the
     * main/sub-account hierarchy (parent_id).
     */
    public function up(): void
    {
        Schema::table('ledgers', function (Blueprint $table) {
            $table->string('account_type')->nullable()->after('category');
            $table->enum('financial_category', FinancialCategory::values())->nullable()->after('account_type');
            $table->enum('tax_type', VatType::values())->nullable()->after('financial_category');
            $table->string('fund')->default('main')->after('tax_type');

            $table->boolean('allow_sub_accounts')->default(true)->after('fund');

            $table->foreignUuid('parent_id')->nullable()->after('organization_id')->constrained('ledgers')->nullOnDelete();

            $table->index('financial_category');
            $table->index('fund');
            $table->index('parent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ledgers', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['parent_id']);
            $table->dropIndex(['fund']);
            $table->dropIndex(['financial_category']);

            $table->dropColumn([
                'account_type',
                'financial_category',
                'tax_type',
                'fund',
                'allow_sub_accounts',
                'parent_id',
            ]);
        });
    }
};
