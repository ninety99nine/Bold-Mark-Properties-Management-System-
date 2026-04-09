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
        Schema::create('unit_charge_configs', function (Blueprint $table) {

            $table->uuid('id')->primary();

            $table->decimal('amount', 12, 2);
            $table->boolean('is_active')->default(true);

            $table->foreignUuid('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignUuid('charge_type_id')->constrained('charge_types')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['unit_id', 'charge_type_id']);
            $table->index('unit_id');
            $table->index('charge_type_id');
            $table->index('is_active');
            $table->index(['unit_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_charge_configs');
    }
};
