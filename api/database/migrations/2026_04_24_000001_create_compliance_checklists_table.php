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
        Schema::create('compliance_checklists', function (Blueprint $table) {

            $table->uuid('id')->primary();
            $table->string('financial_year_label', 20);
            $table->date('financial_year_start');
            $table->date('financial_year_end');

            $table->text('notes')->nullable();

            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('community_id')->constrained('communities')->cascadeOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['community_id', 'financial_year_start'], 'compliance_checklists_community_fy_unique');
            $table->index('organization_id');
            $table->index('community_id');
            $table->index(['organization_id', 'community_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_checklists');
    }
};
