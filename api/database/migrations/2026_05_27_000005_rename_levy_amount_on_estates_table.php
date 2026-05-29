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
        Schema::table('estates', function (Blueprint $table) {
            $table->renameColumn('default_levy_amount', 'admin_fund_amount');
            $table->decimal('reserve_fund_amount', 12, 2)->nullable()->after('admin_fund_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('estates', function (Blueprint $table) {
            $table->dropColumn('reserve_fund_amount');
            $table->renameColumn('admin_fund_amount', 'default_levy_amount');
        });
    }
};
