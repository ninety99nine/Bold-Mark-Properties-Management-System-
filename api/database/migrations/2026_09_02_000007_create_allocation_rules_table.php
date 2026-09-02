<?php

use App\Enums\JournalLineType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allocation rules auto-allocate cashbook lines by matching their description.
 * A rule matches when the description STARTS WITH and/or CONTAINS the configured
 * text, the amount sign is enabled (positive/negative), and the line's bank
 * account is in `bank_account_ids` (null/empty = all cashbooks). On match the
 * line is allocated to the rule's ledger TYPE + target account, mirroring
 * WeConnectU's "Create Allocation Rule".
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('allocation_rules', function (Blueprint $table) {

            $table->uuid('id')->primary();

            $table->string('description_starts_with')->nullable();
            $table->string('description_contains')->nullable();

            $table->enum('ledger_type', JournalLineType::values());
            $table->string('vat_type')->nullable();
            $table->string('remarks')->nullable();

            $table->boolean('apply_to_positive')->default(true);
            $table->boolean('apply_to_negative')->default(true);

            $table->unsignedInteger('sort_order')->default(0);

            $table->json('bank_account_ids')->nullable();

            $table->foreignUuid('ledger_id')->nullable()->constrained('ledgers')->nullOnDelete();
            $table->foreignUuid('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignUuid('community_id')->constrained('communities')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->timestamps();

            $table->index('organization_id');
            $table->index('community_id');
            $table->index(['community_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('allocation_rules');
    }
};
