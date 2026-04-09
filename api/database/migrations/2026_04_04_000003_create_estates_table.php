<?php

use App\Enums\EstateType;
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
        Schema::create('estates', function (Blueprint $table) {

            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('address')->nullable();

            $table->enum('type', EstateType::values())->default(EstateType::SECTIONAL_TITLE->value);
            $table->boolean('is_active')->default(true);

            $table->decimal('default_levy_amount', 12, 2)->nullable();
            $table->decimal('default_rent_amount', 12, 2)->nullable();
            $table->unsignedInteger('billing_day')->default(1);

            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->timestamps();

            $table->index('tenant_id');
            $table->index('type');
            $table->index('is_active');
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estates');
    }
};
