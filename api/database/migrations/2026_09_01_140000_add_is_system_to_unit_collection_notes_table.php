<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Flags system/auto-generated collection notes (status changes, notice
     * charges, "Balance Paid", etc.). WeConnectU shows edit/delete only on
     * manual notes — system notes are read-only.
     */
    public function up(): void
    {
        Schema::table('unit_collection_notes', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->after('note');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unit_collection_notes', function (Blueprint $table) {
            $table->dropColumn('is_system');
        });
    }
};
