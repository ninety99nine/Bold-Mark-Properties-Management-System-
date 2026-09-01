<?php

use App\Enums\CommunityEntityType;
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
        Schema::create('communities', function (Blueprint $table) {

            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('address')->nullable();

            $table->enum('entity_type', CommunityEntityType::values())->default(CommunityEntityType::BODY_CORPORATE->value);
            $table->boolean('is_active')->default(true);

            $table->decimal('default_levy_amount', 12, 2)->nullable();
            $table->decimal('default_rent_amount', 12, 2)->nullable();
            $table->unsignedInteger('billing_day')->default(25);

            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->timestamps();

            $table->index('organization_id');
            $table->index('entity_type');
            $table->index('is_active');
            $table->index(['organization_id', 'is_active']);
            $table->index(['organization_id', 'entity_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communities');
    }
};
