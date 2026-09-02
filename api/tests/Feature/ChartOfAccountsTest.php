<?php

use App\Enums\FinancialCategory;
use App\Models\Ledger;
use Database\Seeders\ChartOfAccountsSeeder;

// =============================================================================
// Seeding — GL classification & hierarchy
// =============================================================================

it('sets financial_category on the four control accounts', function (): void {
    $org = createOrganization();
    (new ChartOfAccountsSeeder())->seedForOrganization($org->id);

    expect(Ledger::where('organization_id', $org->id)->where('code', '7000/001')->value('financial_category'))
        ->toBe(FinancialCategory::ACCOUNTS_RECEIVABLE);
    expect(Ledger::where('organization_id', $org->id)->where('code', '6000/003')->value('financial_category'))
        ->toBe(FinancialCategory::ACCOUNTS_PAYABLE);
    expect(Ledger::where('organization_id', $org->id)->where('code', '6000/001')->value('financial_category'))
        ->toBe(FinancialCategory::VAT_CONTROL);
    expect(Ledger::where('organization_id', $org->id)->where('code', '5000/001')->value('financial_category'))
        ->toBe(FinancialCategory::RETAINED_INCOME);
});

it('seeds main accounts with parent links to their sub-accounts', function (): void {
    $org = createOrganization();
    (new ChartOfAccountsSeeder())->seedForOrganization($org->id);

    $main = Ledger::where('organization_id', $org->id)->where('code', '1000/000')->first();
    $sub  = Ledger::where('organization_id', $org->id)->where('code', '1000/001')->first();

    expect($main)->not->toBeNull();
    expect($main->parent_id)->toBeNull();
    expect($main->allow_sub_accounts)->toBeTrue();
    expect($main->name)->toBe('INCOME');
    expect($sub->parent_id)->toBe($main->id);
    expect($sub->allow_sub_accounts)->toBeFalse();
});

it('seeds the reserve fund chart (RFI/RFE) with fund=reserve', function (): void {
    $org = createOrganization();
    (new ChartOfAccountsSeeder())->seedForOrganization($org->id);

    expect(Ledger::where('organization_id', $org->id)->where('code', 'RFI/000')->value('fund'))->toBe('reserve');
    expect(Ledger::where('organization_id', $org->id)->where('code', 'RFE/000')->value('fund'))->toBe('reserve');
    expect(Ledger::where('organization_id', $org->id)->where('code', '1000/000')->value('fund'))->toBe('main');
});

it('applies other_income overrides on interest & other income lines', function (): void {
    $org = createOrganization();
    (new ChartOfAccountsSeeder())->seedForOrganization($org->id);

    foreach (['1000/003', '1000/004', '1000/014'] as $code) {
        expect(Ledger::where('organization_id', $org->id)->where('code', $code)->value('financial_category'))
            ->toBe(FinancialCategory::OTHER_INCOME);
    }
    expect(Ledger::where('organization_id', $org->id)->where('code', '1000/001')->value('financial_category'))
        ->toBe(FinancialCategory::SALES);
});

it('is idempotent across repeated seeding', function (): void {
    $org = createOrganization();
    (new ChartOfAccountsSeeder())->seedForOrganization($org->id);
    $count = Ledger::where('organization_id', $org->id)->count();

    (new ChartOfAccountsSeeder())->seedForOrganization($org->id);

    expect(Ledger::where('organization_id', $org->id)->count())->toBe($count);
});

// =============================================================================
// Ledger::controlAccount()
// =============================================================================

it('resolves the accounts receivable control account by category', function (): void {
    $org = createOrganization();
    (new ChartOfAccountsSeeder())->seedForOrganization($org->id);

    $control = Ledger::controlAccount($org->id, FinancialCategory::ACCOUNTS_RECEIVABLE);

    expect($control)->not->toBeNull();
    expect($control->code)->toBe('7000/001');
});

// =============================================================================
// Ledger::nextCode()
// =============================================================================

it('returns the next free main code for a prefix', function (): void {
    $org = createOrganization();

    expect(Ledger::nextCode($org->id, '8000'))->toBe('8000/001');

    Ledger::create([
        'organization_id'    => $org->id,
        'code'               => '8000/001',
        'name'               => 'Cheque Account',
        'applies_to'         => 'owner',
        'is_recurring'       => false,
    ]);

    expect(Ledger::nextCode($org->id, '8000'))->toBe('8000/002');
});

// =============================================================================
// Ledger::nextSubAccountCode()
// =============================================================================

it('returns the next free sub-account code under a main code', function (): void {
    $org = createOrganization();
    (new ChartOfAccountsSeeder())->seedForOrganization($org->id);

    // 2000/000 group seeds up to 2000/024, so the next free sub code is 2000/025.
    expect(Ledger::nextSubAccountCode($org->id, '2000/000'))->toBe('2000/025');
});
