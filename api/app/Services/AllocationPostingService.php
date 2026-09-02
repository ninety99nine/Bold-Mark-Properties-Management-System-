<?php

namespace App\Services;

use App\Enums\CashbookEntryType;
use App\Enums\FinancialCategory;
use App\Enums\JournalEntryType;
use App\Enums\JournalLineType;
use App\Enums\JournalSource;
use App\Enums\VatType;
use App\Models\CashbookEntry;
use App\Models\Community;
use App\Models\Ledger;
use App\Models\Owner;
use App\Models\Supplier;
use App\Models\Unit;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;

/**
 * The single shared code path for writing (and reversing) an allocation onto a
 * cashbook entry, mirroring WeConnectU's "Allocate" action. Setting an
 * allocation_ledger_type marks a line as allocated; a customer allocation also
 * reflects on the unit's balance via UnitBalanceService.
 */
class AllocationPostingService
{
    public function __construct(private readonly UnitBalanceService $unitBalance)
    {
    }

    /**
     * Write an allocation onto a cashbook entry.
     *
     * $allocation keys:
     *   ledger_type (string, required) — one of JournalLineType values
     *   ledger_id   (general / reserve_fund)
     *   unit_id     (customer)
     *   supplier_id (supplier)
     *   vat_type    (string, optional)
     *   remarks     (string, optional)
     *
     * @param CashbookEntry $entry
     * @param array         $allocation
     * @return CashbookEntry
     * @throws Exception
     */
    public function post(CashbookEntry $entry, array $allocation): CashbookEntry
    {
        $user = Auth::user();
        $type = $allocation['ledger_type'] ?? null;

        $ledgerType = JournalLineType::tryFrom((string) $type);
        if (!$ledgerType) {
            throw new Exception('An allocation requires a valid ledger type.');
        }

        $ledgerId   = null;
        $unitId     = null;
        $supplierId = null;

        switch ($ledgerType) {
            case JournalLineType::GENERAL:
            case JournalLineType::RESERVE_FUND:
                $ledgerId = Ledger::where('id', $allocation['ledger_id'] ?? null)
                    ->where('organization_id', $entry->organization_id)
                    ->value('id');

                if (!$ledgerId) {
                    throw new Exception('The selected account does not exist in this organisation.');
                }
                break;

            case JournalLineType::CUSTOMER:
                $unitId = Unit::where('id', $allocation['unit_id'] ?? null)
                    ->where('organization_id', $entry->organization_id)
                    ->where('community_id', $entry->community_id)
                    ->value('id');

                if (!$unitId) {
                    throw new Exception('The selected customer does not exist in this community.');
                }
                break;

            case JournalLineType::SUPPLIER:
                $supplierId = Supplier::where('id', $allocation['supplier_id'] ?? null)
                    ->where('organization_id', $entry->organization_id)
                    ->value('id');

                if (!$supplierId) {
                    throw new Exception('The selected supplier does not exist in this organisation.');
                }
                break;
        }

        $entry->update([
            'allocation_ledger_type' => $ledgerType->value,
            'ledger_id'              => $ledgerId,
            'unit_id'                => $unitId,
            'supplier_id'            => $supplierId,
            'vat_type'               => $allocation['vat_type'] ?? null,
            'allocation_remarks'     => $allocation['remarks'] ?? null,
            'allocated_by_name'      => $user?->name,
            'allocated_at'           => now(),
        ]);

        // Re-post the bank line's GL batch to reflect the new allocation
        // (Suspense → the real account); this also recalculates the customer
        // balance for any affected unit.
        $this->postEntryLedger($entry->fresh());

        return $entry->fresh();
    }

    /**
     * Remove the allocation from a cashbook entry, clearing every allocation
     * field and re-posting the previously allocated unit's balance.
     *
     * @param CashbookEntry $entry
     * @return void
     */
    public function reverse(CashbookEntry $entry): void
    {
        $oldUnitId = $entry->unit_id;

        $entry->update([
            'allocation_ledger_type' => null,
            'ledger_id'              => null,
            'unit_id'                => null,
            'supplier_id'            => null,
            'vat_type'               => null,
            'allocation_remarks'     => null,
            'allocated_by_name'      => null,
            'allocated_at'           => null,
        ]);

        // Re-post the bank line back to Suspense (unallocated) and recalculate
        // the previously allocated unit's balance.
        $this->postEntryLedger($entry->fresh());

        if ($oldUnitId) {
            $unit = Unit::find($oldUnitId);
            if ($unit) {
                $this->unitBalance->recalculate($unit);
            }
        }
    }

    /**
     * Post (or re-post) the single balanced GL batch for a cashbook bank line,
     * reflecting its CURRENT allocation state (WeConnectU suspense model). The
     * bank side is Dr Bank on a receipt (credit entry) / Cr Bank on a payment
     * (debit entry); the contra side mirrors the allocation:
     *
     *   - split      → one contra line per child (children do not post their own
     *                  bank batches);
     *   - allocated  → customer → Accounts Receivable [unit];
     *                  general/reserve → the chosen ledger (+VAT split);
     *                  supplier → Accounts Payable [supplier];
     *   - unallocated → Suspense (9900/001).
     *
     * Idempotent; child entries never post a bank batch of their own.
     *
     * @param CashbookEntry $entry
     * @return void
     */
    public function postEntryLedger(CashbookEntry $entry): void
    {
        // Child rows of a split are represented as contra lines on the parent's
        // batch, so they never post their own bank batch.
        if ($entry->parent_entry_id) {
            return;
        }

        $community = $entry->community;
        if (! $community) {
            return;
        }

        $bank = $entry->bankAccount;
        if ($bank && ! $bank->ledger_id) {
            app(BankAccountService::class)->ensureLedger($bank);
            $bank->refresh();
        }

        $bankLedgerId = $bank?->ledger_id;

        // Without a bank ledger we cannot post a balanced batch; reverse any
        // stale batch and bail (keeps the operation non-fatal).
        if (! $bankLedgerId) {
            app(GeneralLedgerPostingService::class)->deleteBatchesFor($entry);
            return;
        }

        $orgId    = $entry->organization_id;
        $receipt  = $entry->type === CashbookEntryType::CREDIT;
        $bankSide = $receipt ? JournalEntryType::DEBIT : JournalEntryType::CREDIT;
        $contra   = $receipt ? JournalEntryType::CREDIT : JournalEntryType::DEBIT;
        $amount   = (float) $entry->amount;

        $lines = [[
            'line_type'   => JournalLineType::GENERAL,
            'entry_type'  => $bankSide,
            'amount'      => $amount,
            'ledger_id'   => $bankLedgerId,
            'description' => $entry->description,
        ]];

        if ($entry->is_split) {
            foreach ($entry->childEntries()->get() as $child) {
                $lines = array_merge($lines, $this->contraLinesFor(
                    orgId: $orgId,
                    contra: $contra,
                    ledgerType: $child->allocation_ledger_type,
                    amount: (float) $child->amount,
                    unitId: $child->unit_id,
                    ledgerId: $child->ledger_id,
                    supplierId: $child->supplier_id,
                    vatType: $child->vat_type,
                    description: $child->description,
                ));
            }
        } else {
            $lines = array_merge($lines, $this->contraLinesFor(
                orgId: $orgId,
                contra: $contra,
                ledgerType: $entry->allocation_ledger_type,
                amount: $amount,
                unitId: $entry->unit_id,
                ledgerId: $entry->ledger_id,
                supplierId: $entry->supplier_id,
                vatType: $entry->vat_type,
                description: $entry->description,
            ));
        }

        app(GeneralLedgerPostingService::class)->repostFor(
            $community,
            Carbon::parse($entry->date),
            JournalSource::CASHBOOK,
            $entry,
            'Transfer',
            $lines,
        );
    }

    /**
     * Build the contra line(s) for one allocation slice of a cashbook entry.
     * Unallocated slices post to Suspense. A VAT-bearing general/reserve slice is
     * split into a net line (chosen ledger) and a tax line (VAT Control).
     *
     * @param string $orgId
     * @param JournalEntryType $contra
     * @param JournalLineType|string|null $ledgerType
     * @param float $amount
     * @param string|null $unitId
     * @param string|null $ledgerId
     * @param string|null $supplierId
     * @param VatType|string|null $vatType
     * @param string|null $description
     * @return array<int, array<string, mixed>>
     */
    private function contraLinesFor(
        string $orgId,
        JournalEntryType $contra,
        JournalLineType|string|null $ledgerType,
        float $amount,
        ?string $unitId,
        ?string $ledgerId,
        ?string $supplierId,
        VatType|string|null $vatType,
        ?string $description,
    ): array {
        $gl = app(GeneralLedgerPostingService::class);

        $type = $ledgerType instanceof JournalLineType
            ? $ledgerType
            : ($ledgerType ? JournalLineType::tryFrom((string) $ledgerType) : null);

        // Unallocated → Suspense.
        if (! $type) {
            return [[
                'line_type'   => JournalLineType::GENERAL,
                'entry_type'  => $contra,
                'amount'      => $amount,
                'ledger_id'   => Ledger::suspense($orgId)?->id,
                'description' => $description,
            ]];
        }

        if ($type === JournalLineType::CUSTOMER) {
            return [[
                'line_type'   => JournalLineType::CUSTOMER,
                'entry_type'  => $contra,
                'amount'      => $amount,
                'unit_id'     => $unitId,
                'description' => $description,
            ]];
        }

        if ($type === JournalLineType::SUPPLIER) {
            $ap = $gl->controlAccount($orgId, FinancialCategory::ACCOUNTS_PAYABLE);

            return [[
                'line_type'   => JournalLineType::SUPPLIER,
                'entry_type'  => $contra,
                'amount'      => $amount,
                'supplier_id' => $supplierId,
                'ledger_id'   => $ap->id,
                'description' => $description,
            ]];
        }

        // general / reserve_fund → the chosen ledger, with an optional VAT split.
        $vat = $vatType instanceof VatType
            ? $vatType
            : ($vatType ? VatType::tryFrom((string) $vatType) : null);

        $lines = [];

        if ($vat && $vat->hasVat()) {
            $rate = $vat->rate();
            $tax  = round($amount - $amount / (1 + $rate / 100), 2);
            $net  = round($amount - $tax, 2);

            $lines[] = [
                'line_type'   => $type,
                'entry_type'  => $contra,
                'amount'      => $net,
                'ledger_id'   => $ledgerId,
                'description' => $description,
            ];
            $lines[] = [
                'line_type'   => JournalLineType::GENERAL,
                'entry_type'  => $contra,
                'amount'      => $tax,
                'ledger_id'   => $gl->controlAccount($orgId, FinancialCategory::VAT_CONTROL)->id,
                'description' => 'VAT on ' . $description,
            ];

            return $lines;
        }

        return [[
            'line_type'   => $type,
            'entry_type'  => $contra,
            'amount'      => $amount,
            'ledger_id'   => $ledgerId,
            'description' => $description,
        ]];
    }

    /**
     * The "Account" column label for an allocated entry.
     *
     *   general / reserve_fund → "{ledger.code} - {ledger.name}"
     *   customer               → "{owner.customer_code} - {owner.full_name}"
     *   supplier               → "{supplier.supplier_code} - {supplier.name}"
     *   unallocated            → null
     *
     * @param CashbookEntry $entry
     * @return string|null
     */
    public function accountLabelFor(CashbookEntry $entry): ?string
    {
        $type = $entry->allocation_ledger_type;

        if (!$type) {
            return null;
        }

        return match ($type) {
            JournalLineType::GENERAL,
            JournalLineType::RESERVE_FUND => $this->ledgerLabel($entry->ledger_id),
            JournalLineType::CUSTOMER     => $this->customerLabel($entry->unit_id),
            JournalLineType::SUPPLIER     => $this->supplierLabel($entry->supplier_id),
        };
    }

    /**
     * Build the "{code} - {name}" label for a ledger account.
     *
     * @param string|null $ledgerId
     * @return string|null
     */
    public function ledgerLabel(?string $ledgerId): ?string
    {
        if (!$ledgerId) {
            return null;
        }

        $ledger = Ledger::find($ledgerId);

        return $ledger ? trim("{$ledger->code} - {$ledger->name}") : null;
    }

    /**
     * Build the "{customer_code} - {full_name}" label for a customer (unit).
     *
     * @param string|null $unitId
     * @return string|null
     */
    public function customerLabel(?string $unitId): ?string
    {
        if (!$unitId) {
            return null;
        }

        $owner = Owner::where('unit_id', $unitId)->orderByDesc('is_primary')->first();

        if ($owner) {
            return trim("{$owner->customer_code} - {$owner->full_name}");
        }

        $unit = Unit::find($unitId);

        return $unit ? trim("{$unit->customer_code} - {$unit->unit_number}") : null;
    }

    /**
     * Build the "{supplier_code} - {name}" label for a supplier.
     *
     * @param string|null $supplierId
     * @return string|null
     */
    public function supplierLabel(?string $supplierId): ?string
    {
        if (!$supplierId) {
            return null;
        }

        $supplier = Supplier::find($supplierId);

        return $supplier ? trim("{$supplier->supplier_code} - {$supplier->name}") : null;
    }
}
