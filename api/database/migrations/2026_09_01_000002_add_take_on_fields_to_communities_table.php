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
        Schema::table('communities', function (Blueprint $table) {
            // WeConnectU "Take-on" onboarding step fields.
            $table->string('previous_managing_agent')->nullable()->after('community_manager_id');
            $table->date('opening_balance_date')->nullable()->after('previous_managing_agent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->dropColumn(['previous_managing_agent', 'opening_balance_date']);
        });
    }
};
