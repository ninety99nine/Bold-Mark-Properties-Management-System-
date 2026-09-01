<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Replaces the single document_name/document_path columns on
     * unit_collection_notes with a one-to-many note_attachments table
     * (WeConnectU parity: up to 5 attachments per collection note). The old
     * columns are left in place (unused) and their data is migrated across.
     */
    public function up(): void
    {
        Schema::create('note_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('unit_collection_note_id')
                ->constrained('unit_collection_notes')
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('path');
            $table->uuid('organization_id')->index();
            $table->timestamps();
        });

        // Data-migrate existing single documents into the new table.
        $now = Carbon::now();

        DB::table('unit_collection_notes')
            ->whereNotNull('document_path')
            ->orderBy('id')
            ->each(function ($note) use ($now) {
                DB::table('note_attachments')->insert([
                    'id'                      => (string) Str::uuid(),
                    'unit_collection_note_id' => $note->id,
                    'name'                    => $note->document_name ?: 'Attachment',
                    'path'                    => $note->document_path,
                    'organization_id'         => $note->organization_id,
                    'created_at'              => $now,
                    'updated_at'              => $now,
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('note_attachments');
    }
};
