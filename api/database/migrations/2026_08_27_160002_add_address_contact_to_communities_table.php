<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WeConnectU "Setup → Address & Contact Details" fields. The existing `address`
 * column serves as the Physical Address; this adds the Postal Address, Contact
 * Number and Contact Email address.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->text('postal_address')->nullable()->after('address');
            $table->string('contact_number', 50)->nullable()->after('postal_address');
            $table->string('contact_email', 255)->nullable()->after('contact_number');
        });
    }

    public function down(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->dropColumn(['postal_address', 'contact_number', 'contact_email']);
        });
    }
};
