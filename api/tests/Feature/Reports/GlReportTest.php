<?php

use App\Enums\FinancialCategory;
use App\Enums\JournalEntryType;
use App\Enums\JournalLineType;
use App\Enums\JournalSource;
use App\Models\Community;
use App\Models\CommunityBudget;
use App\Models\Ledger;
use App\Models\Unit;
use App\Services\GeneralLedgerPostingService;
use Carbon\Carbon;
use Database\Seeders\ChartOfAccountsSeeder;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Community (FY ends December → calendar year) with a full chart of accounts.
 */
function reportCommunity(\App\Models\User $user): Community
{
    (new ChartOfAccountsSeeder())->seedForOrganization($user->organization_id);

    return Community::factory()->create([
        'organization_id'          => $user->organization_id,
        'financial_year_end_month' => 12,
    ]);
}

function ledgerByCode(string $orgId, string $code): Ledger
{
    return Ledger::where('organization_id', $orgId)->where('code', $code)->firstOrFail();
}

function gl(): GeneralLedgerPostingService
{
    return app(GeneralLedgerPostingService::class);
}

// ──────────────────────────────────────────────────────────────────────────────
// Auth
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on report routes when unauthenticated', function (string $route) {
    $this->getJson(route($route, ['community' => \Illuminate\Support\Str::uuid()->toString()]))->assertUnauthorized();
})->with([
    'api.v1.show.community.report.trial.balance',
    'api.v1.show.community.report.general.ledger',
    'api.v1.show.community.report.income.statement',
    'api.v1.show.community.report.actual.vs.budget',
    'api.v1.show.community.report.vat.201',
    'api.v1.show.community.report.cash.movement',
]);

// ──────────────────────────────────────────────────────────────────────────────
// 1. Trial Balance
// ──────────────────────────────────────────────────────────────────────────────

it('trial balance balances after invoices, receipts and a supplier invoice', function () {
    $user      = adminUser();
    $community = reportCommunity($user);
    $unit      = Unit::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id]);

    $ar      = ledgerByCode($user->organization_id, '7000/001');
    $levies  = ledgerByCode($user->organization_id, '1000/001');
    $ap      = ledgerByCode($user->organization_id, '6000/003');
    $expense = ledgerByCode($user->organization_id, '2000/002');
    $bank    = ledgerByCode($user->organization_id, '5000/002'); // any contra for the receipt

    // Invoice: Dr AR 1000 / Cr Levies 1000
    gl()->postBatch($community, Carbon::parse('2026-03-01'), JournalSource::INVOICE, null, 'Levy', [
        ['line_type' => JournalLineType::CUSTOMER, 'entry_type' => JournalEntryType::DEBIT,  'amount' => 1000, 'unit_id' => $unit->id, 'ledger_id' => $ar->id],
        ['line_type' => JournalLineType::GENERAL,  'entry_type' => JournalEntryType::CREDIT, 'amount' => 1000, 'ledger_id' => $levies->id],
    ]);

    // Receipt: Dr Reserve 400 / Cr AR 400 (customer pays)
    gl()->postBatch($community, Carbon::parse('2026-03-15'), JournalSource::CASHBOOK, null, 'Transfer', [
        ['line_type' => JournalLineType::GENERAL,  'entry_type' => JournalEntryType::DEBIT,  'amount' => 400, 'ledger_id' => $bank->id],
        ['line_type' => JournalLineType::CUSTOMER, 'entry_type' => JournalEntryType::CREDIT, 'amount' => 400, 'unit_id' => $unit->id, 'ledger_id' => $ar->id],
    ]);

    // Supplier invoice: Dr Expense 300 / Cr AP 300
    gl()->postBatch($community, Carbon::parse('2026-04-01'), JournalSource::SUPPLIER_INVOICE, null, 'Accrual', [
        ['line_type' => JournalLineType::GENERAL,  'entry_type' => JournalEntryType::DEBIT,  'amount' => 300, 'ledger_id' => $expense->id],
        ['line_type' => JournalLineType::GENERAL,  'entry_type' => JournalEntryType::CREDIT, 'amount' => 300, 'ledger_id' => $ap->id],
    ]);

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.report.trial.balance', ['community' => $community->id, 'financial_year' => 2026]))
        ->assertOk()
        ->json();

    // The load-bearing assertion: total debits === total credits.
    expect(round($data['totals']['debit'], 2))->toBe(round($data['totals']['credit'], 2));
    expect($data['totals']['debit'])->toBeGreaterThan(0);

    // Levies is credit-natural → shows a 1000 credit.
    $leviesRow = collect($data['rows'])->firstWhere('code', '1000/001');
    expect(round((float) $leviesRow['credit'], 2))->toBe(1000.00);

    // Expense is debit-natural → shows a 300 debit.
    $expenseRow = collect($data['rows'])->firstWhere('code', '2000/002');
    expect(round((float) $expenseRow['debit'], 2))->toBe(300.00);
});

it('trial balance hides zero-movement accounts by default and can include them', function () {
    $user      = adminUser();
    $community = reportCommunity($user);
    $levies    = ledgerByCode($user->organization_id, '1000/001');
    $expense   = ledgerByCode($user->organization_id, '2000/002');

    gl()->postBatch($community, Carbon::parse('2026-03-01'), JournalSource::MANUAL, null, null, [
        ['line_type' => JournalLineType::GENERAL, 'entry_type' => JournalEntryType::DEBIT,  'amount' => 500, 'ledger_id' => $expense->id],
        ['line_type' => JournalLineType::GENERAL, 'entry_type' => JournalEntryType::CREDIT, 'amount' => 500, 'ledger_id' => $levies->id],
    ]);

    $hidden = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.report.trial.balance', ['community' => $community->id]))
        ->assertOk()->json();
    expect(collect($hidden['rows']))->toHaveCount(2);

    $all = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.report.trial.balance', ['community' => $community->id, 'hide_zero' => 0]))
        ->assertOk()->json();
    expect(collect($all['rows'])->count())->toBeGreaterThan(2);
});

// ──────────────────────────────────────────────────────────────────────────────
// 2. Detailed General Ledger
// ──────────────────────────────────────────────────────────────────────────────

it('detailed general ledger returns opening, running balance and closing per account', function () {
    $user      = adminUser();
    $community = reportCommunity($user);
    $levies    = ledgerByCode($user->organization_id, '1000/001');
    $expense   = ledgerByCode($user->organization_id, '2000/002');

    // Before the window (contributes to opening).
    gl()->postBatch($community, Carbon::parse('2026-01-10'), JournalSource::MANUAL, null, null, [
        ['line_type' => JournalLineType::GENERAL, 'entry_type' => JournalEntryType::DEBIT,  'amount' => 100, 'ledger_id' => $expense->id],
        ['line_type' => JournalLineType::GENERAL, 'entry_type' => JournalEntryType::CREDIT, 'amount' => 100, 'ledger_id' => $levies->id],
    ]);

    // In the window.
    gl()->postBatch($community, Carbon::parse('2026-03-05'), JournalSource::MANUAL, null, null, [
        ['line_type' => JournalLineType::GENERAL, 'entry_type' => JournalEntryType::DEBIT,  'amount' => 250, 'ledger_id' => $expense->id],
        ['line_type' => JournalLineType::GENERAL, 'entry_type' => JournalEntryType::CREDIT, 'amount' => 250, 'ledger_id' => $levies->id],
    ]);

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.report.general.ledger', [
            'community' => $community->id,
            'from'      => '2026-02-01',
            'to'        => '2026-06-30',
            'ledger_id' => $expense->id,
        ]))
        ->assertOk()->json();

    expect($data['accounts'])->toHaveCount(1);
    $acc = $data['accounts'][0];

    expect(round((float) $acc['opening'], 2))->toBe(100.00)          // debit before window
        ->and($acc['transactions'])->toHaveCount(1)
        ->and(round((float) $acc['transactions'][0]['debit'], 2))->toBe(250.00)
        ->and(round((float) $acc['transactions'][0]['balance'], 2))->toBe(350.00)
        ->and(round((float) $acc['closing'], 2))->toBe(350.00);
});

// ──────────────────────────────────────────────────────────────────────────────
// 3. Income Statement
// ──────────────────────────────────────────────────────────────────────────────

it('income statement nets income minus expenses', function () {
    $user      = adminUser();
    $community = reportCommunity($user);
    $levies    = ledgerByCode($user->organization_id, '1000/001');
    $expense   = ledgerByCode($user->organization_id, '2000/002');
    $ar        = ledgerByCode($user->organization_id, '7000/001');
    $ap        = ledgerByCode($user->organization_id, '6000/003');

    // Income 1000.
    gl()->postBatch($community, Carbon::parse('2026-03-01'), JournalSource::INVOICE, null, 'Levy', [
        ['line_type' => JournalLineType::GENERAL, 'entry_type' => JournalEntryType::DEBIT,  'amount' => 1000, 'ledger_id' => $ar->id],
        ['line_type' => JournalLineType::GENERAL, 'entry_type' => JournalEntryType::CREDIT, 'amount' => 1000, 'ledger_id' => $levies->id],
    ]);

    // Expense 400.
    gl()->postBatch($community, Carbon::parse('2026-04-01'), JournalSource::SUPPLIER_INVOICE, null, 'Accrual', [
        ['line_type' => JournalLineType::GENERAL, 'entry_type' => JournalEntryType::DEBIT,  'amount' => 400, 'ledger_id' => $expense->id],
        ['line_type' => JournalLineType::GENERAL, 'entry_type' => JournalEntryType::CREDIT, 'amount' => 400, 'ledger_id' => $ap->id],
    ]);

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.report.income.statement', ['community' => $community->id, 'financial_year' => 2026]))
        ->assertOk()->json();

    expect(round($data['totals']['income'], 2))->toBe(1000.00)
        ->and(round($data['totals']['expense'], 2))->toBe(400.00)
        ->and(round($data['totals']['net'], 2))->toBe(600.00);

    // Income group carries the Levies account row with a positive actual.
    $incomeAccounts = collect($data['income'])->flatMap(fn ($g) => $g['accounts']);
    expect(round((float) $incomeAccounts->firstWhere('code', '1000/001')['actual'], 2))->toBe(1000.00);
});

it('income statement reserve fund variant only shows reserve accounts', function () {
    $user      = adminUser();
    $community = reportCommunity($user);
    $rfi       = ledgerByCode($user->organization_id, 'RFI/001');
    $reserve   = ledgerByCode($user->organization_id, '5000/002');

    gl()->postBatch($community, Carbon::parse('2026-05-01'), JournalSource::MANUAL, null, null, [
        ['line_type' => JournalLineType::RESERVE_FUND, 'entry_type' => JournalEntryType::CREDIT, 'amount' => 800, 'ledger_id' => $rfi->id],
        ['line_type' => JournalLineType::GENERAL,      'entry_type' => JournalEntryType::DEBIT,  'amount' => 800, 'ledger_id' => $reserve->id],
    ]);

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.report.income.statement', ['community' => $community->id, 'financial_year' => 2026, 'fund' => 'reserve']))
        ->assertOk()->json();

    expect(round($data['totals']['income'], 2))->toBe(800.00)
        ->and($data['fund'])->toBe('reserve');
});

// ──────────────────────────────────────────────────────────────────────────────
// 4. Actual vs Budget
// ──────────────────────────────────────────────────────────────────────────────

it('actual vs budget pulls community budgets and computes variance', function () {
    $user      = adminUser();
    $community = reportCommunity($user);
    $levies    = ledgerByCode($user->organization_id, '1000/001');
    $ar        = ledgerByCode($user->organization_id, '7000/001');

    // March actual income of 1200.
    gl()->postBatch($community, Carbon::parse('2026-03-10'), JournalSource::INVOICE, null, 'Levy', [
        ['line_type' => JournalLineType::GENERAL, 'entry_type' => JournalEntryType::DEBIT,  'amount' => 1200, 'ledger_id' => $ar->id],
        ['line_type' => JournalLineType::GENERAL, 'entry_type' => JournalEntryType::CREDIT, 'amount' => 1200, 'ledger_id' => $levies->id],
    ]);

    // Budget: 1000/month for Levies.
    CommunityBudget::create([
        'community_id'    => $community->id,
        'organization_id' => $user->organization_id,
        'ledger_id'       => $levies->id,
        'year'            => 2026,
        'jan' => 1000, 'feb' => 1000, 'mar' => 1000, 'apr' => 1000, 'may' => 1000, 'jun' => 1000,
        'jul' => 1000, 'aug' => 1000, 'sep' => 1000, 'oct' => 1000, 'nov' => 1000, 'dec' => 1000,
        'per_year' => 12000,
    ]);

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.report.actual.vs.budget', ['community' => $community->id, 'financial_year' => 2026, 'hide_zero' => 1]))
        ->assertOk()->json();

    $levyRow = collect($data['income'])->flatMap(fn ($g) => $g['accounts'])->firstWhere('code', '1000/001');

    expect(round((float) $levyRow['ytd_actual'], 2))->toBe(1200.00)
        ->and(round((float) $levyRow['ytd_budget'], 2))->toBe(12000.00)
        ->and(round((float) $levyRow['total_budget'], 2))->toBe(12000.00)
        ->and(round((float) $levyRow['variance'], 2))->toBe(-10800.00)
        ->and(round((float) $levyRow['actual']['mar'], 2))->toBe(1200.00)
        ->and(round((float) $levyRow['budget']['jan'], 2))->toBe(1000.00);
});

// ──────────────────────────────────────────────────────────────────────────────
// 5. VAT 201
// ──────────────────────────────────────────────────────────────────────────────

it('vat 201 summarises output and input vat from the vat control account', function () {
    $user      = adminUser();
    $community = reportCommunity($user);
    $vat       = ledgerByCode($user->organization_id, '6000/001');
    $levies    = ledgerByCode($user->organization_id, '1000/001');
    $expense   = ledgerByCode($user->organization_id, '2000/002');

    // Output VAT — Cr VAT Control 150 (on a sale).
    gl()->postBatch($community, Carbon::parse('2026-03-01'), JournalSource::INVOICE, null, 'Levy', [
        ['line_type' => JournalLineType::GENERAL, 'entry_type' => JournalEntryType::CREDIT, 'amount' => 150, 'ledger_id' => $vat->id],
        ['line_type' => JournalLineType::GENERAL, 'entry_type' => JournalEntryType::DEBIT,  'amount' => 150, 'ledger_id' => $levies->id],
    ]);

    // Input VAT — Dr VAT Control 40 (on a purchase).
    gl()->postBatch($community, Carbon::parse('2026-03-20'), JournalSource::SUPPLIER_INVOICE, null, 'Accrual', [
        ['line_type' => JournalLineType::GENERAL, 'entry_type' => JournalEntryType::DEBIT,  'amount' => 40, 'ledger_id' => $vat->id],
        ['line_type' => JournalLineType::GENERAL, 'entry_type' => JournalEntryType::CREDIT, 'amount' => 40, 'ledger_id' => $expense->id],
    ]);

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.report.vat.201', ['community' => $community->id, 'from' => '2026-01-01', 'to' => '2026-12-31']))
        ->assertOk()->json();

    expect(round($data['output_vat'], 2))->toBe(150.00)
        ->and(round($data['input_vat'], 2))->toBe(40.00)
        ->and(round($data['net_vat'], 2))->toBe(110.00)
        ->and($data['direction'])->toBe('payable');
});

// ──────────────────────────────────────────────────────────────────────────────
// 6. Basic Cash Movement
// ──────────────────────────────────────────────────────────────────────────────

it('cash movement reports opening, receipts, payments and closing per bank', function () {
    $user      = adminUser();
    $community = reportCommunity($user);

    // A bank ledger (financial_category=bank, 8000 prefix).
    $bank = Ledger::create([
        'organization_id'    => $user->organization_id,
        'code'               => '8000/001',
        'name'               => 'Standard Bank Current',
        'account_type'       => 'balance_sheet',
        'financial_category' => FinancialCategory::BANK,
        'fund'               => 'main',
        'parent_id'          => null,
        'applies_to'         => 'owner',
    ]);
    // Give it a parent so it appears as a leaf on report ledgers.
    $bankParent = Ledger::create([
        'organization_id'    => $user->organization_id,
        'code'               => '8000/000',
        'name'               => 'BANK',
        'account_type'       => 'balance_sheet',
        'financial_category' => FinancialCategory::BANK,
        'fund'               => 'main',
        'parent_id'          => null,
        'applies_to'         => 'owner',
    ]);
    $bank->update(['parent_id' => $bankParent->id]);

    $suspense = ledgerByCode($user->organization_id, '9900/001');

    // Opening: Dr Bank 5000 before the window.
    gl()->postBatch($community, Carbon::parse('2026-01-05'), JournalSource::CASHBOOK, null, null, [
        ['line_type' => JournalLineType::GENERAL, 'entry_type' => JournalEntryType::DEBIT,  'amount' => 5000, 'ledger_id' => $bank->id],
        ['line_type' => JournalLineType::GENERAL, 'entry_type' => JournalEntryType::CREDIT, 'amount' => 5000, 'ledger_id' => $suspense->id],
    ]);

    // In window — receipt 2000, payment 800.
    gl()->postBatch($community, Carbon::parse('2026-03-01'), JournalSource::CASHBOOK, null, null, [
        ['line_type' => JournalLineType::GENERAL, 'entry_type' => JournalEntryType::DEBIT,  'amount' => 2000, 'ledger_id' => $bank->id],
        ['line_type' => JournalLineType::GENERAL, 'entry_type' => JournalEntryType::CREDIT, 'amount' => 2000, 'ledger_id' => $suspense->id],
    ]);
    gl()->postBatch($community, Carbon::parse('2026-03-15'), JournalSource::CASHBOOK, null, null, [
        ['line_type' => JournalLineType::GENERAL, 'entry_type' => JournalEntryType::CREDIT, 'amount' => 800, 'ledger_id' => $bank->id],
        ['line_type' => JournalLineType::GENERAL, 'entry_type' => JournalEntryType::DEBIT,  'amount' => 800, 'ledger_id' => $suspense->id],
    ]);

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.report.cash.movement', ['community' => $community->id, 'from' => '2026-02-01', 'to' => '2026-06-30']))
        ->assertOk()->json();

    $row = collect($data['banks'])->firstWhere('code', '8000/001');

    expect(round((float) $row['opening'], 2))->toBe(5000.00)
        ->and(round((float) $row['receipts'], 2))->toBe(2000.00)
        ->and(round((float) $row['payments'], 2))->toBe(800.00)
        ->and(round((float) $row['closing'], 2))->toBe(6200.00);
});

// ──────────────────────────────────────────────────────────────────────────────
// Exports
// ──────────────────────────────────────────────────────────────────────────────

it('downloads each report as xlsx', function (string $route) {
    $user      = adminUser();
    $community = reportCommunity($user);
    $levies    = ledgerByCode($user->organization_id, '1000/001');
    $expense   = ledgerByCode($user->organization_id, '2000/002');

    gl()->postBatch($community, Carbon::parse('2026-03-01'), JournalSource::MANUAL, null, null, [
        ['line_type' => JournalLineType::GENERAL, 'entry_type' => JournalEntryType::DEBIT,  'amount' => 500, 'ledger_id' => $expense->id],
        ['line_type' => JournalLineType::GENERAL, 'entry_type' => JournalEntryType::CREDIT, 'amount' => 500, 'ledger_id' => $levies->id],
    ]);

    $this->actingAs($user, 'api')
        ->get(route($route, ['community' => $community->id, 'financial_year' => 2026]))
        ->assertOk();
})->with([
    'api.v1.export.community.report.trial.balance',
    'api.v1.export.community.report.general.ledger',
    'api.v1.export.community.report.income.statement',
    'api.v1.export.community.report.actual.vs.budget',
    'api.v1.export.community.report.vat.201',
    'api.v1.export.community.report.cash.movement',
]);

it('serves the export via the ?export=1 flag on the json endpoint', function () {
    $user      = adminUser();
    $community = reportCommunity($user);

    $this->actingAs($user, 'api')
        ->get(route('api.v1.show.community.report.trial.balance', ['community' => $community->id, 'export' => 1]))
        ->assertOk()
        ->assertHeader('content-disposition');
});

// ──────────────────────────────────────────────────────────────────────────────
// Scoping
// ──────────────────────────────────────────────────────────────────────────────

it('scopes reports to the community — another community sees no movement', function () {
    $user       = adminUser();
    $communityA = reportCommunity($user);
    $communityB = Community::factory()->create(['organization_id' => $user->organization_id, 'financial_year_end_month' => 12]);
    $levies     = ledgerByCode($user->organization_id, '1000/001');
    $expense    = ledgerByCode($user->organization_id, '2000/002');

    gl()->postBatch($communityA, Carbon::parse('2026-03-01'), JournalSource::MANUAL, null, null, [
        ['line_type' => JournalLineType::GENERAL, 'entry_type' => JournalEntryType::DEBIT,  'amount' => 500, 'ledger_id' => $expense->id],
        ['line_type' => JournalLineType::GENERAL, 'entry_type' => JournalEntryType::CREDIT, 'amount' => 500, 'ledger_id' => $levies->id],
    ]);

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.report.trial.balance', ['community' => $communityB->id]))
        ->assertOk()->json();

    expect($data['rows'])->toHaveCount(0)
        ->and(round($data['totals']['debit'], 2))->toBe(0.00);
});
