<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WeConnectU "Manage Customers" parity: a Customer IS an owner record, but may
 * stand alone (Body Corporate / Unallocated) with no unit and no email. Adds the
 * customer code, country, disable flag and debit-order mandate fields the
 * community-scoped customer register needs.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('owners', function (Blueprint $table) {

            // Standalone customers have no unit and (per WeConnectU) an optional email.
            $table->uuid('unit_id')->nullable()->change();
            $table->string('email')->nullable()->change();

            // Customer register fields
            $table->string('customer_code')->nullable()->after('old_customer_code');
            $table->string('country')->nullable()->default('South Africa')->after('reference');

            $table->boolean('is_disabled')->default(false)->after('is_primary');
            $table->timestamp('disabled_at')->nullable()->after('is_disabled');

            // Debit-order mandate (Update Debit Order Mandates bulk tool)
            $table->boolean('debit_order')->default(false)->after('branch_name');
            $table->string('mandate_type')->nullable()->after('debit_order');
            $table->decimal('monthly_plus_amount', 12, 2)->nullable()->after('mandate_type');
            $table->unsignedTinyInteger('collection_day')->nullable()->after('monthly_plus_amount');

            // Community scope for standalone customers (unit-linked ones resolve via unit).
            $table->foreignUuid('community_id')->nullable()->after('unit_id')->constrained('communities')->nullOnDelete();

            $table->index('customer_code');
            $table->index('is_disabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            $table->dropConstrainedForeignId('community_id');

            $table->dropIndex(['customer_code']);
            $table->dropIndex(['is_disabled']);

            $table->dropColumn([
                'customer_code', 'country',
                'is_disabled', 'disabled_at',
                'debit_order', 'mandate_type', 'monthly_plus_amount', 'collection_day',
            ]);
        });
    }
};
