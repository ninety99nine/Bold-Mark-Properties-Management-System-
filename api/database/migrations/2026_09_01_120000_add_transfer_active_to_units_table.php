<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a `transfer_active` flag (WeConnectU "Transfer active on unit" marker
     * shown by the transfer-arrows icon in the Age Analysis customer column).
     */
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->boolean('transfer_active')->default(false)->after('debit_order');
            $table->index('transfer_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropIndex(['transfer_active']);
            $table->dropColumn('transfer_active');
        });
    }
};
