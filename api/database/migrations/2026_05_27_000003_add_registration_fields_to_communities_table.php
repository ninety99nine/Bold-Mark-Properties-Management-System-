<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->string('registration_number', 100)->nullable()->after('currency');
            $table->string('csos_registration_number', 100)->nullable()->after('registration_number');
            $table->string('income_tax_number', 100)->nullable()->after('csos_registration_number');
        });
    }

    public function down(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->dropColumn(['registration_number', 'csos_registration_number', 'income_tax_number']);
        });
    }
};
