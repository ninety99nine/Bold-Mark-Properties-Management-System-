<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-community email branding overrides backing the community
 * Communicate → Settings tab (WeConnectU parity): an optional header logo
 * plus header/footer images. When set, these override the organization's
 * default email header/footer for communications sent from this community.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->string('email_logo_url')->nullable();
            $table->string('email_header_url')->nullable();
            $table->string('email_footer_url')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->dropColumn(['email_logo_url', 'email_header_url', 'email_footer_url']);
        });
    }
};
