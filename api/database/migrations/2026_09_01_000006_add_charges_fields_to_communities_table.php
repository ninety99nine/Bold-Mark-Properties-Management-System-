<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fields backing the community Settings → Charges page (WeConnectU parity):
 * the notice-charge matrix, notices exemption, handed-over fee and notice
 * threshold. The per-fee amounts (penalty/warning/transfer-clearance/phonecall)
 * and the debt-collection toggle were added in an earlier migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->decimal('handed_over_fee', 12, 2)->nullable()->after('apply_debt_collection_fee');
            $table->decimal('notice_threshold_amount', 12, 2)->nullable()->after('handed_over_fee');
            $table->json('notice_charges')->nullable()->after('notice_threshold_amount');
            $table->json('notices_exemption')->nullable()->after('notice_charges');
        });
    }

    public function down(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->dropColumn(['handed_over_fee', 'notice_threshold_amount', 'notice_charges', 'notices_exemption']);
        });
    }
};
