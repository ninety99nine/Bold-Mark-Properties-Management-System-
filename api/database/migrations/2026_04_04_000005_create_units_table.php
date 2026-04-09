<?php

use App\Enums\OccupancyType;
use App\Enums\UnitStatus;
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
        Schema::create('units', function (Blueprint $table) {

            $table->uuid('id')->primary();
            $table->string('unit_number', 50);
            $table->text('address')->nullable();

            $table->enum('occupancy_type', OccupancyType::values())->default(OccupancyType::OWNER_OCCUPIED->value);
            $table->enum('status', UnitStatus::values())->default(UnitStatus::ACTIVE->value);

            $table->decimal('levy_override', 12, 2)->nullable();
            $table->decimal('rent_amount', 12, 2)->nullable();

            $table->foreignUuid('estate_id')->constrained('estates')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['estate_id', 'unit_number']);
            $table->index('estate_id');
            $table->index('tenant_id');
            $table->index('status');
            $table->index('occupancy_type');
            $table->index(['estate_id', 'status']);
            $table->index(['estate_id', 'occupancy_type']);
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
