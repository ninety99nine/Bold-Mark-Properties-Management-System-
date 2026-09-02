<?php

use App\Enums\TakeonItemStatus;
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
        Schema::create('community_takeon_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Which take-on step this row tracks (e.g. owner_sheet, budget).
            $table->string('key', 50);
            $table->enum('status', TakeonItemStatus::values())->default(TakeonItemStatus::PENDING->value);

            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();

            $table->timestamp('uploaded_at')->nullable();

            $table->foreignUuid('community_id')->constrained('communities')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['community_id', 'key']);
            $table->index('community_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('community_takeon_items');
    }
};
