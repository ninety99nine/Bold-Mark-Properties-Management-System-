<?php

namespace App\Services;

use App\Models\Community;
use App\Models\NoteAttachment;
use App\Models\Unit;
use App\Models\UnitCollectionNote;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CustomerNoteService extends BaseService
{
    /**
     * The maximum number of attachments allowed on a single note (WeConnectU parity).
     */
    private const MAX_ATTACHMENTS = 5;

    /**
     * Return the customer notes for a unit (newest first).
     *
     * @param Community $community
     * @param Unit $unit
     * @return array
     */
    public function index(Community $community, Unit $unit): array
    {
        $this->assertUnitInCommunity($community, $unit);

        $notes = UnitCollectionNote::where('unit_id', $unit->id)
            ->where('organization_id', Auth::user()->organization_id)
            ->with('attachments')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (UnitCollectionNote $note) => [
                'id'              => $note->id,
                'note'            => $note->note,
                'is_system'       => (bool) $note->is_system,
                // WeConnectU shows edit/delete only on manual notes.
                'editable'        => !$note->is_system,
                'attachments'     => $note->attachments->map(fn (NoteAttachment $a) => [
                    'id'   => $a->id,
                    'name' => $a->name,
                    'url'  => Storage::disk('public')->url($a->path),
                ])->all(),
                'created_by_name' => $note->created_by_name,
                'created_at'      => $note->created_at?->toDateTimeString(),
            ])
            ->all();

        return ['data' => $notes];
    }

    /**
     * Create a customer note (with up to five uploaded documents).
     *
     * @param Community $community
     * @param Unit $unit
     * @param array $data
     * @return array
     */
    public function store(Community $community, Unit $unit, array $data): array
    {
        $this->assertUnitInCommunity($community, $unit);

        $user  = Auth::user();
        $files = array_values(array_filter((array) request()->file('documents')));

        abort_if(count($files) > self::MAX_ATTACHMENTS, 422, 'A note can have at most 5 attachments.');

        $note = UnitCollectionNote::create([
            'unit_id'         => $unit->id,
            'organization_id' => $user->organization_id,
            'note'            => $data['note'] ?? null,
            'created_by_name' => $user->name ?? 'System',
            'user_id'         => $user->id,
        ]);

        $single = count($files) === 1;

        foreach ($files as $file) {
            /** @var UploadedFile $file */
            $name = ($single && !empty($data['document_name']))
                ? $data['document_name']
                : $file->getClientOriginalName();

            NoteAttachment::create([
                'unit_collection_note_id' => $note->id,
                'name'                    => $name,
                'path'                    => $file->store("collection_notes/{$user->organization_id}", 'public'),
                'organization_id'         => $user->organization_id,
            ]);
        }

        return ['message' => 'Note added.'];
    }

    /**
     * Update a customer note — edit its text, remove attachments, and/or add more.
     *
     * @param Community $community
     * @param Unit $unit
     * @param UnitCollectionNote $note
     * @param array $data
     * @return array
     */
    public function update(Community $community, Unit $unit, UnitCollectionNote $note, array $data): array
    {
        $this->assertUnitInCommunity($community, $unit);
        $this->assertNoteOnUnit($unit, $note);
        abort_if($note->is_system, 403, 'System-generated notes cannot be edited.');

        $user = Auth::user();

        $note->update(['note' => $data['note'] ?? null]);

        // Remove selected attachments (files + rows).
        $removeIds = array_filter((array) ($data['remove_attachment_ids'] ?? []));
        if ($removeIds) {
            $toRemove = $note->attachments()->whereIn('id', $removeIds)->get();
            foreach ($toRemove as $attachment) {
                if ($attachment->path && Storage::disk('public')->exists($attachment->path)) {
                    Storage::disk('public')->delete($attachment->path);
                }
                $attachment->delete();
            }
        }

        // Add newly uploaded attachments, enforcing the hard cap of five.
        $files = array_values(array_filter((array) request()->file('documents')));
        if ($files) {
            $remaining = $note->attachments()->count();
            abort_if($remaining + count($files) > self::MAX_ATTACHMENTS, 422, 'A note can have at most 5 attachments.');

            $single = count($files) === 1;

            foreach ($files as $file) {
                /** @var UploadedFile $file */
                $name = ($single && !empty($data['document_name']))
                    ? $data['document_name']
                    : $file->getClientOriginalName();

                NoteAttachment::create([
                    'unit_collection_note_id' => $note->id,
                    'name'                    => $name,
                    'path'                    => $file->store("collection_notes/{$user->organization_id}", 'public'),
                    'organization_id'         => $user->organization_id,
                ]);
            }
        }

        return ['message' => 'Note updated.'];
    }

    /**
     * Delete a customer note (and its attachment files).
     *
     * @param Community $community
     * @param Unit $unit
     * @param UnitCollectionNote $note
     * @return array
     */
    public function destroy(Community $community, Unit $unit, UnitCollectionNote $note): array
    {
        $this->assertUnitInCommunity($community, $unit);
        $this->assertNoteOnUnit($unit, $note);
        abort_if($note->is_system, 403, 'System-generated notes cannot be deleted.');

        // Delete files from disk; the DB rows cascade on note delete.
        foreach ($note->attachments as $attachment) {
            if ($attachment->path && Storage::disk('public')->exists($attachment->path)) {
                Storage::disk('public')->delete($attachment->path);
            }
        }

        $note->delete();

        return ['message' => 'Note deleted.'];
    }

    /**
     * Log a customer phone call as a note (WeConnectU "Customer Phonecall").
     *
     * @param Community $community
     * @param Unit $unit
     * @param array $data
     * @return array
     */
    public function phonecall(Community $community, Unit $unit, array $data): array
    {
        $this->assertUnitInCommunity($community, $unit);

        $user = Auth::user();

        // TODO: post community->phonecall_fee charge — owned by finance module
        $addCharge = (bool) ($data['add_charge'] ?? false);

        UnitCollectionNote::create([
            'unit_id'         => $unit->id,
            'organization_id' => $user->organization_id,
            'note'            => $data['note'] ?? null,
            'created_by_name' => $user->name ?? 'System',
            'user_id'         => $user->id,
        ]);

        return ['message' => 'Phone call logged.'];
    }

    /**
     * Ensure the unit belongs to the community.
     *
     * @param Community $community
     * @param Unit $unit
     * @return void
     */
    private function assertUnitInCommunity(Community $community, Unit $unit): void
    {
        abort_unless($unit->community_id === $community->id, 404);
    }

    /**
     * Ensure the note belongs to the unit.
     *
     * @param Unit $unit
     * @param UnitCollectionNote $note
     * @return void
     */
    private function assertNoteOnUnit(Unit $unit, UnitCollectionNote $note): void
    {
        abort_unless($note->unit_id === $unit->id, 404);
    }
}
