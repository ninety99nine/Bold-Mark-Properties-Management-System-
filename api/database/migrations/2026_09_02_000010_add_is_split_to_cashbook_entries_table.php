<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WeConnectU cashbook split allocation: a bank line whose amount is spread
 * across several accounts is marked `is_split = true` and gets one child
 * cashbook entry per split line (linked via parent_entry_id). The flag doubles
 * as an "is allocated" marker for the parent alongside allocation_ledger_type.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cashbook_entries', function (Blueprint $table) {
            $table->boolean('is_split')->default(false)->after('allocation_ledger_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cashbook_entries', function (Blueprint $table) {
            $table->dropColumn('is_split');
        });
    }
};
