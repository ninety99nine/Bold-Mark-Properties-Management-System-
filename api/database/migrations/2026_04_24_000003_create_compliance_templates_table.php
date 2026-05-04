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
        Schema::create('compliance_templates', function (Blueprint $table) {

            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('country', 2)->nullable();

            $table->boolean('is_default')->default(false);
            $table->boolean('is_system')->default(false);

            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->timestamps();

            $table->index('organization_id');
            $table->index(['organization_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_templates');
    }
};
