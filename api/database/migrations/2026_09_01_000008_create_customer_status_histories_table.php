<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Status Management (WeConnectU parity): log every collection-status change
 * applied to a customer (unit), plus a per-customer "Exempt from Interest" flag.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('units', 'interest_exempt')) {
            Schema::table('units', function (Blueprint $table) {
                $table->boolean('interest_exempt')->default(false)->after('collection_status');
            });
        }

        if (Schema::hasTable('customer_status_histories')) {
            return;
        }

        Schema::create('customer_status_histories', function (Blueprint $table) {

            $table->uuid('id')->primary();
            $table->string('status');
            $table->text('note')->nullable();
            $table->date('status_date');
            $table->boolean('is_automatic')->default(false);
            $table->string('changed_by_name')->nullable();

            $table->foreignUuid('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignUuid('community_id')->constrained('communities')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('unit_id');
            $table->index('community_id');
            $table->index('status_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_status_histories');

        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('interest_exempt');
        });
    }
};
