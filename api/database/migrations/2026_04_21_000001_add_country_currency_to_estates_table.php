<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estates', function (Blueprint $table) {
            // Country and currency per estate — defaults to the tenant's settings
            // but can be overridden for multi-country portfolios (e.g. Botswana + SA)
            $table->string('country', 3)->nullable()->after('billing_day');
            $table->string('currency', 3)->nullable()->after('country');

            $table->index('country');
        });
    }

    public function down(): void
    {
        Schema::table('estates', function (Blueprint $table) {
            $table->dropIndex(['country']);
            $table->dropColumn(['country', 'currency']);
        });
    }
};
