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
        Schema::create('tenants', function (Blueprint $table) {

            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('company_name')->nullable();
            $table->string('company_slogan')->nullable();
            $table->string('logo_url')->nullable();

            $table->boolean('is_active')->default(true);

            $table->string('contact_email');
            $table->string('contact_phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('country', 3)->default('ZA');
            $table->string('currency', 3)->default('ZAR');

            $table->string('primary_color', 10)->default('#1F3A5C');
            $table->string('secondary_color', 10)->default('#D89B4B');
            $table->string('copyright_name')->nullable();

            $table->json('credentials')->nullable();

            $table->timestamps();

            $table->index('slug');
            $table->index('is_active');
            $table->index('country');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
