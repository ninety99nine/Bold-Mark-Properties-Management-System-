<?php

namespace App\Services;

use Exception;
use App\Enums\TakeonItemStatus;
use App\Models\Community;
use App\Models\CommunityTakeonItem;
use Illuminate\Support\Facades\Storage;

class CommunityTakeonService extends BaseService
{
    /**
     * The file-backed take-on steps (WeConnectU: each can be uploaded or N/A).
     */
    public const FILE_KEYS = ['owner_sheet', 'budget'];

    /**
     * Return the take-on history for a community, keyed by step. Each step carries
     * a `done` flag and an append-only list of entries (uploads + N/A markers),
     * mirroring WeConnectU's take-on activity log.
     *
     * @param Community $community
     * @return array
     */
    public function status(Community $community): array
    {
        $grouped = CommunityTakeonItem::where('community_id', $community->id)
            ->orderBy('created_at')
            ->get()
            ->groupBy('key');

        $out = [];
        foreach (self::FILE_KEYS as $key) {
            $entries = ($grouped->get($key) ?? collect())->map(fn (CommunityTakeonItem $item) => [
                'id'        => $item->id,
                'status'    => $item->status->value,
                'file_name' => $item->file_name,
                'date'      => $item->created_at?->toDateString(),
                'has_file'  => (bool) $item->file_path,
            ])->values()->all();

            $out[$key] = [
                'done'    => count($entries) > 0,
                'entries' => $entries,
            ];
        }

        return ['items' => $out];
    }

    /**
     * Append an "uploaded" entry for a take-on step, storing the raw file. Every
     * upload is kept (versioned history) — previous files are not removed.
     *
     * @param Community $community
     * @param string $key
     * @param mixed $file
     * @return void
     */
    public function recordUpload(Community $community, string $key, mixed $file): void
    {
        if (! $file) {
            return;
        }

        $path = $file->store("takeon/{$community->id}/{$key}", 'public');

        CommunityTakeonItem::create([
            'key'             => $key,
            'status'          => TakeonItemStatus::UPLOADED->value,
            'file_path'       => $path,
            'file_name'       => $file->getClientOriginalName(),
            'uploaded_at'     => now(),
            'community_id'    => $community->id,
            'organization_id' => $community->organization_id,
        ]);
    }

    /**
     * Append a "Not Applicable" entry for a take-on step.
     *
     * @param Community $community
     * @param string $key
     * @return array
     * @throws Exception
     */
    public function markNotApplicable(Community $community, string $key): array
    {
        $this->assertValidKey($key);

        CommunityTakeonItem::create([
            'key'             => $key,
            'status'          => TakeonItemStatus::NOT_APPLICABLE->value,
            'community_id'    => $community->id,
            'organization_id' => $community->organization_id,
        ]);

        return ['status' => TakeonItemStatus::NOT_APPLICABLE->value, 'message' => 'Take-on file status changed.'];
    }

    /**
     * Stream a specific take-on entry's stored file as a download.
     *
     * @param Community $community
     * @param CommunityTakeonItem $item
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     * @throws Exception
     */
    public function downloadItem(Community $community, CommunityTakeonItem $item): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        if ($item->community_id !== $community->id || ! $item->file_path || ! Storage::disk('public')->exists($item->file_path)) {
            throw new Exception('No file has been uploaded for this entry.');
        }

        return Storage::disk('public')->download($item->file_path, $item->file_name);
    }

    /**
     * Guard that a step key is one of the known file-backed steps.
     *
     * @param string $key
     * @return void
     * @throws Exception
     */
    private function assertValidKey(string $key): void
    {
        if (! in_array($key, self::FILE_KEYS, true)) {
            throw new Exception('Unknown take-on step.');
        }
    }
}
