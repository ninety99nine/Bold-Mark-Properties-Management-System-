<?php

use App\Enums\CashbookEntryType;
use App\Enums\InvoiceStatus;
use App\Enums\JournalEntryType;
use App\Enums\JournalLineType;
use App\Models\BankAccount;
use App\Models\CashbookEntry;
use App\Models\Community;
use App\Models\Invoice;
use App\Models\JournalBatch;
use App\Models\JournalLine;
use App\Models\Ledger;
use App\Models\Owner;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\Unit;
use App\Services\AllocationPostingService;
use App\Services\UnitBalanceService;
use Database\Seeders\ChartOfAccountsSeeder;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * A community with the full chart of accounts seeded for the org (financial
 * year ending 30 June, matching the other GL suites).
 */
function backfillCommunity(\App\Models\User $user): Community
{
    (new ChartOfAccountsSeeder())->seedForOrganization($user->organization_id);

    return Community::factory()->create([
        'organization_id'          => $user->organization_id,
        'financial_year_end_month' => 6,
    ]);
}

/**
 * Total debits and credits across every journal line in a community's books.
 *
 * @return array{0: float, 1: float}
 */
function bookTotals(Community $community): array
{
    $lines = JournalLine::query()
        ->whereHas('batch', fn ($q) => $q->where('community_id', $community->id))
        ->get(['entry_type', 'amount']);

    $debit  = (float) $lines->where('entry_type', JournalEntryType::DEBIT->value)->sum('amount');
    $credit = (float) $lines->where('entry_type', JournalEntryType::CREDIT->value)->sum('amount');

    return [round($debit, 2), round($credit, 2)];
}

// ──────────────────────────────────────────────────────────────────────────────
// Idempotency
// ──────────────────────────────────────────────────────────────────────────────

it('posts zero new batches when every document already posted at create time', function () {
    $user      = adminUser();
    $community = backfillCommunity($user);

    $unit  = Unit::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id]);
    Owner::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'unit_id'         => $unit->id,
        'is_primary'      => true,
    ]);

    // Invoices (auto-post via factory afterCreating).
    Invoice::factory()->count(3)->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'status'          => InvoiceStatus::UNPAID->value,
    ]);

    // A supplier invoice (auto-posts, status = created).
    $supplier = Supplier::factory()->create(['organization_id' => $user->organization_id]);
    SupplierInvoice::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'supplier_id'     => $supplier->id,
    ]);

    // A bank account + an allocated customer receipt (auto-posts).
    $bank = BankAccount::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
    ]);
    $receipt = CashbookEntry::factory()->create([
        'organization_id'  => $user->organization_id,
        'community_id'     => $community->id,
        'bank_account_id'  => $bank->id,
        'type'             => CashbookEntryType::CREDIT->value,
        'amount'           => 1000,
    ]);
    app(AllocationPostingService::class)->post($receipt, [
        'ledger_type' => JournalLineType::CUSTOMER->value,
        'unit_id'     => $unit->id,
    ]);

    $before = JournalBatch::query()->where('community_id', $community->id)->count();

    $this->artisan('gl:backfill', ['--community' => $community->id])->assertSuccessful();

    $after = JournalBatch::query()->where('community_id', $community->id)->count();

    // Everything was already posted, so the backfill adds nothing.
    expect($after)->toBe($before);
});

it('is safe to run twice — the second run posts nothing new', function () {
    $user      = adminUser();
    $community = backfillCommunity($user);

    $bank = BankAccount::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'opening_balance' => 25000,
        'balance_as_at'   => '2026-07-01',
    ]);

    $this->artisan('gl:backfill', ['--community' => $community->id])->assertSuccessful();
    $afterFirst = JournalBatch::query()->where('community_id', $community->id)->count();

    // The opening balance was posted exactly once.
    expect($afterFirst)->toBe(1);

    $this->artisan('gl:backfill', ['--community' => $community->id])->assertSuccessful();
    $afterSecond = JournalBatch::query()->where('community_id', $community->id)->count();

    expect($afterSecond)->toBe($afterFirst);
});

// ──────────────────────────────────────────────────────────────────────────────
// Posting a document that bypassed the service
// ──────────────────────────────────────────────────────────────────────────────

it('posts exactly one batch for a document created without GL posting', function () {
    $user      = adminUser();
    $community = backfillCommunity($user);

    $unit  = Unit::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id]);
    Owner::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'unit_id'         => $unit->id,
        'is_primary'      => true,
    ]);

    // Create the invoice WITHOUT firing the factory afterCreating hook, so it has
    // no GL batch (simulating a legacy / imported document).
    $invoice = Invoice::withoutEvents(fn () => Invoice::query()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'ledger_id'       => Ledger::where('organization_id', $user->organization_id)->where('code', '1000/001')->value('id'),
        'invoice_number'  => 'INV-LEGACY-0001',
        'billed_to_type'  => \App\Enums\BilledToType::OWNER->value,
        'billed_to_id'    => (string) \Illuminate\Support\Str::uuid(),
        'status'          => InvoiceStatus::UNPAID->value,
        'amount'          => 1500,
        'billing_period'  => '2026-07-01',
        'due_date'        => '2026-07-25',
    ]));

    expect(JournalBatch::query()->where('source_type', $invoice->getMorphClass())->where('source_id', $invoice->id)->count())->toBe(0);

    $this->artisan('gl:backfill', ['--community' => $community->id])->assertSuccessful();

    expect(JournalBatch::query()->where('source_type', $invoice->getMorphClass())->where('source_id', $invoice->id)->count())->toBe(1);
});

it('dry-run reports without posting anything', function () {
    $user      = adminUser();
    $community = backfillCommunity($user);

    BankAccount::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'opening_balance' => 12000,
        'balance_as_at'   => '2026-07-01',
    ]);

    $this->artisan('gl:backfill', ['--community' => $community->id, '--dry-run' => true])->assertSuccessful();

    expect(JournalBatch::query()->where('community_id', $community->id)->count())->toBe(0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Opening balances
// ──────────────────────────────────────────────────────────────────────────────

it('posts a balanced Dr bank / Cr retained income opening balance batch', function () {
    $user      = adminUser();
    $community = backfillCommunity($user);

    $bank = BankAccount::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'opening_balance' => 50000,
        'balance_as_at'   => '2026-07-01',
    ]);

    $this->artisan('gl:backfill', ['--community' => $community->id])->assertSuccessful();

    $batch = JournalBatch::query()
        ->where('source_type', $bank->getMorphClass())
        ->where('source_id', $bank->id)
        ->with('lines')
        ->first();

    expect($batch)->not->toBeNull();
    expect($batch->journal_group)->toBe('Opening Balances');

    $bankLine     = $batch->lines->firstWhere('ledger_id', $bank->fresh()->ledger_id);
    $retained     = Ledger::controlAccount($user->organization_id, \App\Enums\FinancialCategory::RETAINED_INCOME);
    $retainedLine = $batch->lines->firstWhere('ledger_id', $retained->id);

    expect($bankLine->entry_type)->toBe(JournalEntryType::DEBIT);
    expect((float) $bankLine->amount)->toBe(50000.0);
    expect($retainedLine->entry_type)->toBe(JournalEntryType::CREDIT);
    expect((float) $retainedLine->amount)->toBe(50000.0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Reconciliation — the books balance and match the stored balances
// ──────────────────────────────────────────────────────────────────────────────

it('reconciles: GL accounts-receivable per unit equals the stored unit balance', function () {
    $user      = adminUser();
    $community = backfillCommunity($user);

    $unit = Unit::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id]);
    Owner::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'unit_id'         => $unit->id,
        'is_primary'      => true,
    ]);

    Invoice::factory()->count(2)->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'status'          => InvoiceStatus::UNPAID->value,
        'amount'          => 1200,
    ]);

    $this->artisan('gl:backfill', ['--community' => $community->id])->assertSuccessful();

    // AR balance for the unit = Σ(credit − debit) on its customer lines — the same
    // formula UnitBalanceService stores.
    $customerLines = JournalLine::query()
        ->where('line_type', JournalLineType::CUSTOMER->value)
        ->where('unit_id', $unit->id)
        ->get(['entry_type', 'amount']);

    $glBalance = round((float) $customerLines->sum(
        fn ($l) => $l->entry_type === JournalEntryType::CREDIT ? (float) $l->amount : -(float) $l->amount
    ), 2);

    app(UnitBalanceService::class)->recalculate($unit);

    expect($glBalance)->toBe(round((float) $unit->fresh()->balance, 2));
    expect($glBalance)->toBe(-2400.0); // owes two 1200 invoices
});

it('reconciles: total debits equal total credits across the whole community', function () {
    $user      = adminUser();
    $community = backfillCommunity($user);

    $unit = Unit::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id]);
    Owner::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'unit_id'         => $unit->id,
        'is_primary'      => true,
    ]);

    Invoice::factory()->count(2)->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'status'          => InvoiceStatus::UNPAID->value,
    ]);

    $supplier = Supplier::factory()->create(['organization_id' => $user->organization_id]);
    SupplierInvoice::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'supplier_id'     => $supplier->id,
    ]);

    $bank = BankAccount::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'opening_balance' => 75000,
        'balance_as_at'   => '2026-07-01',
    ]);
    $receipt = CashbookEntry::factory()->create([
        'organization_id'  => $user->organization_id,
        'community_id'     => $community->id,
        'bank_account_id'  => $bank->id,
        'type'             => CashbookEntryType::CREDIT->value,
        'amount'           => 900,
    ]);
    app(AllocationPostingService::class)->post($receipt, [
        'ledger_type' => JournalLineType::CUSTOMER->value,
        'unit_id'     => $unit->id,
    ]);

    $this->artisan('gl:backfill', ['--community' => $community->id])->assertSuccessful();

    [$debit, $credit] = bookTotals($community);

    expect($debit)->toBe($credit);
    expect($debit)->toBeGreaterThan(0.0);
});
