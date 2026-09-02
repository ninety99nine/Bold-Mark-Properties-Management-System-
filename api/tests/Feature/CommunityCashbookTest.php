<?php

use App\Models\BankAccount;
use App\Models\CashbookEntry;
use App\Models\Community;
use App\Models\Ledger;

// ──────────────────────────────────────────────────────────────────────────────
// Unauthenticated access
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on community cashbook routes when unauthenticated', function (string $method, string $route) {
    $community = Community::factory()->create(['organization_id' => createOrganization()->id]);

    $this->{$method . 'Json'}(route($route, ['community' => $community->id]))->assertUnauthorized();
})->with([
    ['get',  'api.v1.show.community.cashbook.transactions'],
    ['post', 'api.v1.create.community.cashbook.manual.transactions'],
]);

// ──────────────────────────────────────────────────────────────────────────────
// GET /communities/{community}/cashbook/transactions — running balances
// ──────────────────────────────────────────────────────────────────────────────

it('returns opening, running and closing balances for a bank account', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $bank      = BankAccount::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'balance'         => 1000,
        'balance_as_at'   => '2026-06-01',
    ]);

    CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
        'bank_account_id' => $bank->id, 'type' => 'credit', 'amount' => 500, 'date' => '2026-07-05',
    ]);
    CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
        'bank_account_id' => $bank->id, 'type' => 'debit', 'amount' => 200, 'date' => '2026-07-10',
    ]);
    CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
        'bank_account_id' => $bank->id, 'type' => 'credit', 'amount' => 300, 'date' => '2026-08-01',
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.cashbook.transactions', ['community' => $community->id]) .
            '?bank_account_id=' . $bank->id . '&from=2026-07-01&to=2026-09-02')
        ->assertOk();

    expect((float) $response->json('opening_balance'))->toBe(1000.0);
    expect((float) $response->json('closing_balance'))->toBe(1600.0);
    expect($response->json('transactions'))->toHaveCount(3);
    // Running balance: 1000 +500 -200 +300
    expect((float) $response->json('transactions.0.balance'))->toBe(1500.0);
    expect((float) $response->json('transactions.1.balance'))->toBe(1300.0);
    expect((float) $response->json('transactions.2.balance'))->toBe(1600.0);
});

it('excludes movements before the from date from the running window', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $bank      = BankAccount::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
        'balance'         => 1000, 'balance_as_at' => '2026-06-01',
    ]);

    // Before the window — folds into the opening balance, not the row list.
    CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
        'bank_account_id' => $bank->id, 'type' => 'credit', 'amount' => 250, 'date' => '2026-06-15',
    ]);
    CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
        'bank_account_id' => $bank->id, 'type' => 'credit', 'amount' => 100, 'date' => '2026-07-05',
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.cashbook.transactions', ['community' => $community->id]) .
            '?bank_account_id=' . $bank->id . '&from=2026-07-01&to=2026-09-02')
        ->assertOk();

    expect((float) $response->json('opening_balance'))->toBe(1250.0);  // 1000 + 250 before window
    expect($response->json('transactions'))->toHaveCount(1);
    expect((float) $response->json('transactions.0.balance'))->toBe(1350.0);
    expect((float) $response->json('closing_balance'))->toBe(1350.0);
});

it('hides ledger-allocated rows but keeps the running balance intact', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ledger    = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $bank      = BankAccount::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
        'balance'         => 0, 'balance_as_at' => '2026-06-01',
    ]);

    CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
        'bank_account_id' => $bank->id, 'type' => 'credit', 'amount' => 400, 'date' => '2026-07-05',
        'ledger_id' => $ledger->id, 'allocation_ledger_type' => 'general',  // allocated
    ]);
    CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
        'bank_account_id' => $bank->id, 'type' => 'credit', 'amount' => 100, 'date' => '2026-07-10',
    ]);  // unallocated

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.cashbook.transactions', ['community' => $community->id]) .
            '?bank_account_id=' . $bank->id . '&from=2026-07-01&to=2026-09-02&hide_allocated=1')
        ->assertOk();

    // Only the unallocated row shows, but its balance reflects the hidden 400 credit.
    expect($response->json('transactions'))->toHaveCount(1);
    expect((float) $response->json('transactions.0.balance'))->toBe(500.0);
    expect($response->json('all_allocated'))->toBeFalse();
});

it('reports all_allocated true when every in-range line has a ledger account', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ledger    = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $bank      = BankAccount::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
        'balance'         => 0, 'balance_as_at' => '2026-06-01',
    ]);

    CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
        'bank_account_id' => $bank->id, 'type' => 'credit', 'amount' => 400, 'date' => '2026-07-05',
        'ledger_id' => $ledger->id, 'allocation_ledger_type' => 'general',
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.cashbook.transactions', ['community' => $community->id]) .
            '?bank_account_id=' . $bank->id . '&from=2026-07-01&to=2026-09-02')
        ->assertOk();

    expect($response->json('all_allocated'))->toBeTrue();
});

it('returns an empty cashbook when no bank account is selected', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.cashbook.transactions', ['community' => $community->id]))
        ->assertOk();

    expect($response->json('transactions'))->toBe([]);
    expect((float) $response->json('opening_balance'))->toBe(0.0);
    expect((float) $response->json('closing_balance'))->toBe(0.0);
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /communities/{community}/cashbook/manual-transactions
// ──────────────────────────────────────────────────────────────────────────────

it('bulk-creates manual transactions mapping income to credit and expense to debit', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $bank      = BankAccount::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
    ]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community.cashbook.manual.transactions', ['community' => $community->id]), [
            'bank_account_id' => $bank->id,
            'transactions'    => [
                ['date' => '2026-09-02', 'description' => 'Levy receipt', 'amount' => 1500, 'type' => 'income'],
                ['date' => '2026-09-02', 'description' => 'Garden service', 'amount' => 750, 'type' => 'expense'],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('created', 2);

    $this->assertDatabaseHas('cashbook_entries', [
        'bank_account_id' => $bank->id, 'description' => 'Levy receipt', 'type' => 'credit', 'amount' => 1500,
    ]);
    $this->assertDatabaseHas('cashbook_entries', [
        'bank_account_id' => $bank->id, 'description' => 'Garden service', 'type' => 'debit', 'amount' => 750,
    ]);
});

it('rejects a bank account that belongs to another community', function () {
    $user           = adminUser();
    $community      = Community::factory()->create(['organization_id' => $user->organization_id]);
    $otherCommunity = Community::factory()->create(['organization_id' => $user->organization_id]);
    $otherBank      = BankAccount::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $otherCommunity->id,
    ]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community.cashbook.manual.transactions', ['community' => $community->id]), [
            'bank_account_id' => $otherBank->id,
            'transactions'    => [
                ['date' => '2026-09-02', 'description' => 'X', 'amount' => 1, 'type' => 'income'],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['bank_account_id']);
});

it('returns 422 when a transaction line is missing its description', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $bank      = BankAccount::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
    ]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community.cashbook.manual.transactions', ['community' => $community->id]), [
            'bank_account_id' => $bank->id,
            'transactions'    => [
                ['date' => '2026-09-02', 'description' => '', 'amount' => 1, 'type' => 'income'],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['transactions.0.description']);
});

it('returns 422 when transactions is empty', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $bank      = BankAccount::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
    ]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community.cashbook.manual.transactions', ['community' => $community->id]), [
            'bank_account_id' => $bank->id,
            'transactions'    => [],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['transactions']);
});
