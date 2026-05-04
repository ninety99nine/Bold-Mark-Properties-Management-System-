<?php

use App\Enums\ChargeTypeAppliesTo;
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
        Schema::create('charge_types', function (Blueprint $table) {

            $table->uuid('id')->primary();
            $table->string('code', 50);
            $table->string('name');
            $table->text('description')->nullable();

            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_recurring')->default(true);

            $table->unsignedInteger('sort_order')->default(0);

            $table->enum('applies_to', ChargeTypeAppliesTo::values())->default(ChargeTypeAppliesTo::EITHER->value);

            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index('organization_id');
            $table->index('is_active');
            $table->index('is_recurring');
            $table->index('applies_to');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('charge_types');
    }
};
