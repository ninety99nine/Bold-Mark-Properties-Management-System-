<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            // Customer profile
            $table->string('customer_type')->nullable()->after('entity_type');
            $table->string('vat_no', 50)->nullable()->after('customer_type');
            $table->string('alt_email')->nullable()->after('vat_no');
            $table->string('alt_phone', 30)->nullable()->after('alt_email');
            $table->string('payment_type', 50)->nullable()->after('alt_phone');
            $table->string('pdf_password', 50)->nullable()->after('payment_type');
            $table->string('customer_group')->nullable()->after('pdf_password');
            $table->string('reference')->nullable()->after('customer_group');
            $table->string('old_customer_code', 50)->nullable()->after('reference');

            // Address details
            $table->string('address_line_2')->nullable()->after('address');
            $table->string('suburb')->nullable()->after('address_line_2');
            $table->string('town')->nullable()->after('suburb');
            $table->string('postal_code', 20)->nullable()->after('town');

            // Banking details
            $table->string('account_holder')->nullable()->after('postal_code');
            $table->string('bank_name')->nullable()->after('account_holder');
            $table->string('account_type', 50)->nullable()->after('bank_name');
            $table->string('account_number', 50)->nullable()->after('account_type');
            $table->string('branch_code', 50)->nullable()->after('account_number');
            $table->string('branch_name')->nullable()->after('branch_code');

            $table->text('notes')->nullable()->after('branch_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            $table->dropColumn([
                'customer_type', 'vat_no', 'alt_email', 'alt_phone', 'payment_type', 'pdf_password',
                'customer_group', 'reference', 'old_customer_code',
                'address_line_2', 'suburb', 'town', 'postal_code',
                'account_holder', 'bank_name', 'account_type', 'account_number', 'branch_code', 'branch_name',
                'notes',
            ]);
        });
    }
};
