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
        Schema::create('orders', function (Blueprint $table) {

            $table->uuid('id')->primary();

            $table->string('reference', 64);
            $table->string('currency', 3)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('channel', 40)->nullable();
            $table->string('external_id', 128)->nullable();

            $table->boolean('fulfilled');
            $table->boolean('simulated');

            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('open_disputes_count')->default(0);
            $table->unsignedInteger('total_amount')->default(0);

            $table->text('notes')->nullable();
            $table->text('failure_reason')->nullable();

            $table->json('metadata')->nullable();

            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();

            $table->timestamps();

            $table->index('reference');
            $table->index('currency');
            $table->index('country');
            $table->index('channel');
            $table->index('open_disputes_count');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
