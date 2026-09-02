<?php

use App\Enums\SupplierAccountType;
use App\Enums\SupplierPaymentType;
use App\Enums\SupplierStatus;
use App\Enums\SupplierType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extend suppliers to full WeConnectU "Manage Suppliers" parity: entity type,
 * verification status, payment/account type, extra contact fields, structured
 * address, and an optional supplier group.
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
        Schema::table('suppliers', function (Blueprint $table) {
            $table->enum('supplier_type', SupplierType::values())->nullable()->after('name');
            $table->enum('status', SupplierStatus::values())->default(SupplierStatus::VERIFIED->value)->after('supplier_type');
            $table->enum('payment_type', SupplierPaymentType::values())->default(SupplierPaymentType::NOT_SPECIFIED->value)->after('status');
            $table->enum('account_type', SupplierAccountType::values())->nullable()->after('branch_code');

            $table->string('reference')->nullable()->after('supplier_code');
            $table->string('alt_email')->nullable()->after('email');
            $table->string('alt_phone', 20)->nullable()->after('phone');
            $table->string('branch_name')->nullable()->after('branch_code');

            $table->string('address_line_1')->nullable()->after('address');
            $table->string('address_line_2')->nullable()->after('address_line_1');
            $table->string('suburb')->nullable()->after('address_line_2');
            $table->string('town')->nullable()->after('suburb');
            $table->string('postal_code', 20)->nullable()->after('town');

            $table->foreignUuid('supplier_group_id')->nullable()->after('community_id')->constrained('supplier_groups')->nullOnDelete();

            $table->index('supplier_group_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropForeign(['supplier_group_id']);
            $table->dropColumn([
                'supplier_type',
                'status',
                'payment_type',
                'account_type',
                'reference',
                'alt_email',
                'alt_phone',
                'branch_name',
                'address_line_1',
                'address_line_2',
                'suburb',
                'town',
                'postal_code',
                'supplier_group_id',
            ]);
        });
    }
};
