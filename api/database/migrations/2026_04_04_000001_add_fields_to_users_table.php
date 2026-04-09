<?php

use App\Enums\UserStatus;
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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('name');
            $table->enum('status', UserStatus::values())->default(UserStatus::ACTIVE->value)->after('phone');
            $table->timestamp('last_login_at')->nullable()->after('status');
            $table->foreignUuid('tenant_id')->nullable()->after('last_login_at')->constrained('tenants')->nullOnDelete();

            $table->index('status');
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['tenant_id']);
            $table->dropColumn(['phone', 'status', 'last_login_at', 'tenant_id']);
        });
    }
};
