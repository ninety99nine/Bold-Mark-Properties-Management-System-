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
        Schema::table('units', function (Blueprint $table) {
            $table->string('section', 50)->nullable()->after('unit_number');
            $table->decimal('pq', 14, 10)->nullable()->after('size_sqm');

            $table->index('pq');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropIndex(['pq']);
            $table->dropColumn(['section', 'pq']);
        });
    }
};
