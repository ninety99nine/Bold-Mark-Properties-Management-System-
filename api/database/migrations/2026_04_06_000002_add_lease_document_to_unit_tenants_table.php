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
        Schema::table('unit_tenants', function (Blueprint $table) {
            $table->string('lease_document_url')->nullable()->after('lease_end');
            $table->string('lease_document_name')->nullable()->after('lease_document_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unit_tenants', function (Blueprint $table) {
            $table->dropColumn(['lease_document_url', 'lease_document_name']);
        });
    }
};
