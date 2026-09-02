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
        Schema::create('risk_rules', function (Blueprint $table) {

            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->text('description')->nullable();

            $table->string('severity', 20);
            $table->boolean('is_active')->default(true);

            $table->json('conditions');

            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->timestamps();

            $table->index(['organization_id', 'is_active'], 'risk_rules_organization_active_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('risk_rules');
    }
};
