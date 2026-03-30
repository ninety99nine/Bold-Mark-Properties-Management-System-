<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique(); // subdomain identifier, e.g. "boldmark"
            $table->string('logo_url')->nullable();
            $table->string('primary_color')->default('#0B1F38');
            $table->string('accent_color')->default('#D89B4B');
            $table->json('credentials')->nullable(); // e.g. ["NAMA-9141", "PPRA Registered", "Johannesburg · Botswana"]
            $table->string('copyright_name')->nullable(); // defaults to name if null
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
