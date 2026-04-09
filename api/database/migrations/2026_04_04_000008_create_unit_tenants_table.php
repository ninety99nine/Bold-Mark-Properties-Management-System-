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
        Schema::create('unit_tenants', function (Blueprint $table) {

            $table->uuid('id')->primary();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone', 20)->nullable();
            $table->string('id_number', 50)->nullable();

            $table->boolean('is_active')->default(true);

            $table->date('lease_start')->nullable();
            $table->date('lease_end')->nullable();

            $table->foreignUuid('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->timestamps();

            $table->index('unit_id');
            $table->index('tenant_id');
            $table->index('email');
            $table->index('is_active');
            $table->index('lease_start');
            $table->index('lease_end');
            $table->index(['unit_id', 'is_active']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_tenants');
    }
};
