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
        Schema::table('unit_tenants', function (Blueprint $table) {
            $table->date('move_out_date')->nullable()->after('lease_end');
            $table->string('move_out_reason')->nullable()->after('move_out_date');
            $table->text('move_out_notes')->nullable()->after('move_out_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unit_tenants', function (Blueprint $table) {
            $table->dropColumn(['move_out_date', 'move_out_reason', 'move_out_notes']);
        });
    }
};
