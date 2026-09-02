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
        Schema::table('owners', function (Blueprint $table) {
            // WeConnectU owner sheet captures the registered-entity name + reg number
            // per legal-entity type (Trust / CC / Pty / Body Corporate).
            $table->string('trust_name')->nullable()->after('entity_type');
            $table->string('trust_reg')->nullable()->after('trust_name');
            $table->string('cc_name')->nullable()->after('trust_reg');
            $table->string('cc_reg_no')->nullable()->after('cc_name');
            $table->string('pty_name')->nullable()->after('cc_reg_no');
            $table->string('pty_reg_no')->nullable()->after('pty_name');
            $table->string('body_corporate_name')->nullable()->after('pty_reg_no');
            $table->string('body_corporate_reg_no')->nullable()->after('body_corporate_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            $table->dropColumn([
                'trust_name', 'trust_reg',
                'cc_name', 'cc_reg_no',
                'pty_name', 'pty_reg_no',
                'body_corporate_name', 'body_corporate_reg_no',
            ]);
        });
    }
};
