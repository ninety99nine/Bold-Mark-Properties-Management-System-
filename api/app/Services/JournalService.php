<?php

namespace App\Services;

use App\Enums\JournalEntryType;
use App\Enums\JournalLineType;
use App\Exports\JournalBatchExport;
use App\Exports\SimpleExport;
use App\Http\Resources\JournalBatchResource;
use App\Http\Resources\JournalBatchResources;
use App\Imports\JournalBatchImport;
use App\Models\Community;
use App\Models\JournalBatch;
use App\Models\Ledger;
use App\Models\Unit;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class JournalService extends BaseService
{
    protected string $resourceClass           = JournalBatchResource::class;
    protected string $resourceCollectionClass = JournalBatchResources::class;
    protected string $dateRangeColumn         = 'date';

    /** Column headings for the batch-upload template and the Excel exports. */
    private const TEMPLATE_HEADINGS = [
        'Ledger Type (general / customer / supplier / reserve fund)',
        'Account  (e.g. 1000/001 or customer code)',
        'Description',
        'Debit',
        'Credit',
        'Balance (for checking purposes only)',
    ];

    public function __construct(private readonly UnitBalanceService $unitBalance)
    {
        parent::__construct();
    }

    /**
     * Paginated, filtered list of journal batches for a community.
     *
     * @param array $data
     * @return ResourceCollection
     */
    public function showJournalBatches(array $data): ResourceCollection
    {
        $user = Auth::user();

        $community = $this->resolveCommunity($data['community_id']);

        $query = JournalBatch::where('organization_id', $user->organization_id)
            ->where('community_id', $community->id)
            ->manual()
            ->withCount('lines')
            ->with(['createdBy', 'updatedBy']);

        if (!empty($data['financial_year'])) {
            $query->where('financial_year', (int) $data['financial_year']);
        }

        if (!empty($data['date_from'])) {
            $query->whereDate('date', '>=', $data['date_from']);
        }

        if (!empty($data['date_to'])) {
            $query->whereDate('date', '<=', $data['date_to']);
        }

        if (!empty($data['search'])) {
            $query->search($data['search']);
        }

        if (!request()->has('_sort')) {
            $query->orderByDesc('date')->orderByDesc('batch_number');
        }

        return $this->setQuery($query)->getOutput();
    }

    /**
     * Create a balanced, WeConnectU-style manual journal batch.
     *
     * @param array $data
     * @return array
     * @throws Exception
     */
    public function createJournalBatch(array $data): array
    {
        $user      = Auth::user();
        $community = $this->resolveCommunity($data['community_id']);

        $lines = $this->normaliseLines($data['lines'], $community);
        $files = $this->persistFiles($data['files'] ?? [], $user->organization_id);

        $batch = $this->persistBatch($community, $data, $lines, $files);

        $this->recalculateAffectedUnits($lines);

        return $this->showCreatedResource($this->loadBatch($batch));
    }

    /**
     * Show a single journal batch with its lines and account labels.
     *
     * @param JournalBatch $journalBatch
     * @return JournalBatchResource
     */
    public function showJournalBatch(JournalBatch $journalBatch): JournalBatchResource
    {
        return $this->showResource($this->loadBatch($journalBatch));
    }

    /**
     * Update a journal batch: replace its lines, refresh files and re-post any
     * affected customer balances (old customers + new customers).
     *
     * @param JournalBatch $journalBatch
     * @param array        $data
     * @return array
     * @throws Exception
     */
    public function updateJournalBatch(JournalBatch $journalBatch, array $data): array
    {
        $user = Auth::user();

        $journalBatch->load('lines');
        $previousUnitIds = $journalBatch->lines
            ->where('line_type', JournalLineType::CUSTOMER)
            ->pluck('unit_id')
            ->filter()
            ->all();

        $lines = $this->normaliseLines($data['lines'], $journalBatch->community);

        // Keep only the existing files the caller retained, then append new ones.
        $keptPaths = collect($data['existing_files'] ?? [])->pluck('path')->filter()->all();
        $keptFiles = collect($journalBatch->files ?? [])
            ->filter(fn ($f) => in_array($f['path'] ?? null, $keptPaths, true))
            ->values()
            ->all();

        // Delete files that were removed by the caller.
        collect($journalBatch->files ?? [])
            ->reject(fn ($f) => in_array($f['path'] ?? null, $keptPaths, true))
            ->each(fn ($f) => isset($f['path']) && Storage::disk('public')->delete($f['path']));

        $newFiles = $this->persistFiles($data['files'] ?? [], $user->organization_id);
        $files    = array_merge($keptFiles, $newFiles);

        DB::transaction(function () use ($journalBatch, $data, $lines, $files, $user) {
            $journalBatch->update([
                'date'               => Carbon::parse($data['date'])->format('Y-m-d'),
                'journal_group'      => $data['journal_group'] ?? null,
                'files'              => $files,
                'updated_by_user_id' => $user->id,
            ]);

            $journalBatch->lines()->delete();
            $this->writeLines($journalBatch, $lines);
        });

        // Re-post balances for every customer touched before or after the edit.
        $newUnitIds = collect($lines)->where('line_type', JournalLineType::CUSTOMER->value)
            ->pluck('unit_id')->filter()->all();

        $this->recalculateUnitIds(array_unique(array_merge($previousUnitIds, $newUnitIds)));

        return $this->showUpdatedResource($this->loadBatch($journalBatch->fresh()));
    }

    /**
     * Delete a journal batch, its files and re-post affected customer balances.
     *
     * @param JournalBatch $journalBatch
     * @return array
     */
    public function deleteJournalBatch(JournalBatch $journalBatch): array
    {
        $journalBatch->load('lines');
        $unitIds = $journalBatch->lines
            ->where('line_type', JournalLineType::CUSTOMER)
            ->pluck('unit_id')->filter()->all();

        collect($journalBatch->files ?? [])
            ->each(fn ($f) => isset($f['path']) && Storage::disk('public')->delete($f['path']));

        $deleted = $journalBatch->delete();

        if ($deleted) {
            $this->recalculateUnitIds($unitIds);
        }

        return [
            'deleted' => (bool) $deleted,
            'message' => $deleted ? 'Journal batch deleted' : 'Journal batch delete unsuccessful',
        ];
    }

    /**
     * Import a completed journal-batch spreadsheet into a new batch.
     *
     * @param array $data
     * @return array
     * @throws Exception
     */
    public function uploadJournalBatch(array $data): array
    {
        $user      = Auth::user();
        $community = $this->resolveCommunity($data['community_id']);

        /** @var UploadedFile $file */
        $file = $data['journal_file'];

        $sheets = Excel::toArray(new JournalBatchImport(), $file);
        $rows   = $sheets[0] ?? [];

        $lineData = $this->parseUploadedRows($rows, $community);

        if (count($lineData) < 2) {
            throw new Exception('The uploaded file did not contain at least two valid journal lines.');
        }

        $lines       = $this->normaliseLines($lineData, $community);
        $extraFiles  = $this->persistFiles($data['files'] ?? [], $user->organization_id);

        // Keep the source spreadsheet as an attachment on the batch.
        $sourcePath  = $file->store("journal-batches/{$user->organization_id}", 'public');
        $files       = array_merge([['name' => $file->getClientOriginalName(), 'path' => $sourcePath]], $extraFiles);

        $batch = $this->persistBatch($community, $data, $lines, $files);

        $this->recalculateAffectedUnits($lines);

        return $this->showCreatedResource($this->loadBatch($batch));
    }

    /**
     * Stream the blank batch-upload template (the "Download Template" link).
     *
     * @return Response
     */
    public function downloadTemplate(): Response
    {
        $example = [
            ['general',  '1000/001',   'Test', 8000, '',     8000],
            ['customer', 'DIR001-U1',  'Test', '',   4000,   -8000],
            ['general',  '1000/002',   'Test', '',   4000,   0],
        ];

        return Excel::download(new SimpleExport($example, self::TEMPLATE_HEADINGS), 'journal-batch.xlsx');
    }

    /**
     * Excel export of the batch list (the "Download Excel" button).
     *
     * @param array $data
     * @return Response
     */
    public function downloadBatchesExcel(array $data): Response
    {
        $user      = Auth::user();
        $community = $this->resolveCommunity($data['community_id']);

        $query = JournalBatch::where('organization_id', $user->organization_id)
            ->where('community_id', $community->id)
            ->manual()
            ->withCount('lines')
            ->with(['createdBy', 'updatedBy']);

        if (!empty($data['financial_year'])) {
            $query->where('financial_year', (int) $data['financial_year']);
        }
        if (!empty($data['date_from'])) {
            $query->whereDate('date', '>=', $data['date_from']);
        }
        if (!empty($data['date_to'])) {
            $query->whereDate('date', '<=', $data['date_to']);
        }

        $batches = $query->orderByDesc('date')->orderByDesc('batch_number')->get();

        $headings = ['Date', 'Batch Name', 'Journal Group', 'Entries', 'Created By', 'Created On', 'Last Updated By', 'Last Updated On'];

        $rows = $batches->map(fn (JournalBatch $b) => [
            $b->date?->format('Y-m-d'),
            $b->batch_name,
            $b->journal_group ?? '',
            $b->lines_count,
            $b->createdBy?->name ?? '',
            $b->created_at?->format('Y-m-d'),
            $b->updatedBy?->name ?? '',
            $b->updated_at?->format('Y-m-d'),
        ])->all();

        return Excel::download(new SimpleExport($rows, $headings), 'journals.xlsx');
    }

    /**
     * Excel export of a single batch (the per-row download icon).
     *
     * @param JournalBatch $journalBatch
     * @return Response
     */
    public function downloadBatch(JournalBatch $journalBatch): Response
    {
        $journalBatch->load(['lines.ledger', 'lines.unit.owner', 'lines.unit.currentOccupant']);

        // WeConnectU filename: "journal batch - {date}-{batch name}-.xlsx"
        $date     = optional($journalBatch->date)->format('Y-m-d');
        $filename = 'journal batch - ' . $date . '-' . strtolower($journalBatch->batch_name) . '-.xlsx';

        return Excel::download(new JournalBatchExport($journalBatch), $filename);
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Persist a batch header + its lines inside a transaction, allocating the
     * next per-community batch number.
     */
    private function persistBatch(Community $community, array $data, array $lines, array $files): JournalBatch
    {
        $user = Auth::user();

        return DB::transaction(function () use ($user, $community, $data, $lines, $files) {
            $batch = JournalBatch::create([
                'batch_number'       => $this->nextBatchNumber($community->id),
                'journal_group'      => $data['journal_group'] ?? null,
                'date'               => Carbon::parse($data['date'])->format('Y-m-d'),
                'financial_year'     => (int) $data['financial_year'],
                'files'              => $files,
                'community_id'       => $community->id,
                'organization_id'    => $user->organization_id,
                'created_by_user_id' => $user->id,
            ]);

            $this->writeLines($batch, $lines);

            return $batch;
        });
    }

    /**
     * Write normalised line rows onto a batch.
     */
    private function writeLines(JournalBatch $batch, array $lines): void
    {
        foreach (array_values($lines) as $index => $line) {
            $batch->lines()->create([
                'line_type'       => $line['line_type'],
                'ledger_id'       => $line['ledger_id'] ?? null,
                'unit_id'         => $line['unit_id'] ?? null,
                'description'     => $line['description'] ?? null,
                'amount'          => round((float) $line['amount'], 2),
                'entry_type'      => $line['entry_type'],
                'sort_order'      => $index,
                'organization_id' => $batch->organization_id,
            ]);
        }
    }

    /**
     * Validate + normalise raw line input, dropping account ids that don't
     * belong to this organization / community.
     *
     * @throws Exception
     */
    private function normaliseLines(array $rawLines, Community $community): array
    {
        $user  = Auth::user();
        $lines = [];

        foreach ($rawLines as $row) {
            $type      = $row['line_type'];
            $ledgerId  = null;
            $unitId    = null;

            if (in_array($type, [JournalLineType::GENERAL->value, JournalLineType::RESERVE_FUND->value], true)) {
                $ledgerId = Ledger::where('id', $row['ledger_id'] ?? null)
                    ->where('organization_id', $user->organization_id)
                    ->value('id');

                if (!$ledgerId) {
                    throw new Exception('A journal line references an account that does not exist.');
                }
            }

            if ($type === JournalLineType::CUSTOMER->value) {
                $unitId = Unit::where('id', $row['unit_id'] ?? null)
                    ->where('organization_id', $user->organization_id)
                    ->where('community_id', $community->id)
                    ->value('id');

                if (!$unitId) {
                    throw new Exception('A journal line references a customer that does not exist in this community.');
                }
            }

            $lines[] = [
                'line_type'   => $type,
                'ledger_id'   => $ledgerId,
                'unit_id'     => $unitId,
                'description' => $row['description'] ?? null,
                'amount'      => round((float) ($row['amount'] ?? 0), 2),
                'entry_type'  => $row['entry_type'],
            ];
        }

        return $lines;
    }

    /**
     * Turn raw spreadsheet rows into the line shape used by normaliseLines().
     *
     * @throws Exception
     */
    private function parseUploadedRows(array $rows, Community $community): array
    {
        $user  = Auth::user();
        $lines = [];

        foreach ($rows as $index => $row) {
            // Skip the heading row and any blank rows.
            if ($index === 0) {
                continue;
            }

            $rawType    = strtolower(trim((string) ($row[0] ?? '')));
            $account    = trim((string) ($row[1] ?? ''));
            $descr      = trim((string) ($row[2] ?? ''));
            $debit      = $this->toAmount($row[3] ?? null);
            $credit     = $this->toAmount($row[4] ?? null);

            if ($rawType === '' && $account === '') {
                continue;
            }

            $type = match (true) {
                str_starts_with($rawType, 'gen')                       => JournalLineType::GENERAL->value,
                str_starts_with($rawType, 'cust')                      => JournalLineType::CUSTOMER->value,
                str_starts_with($rawType, 'sup')                       => JournalLineType::SUPPLIER->value,
                str_contains($rawType, 'reserve') || $rawType === 'rf' => JournalLineType::RESERVE_FUND->value,
                default => throw new Exception("Row " . ($index + 1) . ": unknown ledger type \"{$row[0]}\"."),
            };

            $ledgerId = null;
            $unitId   = null;

            if (in_array($type, [JournalLineType::GENERAL->value, JournalLineType::RESERVE_FUND->value], true)) {
                $ledgerId = Ledger::where('organization_id', $user->organization_id)
                    ->where('code', $account)
                    ->value('id');

                if (!$ledgerId) {
                    throw new Exception("Row " . ($index + 1) . ": no account with code \"{$account}\".");
                }
            } elseif ($type === JournalLineType::CUSTOMER->value) {
                $unitId = Unit::where('organization_id', $user->organization_id)
                    ->where('community_id', $community->id)
                    ->where('customer_code', $account)
                    ->value('id');

                if (!$unitId) {
                    throw new Exception("Row " . ($index + 1) . ": no customer with code \"{$account}\".");
                }
            } else {
                throw new Exception("Row " . ($index + 1) . ": supplier lines are not supported yet.");
            }

            $isDebit = $debit > 0;

            $lines[] = [
                'line_type'   => $type,
                'ledger_id'   => $ledgerId,
                'unit_id'     => $unitId,
                'description' => $descr ?: null,
                'amount'      => $isDebit ? $debit : $credit,
                'entry_type'  => $isDebit ? JournalEntryType::DEBIT->value : JournalEntryType::CREDIT->value,
            ];
        }

        // The batch must balance, exactly like a manual capture.
        $debitTotal  = collect($lines)->where('entry_type', JournalEntryType::DEBIT->value)->sum('amount');
        $creditTotal = collect($lines)->where('entry_type', JournalEntryType::CREDIT->value)->sum('amount');

        if (round($debitTotal, 2) !== round($creditTotal, 2)) {
            throw new Exception('The uploaded batch does not balance — total debits must equal total credits.');
        }

        return $lines;
    }

    private function toAmount(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) preg_replace('/[^0-9.\-]/', '', (string) $value);
    }

    /**
     * The next per-community batch number ("Journal Batch {n}").
     */
    private function nextBatchNumber(string $communityId): int
    {
        $max = JournalBatch::where('community_id', $communityId)->max('batch_number');

        return ($max ?? 0) + 1;
    }

    /**
     * Store uploaded supporting files, returning [{name, path}] rows.
     *
     * @param array<UploadedFile> $files
     */
    private function persistFiles(array $files, string $organizationId): array
    {
        $stored = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $stored[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $file->store("journal-batches/{$organizationId}", 'public'),
                ];
            }
        }

        return $stored;
    }

    /**
     * Re-post customer balances for every customer line in a normalised set.
     */
    private function recalculateAffectedUnits(array $lines): void
    {
        $unitIds = collect($lines)
            ->where('line_type', JournalLineType::CUSTOMER->value)
            ->pluck('unit_id')
            ->filter()
            ->unique()
            ->all();

        $this->recalculateUnitIds($unitIds);
    }

    /**
     * Recalculate the stored balance for a set of unit ids.
     *
     * @param array<string> $unitIds
     */
    private function recalculateUnitIds(array $unitIds): void
    {
        if (empty($unitIds)) {
            return;
        }

        Unit::whereIn('id', array_unique($unitIds))->get()
            ->each(fn (Unit $unit) => $this->unitBalance->recalculate($unit));
    }

    private function resolveCommunity(string $communityId): Community
    {
        $user = Auth::user();

        return Community::where('id', $communityId)
            ->where('organization_id', $user->organization_id)
            ->firstOrFail();
    }

    private function loadBatch(JournalBatch $batch): JournalBatch
    {
        return $batch->load([
            'community',
            'createdBy',
            'updatedBy',
            'lines.ledger',
            'lines.unit.owner',
            'lines.unit.currentOccupant',
        ])->loadCount('lines');
    }
}
