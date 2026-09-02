<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Take-on items become an append-only history (WeConnectU parity): each upload
     * or "Not Applicable" is its own row, so the (community, key) pair is no longer unique.
     */
    public function up(): void
    {
        Schema::table('community_takeon_items', function (Blueprint $table) {
            $table->dropUnique(['community_id', 'key']);
            $table->index(['community_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('community_takeon_items', function (Blueprint $table) {
            $table->dropIndex(['community_id', 'key']);
            $table->unique(['community_id', 'key']);
        });
    }
};
