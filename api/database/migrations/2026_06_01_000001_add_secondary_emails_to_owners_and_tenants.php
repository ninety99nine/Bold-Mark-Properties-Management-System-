<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('owners', 'secondary_emails')) {
            Schema::table('owners', function (Blueprint $table) {
                $table->json('secondary_emails')->nullable()->after('email');
            });
        }

        if (!Schema::hasColumn('tenants', 'secondary_emails')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->json('secondary_emails')->nullable()->after('email');
            });
        }
    }

    public function down(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            $table->dropColumn('secondary_emails');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('secondary_emails');
        });
    }
};
