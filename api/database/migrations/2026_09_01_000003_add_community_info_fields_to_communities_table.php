<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fields backing the community "Information" and "Admin Charges" tabs of the
 * community-info modal (WeConnectU parity).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            // Information tab — utility recovery toggles
            $table->boolean('water_recovery')->default(false)->after('apply_pq');
            $table->boolean('electricity_recovery')->default(false)->after('water_recovery');

            // Admin Charges tab — fee amounts + debt-collection toggle
            $table->decimal('penalty_admin_fee', 12, 2)->nullable()->after('electricity_recovery');
            $table->decimal('warning_admin_fee', 12, 2)->nullable()->after('penalty_admin_fee');
            $table->decimal('transfer_clearance_fee', 12, 2)->nullable()->after('warning_admin_fee');
            $table->decimal('phonecall_fee', 12, 2)->nullable()->after('transfer_clearance_fee');
            $table->boolean('apply_debt_collection_fee')->default(false)->after('phonecall_fee');
        });
    }

    public function down(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->dropColumn([
                'water_recovery',
                'electricity_recovery',
                'penalty_admin_fee',
                'warning_admin_fee',
                'transfer_clearance_fee',
                'phonecall_fee',
                'apply_debt_collection_fee',
            ]);
        });
    }
};
