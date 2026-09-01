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
        Schema::table('units', function (Blueprint $table) {
            $table->string('rental_agent_email')->nullable()->after('address');
            $table->string('attorney_email')->nullable()->after('rental_agent_email');
            $table->string('bondholder_email')->nullable()->after('attorney_email');
            $table->text('unit_notes')->nullable()->after('bondholder_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn(['rental_agent_email', 'attorney_email', 'bondholder_email', 'unit_notes']);
        });
    }
};
