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
        Schema::create('user_estates', function (Blueprint $table) {

            $table->uuid('id')->primary();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('estate_id')->constrained('estates')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['user_id', 'estate_id']);
            $table->index('user_id');
            $table->index('estate_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_estates');
    }
};
