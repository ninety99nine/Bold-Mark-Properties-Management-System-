<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A reusable split-allocation template, mirroring WeConnectU's "Save Split
 * Template" / "Select Split Template". `lines` stores the split rows as JSON —
 * each with a ledger type, target account (ledger_id / unit_id / supplier_id),
 * remarks, and either a fixed amount or a ratio to re-apply to a new line.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('split_templates', function (Blueprint $table) {

            $table->uuid('id')->primary();

            $table->string('name');

            $table->json('lines');

            $table->foreignUuid('community_id')->constrained('communities')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->timestamps();

            $table->index('organization_id');
            $table->index('community_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('split_templates');
    }
};
