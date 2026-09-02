<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per community: the WeConnectU "Default Billing Setup" — maps each
     * billing concept (levies, reserve fund, water, CSOS, ratios, bank charges…)
     * to a chart-of-accounts ledger, plus the recovery/billing toggles.
     *
     * Ledger references are plain nullable UUIDs (existence validated at the
     * request layer) to keep this wide mapping table lean.
     */
    public function up(): void
    {
        Schema::create('community_billing_setups', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Charge → ledger mappings.
            $ledgerColumns = [
                'levies_ledger_id',
                'reserve_fund_levies_ledger_id',
                'reserve_fund_ledger_id',
                'arrears_interest_ledger_id',
                'water_ledger_id',
                'sewerage_ledger_id',
                'rule_enforcement_income_ledger_id',
                'arrear_admin_ledger_id',
                'debt_collecting_ledger_id',
                'admin_fees_ledger_id',
                'electricity_ledger_id',
                'insurance_ledger_id',
                'csos_ledger_id',
                'ratio_1_ledger_id',
                'ratio_2_ledger_id',
                'ratio_3_ledger_id',
                'ratio_4_ledger_id',
                'ratio_5_ledger_id',
                'bank_charges_ledger_id',
            ];

            foreach ($ledgerColumns as $column) {
                $table->uuid($column)->nullable();
            }

            // Per-ratio CSOS exemption.
            for ($i = 1; $i <= 5; $i++) {
                $table->boolean("ratio_{$i}_csos_exempt")->default(false);
            }

            // Feature toggles.
            $table->boolean('apply_csos_levy')->default(true);
            $table->boolean('occupant_billing')->default(false);
            $table->boolean('water_recovery')->default(false);
            $table->boolean('electricity_recovery')->default(false);

            $table->foreignUuid('community_id')->unique()->constrained('communities')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->timestamps();

            $table->index('organization_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('community_billing_setups');
    }
};
