<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fields backing the global Settings → Company ("Company Details") page
 * (WeConnectU parity): company registration + transfer clearance fee +
 * outgoing email, the company bank-account block, the top-left brand icon,
 * and the default email header/footer images. The Reseller Name reuses
 * company_name, Telephone No. reuses contact_phone and the Header Logo
 * reuses the existing logo_url column.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {

            $table->string('company_reg_no')->nullable()->after('company_slogan');
            $table->decimal('transfer_clearance_fee', 10, 2)->nullable()->after('company_reg_no');
            $table->string('outgoing_email')->nullable()->after('contact_email');

            $table->string('bank_account_holder')->nullable()->after('outgoing_email');
            $table->string('bank_name')->nullable()->after('bank_account_holder');
            $table->string('bank_account_type')->nullable()->after('bank_name');
            $table->string('bank_account_number')->nullable()->after('bank_account_type');
            $table->string('bank_branch_code')->nullable()->after('bank_account_number');
            $table->string('bank_branch_name')->nullable()->after('bank_branch_code');

            $table->string('icon_url')->nullable()->after('logo_url');
            $table->string('email_header_url')->nullable()->after('icon_url');
            $table->string('email_footer_url')->nullable()->after('email_header_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'company_reg_no',
                'transfer_clearance_fee',
                'outgoing_email',
                'bank_account_holder',
                'bank_name',
                'bank_account_type',
                'bank_account_number',
                'bank_branch_code',
                'bank_branch_name',
                'icon_url',
                'email_header_url',
                'email_footer_url',
            ]);
        });
    }
};
