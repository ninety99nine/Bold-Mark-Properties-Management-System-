<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WeConnectU Unit PQ parity — store the per-unit Ratio 1–5 and Unit Size values
 * that ship in the PQ batch file, so imported data displays exactly as uploaded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->decimal('ratio_1', 18, 10)->nullable()->after('pq');
            $table->decimal('ratio_2', 18, 10)->nullable()->after('ratio_1');
            $table->decimal('ratio_3', 18, 10)->nullable()->after('ratio_2');
            $table->decimal('ratio_4', 18, 10)->nullable()->after('ratio_3');
            $table->decimal('ratio_5', 18, 10)->nullable()->after('ratio_4');
            $table->decimal('unit_size', 12, 2)->nullable()->after('ratio_5');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn(['ratio_1', 'ratio_2', 'ratio_3', 'ratio_4', 'ratio_5', 'unit_size']);
        });
    }
};
