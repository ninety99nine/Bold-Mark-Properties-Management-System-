<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->string('merchant_number', 100)->nullable()->after('income_tax_number');
            $table->boolean('pdf_passwords')->default(false)->after('merchant_number');
        });
    }

    public function down(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->dropColumn(['merchant_number', 'pdf_passwords']);
        });
    }
};
