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
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('issued_by_type')->default('system')->after('sent_at');
            $table->unsignedBigInteger('issued_by_user_id')->nullable()->after('issued_by_type');
            $table->foreign('issued_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->index('issued_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign('invoices_issued_by_user_id_foreign');
            $table->dropIndex('invoices_issued_by_user_id_index');
            $table->dropColumn(['issued_by_type', 'issued_by_user_id']);
        });
    }
};
