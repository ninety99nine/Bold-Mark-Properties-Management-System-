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
        Schema::create('customer_group_owner', function (Blueprint $table) {
            $table->foreignUuid('customer_group_id')->constrained('customer_groups')->cascadeOnDelete();
            $table->foreignUuid('owner_id')->constrained('owners')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['customer_group_id', 'owner_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_group_owner');
    }
};
