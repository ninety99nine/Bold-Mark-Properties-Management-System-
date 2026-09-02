<?php

use App\Models\AllocationRule;
use App\Models\BankAccount;
use App\Models\CashbookEntry;
use App\Models\Community;
use App\Models\Ledger;
use App\Models\Owner;
use App\Models\Unit;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Create a community + bank account for the given user's organisation.
 *
 * @return array{0: Community, 1: BankAccount}
 */
function allocationCommunity(\App\Models\User $user): array
{
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $bank      = BankAccount::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'balance'         => 0,
        'balance_as_at'   => '2026-06-01',
    ]);

    return [$community, $bank];
}

/**
 * Create a unit + primary owner (customer) in the given community.
 */
function allocationCustomer(\App\Models\User $user, Community $community, string $code = 'CUST001'): Owner
{
    $unit = Unit::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'customer_code'   => $code,
    ]);

    return Owner::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'unit_id'         => $unit->id,
        'customer_code'   => $code,
        'is_primary'      => true,
    ]);
}

// ──────────────────────────────────────────────────────────────────────────────
// POST entries/{cashbookEntry}/allocate
// ──────────────────────────────────────────────────────────────────────────────

it('allocates a bank line to a general ledger account', function () {
    $user            = adminUser();
    [$community, $bank] = allocationCommunity($user);
    $ledger          = Ledger::factory()->create(['organization_id' => $user->organization_id, 'code' => '1000/001', 'name' => 'Levies']);

    $entry = CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
        'bank_account_id' => $bank->id, 'type' => 'credit', 'amount' => 500, 'date' => '2026-07-05',
    ]);

    $response = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.allocate.community.cashbook.entry', ['community' => $community->id, 'cashbookEntry' => $entry->id]), [
            'ledger_type' => 'general',
            'ledger_id'   => $ledger->id,
        ])
        ->assertOk();

    expect($response->json('data.is_allocated'))->toBeTrue();
    expect($response->json('data.account_label'))->toBe('1000/001 - Levies');

    $this->assertDatabaseHas('cashbook_entries', [
        'id' => $entry->id, 'allocation_ledger_type' => 'general', 'ledger_id' => $ledger->id,
    ]);
});

it('allocates a bank line to a customer and recalculates the unit balance', function () {
    $user            = adminUser();
    [$community, $bank] = allocationCommunity($user);
    $owner           = allocationCustomer($user, $community);

    $entry = CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
        'bank_account_id' => $bank->id, 'type' => 'credit', 'amount' => 750, 'date' => '2026-07-05',
    ]);

    $response = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.allocate.community.cashbook.entry', ['community' => $community->id, 'cashbookEntry' => $entry->id]), [
            'ledger_type' => 'customer',
            'unit_id'     => $owner->unit_id,
        ])
        ->assertOk();

    expect($response->json('data.is_allocated'))->toBeTrue();
    expect($response->json('data.account_label'))->toContain('CUST001');

    // A 750 unallocated customer credit puts the unit 750 in credit.
    expect((float) Unit::find($owner->unit_id)->balance)->toBe(750.0);
});

it('posts Dr Bank / Cr Suspense on create then reclassifies Suspense to the customer on allocation', function () {
    $user            = adminUser();
    [$community, $bank] = allocationCommunity($user);
    $owner           = allocationCustomer($user, $community);

    $suspense = \App\Models\Ledger::suspense($user->organization_id);

    $entry = CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
        'bank_account_id' => $bank->id, 'type' => 'credit', 'amount' => 750, 'date' => '2026-07-05',
    ]);

    // On create the bank line sits in Suspense: Dr Bank 750 / Cr Suspense 750.
    $batch = \App\Models\JournalBatch::where('source', 'cashbook')
        ->where('source_id', $entry->id)->with('lines')->firstOrFail();
    expect((float) $batch->lines->sum(fn ($l) => $l->entry_type->value === 'debit' ? $l->amount : 0))
        ->toBe((float) $batch->lines->sum(fn ($l) => $l->entry_type->value === 'credit' ? $l->amount : 0));
    expect((float) $batch->lines->firstWhere('ledger_id', $suspense->id)->amount)->toBe(750.0);

    // Allocate to the customer → the contra reclassifies to Accounts Receivable.
    app(\App\Services\AllocationPostingService::class)->post($entry, [
        'ledger_type' => 'customer',
        'unit_id'     => $owner->unit_id,
    ]);

    $batch = \App\Models\JournalBatch::where('source', 'cashbook')
        ->where('source_id', $entry->id)->with('lines')->firstOrFail();

    // Suspense is cleared; a customer (AR) credit now carries the 750, and the
    // batch still balances.
    expect($batch->lines->firstWhere('ledger_id', $suspense->id))->toBeNull();
    $customerLine = $batch->lines->firstWhere('line_type', \App\Enums\JournalLineType::CUSTOMER);
    expect($customerLine)->not->toBeNull()
        ->and((float) $customerLine->amount)->toBe(750.0)
        ->and($customerLine->unit_id)->toBe($owner->unit_id);
    expect((float) $batch->lines->sum(fn ($l) => $l->entry_type->value === 'debit' ? $l->amount : 0))
        ->toBe((float) $batch->lines->sum(fn ($l) => $l->entry_type->value === 'credit' ? $l->amount : 0));

    expect((float) Unit::find($owner->unit_id)->balance)->toBe(750.0);
});

it('returns 422 when allocating a customer line without a unit', function () {
    $user            = adminUser();
    [$community, $bank] = allocationCommunity($user);

    $entry = CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
        'bank_account_id' => $bank->id, 'type' => 'credit', 'amount' => 100, 'date' => '2026-07-05',
    ]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.allocate.community.cashbook.entry', ['community' => $community->id, 'cashbookEntry' => $entry->id]), [
            'ledger_type' => 'customer',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit_id']);
});

// ──────────────────────────────────────────────────────────────────────────────
// DELETE entries/{cashbookEntry}/allocation
// ──────────────────────────────────────────────────────────────────────────────

it('deallocates a bank line', function () {
    $user            = adminUser();
    [$community, $bank] = allocationCommunity($user);
    $ledger          = Ledger::factory()->create(['organization_id' => $user->organization_id, 'code' => '1000/001', 'name' => 'Levies']);

    $entry = CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
        'bank_account_id' => $bank->id, 'type' => 'credit', 'amount' => 500, 'date' => '2026-07-05',
        'allocation_ledger_type' => 'general', 'ledger_id' => $ledger->id,
    ]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.deallocate.community.cashbook.entry', ['community' => $community->id, 'cashbookEntry' => $entry->id]))
        ->assertOk();

    $this->assertDatabaseHas('cashbook_entries', [
        'id' => $entry->id, 'allocation_ledger_type' => null, 'ledger_id' => null,
    ]);
});

// ──────────────────────────────────────────────────────────────────────────────
// POST entries/{cashbookEntry}/split
// ──────────────────────────────────────────────────────────────────────────────

it('returns 422 when split lines do not sum to the transaction amount', function () {
    $user            = adminUser();
    [$community, $bank] = allocationCommunity($user);
    $ledgerA         = Ledger::factory()->create(['organization_id' => $user->organization_id, 'code' => '1000/001', 'name' => 'Levies']);
    $ledgerB         = Ledger::factory()->create(['organization_id' => $user->organization_id, 'code' => '1000/002', 'name' => 'Special']);

    $entry = CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
        'bank_account_id' => $bank->id, 'type' => 'credit', 'amount' => 1000, 'date' => '2026-07-05',
    ]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.split.community.cashbook.entry', ['community' => $community->id, 'cashbookEntry' => $entry->id]), [
            'lines' => [
                ['ledger_type' => 'general', 'target' => $ledgerA->id, 'amount' => 600],
                ['ledger_type' => 'general', 'target' => $ledgerB->id, 'amount' => 300],
            ],
        ])
        ->assertStatus(422);
});

it('splits a bank line into balanced child allocations', function () {
    $user            = adminUser();
    [$community, $bank] = allocationCommunity($user);
    $ledgerA         = Ledger::factory()->create(['organization_id' => $user->organization_id, 'code' => '1000/001', 'name' => 'Levies']);
    $ledgerB         = Ledger::factory()->create(['organization_id' => $user->organization_id, 'code' => '1000/002', 'name' => 'Special']);

    $entry = CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
        'bank_account_id' => $bank->id, 'type' => 'credit', 'amount' => 1000, 'date' => '2026-07-05',
    ]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.split.community.cashbook.entry', ['community' => $community->id, 'cashbookEntry' => $entry->id]), [
            'lines' => [
                ['ledger_type' => 'general', 'target' => $ledgerA->id, 'amount' => 700],
                ['ledger_type' => 'general', 'target' => $ledgerB->id, 'amount' => 300],
            ],
        ])
        ->assertOk();

    $this->assertDatabaseHas('cashbook_entries', ['id' => $entry->id, 'is_split' => true]);
    expect(CashbookEntry::where('parent_entry_id', $entry->id)->count())->toBe(2);

    // The running-balance view shows only the parent line, not the children.
    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.cashbook.transactions', ['community' => $community->id]) .
            '?bank_account_id=' . $bank->id . '&from=2026-07-01&to=2026-09-02')
        ->assertOk();

    expect($response->json('transactions'))->toHaveCount(1);
    expect((float) $response->json('transactions.0.balance'))->toBe(1000.0);
    expect($response->json('transactions.0.is_allocated'))->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET customer-search
// ──────────────────────────────────────────────────────────────────────────────

it('returns matching customers with the allocation-modal shape', function () {
    $user            = adminUser();
    [$community]     = allocationCommunity($user);
    allocationCustomer($user, $community, 'ABC001');

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.cashbook.customer.search', ['community' => $community->id]) . '?q=ABC')
        ->assertOk();

    expect($response->json())->toHaveCount(1);
    expect($response->json('0'))->toHaveKeys(['unit_id', 'code', 'customer', 'reference', 'balance']);
    expect($response->json('0.code'))->toBe('ABC001');
});

// ──────────────────────────────────────────────────────────────────────────────
// GET ledger-options
// ──────────────────────────────────────────────────────────────────────────────

it('returns general, reserve-fund and vat-type options', function () {
    $user        = adminUser();
    [$community] = allocationCommunity($user);
    // A custom general ledger (a postable sub-account under 1000/000 INCOME);
    // RFI/001 already exists in the seeded chart.
    $incomeMain = Ledger::where('organization_id', $user->organization_id)->where('code', '1000/000')->first();
    Ledger::factory()->create([
        'organization_id' => $user->organization_id,
        'code'            => '1000/050',
        'name'            => 'Special Levy',
        'parent_id'       => $incomeMain->id,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.cashbook.ledger.options', ['community' => $community->id]))
        ->assertOk()
        ->assertJsonStructure([
            'general'      => ['*' => ['ledger_id', 'code', 'name', 'label', 'category']],
            'reserve_fund' => ['*' => ['ledger_id', 'code', 'name', 'label', 'category']],
            'vat_types'    => ['*' => ['value', 'label']],
        ]);

    // General bucket includes our custom ledger; reserve bucket carries the RFI/RFE
    // chart accounts (and never a general one).
    $generalCodes = collect($response->json('general'))->pluck('code');
    $reserveCodes = collect($response->json('reserve_fund'))->pluck('code');

    expect($generalCodes)->toContain('1000/050');
    expect($reserveCodes)->toContain('RFI/001');
    expect($reserveCodes)->not->toContain('1000/050');
    expect($generalCodes)->not->toContain('RFI/001');
});

// ──────────────────────────────────────────────────────────────────────────────
// POST run-rules  &  rule hints
// ──────────────────────────────────────────────────────────────────────────────

it('allocates matching entries when rules are run', function () {
    $user            = adminUser();
    [$community, $bank] = allocationCommunity($user);
    $ledger          = Ledger::factory()->create(['organization_id' => $user->organization_id, 'code' => '1000/001', 'name' => 'Levies']);

    AllocationRule::factory()->create([
        'organization_id'         => $user->organization_id,
        'community_id'            => $community->id,
        'description_starts_with' => 'LEVY',
        'ledger_type'             => 'general',
        'ledger_id'               => $ledger->id,
    ]);

    $entry = CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
        'bank_account_id' => $bank->id, 'type' => 'credit', 'amount' => 500, 'date' => '2026-07-05',
        'description' => 'LEVY payment July',
    ]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.run.community.cashbook.rules', ['community' => $community->id]), [])
        ->assertOk()
        ->assertJsonPath('allocated', 1);

    $this->assertDatabaseHas('cashbook_entries', [
        'id' => $entry->id, 'allocation_ledger_type' => 'general', 'ledger_id' => $ledger->id,
    ]);
});

it('exposes a rule hint on unallocated transaction rows', function () {
    $user            = adminUser();
    [$community, $bank] = allocationCommunity($user);
    $ledger          = Ledger::factory()->create(['organization_id' => $user->organization_id, 'code' => '1000/001', 'name' => 'Levies']);

    AllocationRule::factory()->create([
        'organization_id'         => $user->organization_id,
        'community_id'            => $community->id,
        'description_starts_with' => 'LEVY',
        'ledger_type'             => 'general',
        'ledger_id'               => $ledger->id,
    ]);

    // Created directly (not via the endpoint) so auto-apply does not consume it.
    CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
        'bank_account_id' => $bank->id, 'type' => 'credit', 'amount' => 500, 'date' => '2026-07-05',
        'description' => 'LEVY payment July',
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.cashbook.transactions', ['community' => $community->id]) .
            '?bank_account_id=' . $bank->id . '&from=2026-07-01&to=2026-09-02')
        ->assertOk();

    expect($response->json('transactions.0.rule_hint'))->not->toBeNull();
    expect($response->json('transactions.0.rule_hint.account_label'))->toBe('1000/001 - Levies');
});
