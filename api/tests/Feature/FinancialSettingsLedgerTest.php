<?php

use App\Models\Ledger;
use App\Enums\FinancialCategory;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Create a MAIN account ledger for an organisation.
 */
function glMain(string $orgId, string $code, string $name, string $fund = 'main', string $accountType = 'income_statement', ?FinancialCategory $category = null): Ledger
{
    return Ledger::factory()->create([
        'organization_id'    => $orgId,
        'code'               => $code,
        'name'               => $name,
        'fund'               => $fund,
        'account_type'       => $accountType,
        'financial_category' => $category,
        'parent_id'          => null,
        'allow_sub_accounts' => true,
        'is_system'          => false,
    ]);
}

// ──────────────────────────────────────────────────────────────────────────────
// GET /ledgers/options
// ──────────────────────────────────────────────────────────────────────────────

it('returns the general ledger form options', function () {
    // The seeded chart already provides the 1000/000 INCOME main account.
    $user = adminUser();

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.ledger.options'))
        ->assertOk()
        ->assertJsonStructure([
            'main_accounts'        => [['id', 'code', 'label']],
            'financial_categories' => [['value', 'label']],
            'account_types'        => [['value', 'label']],
            'tax_types'            => [['value', 'label']],
        ]);

    expect($resp->json('account_types'))->toHaveCount(2);
    expect(collect($resp->json('main_accounts'))->pluck('label'))->toContain('1000/000 - INCOME');
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /ledgers?grouped=1 & ?fund
// ──────────────────────────────────────────────────────────────────────────────

it('returns ledgers grouped by main account with nested sub-accounts', function () {
    // Seeded chart: 1000/000 INCOME has several sub-accounts (Levy etc).
    $user = adminUser();

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.charge.types', ['grouped' => 1]))
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'code', 'name', 'sub_accounts']]]);

    $income = collect($resp->json('data'))->firstWhere('code', '1000/000');
    expect($income)->not->toBeNull();
    expect(count($income['sub_accounts']))->toBeGreaterThan(0);
});

it('filters ledgers by fund', function () {
    // Seeded chart carries both main-fund and reserve-fund (RFI/RFE) accounts.
    $user = adminUser();

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.charge.types', ['fund' => 'reserve']))
        ->assertOk();

    $codes = collect($resp->json('data'))->pluck('code');
    expect($codes)->toContain('RFI/000');
    expect($codes->every(fn ($c) => str_starts_with($c, 'RF')))->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /ledgers — main account
// ──────────────────────────────────────────────────────────────────────────────

it('creates a main account with auto X000/000 code', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.charge.type'), [
            'type'               => 'main',
            'prefix'             => '3000',
            'name'               => 'SUNDRY EXPENSES',
            'account_type'       => 'income_statement',
            'financial_category' => FinancialCategory::EXPENSES->value,
            'allow_sub_accounts' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', '3000/000')
        ->assertJsonPath('data.account_type', 'income_statement')
        ->assertJsonPath('data.financial_category', 'expenses');

    $this->assertDatabaseHas('ledgers', [
        'organization_id' => $user->organization_id,
        'code'            => '3000/000',
    ]);
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /ledgers — sub account
// ──────────────────────────────────────────────────────────────────────────────

it('creates a sub-account with the next auto code under its parent', function () {
    $user = adminUser();
    $main = Ledger::where('organization_id', $user->organization_id)
        ->where('code', '2000/000')
        ->firstOrFail();

    // Next free sub-account code under the seeded 2000/000 group.
    $expected = Ledger::nextSubAccountCode($user->organization_id, '2000/000');

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.charge.type'), [
            'type'      => 'sub',
            'parent_id' => $main->id,
            'name'      => 'Audit Fees',
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', $expected)
        ->assertJsonPath('data.parent_id', $main->id)
        ->assertJsonPath('data.account_type', 'income_statement')
        ->assertJsonPath('data.financial_category', 'expenses');
});

it('requires a prefix for a main account', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.charge.type'), [
            'type' => 'main',
            'name' => 'No prefix',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['prefix']);
});

it('requires a parent for a sub-account', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.charge.type'), [
            'type' => 'sub',
            'name' => 'No parent',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['parent_id']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Singleton categories
// ──────────────────────────────────────────────────────────────────────────────

it('rejects a second ledger in a singleton category', function () {
    // The seeded chart already carries a single Accounts Receivable account
    // (7000/001), so any attempt to add a second must be rejected.
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.charge.type'), [
            'type'               => 'main',
            'prefix'             => '7100',
            'name'               => 'Another AR',
            'account_type'       => 'balance_sheet',
            'financial_category' => FinancialCategory::ACCOUNTS_RECEIVABLE->value,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['financial_category']);
});

it('blocks deleting a singleton control account', function () {
    $user    = adminUser();
    $control = Ledger::where('organization_id', $user->organization_id)
        ->where('financial_category', FinancialCategory::ACCOUNTS_RECEIVABLE)
        ->firstOrFail();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.charge.type', $control))
        ->assertStatus(403);
});

// ──────────────────────────────────────────────────────────────────────────────
// Reserve fund ledgers (RFI / RFE)
// ──────────────────────────────────────────────────────────────────────────────

it('creates a reserve fund sub-account with an RFI auto code', function () {
    // Seeded chart has RFI/000 (main) + RFI/001 (Reserve Fund Levy); the next
    // sub-account under RFI/000 must therefore auto-code to RFI/002.
    $user = adminUser();
    $rfi  = Ledger::where('organization_id', $user->organization_id)
        ->where('code', 'RFI/000')
        ->firstOrFail();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.charge.type'), [
            'type'      => 'sub',
            'parent_id' => $rfi->id,
            'name'      => 'Interest Received',
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'RFI/002')
        ->assertJsonPath('data.fund', 'reserve');
});
