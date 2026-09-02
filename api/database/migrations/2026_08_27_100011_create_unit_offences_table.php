<?php

use App\Enums\OffenceStatus;
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
        Schema::create('unit_offences', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->enum('status', OffenceStatus::values())->default(OffenceStatus::WARNING->value);
            $table->date('issued_date')->nullable();
            $table->text('description')->nullable();

            $table->json('rules')->nullable();
            $table->json('attachment_names')->nullable();

            $table->string('created_by_name')->nullable();
            $table->uuid('user_id')->nullable();
            $table->foreignUuid('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();

            $table->timestamps();

            $table->index('unit_id');
            $table->index('organization_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_offences');
    }
};
