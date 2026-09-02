<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Records which community budget periods (per fund + year) have been locked
     * ("Budget locked" in WeConnectU); a locked period rejects budget upserts
     * until it is re-opened for editing.
     */
    public function up(): void
    {
        Schema::create('community_budget_locks', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->smallInteger('year');
            $table->string('fund')->default('main');

            $table->timestamp('locked_at')->nullable();

            $table->foreignUuid('community_id')->constrained('communities')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['community_id', 'year', 'fund']);
            $table->index('community_id');
            $table->index('organization_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('community_budget_locks');
    }
};
