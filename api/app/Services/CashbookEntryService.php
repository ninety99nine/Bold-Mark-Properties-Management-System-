<?php

namespace App\Services;

use Exception;
use App\Models\BankAccount;
use App\Models\CashbookEntry;
use App\Models\Community;
use App\Models\Invoice;
use App\Models\Ledger;
use App\Models\Owner;
use App\Models\Supplier;
use App\Models\Unit;
use App\Enums\CashbookEntryType;
use App\Enums\InvoiceStatus;
use App\Enums\JournalLineType;
use App\Enums\VatType;
use App\Exports\SplitTemplateExport;
use App\Imports\SplitUploadImport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Http\Resources\CashbookEntryResource;
use App\Http\Resources\CashbookEntryResources;

class CashbookEntryService extends BaseService
{
    protected array $allowedRelationships = ['community', 'unit', 'invoice', 'ledger', 'parentEntry', 'childEntries'];

    public function __construct(
        private readonly UnitBalanceService $unitBalance,
        private readonly AllocationPostingService $allocationPosting,
        private readonly AllocationRuleService $allocationRules
    ) {
        parent::__construct();
    }

    /**
     * Return a paginated, filtered list of cashbook entries for the authenticated occupant.
     *
     * @param array $data
     * @return CashbookEntryResources
     */
    public function showCashbookEntries(array $data): CashbookEntryResources
    {
        $user  = Auth::user();
        $query = CashbookEntry::where('organization_id', $user->organization_id)
            ->with(['unit', 'invoice']);

        if (!empty($data['community_id'])) {
            $query->where('community_id', $data['community_id']);
        }

        if (!empty($data['type'])) {
            $query->where('type', $data['type']);
        }

        if (!empty($data['unit_id'])) {
            $query->where('unit_id', $data['unit_id']);
        }

        // Allocation status filter: derived from invoice_id presence
        if (!empty($data['allocation_status'])) {
            if ($data['allocation_status'] === 'allocated') {
                $query->whereNotNull('invoice_id');
            } elseif ($data['allocation_status'] === 'unallocated') {
                $query->whereNull('invoice_id');
            }
        }

        if (!empty($data['ledger_id'])) {
            $query->where('ledger_id', $data['ledger_id']);
        }

        // Date range on the transaction date column
        if (!empty($data['date_range'])) {
            $query = $this->applyDateRange(
                $query,
                $data['date_range'],
                $data['date_range_start'] ?? null,
                $data['date_range_end'] ?? null,
                'date'
            );
        }

        if (!request()->has('_sort')) {
            $query = $query->orderBy('date', 'desc');
        }

        return $this->setQuery($query)->getOutput();
    }

    /**
     * Export cashbook entries as CSV, Excel, or PDF — same filters as showCashbookEntries().
     *
     * Extra parameters in $data:
     *   _format  — 'csv' | 'xlsx' | 'pdf'  (required)
     *   _limit   — integer record cap, or 'current' (= 15)
     *
     * @param array $data
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function exportCashbookEntries(array $data): \Symfony\Component\HttpFoundation\Response
    {
        $user  = Auth::user();
        $query = CashbookEntry::where('organization_id', $user->organization_id)
            ->with(['unit', 'invoice']);

        if (!empty($data['community_id'])) {
            $query->where('community_id', $data['community_id']);
        }
        if (!empty($data['type'])) {
            $query->where('type', $data['type']);
        }
        if (!empty($data['unit_id'])) {
            $query->where('unit_id', $data['unit_id']);
        }
        if (!empty($data['allocation_status'])) {
            if ($data['allocation_status'] === 'allocated') {
                $query->whereNotNull('invoice_id');
            } elseif ($data['allocation_status'] === 'unallocated') {
                $query->whereNull('invoice_id');
            }
        }
        if (!empty($data['ledger_id'])) {
            $query->where('ledger_id', $data['ledger_id']);
        }
        if (!empty($data['date_range'])) {
            $query = $this->applyDateRange(
                $query,
                $data['date_range'],
                $data['date_range_start'] ?? null,
                $data['date_range_end'] ?? null,
                'date'
            );
        }
        if (!empty($data['search'])) {
            $query->whereLike('description', $data['search']);
        }

        if (!$this->request->has('_sort')) {
            $query->orderBy('date', 'desc');
        }

        $this->setQuery($query);
        $this->applySortOnQuery();

        $limit   = $this->resolveExportLimit($data['_limit'] ?? 'current');
        $entries = $this->query->limit($limit)->get();

        $headings = ['Date', 'Description', 'Type', 'Amount', 'Unit', 'Invoice #', 'Status'];

        $rows = $entries->map(function ($entry) {
            $type   = $entry->type instanceof \BackedEnum ? $entry->type->value : (string) $entry->type;
            $prefix = strtolower($type) === 'credit' ? '+' : '-';
            $status = $entry->invoice_id ? 'Allocated' : 'Unallocated';

            return [
                $entry->date?->format('d M Y'),
                $entry->description,
                ucfirst($type),
                $prefix . number_format((float) $entry->amount, 2),
                $entry->unit?->unit_number ?? '—',
                $entry->invoice?->invoice_number ?? '—',
                $status,
            ];
        })->toArray();

        $format = $data['_format'] ?? 'csv';

        return $this->buildFileResponse(
            $rows,
            $headings,
            'cashbook-' . now()->format('Y-m-d'),
            $format,
            'Cashbook Entries Export',
            ['Generated' => now()->format('d M Y'), 'Records' => count($rows)]
        );
    }

    /**
     * Return aggregate summary statistics for the cashbook.
     *
     * @param array $data
     * @return array
     */
    public function showCashbookSummary(array $data): array
    {
        $user  = Auth::user();
        $query = CashbookEntry::where('organization_id', $user->organization_id)
            ->with(['invoice']);

        if (!empty($data['community_id'])) {
            $query->where('community_id', $data['community_id']);
        }

        $stats = (clone $query)->selectRaw(
            'SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as total_credits,
             SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as total_debits,
             COUNT(CASE WHEN invoice_id IS NULL THEN 1 END) as unallocated_count,
             SUM(CASE WHEN invoice_id IS NULL AND type = ? THEN amount ELSE 0 END) as unallocated_amount',
            [
                CashbookEntryType::CREDIT->value,
                CashbookEntryType::DEBIT->value,
                CashbookEntryType::CREDIT->value,
            ]
        )->first();

        $totalCredits       = (float) ($stats->total_credits ?? 0);
        $totalDebits        = (float) ($stats->total_debits ?? 0);

        return [
            'total_credits'      => $totalCredits,
            'total_debits'       => $totalDebits,
            'net_balance'        => $totalCredits - $totalDebits,
            'unallocated_count'  => (int) ($stats->unallocated_count ?? 0),
            'unallocated_amount' => (float) ($stats->unallocated_amount ?? 0),
        ];
    }

    /**
     * WeConnectU Cashbook — running-balance transaction list for a single
     * bank account ("cashbook") within a community and date window.
     *
     * Balance model (documented assumption): `bank_accounts.balance` is treated
     * as the opening balance as at `balance_as_at`. Each entry is signed
     * (+amount for credit/income, -amount for debit/expense):
     *   - opening_balance = baseline + Σ signed(entries with date < from)
     *   - each row balance = baseline + Σ signed(entries up to & incl. that row)
     *   - closing_balance = baseline + Σ signed(entries with date <= to)
     * If the client's take-on model differs, this method is the single place to
     * adjust the formula.
     *
     * @param Community $community
     * @param array     $data  Keys: bank_account_id, from, to, search, hide_allocated
     * @return array
     */
    public function showCashbookTransactions(Community $community, array $data): array
    {
        $user          = Auth::user();
        $bankAccountId = $data['bank_account_id'] ?? null;

        $empty = [
            'opening_balance' => 0.0,
            'closing_balance' => 0.0,
            'last_upload'     => null,
            'all_allocated'   => true,
            'transactions'    => [],
        ];

        if (empty($bankAccountId)) {
            return $empty;
        }

        $bankAccount = BankAccount::where('id', $bankAccountId)
            ->where('organization_id', $user->organization_id)
            ->where('community_id', $community->id)
            ->first();

        if (!$bankAccount) {
            return $empty;
        }

        $baseline = (float) $bankAccount->balance;
        $from     = $data['from'] ?? null;
        $to       = $data['to'] ?? null;

        // Fresh base query scoped to this cashbook. Only TOP-LEVEL bank lines
        // appear in the running-balance view — split child rows are excluded.
        $base = fn (): Builder => CashbookEntry::query()
            ->where('organization_id', $user->organization_id)
            ->where('community_id', $community->id)
            ->where('bank_account_id', $bankAccount->id)
            ->whereNull('parent_entry_id');

        // Opening = baseline + signed movements strictly before `from`.
        $opening = $baseline;
        if ($from) {
            $opening += $this->signedSum($base()->whereDate('date', '<', $from));
        }

        // Closing = baseline + signed movements up to and including `to`.
        $closing = $baseline + $this->signedSum(
            $to ? $base()->whereDate('date', '<=', $to) : $base()
        );

        // In-range transactions ordered chronologically for a correct running balance.
        $inRange = $base()->with('ledger');
        if ($from) {
            $inRange->whereDate('date', '>=', $from);
        }
        if ($to) {
            $inRange->whereDate('date', '<=', $to);
        }
        $entries = $inRange->orderBy('date')->orderBy('created_at')->orderBy('id')->get();

        $search        = isset($data['search']) ? trim((string) $data['search']) : '';
        $hideAllocated = filter_var($data['hide_allocated'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // Running balance is computed across ALL in-range entries so display
        // filters (search / hide-allocated) never distort the balance column.
        $running = $opening;
        $rows    = [];
        foreach ($entries as $entry) {
            $signed   = $entry->type === CashbookEntryType::CREDIT ? $entry->amount : -$entry->amount;
            $running += $signed;

            $isAllocated = $entry->is_allocated;

            if ($hideAllocated && $isAllocated) {
                continue;
            }
            if ($search !== '' && stripos((string) $entry->description, $search) === false) {
                continue;
            }

            $rows[] = [
                'id'                     => $entry->id,
                'date'                   => $entry->date?->toDateString(),
                'description'            => $entry->description,
                'amount'                 => (float) $entry->amount,
                'signed_amount'          => (float) $signed,
                'type'                   => $entry->type instanceof \BackedEnum ? $entry->type->value : $entry->type,
                'balance'                => round($running, 2),
                'is_allocated'           => $isAllocated,
                'allocation_ledger_type' => $entry->allocation_ledger_type instanceof \BackedEnum
                    ? $entry->allocation_ledger_type->value
                    : $entry->allocation_ledger_type,
                'vat_type'               => $entry->vat_type,
                'allocation_remarks'     => $entry->allocation_remarks,
                'account_label'          => $this->allocationPosting->accountLabelFor($entry),
                'rule_hint'              => $isAllocated ? null : $this->allocationRules->ruleHintFor($entry),
            ];
        }

        // "All transactions allocated" = no in-range top-level line is still
        // unallocated (no ledger type assigned AND not split).
        $allAllocated = $base()
            ->when($from, fn (Builder $q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('date', '<=', $to))
            ->unallocated()
            ->doesntExist();

        $lastUpload = $base()->max('created_at');

        return [
            'opening_balance' => round($opening, 2),
            'closing_balance' => round($closing, 2),
            'last_upload'     => $lastUpload ? Carbon::parse($lastUpload)->toDateString() : null,
            'all_allocated'   => $allAllocated,
            'transactions'    => $rows,
        ];
    }

    /**
     * WeConnectU Cashbook — bulk-create manual transaction lines for a bank
     * account. Income maps to a credit, expense to a debit.
     *
     * @param Community $community
     * @param array     $data  Keys: bank_account_id, transactions[]
     * @return array
     */
    public function createManualTransactions(Community $community, array $data): array
    {
        $user = Auth::user();

        $bankAccount = BankAccount::where('id', $data['bank_account_id'])
            ->where('organization_id', $user->organization_id)
            ->where('community_id', $community->id)
            ->firstOrFail();

        $created = 0;
        foreach ($data['transactions'] as $line) {
            $entry = CashbookEntry::create([
                'community_id'    => $community->id,
                'organization_id' => $user->organization_id,
                'bank_account_id' => $bankAccount->id,
                'date'            => $line['date'],
                'description'     => $line['description'],
                'amount'          => $line['amount'],
                'type'            => $line['type'] === 'income'
                    ? CashbookEntryType::CREDIT->value
                    : CashbookEntryType::DEBIT->value,
            ]);

            // Auto-apply any matching allocation rule to the new entry. When a
            // rule allocates it, the allocation already posts the GL batch;
            // otherwise post the unallocated (Suspense) bank batch.
            if (! $this->allocationRules->applyRulesToEntry($entry)) {
                $this->allocationPosting->postEntryLedger($entry->fresh());
            }

            $created++;
        }

        return [
            'message' => $created . ' transaction' . ($created === 1 ? '' : 's') . ' uploaded',
            'created' => $created,
        ];
    }

    /**
     * Sum the signed amount of the given cashbook query (credit +, debit -).
     *
     * @param Builder $query
     * @return float
     */
    private function signedSum(Builder $query): float
    {
        $sum = (clone $query)->selectRaw(
            'COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE -amount END), 0) as signed_sum',
            [CashbookEntryType::CREDIT->value]
        )->value('signed_sum');

        return (float) $sum;
    }

    /**
     * Create a new cashbook entry.
     *
     * @param array $data
     * @return array
     */
    public function createCashbookEntry(array $data): array
    {
        $user = Auth::user();

        $entryData = collect($data)
            ->only(['community_id', 'date', 'type', 'description', 'amount', 'notes', 'unit_id', 'invoice_id', 'ledger_id'])
            ->toArray();

        if (request()->hasFile('proof_of_payment')) {
            $entryData['proof_of_payment_path'] = request()->file('proof_of_payment')
                ->store("proof_of_payment/{$user->organization_id}", 'public');
        }

        $entry = CashbookEntry::create(array_merge($entryData, [
            'organization_id'   => $user->organization_id,
            'allocated_by_name' => $user->name,
            'allocated_at'      => now(),
        ]));

        // Post the bank line's GL batch (Dr/Cr Bank vs Suspense — a bare unit_id
        // without an allocation is unallocated and does NOT credit the customer).
        $this->allocationPosting->postEntryLedger($entry->fresh());

        // Auto-update invoice status when the entry is created with an invoice_id
        if (!empty($entryData['invoice_id'])) {
            $this->recalculateInvoiceStatus(Invoice::find($entryData['invoice_id']));
        }

        // Recalculate unit balance whenever a cashbook entry is linked to a unit
        if (!empty($entryData['unit_id'])) {
            $unit = Unit::find($entryData['unit_id']);
            if ($unit) {
                $this->unitBalance->recalculate($unit);
            }
        }

        return $this->showCreatedResource($entry);
    }

    /**
     * Attempt automatic allocation of unallocated credit entries to outstanding invoices.
     * Phase 1 stub — returns a minimal response. Full implementation in Phase 2.
     *
     * @param array $data
     * @return array
     */
    public function autoAllocateCashbookEntries(array $data): array
    {
        $user    = Auth::user();
        $matched = 0;

        // Phase 1 stub — auto-allocation algorithm to be implemented
        return [
            'matched' => $matched,
            'message' => 'Auto-allocation complete',
        ];
    }

    /**
     * Return a single cashbook entry resource with its relationships loaded.
     *
     * @param CashbookEntry $cashbookEntry
     * @return CashbookEntryResource
     */
    public function showCashbookEntry(CashbookEntry $cashbookEntry): CashbookEntryResource
    {
        $cashbookEntry->load(['community', 'unit', 'invoice.ledger', 'ledger', 'parentEntry']);

        return $this->showResource($cashbookEntry);
    }

    /**
     * Update a cashbook entry's editable fields.
     *
     * @param CashbookEntry $cashbookEntry
     * @param array         $data
     * @return array
     */
    public function updateCashbookEntry(CashbookEntry $cashbookEntry, array $data): array
    {
        $updateData = collect($data)
            ->only(['date', 'type', 'description', 'amount', 'notes', 'unit_id'])
            ->filter(fn($v) => !is_null($v))
            ->toArray();

        $cashbookEntry->update($updateData);

        return $this->showUpdatedResource($cashbookEntry);
    }

    /**
     * Allocate a cashbook entry to an invoice.
     *
     * Handles three scenarios:
     *   1. Exact match — entry amount === invoice outstanding → allocate fully
     *   2. Partial payment — entry amount < invoice outstanding → partially_paid
     *   3. Overpayment / advance — entry amount > invoice outstanding → split entry
     *
     * @param CashbookEntry $cashbookEntry
     * @param array         $data  Must include: invoice_id, unit_id
     * @return array
     * @throws Exception
     */
    public function allocateCashbookEntry(CashbookEntry $cashbookEntry, array $data): array
    {
        if ($cashbookEntry->invoice_id !== null) {
            throw new Exception('This entry has already been allocated to an invoice');
        }

        $invoice  = Invoice::findOrFail($data['invoice_id']);
        $unitId   = $data['unit_id'];

        // Recalculate outstanding (amount minus all currently allocated credits)
        $alreadyPaid  = $invoice->cashbookEntries()->sum('amount');
        $outstanding  = max(0, $invoice->amount - $alreadyPaid);

        if ($outstanding <= 0) {
            throw new Exception('This invoice is already fully paid');
        }

        $entryAmount = $cashbookEntry->amount;

        if ($entryAmount > $outstanding) {
            // OVERPAYMENT / ADVANCE: split the entry into two child entries
            $remainder = $entryAmount - $outstanding;

            // Allocated child — covers the invoice amount exactly
            CashbookEntry::create([
                'community_id'       => $cashbookEntry->community_id,
                'date'            => $cashbookEntry->date,
                'type'            => $cashbookEntry->type,
                'description'     => $cashbookEntry->description,
                'amount'          => $outstanding,
                'notes'           => $cashbookEntry->notes,
                'organization_id'       => $cashbookEntry->organization_id,
                'unit_id'         => $unitId,
                'invoice_id'      => $invoice->id,
                'ledger_id'  => $invoice->ledger_id,
                'parent_entry_id' => $cashbookEntry->id,
            ]);

            // Unallocated remainder — credit on account
            CashbookEntry::create([
                'community_id'       => $cashbookEntry->community_id,
                'date'            => $cashbookEntry->date,
                'type'            => $cashbookEntry->type,
                'description'     => $cashbookEntry->description,
                'amount'          => $remainder,
                'notes'           => $cashbookEntry->notes ?? 'Unallocated remainder after allocation to ' . $invoice->invoice_number,
                'organization_id'       => $cashbookEntry->organization_id,
                'unit_id'         => $unitId,
                'invoice_id'      => null,
                'ledger_id'  => null,
                'parent_entry_id' => $cashbookEntry->id,
            ]);

            // Mark original as superseded by deleting it
            $cashbookEntry->delete();

            $this->recalculateInvoiceStatus($invoice->fresh());

            $unit = Unit::find($unitId);
            if ($unit) {
                $this->unitBalance->recalculate($unit);
            }

            return [
                'message' => "Entry split: {$outstanding} allocated to {$invoice->invoice_number}, {$remainder} remains as unallocated credit on unit",
                'data'    => null,
            ];
        } elseif ($entryAmount < $outstanding) {
            // PARTIAL PAYMENT: allocate the full entry amount, invoice goes to partially_paid
            $cashbookEntry->update([
                'unit_id'        => $unitId,
                'invoice_id'     => $invoice->id,
                'ledger_id' => $invoice->ledger_id,
            ]);

            $this->recalculateInvoiceStatus($invoice->fresh());

            $unit = Unit::find($unitId);
            if ($unit) {
                $this->unitBalance->recalculate($unit);
            }

            return [
                'message' => "Partial payment of {$entryAmount} allocated to {$invoice->invoice_number}. Outstanding: " . ($outstanding - $entryAmount),
                'data'    => $this->showResource($cashbookEntry->fresh()),
            ];
        } else {
            // EXACT MATCH: allocate and mark invoice paid
            $cashbookEntry->update([
                'unit_id'        => $unitId,
                'invoice_id'     => $invoice->id,
                'ledger_id' => $invoice->ledger_id,
            ]);

            $this->recalculateInvoiceStatus($invoice->fresh());

            $unit = Unit::find($unitId);
            if ($unit) {
                $this->unitBalance->recalculate($unit);
            }

            return [
                'message' => "Entry allocated successfully to {$invoice->invoice_number}",
                'data'    => $this->showResource($cashbookEntry->fresh()),
            ];
        }
    }

    /**
     * Deallocate a cashbook entry from its invoice.
     *
     * Clears invoice_id and ledger_id on the entry, stores the reason in notes,
     * and recalculates the invoice status based on remaining allocated payments.
     *
     * @param CashbookEntry $cashbookEntry
     * @param array         $data  Must include: reason
     * @return array
     * @throws Exception
     */
    public function deallocateCashbookEntry(CashbookEntry $cashbookEntry, array $data): array
    {
        if ($cashbookEntry->invoice_id === null) {
            throw new Exception('This entry is not allocated to any invoice');
        }

        $invoice = Invoice::findOrFail($cashbookEntry->invoice_id);

        $removalNote = '[Payment removed: ' . $data['reason'] . ']';
        $updatedNotes = $cashbookEntry->notes
            ? $cashbookEntry->notes . ' ' . $removalNote
            : $removalNote;

        $unitId = $cashbookEntry->unit_id;

        $cashbookEntry->update([
            'invoice_id'     => null,
            'ledger_id' => null,
            'notes'          => $updatedNotes,
        ]);

        // Recalculate invoice status based on remaining allocated payments
        $this->recalculateInvoiceStatus($invoice->fresh());

        if ($unitId) {
            $unit = Unit::find($unitId);
            if ($unit) {
                $this->unitBalance->recalculate($unit);
            }
        }

        return [
            'message' => 'Payment removed from invoice',
            'data'    => $this->showResource($cashbookEntry->fresh()),
        ];
    }

    /**
     * Recalculate and persist invoice status based on total allocated payments.
     *
     * Paid:          total_paid >= invoice amount
     * Partially paid: 0 < total_paid < invoice amount
     * Unpaid:        total_paid <= 0  (restore original unpaid/overdue state)
     *
     * @param Invoice|null $invoice
     * @return void
     */
    private function recalculateInvoiceStatus(?Invoice $invoice): void
    {
        if (!$invoice) {
            return;
        }

        $totalPaid = (float) $invoice->cashbookEntries()->sum('amount');

        if ($totalPaid >= $invoice->amount) {
            $invoice->update(['status' => InvoiceStatus::PAID->value]);
        } elseif ($totalPaid > 0) {
            $invoice->update(['status' => InvoiceStatus::PARTIALLY_PAID->value]);
        } else {
            // Revert to unpaid (scheduler will re-apply overdue if past due date)
            $invoice->update(['status' => InvoiceStatus::UNPAID->value]);
        }
    }

    /**
     * Upload or replace the proof of payment file for a cashbook entry.
     *
     * @param CashbookEntry $cashbookEntry
     * @param \Illuminate\Http\UploadedFile $file
     * @return CashbookEntryResource
     */
    public function uploadProofOfPayment(CashbookEntry $cashbookEntry, \Illuminate\Http\UploadedFile $file): CashbookEntryResource
    {
        // Delete the existing file if one exists
        if ($cashbookEntry->proof_of_payment_path) {
            Storage::disk('public')->delete($cashbookEntry->proof_of_payment_path);
        }

        $path = $file->store('proof-of-payment', 'public');

        $cashbookEntry->update(['proof_of_payment_path' => $path]);

        return new CashbookEntryResource($cashbookEntry->fresh());
    }

    /**
     * Stream the proof of payment file as a forced download.
     *
     * @param CashbookEntry $cashbookEntry
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     * @throws Exception
     */
    public function downloadProofOfPayment(CashbookEntry $cashbookEntry): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        if (!$cashbookEntry->proof_of_payment_path) {
            throw new Exception('No proof of payment attached to this entry.');
        }

        $filename = basename($cashbookEntry->proof_of_payment_path);

        return Storage::disk('public')->download($cashbookEntry->proof_of_payment_path, $filename);
    }

    /**
     * Delete the proof of payment file from a cashbook entry.
     *
     * @param CashbookEntry $cashbookEntry
     * @return array
     */
    public function deleteProofOfPayment(CashbookEntry $cashbookEntry): array
    {
        if ($cashbookEntry->proof_of_payment_path) {
            Storage::disk('public')->delete($cashbookEntry->proof_of_payment_path);
            $cashbookEntry->update(['proof_of_payment_path' => null]);
        }

        return ['message' => 'Proof of payment removed'];
    }

    /**
     * Bulk delete cashbook entries by an array of IDs.
     *
     * @param array $ids
     * @return array
     * @throws Exception
     */
    public function deleteCashbookEntries(array $ids): array
    {
        $user    = Auth::user();
        $entries = CashbookEntry::whereIn('id', $ids)
            ->where('organization_id', $user->organization_id)
            ->get();

        $total = $entries->count();

        if ($total === 0) {
            throw new Exception('No Cashbook Entries deleted');
        }

        $affectedUnitIds = $entries->pluck('unit_id')->filter()->unique()->values();
        $invoiceIds      = $entries->pluck('invoice_id')->filter()->unique()->values();

        foreach ($entries as $entry) {
            $entry->delete();
        }

        // Recalculate invoice statuses for all affected invoices
        foreach ($invoiceIds as $invoiceId) {
            $this->recalculateInvoiceStatus(Invoice::find($invoiceId));
        }

        // Recalculate unit balances for all affected units
        foreach ($affectedUnitIds as $unitId) {
            $unit = Unit::find($unitId);
            if ($unit) {
                $this->unitBalance->recalculate($unit);
            }
        }

        $label = $total === 1 ? 'Cashbook Entry' : 'Cashbook Entries';

        return ['message' => "{$total} {$label} deleted"];
    }

    /**
     * Delete a single cashbook entry.
     *
     * @param CashbookEntry $cashbookEntry
     * @return array
     */
    public function deleteCashbookEntry(CashbookEntry $cashbookEntry): array
    {
        $unitId    = $cashbookEntry->unit_id;
        $invoiceId = $cashbookEntry->invoice_id;
        $deleted   = $cashbookEntry->delete();

        if ($deleted) {
            if ($invoiceId) {
                $this->recalculateInvoiceStatus(Invoice::find($invoiceId));
            }
            if ($unitId) {
                $unit = Unit::find($unitId);
                if ($unit) {
                    $this->unitBalance->recalculate($unit);
                }
            }
        }

        return [
            'deleted' => $deleted,
            'message' => $deleted ? 'Cashbook Entry deleted' : 'Cashbook Entry delete unsuccessful',
        ];
    }

    // -------------------------------------------------------------------------
    // WeConnectU cashbook allocation core
    // -------------------------------------------------------------------------

    /**
     * Allocate a cashbook entry to a general / customer / supplier / reserve-fund
     * account, delegating to the shared AllocationPostingService.
     *
     * @param CashbookEntry $entry
     * @param array         $data  Keys: ledger_type, ledger_id|unit_id|supplier_id, vat_type?, remarks?
     * @return CashbookEntry
     * @throws Exception
     */
    public function allocateEntry(CashbookEntry $entry, array $data): CashbookEntry
    {
        return $this->allocationPosting->post($entry, $data);
    }

    /**
     * Remove the allocation from a cashbook entry.
     *
     * A split parent has its child rows deleted (re-posting affected customer
     * balances) and its is_split flag cleared; a normal allocation is reversed
     * through the shared AllocationPostingService.
     *
     * @param CashbookEntry $entry
     * @return void
     */
    public function deallocateEntry(CashbookEntry $entry): void
    {
        if ($entry->is_split) {
            $children = $entry->childEntries()->get();
            $unitIds  = $children->pluck('unit_id')->filter()->unique()->values();

            foreach ($children as $child) {
                $child->delete();
            }

            $entry->update([
                'is_split'           => false,
                'allocation_remarks' => null,
                'allocated_by_name'  => null,
                'allocated_at'       => null,
            ]);

            foreach ($unitIds as $unitId) {
                $unit = Unit::find($unitId);
                if ($unit) {
                    $this->unitBalance->recalculate($unit);
                }
            }

            return;
        }

        $this->allocationPosting->reverse($entry);
    }

    /**
     * Split a single bank line across several allocation lines, creating one
     * child cashbook entry per line and marking the parent as split.
     *
     * Each line carries the account target under `target` (interpreted per the
     * line's ledger_type) — or, from a parsed upload, directly as ledger_id /
     * unit_id / supplier_id.
     *
     * @param CashbookEntry $entry
     * @param array         $lines         Each: ledger_type, target|ledger_id|unit_id|supplier_id, amount, vat_type?, remarks?
     * @param bool          $saveTemplate
     * @param string|null   $templateName
     * @return CashbookEntry
     * @throws Exception
     */
    public function splitEntry(CashbookEntry $entry, array $lines, bool $saveTemplate = false, ?string $templateName = null): CashbookEntry
    {
        $user = Auth::user();

        // Parent signed amount: +amount for credit, -amount for debit.
        $parentSigned = $entry->type === CashbookEntryType::CREDIT
            ? (float) $entry->amount
            : -(float) $entry->amount;

        $lineSum = 0.0;
        foreach ($lines as $line) {
            $lineSum += (float) ($line['amount'] ?? 0);
        }

        if (round($lineSum, 2) !== round($parentSigned, 2)) {
            throw ValidationException::withMessages([
                'lines' => 'The split lines must sum to the transaction amount of ' . round($parentSigned, 2) . '.',
            ]);
        }

        foreach ($lines as $line) {
            $amount = (float) ($line['amount'] ?? 0);

            $child = CashbookEntry::create([
                'community_id'    => $entry->community_id,
                'organization_id' => $entry->organization_id,
                'bank_account_id' => $entry->bank_account_id,
                'date'            => $entry->date,
                'description'     => $entry->description,
                'type'            => $amount >= 0
                    ? CashbookEntryType::CREDIT->value
                    : CashbookEntryType::DEBIT->value,
                'amount'          => abs($amount),
                'parent_entry_id' => $entry->id,
            ]);

            $this->allocationPosting->post($child, $this->splitLineAllocation($line));
        }

        $entry->update([
            'is_split'           => true,
            'allocation_remarks' => 'Split allocation',
            'allocated_by_name'  => $user?->name,
            'allocated_at'       => now(),
        ]);

        // Re-post the PARENT bank line with one contra per child (children never
        // post their own bank batch).
        $this->allocationPosting->postEntryLedger($entry->fresh());

        if ($saveTemplate) {
            $this->splitTemplates()->createSplitTemplate([
                'name'            => $templateName,
                'lines'           => $lines,
                'community_id'    => $entry->community_id,
                'organization_id' => $entry->organization_id,
            ]);
        }

        return $entry->fresh();
    }

    /**
     * Type-ahead customer search within a community, returning the shape the
     * allocation modal renders (unit id, code, name, reference and balance).
     *
     * @param Community $community
     * @param string    $q
     * @return array
     */
    public function customerSearch(Community $community, string $q): array
    {
        $user = Auth::user();
        $q    = trim($q);

        $owners = Owner::query()
            ->where('owners.organization_id', $user->organization_id)
            ->whereNotNull('owners.unit_id')
            ->whereHas('unit', fn (Builder $unit) => $unit->where('community_id', $community->id))
            ->when($q !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($q) {
                $query->whereLike('customer_code', "%{$q}%")
                      ->orWhereLike('full_name', "%{$q}%");
            }))
            ->with('unit')
            ->orderByDesc('is_primary')
            ->orderBy('full_name')
            ->limit(25)
            ->get();

        return $owners->map(fn (Owner $owner) => [
            'unit_id'   => $owner->unit_id,
            'code'      => $owner->customer_code,
            'customer'  => $owner->full_name,
            'reference' => $owner->reference,
            'balance'   => (float) ($owner->unit?->balance ?? 0),
        ])->values()->all();
    }

    /**
     * The account options for the allocation modal: general ledgers, reserve-fund
     * ledgers and the VAT-type dropdown.
     *
     * @param Community $community
     * @return array
     */
    public function ledgerOptions(Community $community): array
    {
        $user = Auth::user();

        $ledgers = Ledger::where('organization_id', $user->organization_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        $general     = [];
        $reserveFund = [];

        foreach ($ledgers as $ledger) {
            $option = [
                'ledger_id' => $ledger->id,
                'code'      => $ledger->code,
                'name'      => $ledger->name,
                'label'     => trim("{$ledger->code} - {$ledger->name}"),
                'category'  => $ledger->category,
            ];

            if ($this->isReserveFundLedger($ledger)) {
                $reserveFund[] = $option;
            } else {
                $general[] = $option;
            }
        }

        return [
            'general'      => $general,
            'reserve_fund' => $reserveFund,
            'vat_types'    => VatType::options(),
        ];
    }

    /**
     * Per-bank-account allocation status for a community's cashbooks, plus the
     * total pending (unallocated) top-level line count.
     *
     * @param Community $community
     * @return array
     */
    public function cashbookStatus(Community $community): array
    {
        $user = Auth::user();

        $bankAccounts = BankAccount::where('organization_id', $user->organization_id)
            ->where('community_id', $community->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $accounts     = [];
        $totalPending = 0;

        foreach ($bankAccounts as $index => $bankAccount) {
            $pending = CashbookEntry::where('organization_id', $user->organization_id)
                ->where('community_id', $community->id)
                ->where('bank_account_id', $bankAccount->id)
                ->whereNull('parent_entry_id')
                ->unallocated()
                ->count();

            $totalPending += $pending;

            $accounts[] = [
                'bank_account_id' => $bankAccount->id,
                'name'            => $bankAccount->name,
                'gl_account'      => '8000/' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'all_allocated'   => $pending === 0,
                'pending_count'   => $pending,
            ];
        }

        return [
            'bank_accounts' => $accounts,
            'total_pending' => $totalPending,
        ];
    }

    /**
     * Run the community's allocation rules across its unallocated entries.
     *
     * @param Community   $community
     * @param string|null $bankAccountId
     * @return int  Number of entries allocated.
     */
    public function runRules(Community $community, ?string $bankAccountId): int
    {
        return $this->allocationRules->applyRulesForCommunity($community->id, $bankAccountId);
    }

    /**
     * Parse an uploaded split spreadsheet into resolved (but unsaved) split
     * lines for the split modal. Headers occupy row 1; data starts at row 2.
     *
     * @param CashbookEntry $entry
     * @param UploadedFile  $file
     * @return array
     */
    public function parseSplitUpload(CashbookEntry $entry, UploadedFile $file): array
    {
        $sheets = Excel::toArray(new SplitUploadImport(), $file);
        $rows   = $sheets[0] ?? [];

        $parsed = [];

        foreach ($rows as $index => $row) {
            // Skip the heading row and any blank rows.
            if ($index === 0) {
                continue;
            }

            $rawType = strtolower(trim((string) ($row[0] ?? '')));
            $account = trim((string) ($row[1] ?? ''));
            $remarks = trim((string) ($row[2] ?? ''));
            $amount  = $this->toAmount($row[3] ?? null);

            if ($rawType === '' && $account === '') {
                continue;
            }

            $parsed[] = $this->resolveSplitUploadLine($entry, $rawType, $account, $remarks, $amount);
        }

        return $parsed;
    }

    /**
     * Stream the blank split-allocation upload template.
     *
     * @return BinaryFileResponse
     */
    public function downloadSplitTemplate(): BinaryFileResponse
    {
        return Excel::download(new SplitTemplateExport(), 'cashbook-split.xlsx');
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Build the AllocationPostingService payload for a split line, routing the
     * generic `target` onto the correct id key for the line's ledger type.
     *
     * @param array $line
     * @return array
     */
    private function splitLineAllocation(array $line): array
    {
        $type   = $line['ledger_type'] ?? null;
        $target = $line['target'] ?? null;

        $allocation = [
            'ledger_type' => $type,
            'ledger_id'   => $line['ledger_id'] ?? null,
            'unit_id'     => $line['unit_id'] ?? null,
            'supplier_id' => $line['supplier_id'] ?? null,
            'vat_type'    => $line['vat_type'] ?? null,
            'remarks'     => $line['remarks'] ?? null,
        ];

        if ($target !== null) {
            match ($type) {
                JournalLineType::CUSTOMER->value => $allocation['unit_id'] = $target,
                JournalLineType::SUPPLIER->value => $allocation['supplier_id'] = $target,
                default                          => $allocation['ledger_id'] = $target,
            };
        }

        return $allocation;
    }

    /**
     * Resolve a single uploaded split row to its account target and label.
     *
     * @param CashbookEntry $entry
     * @param string        $rawType
     * @param string        $account
     * @param string        $remarks
     * @param float         $amount
     * @return array
     */
    private function resolveSplitUploadLine(CashbookEntry $entry, string $rawType, string $account, string $remarks, float $amount): array
    {
        $line = [
            'ledger_type'    => null,
            'account'        => $account,
            'resolved_label' => null,
            'remarks'        => $remarks ?: null,
            'amount'         => $amount,
        ];

        $type = match (true) {
            str_starts_with($rawType, 'gen')                       => JournalLineType::GENERAL->value,
            str_starts_with($rawType, 'cust')                      => JournalLineType::CUSTOMER->value,
            str_starts_with($rawType, 'sup')                       => JournalLineType::SUPPLIER->value,
            str_contains($rawType, 'reserve') || $rawType === 'rf' => JournalLineType::RESERVE_FUND->value,
            default                                                => null,
        };

        if (!$type) {
            $line['error'] = "Unknown ledger type \"{$rawType}\".";

            return $line;
        }

        $line['ledger_type'] = $type;

        if (in_array($type, [JournalLineType::GENERAL->value, JournalLineType::RESERVE_FUND->value], true)) {
            $ledger = Ledger::where('organization_id', $entry->organization_id)
                ->where('code', $account)
                ->first();

            if (!$ledger) {
                $line['error'] = "No account with code \"{$account}\".";

                return $line;
            }

            $line['ledger_id']      = $ledger->id;
            $line['resolved_label'] = $this->allocationPosting->ledgerLabel($ledger->id);
        } elseif ($type === JournalLineType::CUSTOMER->value) {
            $owner = Owner::where('organization_id', $entry->organization_id)
                ->where('customer_code', $account)
                ->whereHas('unit', fn (Builder $unit) => $unit->where('community_id', $entry->community_id))
                ->first();

            if (!$owner || !$owner->unit_id) {
                $line['error'] = "No customer with code \"{$account}\".";

                return $line;
            }

            $line['unit_id']        = $owner->unit_id;
            $line['resolved_label'] = $this->allocationPosting->customerLabel($owner->unit_id);
        } else {
            $supplier = Supplier::where('organization_id', $entry->organization_id)
                ->where('supplier_code', $account)
                ->first();

            if (!$supplier) {
                $line['error'] = "No supplier with code \"{$account}\".";

                return $line;
            }

            $line['supplier_id']    = $supplier->id;
            $line['resolved_label'] = $this->allocationPosting->supplierLabel($supplier->id);
        }

        return $line;
    }

    /**
     * Whether a ledger represents a Reserve Fund account (code prefixed "RF" or
     * a category naming the reserve fund).
     *
     * @param Ledger $ledger
     * @return bool
     */
    private function isReserveFundLedger(Ledger $ledger): bool
    {
        $code     = strtoupper((string) $ledger->code);
        $category = strtoupper((string) $ledger->category);

        return str_starts_with($code, 'RF') || str_contains($category, 'RESERVE');
    }

    /**
     * Normalise a spreadsheet cell to a float amount, preserving the sign.
     *
     * @param mixed $value
     * @return float
     */
    private function toAmount(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) preg_replace('/[^0-9.\-]/', '', (string) $value);
    }

    /**
     * Resolve the split-template service inline (avoids a constructor cycle).
     *
     * @return SplitTemplateService
     */
    private function splitTemplates(): SplitTemplateService
    {
        return app(SplitTemplateService::class);
    }
}
