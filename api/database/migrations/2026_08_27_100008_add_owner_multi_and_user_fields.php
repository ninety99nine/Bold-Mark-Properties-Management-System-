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
        Schema::table('owners', function (Blueprint $table) {
            $table->boolean('is_primary')->default(true)->after('full_name');
            $table->boolean('user_verified')->default(false)->after('is_primary');
            $table->string('user_display_name')->nullable()->after('user_verified');

            $table->index(['unit_id', 'is_primary']);
        });

        Schema::table('occupants', function (Blueprint $table) {
            $table->boolean('user_verified')->default(false)->after('is_active');
            $table->string('user_display_name')->nullable()->after('user_verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            $table->dropIndex(['unit_id', 'is_primary']);
            $table->dropColumn(['is_primary', 'user_verified', 'user_display_name']);
        });

        Schema::table('occupants', function (Blueprint $table) {
            $table->dropColumn(['user_verified', 'user_display_name']);
        });
    }
};
