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
        Schema::table('notice_batch_items', function (Blueprint $table) {
            $table->decimal('charge', 12, 2)->default(0)->after('balance');
            $table->text('sent_to')->nullable()->after('charge');
            $table->string('customer_type')->nullable()->after('sent_to');
            $table->uuid('invoice_id')->nullable()->after('customer_type');
            $table->timestamp('credited_at')->nullable()->after('invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notice_batch_items', function (Blueprint $table) {
            $table->dropColumn(['charge', 'sent_to', 'customer_type', 'invoice_id', 'credited_at']);
        });
    }
};
