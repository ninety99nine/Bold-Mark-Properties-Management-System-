<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_sessions', function (Blueprint $table) {
            // Sliding-window inactivity tracking (BM-006). Bumped on every
            // authenticated request; a session idle past the configured
            // timeout is revoked.
            $table->timestamp('last_activity_at')->nullable()->after('user_agent');

            // Persistent login (BM-009). When true the session is exempt from
            // the inactivity timeout above.
            $table->boolean('remember')->default(false)->after('last_activity_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_sessions', function (Blueprint $table) {
            $table->dropColumn(['last_activity_at', 'remember']);
        });
    }
};
