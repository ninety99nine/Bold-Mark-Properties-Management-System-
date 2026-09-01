<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add the WeConnectU "Setup → General" fields that the community table did not
 * yet carry: the "Suppress Entity Type in communication" flag, unit addressing
 * mode, the Apply PQ flag, the interest period (per annum / per month), and the
 * transfer-clearance-fee bank account (stored as JSON).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->boolean('suppress_entity_type')->default(false)->after('entity_type');
            $table->string('unit_addressing', 30)->default('unit_no')->after('suppress_entity_type');
            $table->boolean('apply_pq')->default(true)->after('csos_registration_number');
            $table->string('interest_period', 20)->default('per_annum')->after('interest_rate');
            $table->json('transfer_clearance_bank')->nullable()->after('interest_exempt_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->dropColumn([
                'suppress_entity_type',
                'unit_addressing',
                'apply_pq',
                'interest_period',
                'transfer_clearance_bank',
            ]);
        });
    }
};
