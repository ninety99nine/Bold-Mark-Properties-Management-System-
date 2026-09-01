<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds an optional document attachment (WeConnectU "Upload Document" on a
     * Customer Note) — a stored file name + its path on the public disk.
     */
    public function up(): void
    {
        Schema::table('unit_collection_notes', function (Blueprint $table) {
            $table->string('document_name')->nullable()->after('note');
            $table->string('document_path')->nullable()->after('document_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unit_collection_notes', function (Blueprint $table) {
            $table->dropColumn(['document_name', 'document_path']);
        });
    }
};
