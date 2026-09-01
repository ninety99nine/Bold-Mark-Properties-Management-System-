<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Water/electricity recovery already live on community_billing_setups (the
 * Default Billing Setup page). Drop the duplicate columns added to communities;
 * the community-info modal sources them from the billing setup relation instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->dropColumn(['water_recovery', 'electricity_recovery']);
        });
    }

    public function down(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->boolean('water_recovery')->default(false);
            $table->boolean('electricity_recovery')->default(false);
        });
    }
};
