<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_activities', function (Blueprint $table) {

            $table->uuid('id')->primary();

            $table->foreignUuid('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            // Who made the change (nullable so system events are supported)
            // users.id is bigint (default Laravel convention)
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('changed_by_name')->nullable();

            // Human-readable event description, e.g. "Updated owner details"
            $table->string('event');

            // Category for badge colouring: unit | owner | tenant | charges
            $table->string('category', 20);

            // Field-level diff: [{ field, old, new }, ...]
            $table->json('changes')->nullable();

            $table->timestamps();

            $table->index('unit_id');
            $table->index('tenant_id');
            $table->index(['unit_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_activities');
    }
};
