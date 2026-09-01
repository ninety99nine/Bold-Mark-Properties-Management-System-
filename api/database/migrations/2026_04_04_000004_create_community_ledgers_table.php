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
        Schema::create('community_ledgers', function (Blueprint $table) {

            $table->uuid('id')->primary();

            $table->boolean('is_active')->default(true);

            $table->foreignUuid('community_id')->constrained('communities')->cascadeOnDelete();
            $table->foreignUuid('ledger_id')->constrained('ledgers')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['community_id', 'ledger_id']);
            $table->index('community_id');
            $table->index('ledger_id');
            $table->index('is_active');
            $table->index(['community_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('community_ledgers');
    }
};
