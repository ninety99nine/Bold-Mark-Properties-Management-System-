<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add batch_id to unit_activities so that multiple log entries
     * created from a single save action can be grouped together visually.
     */
    public function up(): void
    {
        Schema::table('unit_activities', function (Blueprint $table) {
            $table->uuid('batch_id')->nullable()->after('tenant_id');

            $table->index('batch_id');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('unit_activities', function (Blueprint $table) {
            $table->dropIndex(['batch_id']);
            $table->dropColumn('batch_id');
        });
    }
};
