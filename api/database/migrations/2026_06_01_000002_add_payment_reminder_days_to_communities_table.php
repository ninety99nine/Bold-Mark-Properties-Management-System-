<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', function (Blueprint $table): void {
            // Null = reminders disabled. Integer = days after due date to send reminder.
            $table->unsignedSmallInteger('payment_reminder_days')->nullable()->after('payment_terms_days');
        });
    }

    public function down(): void
    {
        Schema::table('communities', function (Blueprint $table): void {
            $table->dropColumn('payment_reminder_days');
        });
    }
};
