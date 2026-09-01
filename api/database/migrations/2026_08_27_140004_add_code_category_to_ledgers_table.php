<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Gives ledgers a chart-of-accounts identity: a WeConnectU-style account code
     * (e.g. "1000/006") and a category grouping (e.g. "INCOME"). This lets the
     * customer-invoice Account picker present the full chart of accounts grouped by
     * category, exactly like WeConnectU.
     */
    public function up(): void
    {
        Schema::table('ledgers', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->after('id');
            $table->string('category')->nullable()->after('code');

            $table->index(['organization_id', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ledgers', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'category']);
            $table->dropColumn(['code', 'category']);
        });
    }
};
