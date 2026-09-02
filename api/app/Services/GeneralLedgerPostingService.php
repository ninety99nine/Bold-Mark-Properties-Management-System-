<?php

namespace App\Services;

use App\Enums\FinancialCategory;
use App\Enums\JournalEntryType;
use App\Enums\JournalLineType;
use App\Enums\JournalSource;
use App\Models\Community;
use App\Models\JournalBatch;
use App\Models\Ledger;
use App\Models\Unit;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The single engine that posts balanced double-entry journals to the General
 * Ledger (journal_batches + journal_lines) on behalf of every document —
 * customer invoices, credit notes, cashbook receipts/allocations, supplier
 * invoices and opening balances — exactly like WeConnectU.
 *
 * Control accounts are resolved by Financial Category (not hard-coded codes):
 * Accounts Receivable = customer control, Accounts Payable = supplier control,
 * VAT Control = VAT, Bank = a cashbook ledger, Retained Income = opening equity.
 *
 * Auto-posted batches carry a source + polymorphic source_type/source_id back to
 * their document, a NULL batch_number (so they never consume a manual number),
 * and are excluded from the manual Journals list but included in every report.
 */
class GeneralLedgerPostingService
{
    public function __construct(
        private readonly UnitBalanceService $unitBalance,
        private readonly FinancialYearService $financialYears,
    ) {
    }

    /**
     * Resolve the singleton control account for an organisation + financial
     * category (e.g. Accounts Receivable → the customer control ledger).
     *
     * @param string $organizationId
     * @param FinancialCategory $category
     * @return Ledger
     * @throws Exception when the control account has not been set up.
     */
    public function controlAccount(string $organizationId, FinancialCategory $category): Ledger
    {
        $ledger = Ledger::controlAccount($organizationId, $category);

        if (! $ledger) {
            throw new Exception("No general ledger account is classified as \"{$category->label()}\" for this organisation. Set one up under Financial Settings → General Ledger.");
        }

        return $ledger;
    }

    /**
     * Post a balanced, source-tagged journal batch. Lines are plain arrays:
     *   [
     *     'line_type'  => JournalLineType|string,   // general|customer|supplier|reserve_fund
     *     'entry_type' => JournalEntryType|string,  // debit|credit
     *     'amount'     => float,                     // positive; direction from entry_type
     *     'ledger_id'  => ?string,                  // general/reserve_fund
     *     'unit_id'    => ?string,                  // customer
     *     'supplier_id'=> ?string,                  // supplier
     *     'description'=> ?string,
     *     'due_date'   => ?string,                  // customer control debit → invoice due date
     *   ]
     * Zero-amount lines are skipped. Total debits must equal total credits.
     *
     * @param Community $community
     * @param Carbon $date
     * @param JournalSource $source
     * @param Model|null $sourceModel  the originating document (for repost/reverse)
     * @param string|null $journalGroup
     * @param array<int, array<string, mixed>> $lines
     * @return JournalBatch
     * @throws Exception when the batch does not balance.
     */
    public function postBatch(
        Community $community,
        Carbon $date,
        JournalSource $source,
        ?Model $sourceModel,
        ?string $journalGroup,
        array $lines,
    ): JournalBatch {
        $normalised = $this->normalise($lines);

        if (empty($normalised)) {
            throw new Exception('A general ledger batch must contain at least one non-zero line.');
        }

        $this->assertBalanced($normalised);

        $batch = DB::transaction(function () use ($community, $date, $source, $sourceModel, $journalGroup, $normalised) {
            $batch = JournalBatch::create([
                'batch_number'       => null,
                'journal_group'      => $journalGroup,
                'date'               => $date->toDateString(),
                'financial_year'     => $this->financialYears->resolveForDate($community, $date),
                'source'             => $source->value,
                'source_type'        => $sourceModel?->getMorphClass(),
                'source_id'          => $sourceModel?->getKey(),
                'community_id'        => $community->id,
                'organization_id'    => $community->organization_id,
                'created_by_user_id' => Auth::id(),
            ]);

            foreach ($normalised as $index => $line) {
                $batch->lines()->create([
                    'line_type'       => $line['line_type'],
                    'entry_type'      => $line['entry_type'],
                    'amount'          => $line['amount'],
                    'ledger_id'       => $line['ledger_id'] ?? null,
                    'unit_id'         => $line['unit_id'] ?? null,
                    'supplier_id'     => $line['supplier_id'] ?? null,
                    'description'     => $line['description'] ?? null,
                    'due_date'        => $line['due_date'] ?? null,
                    'sort_order'      => $index,
                    'organization_id' => $batch->organization_id,
                ]);
            }

            return $batch;
        });

        $this->recalculateUnits($this->customerUnitIds($normalised));

        return $batch;
    }

    /**
     * Delete every GL batch posted for a source document (idempotent), and
     * re-post affected customer balances.
     *
     * @param Model $sourceModel
     * @return void
     */
    public function deleteBatchesFor(Model $sourceModel): void
    {
        $batches = JournalBatch::where('source_type', $sourceModel->getMorphClass())
            ->where('source_id', $sourceModel->getKey())
            ->with('lines')
            ->get();

        if ($batches->isEmpty()) {
            return;
        }

        $unitIds = $batches
            ->flatMap(fn (JournalBatch $b) => $b->lines
                ->where('line_type', JournalLineType::CUSTOMER)
                ->pluck('unit_id'))
            ->filter()
            ->unique()
            ->all();

        $batches->each(fn (JournalBatch $b) => $b->delete());

        $this->recalculateUnits($unitIds);
    }

    /**
     * Replace a document's GL postings: delete the old batch(es) then post fresh
     * lines. Pass an empty $lines array to only reverse.
     *
     * @param Community $community
     * @param Carbon $date
     * @param JournalSource $source
     * @param Model $sourceModel
     * @param string|null $journalGroup
     * @param array<int, array<string, mixed>> $lines
     * @return JournalBatch|null
     * @throws Exception
     */
    public function repostFor(
        Community $community,
        Carbon $date,
        JournalSource $source,
        Model $sourceModel,
        ?string $journalGroup,
        array $lines,
    ): ?JournalBatch {
        $this->deleteBatchesFor($sourceModel);

        if (empty(array_filter($lines, fn ($l) => (float) ($l['amount'] ?? 0) != 0.0))) {
            return null;
        }

        return $this->postBatch($community, $date, $source, $sourceModel, $journalGroup, $lines);
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Coerce enum/strings, round amounts, drop zero lines.
     *
     * @param array<int, array<string, mixed>> $lines
     * @return array<int, array<string, mixed>>
     */
    private function normalise(array $lines): array
    {
        $out = [];

        foreach ($lines as $line) {
            $amount = round((float) ($line['amount'] ?? 0), 2);
            if ($amount == 0.0) {
                continue;
            }

            $lineType  = $line['line_type'] instanceof JournalLineType ? $line['line_type']->value : (string) $line['line_type'];
            $entryType = $line['entry_type'] instanceof JournalEntryType ? $line['entry_type']->value : (string) $line['entry_type'];

            $out[] = [
                'line_type'   => $lineType,
                'entry_type'  => $entryType,
                'amount'      => $amount,
                'ledger_id'   => $line['ledger_id'] ?? null,
                'unit_id'     => $line['unit_id'] ?? null,
                'supplier_id' => $line['supplier_id'] ?? null,
                'description' => $line['description'] ?? null,
                'due_date'    => $line['due_date'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     * @throws Exception
     */
    private function assertBalanced(array $lines): void
    {
        $debit  = 0.0;
        $credit = 0.0;

        foreach ($lines as $line) {
            if ($line['entry_type'] === JournalEntryType::DEBIT->value) {
                $debit += $line['amount'];
            } else {
                $credit += $line['amount'];
            }
        }

        if (round($debit, 2) !== round($credit, 2)) {
            throw new Exception('The general ledger batch does not balance — total debits (' . number_format($debit, 2) . ') must equal total credits (' . number_format($credit, 2) . ').');
        }
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     * @return array<string>
     */
    private function customerUnitIds(array $lines): array
    {
        return collect($lines)
            ->where('line_type', JournalLineType::CUSTOMER->value)
            ->pluck('unit_id')
            ->filter()
            ->unique()
            ->all();
    }

    /**
     * @param array<string> $unitIds
     * @return void
     */
    private function recalculateUnits(array $unitIds): void
    {
        if (empty($unitIds)) {
            return;
        }

        Unit::whereIn('id', array_unique($unitIds))->get()
            ->each(fn (Unit $unit) => $this->unitBalance->recalculate($unit));
    }
}
