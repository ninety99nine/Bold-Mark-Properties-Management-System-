<?php

namespace App\Console\Commands;

use App\Enums\FinancialCategory;
use App\Enums\JournalEntryType;
use App\Enums\JournalLineType;
use App\Enums\JournalSource;
use App\Enums\SupplierInvoiceStatus;
use App\Models\BankAccount;
use App\Models\CashbookEntry;
use App\Models\Community;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\JournalBatch;
use App\Models\Ledger;
use App\Models\SupplierInvoice;
use App\Services\AllocationPostingService;
use App\Services\BankAccountService;
use App\Services\CreditNoteService;
use App\Services\GeneralLedgerPostingService;
use App\Services\InvoiceService;
use App\Services\SupplierInvoiceService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Idempotently post the double-entry General Ledger batch for every source
 * document that does not already have one — customer invoices, credit notes,
 * supplier invoices, top-level cashbook entries and bank opening balances —
 * using the exact same service methods the app uses at create time. Re-running
 * is always safe: a document that already carries a GL batch (matched on
 * source_type + source_id) is skipped.
 */
class BackfillGeneralLedger extends Command
{
    protected $signature = 'gl:backfill
        {--community= : Limit to a single community ID (default: all communities)}
        {--dry-run : Report what would be posted without writing anything}
        {--chunk=200 : How many documents to process per chunk}';

    protected $description = 'Post the missing General Ledger batch for existing documents + bank opening balances (idempotent)';

    /** @var array<string, array{posted:int, skipped:int, failed:int}> */
    private array $summary = [];

    private bool $dryRun = false;

    private int $chunk = 200;

    /**
     * Execute the console command.
     *
     * @param GeneralLedgerPostingService $gl
     * @param InvoiceService $invoices
     * @param CreditNoteService $creditNotes
     * @param SupplierInvoiceService $supplierInvoices
     * @param AllocationPostingService $allocations
     * @param BankAccountService $bankAccounts
     * @return int
     */
    public function handle(
        GeneralLedgerPostingService $gl,
        InvoiceService $invoices,
        CreditNoteService $creditNotes,
        SupplierInvoiceService $supplierInvoices,
        AllocationPostingService $allocations,
        BankAccountService $bankAccounts,
    ): int {
        $this->dryRun = (bool) $this->option('dry-run');
        $this->chunk  = max(1, (int) $this->option('chunk'));

        if ($this->dryRun) {
            $this->warn('DRY RUN — no batches will be posted.');
        }

        $communities = $this->communities();

        if ($communities->isEmpty()) {
            $this->info('No communities to process.');

            return self::SUCCESS;
        }

        foreach ($communities as $community) {
            $this->line("Community: {$community->name} ({$community->id})");

            $this->backfillInvoices($community, $invoices);
            $this->backfillCreditNotes($community, $creditNotes);
            $this->backfillSupplierInvoices($community, $supplierInvoices);
            $this->backfillCashbookEntries($community, $allocations);
            $this->backfillOpeningBalances($community, $gl, $bankAccounts);
        }

        $this->printSummary();

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Community>
     */
    private function communities(): \Illuminate\Support\Collection
    {
        $query = Community::query()->orderBy('name');

        if ($id = $this->option('community')) {
            $query->where('id', $id);
        }

        return $query->get();
    }

    /**
     * Customer invoices scoped to the community via their unit.
     *
     * @param Community $community
     * @param InvoiceService $invoices
     * @return void
     */
    private function backfillInvoices(Community $community, InvoiceService $invoices): void
    {
        Invoice::query()
            ->whereNotNull('unit_id')
            ->whereHas('unit', fn (Builder $q) => $q->where('community_id', $community->id))
            ->chunkById($this->chunk, function ($rows) use ($invoices): void {
                foreach ($rows as $invoice) {
                    $this->attempt('invoices', $invoice, fn () => $invoices->postInvoiceLedger($invoice));
                }
            });
    }

    /**
     * Credit notes scoped to the community via their unit.
     *
     * @param Community $community
     * @param CreditNoteService $creditNotes
     * @return void
     */
    private function backfillCreditNotes(Community $community, CreditNoteService $creditNotes): void
    {
        CreditNote::query()
            ->whereNotNull('unit_id')
            ->whereHas('unit', fn (Builder $q) => $q->where('community_id', $community->id))
            ->chunkById($this->chunk, function ($rows) use ($creditNotes): void {
                foreach ($rows as $creditNote) {
                    $this->attempt('credit_notes', $creditNote, fn () => $creditNotes->postCreditNoteLedger($creditNote));
                }
            });
    }

    /**
     * Supplier invoices in the "created" state (drafts never post).
     *
     * @param Community $community
     * @param SupplierInvoiceService $supplierInvoices
     * @return void
     */
    private function backfillSupplierInvoices(Community $community, SupplierInvoiceService $supplierInvoices): void
    {
        SupplierInvoice::query()
            ->where('community_id', $community->id)
            ->where('status', SupplierInvoiceStatus::CREATED->value)
            ->chunkById($this->chunk, function ($rows) use ($supplierInvoices): void {
                foreach ($rows as $invoice) {
                    $this->attempt('supplier_invoices', $invoice, fn () => $supplierInvoices->postSupplierInvoiceLedger($invoice));
                }
            });
    }

    /**
     * Top-level cashbook entries (children never post their own batch).
     *
     * @param Community $community
     * @param AllocationPostingService $allocations
     * @return void
     */
    private function backfillCashbookEntries(Community $community, AllocationPostingService $allocations): void
    {
        CashbookEntry::query()
            ->where('community_id', $community->id)
            ->whereNull('parent_entry_id')
            ->chunkById($this->chunk, function ($rows) use ($allocations): void {
                foreach ($rows as $entry) {
                    $this->attempt('cashbook_entries', $entry, fn () => $allocations->postEntryLedger($entry));
                }
            });
    }

    /**
     * Post the "Opening Balances" batch for every bank account with a non-zero
     * opening balance that has not been posted yet: Dr the bank's 8000/00n
     * ledger / Cr Retained Income for a positive opening (reversed for negative),
     * dated the bank's balance_as_at (falling back to the community's opening
     * balance date). Only one batch per bank account (source=opening).
     *
     * @param Community $community
     * @param GeneralLedgerPostingService $gl
     * @param BankAccountService $bankAccounts
     * @return void
     */
    private function backfillOpeningBalances(
        Community $community,
        GeneralLedgerPostingService $gl,
        BankAccountService $bankAccounts,
    ): void {
        $retained = Ledger::controlAccount($community->organization_id, FinancialCategory::RETAINED_INCOME);

        BankAccount::query()
            ->where('community_id', $community->id)
            ->chunkById($this->chunk, function ($rows) use ($community, $gl, $bankAccounts, $retained): void {
                foreach ($rows as $bank) {
                    $opening = round((float) ($bank->opening_balance ?? 0), 2);

                    if ($opening === 0.0) {
                        $this->tally('opening_balances', 'skipped');
                        continue;
                    }

                    if ($this->alreadyPosted($bank)) {
                        $this->tally('opening_balances', 'skipped');
                        continue;
                    }

                    if ($this->dryRun) {
                        $this->tally('opening_balances', 'posted');
                        continue;
                    }

                    try {
                        if (! $retained) {
                            throw new \RuntimeException('No Retained Income control account for organisation ' . $community->organization_id . '.');
                        }

                        if (! $bank->ledger_id) {
                            $bankAccounts->ensureLedger($bank);
                            $bank->refresh();
                        }

                        if (! $bank->ledger_id) {
                            throw new \RuntimeException("Bank account {$bank->id} has no GL ledger.");
                        }

                        $date = $bank->balance_as_at
                            ? Carbon::parse($bank->balance_as_at)
                            : ($community->opening_balance_date
                                ? Carbon::parse($community->opening_balance_date)
                                : Carbon::now());

                        $bankSide     = $opening > 0 ? JournalEntryType::DEBIT : JournalEntryType::CREDIT;
                        $retainedSide = $opening > 0 ? JournalEntryType::CREDIT : JournalEntryType::DEBIT;
                        $amount       = abs($opening);

                        $gl->repostFor(
                            $community,
                            $date,
                            JournalSource::OPENING,
                            $bank,
                            'Opening Balances',
                            [
                                [
                                    'line_type'   => JournalLineType::GENERAL,
                                    'entry_type'  => $bankSide,
                                    'amount'      => $amount,
                                    'ledger_id'   => $bank->ledger_id,
                                    'description' => 'Opening balance',
                                ],
                                [
                                    'line_type'   => JournalLineType::GENERAL,
                                    'entry_type'  => $retainedSide,
                                    'amount'      => $amount,
                                    'ledger_id'   => $retained->id,
                                    'description' => 'Opening balance',
                                ],
                            ],
                        );

                        $this->tally('opening_balances', 'posted');
                    } catch (Throwable $e) {
                        $this->tally('opening_balances', 'failed');
                        $this->error("  opening balance for bank {$bank->id} failed: {$e->getMessage()}");
                    }
                }
            });
    }

    /**
     * Post a document's batch if it has none yet; skip when already posted;
     * honour --dry-run; capture failures without aborting the run.
     *
     * @param string $type
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param callable $post
     * @return void
     */
    private function attempt(string $type, $model, callable $post): void
    {
        if ($this->alreadyPosted($model)) {
            $this->tally($type, 'skipped');

            return;
        }

        if ($this->dryRun) {
            $this->tally($type, 'posted');

            return;
        }

        try {
            $post();

            // A posting service may legitimately post nothing (e.g. an invoice
            // with no unit, a draft supplier invoice); only count it as posted
            // when a batch actually landed.
            $this->tally($type, $this->alreadyPosted($model) ? 'posted' : 'skipped');
        } catch (Throwable $e) {
            $this->tally($type, 'failed');
            $this->error("  {$type} {$model->getKey()} failed: {$e->getMessage()}");
        }
    }

    /**
     * Whether a source document already carries a GL batch.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return bool
     */
    private function alreadyPosted($model): bool
    {
        return JournalBatch::query()
            ->where('source_type', $model->getMorphClass())
            ->where('source_id', $model->getKey())
            ->exists();
    }

    /**
     * @param string $type
     * @param string $bucket  posted|skipped|failed
     * @return void
     */
    private function tally(string $type, string $bucket): void
    {
        if (! isset($this->summary[$type])) {
            $this->summary[$type] = ['posted' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $this->summary[$type][$bucket]++;
    }

    /**
     * @return void
     */
    private function printSummary(): void
    {
        $this->newLine();
        $this->info($this->dryRun ? 'Backfill dry-run summary:' : 'Backfill summary:');

        $totals = ['posted' => 0, 'skipped' => 0, 'failed' => 0];
        $rows   = [];

        foreach ($this->summary as $type => $counts) {
            $rows[] = [$type, $counts['posted'], $counts['skipped'], $counts['failed']];
            $totals['posted']  += $counts['posted'];
            $totals['skipped'] += $counts['skipped'];
            $totals['failed']  += $counts['failed'];
        }

        $rows[] = ['TOTAL', $totals['posted'], $totals['skipped'], $totals['failed']];

        $this->table(['Type', 'Posted', 'Skipped', 'Failed'], $rows);
    }
}
