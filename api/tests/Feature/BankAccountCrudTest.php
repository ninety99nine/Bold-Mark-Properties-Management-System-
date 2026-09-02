<?php

use App\Models\Ledger;
use App\Models\BankAccount;
use App\Models\Community;
use App\Enums\FinancialCategory;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * A user with a plain role (no super/company-admin) and no bank-account permissions.
 * The bank-account permissions are registered (guard: web) so the permission check
 * resolves to "not granted" rather than throwing for an unknown permission.
 */
function bankAccountRestrictedUser(): \App\Models\User
{
    foreach (['bank_account.create', 'bank_account.update', 'bank_account.delete'] as $permission) {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
    }

    return createUser(createOrganization(), 'community-manager');
}

function validBankAccountPayload(array $overrides = []): array
{
    return array_merge([
        'name'      => 'Standard Bank Current',
        'bank_name' => 'Standard Bank',
        'type'      => 'current',
        'balance'   => 15000,
    ], $overrides);
}

// ──────────────────────────────────────────────────────────────────────────────
// Unauthenticated access
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on every bank account CRUD route when unauthenticated', function (string $method, string $route, array $params = []) {
    $this->{$method . 'Json'}(route($route, $params))->assertUnauthorized();
})->with([
    ['post',   'api.v1.create.bank.account'],
    ['get',    'api.v1.show.bank.account',   ['bankAccount' => '00000000-0000-0000-0000-000000000000']],
    ['put',    'api.v1.update.bank.account', ['bankAccount' => '00000000-0000-0000-0000-000000000000']],
    ['delete', 'api.v1.delete.bank.account', ['bankAccount' => '00000000-0000-0000-0000-000000000000']],
]);

// ──────────────────────────────────────────────────────────────────────────────
// POST /v1/bank-accounts — create
// ──────────────────────────────────────────────────────────────────────────────

it('blocks users without permission from creating a bank account', function () {
    $user = bankAccountRestrictedUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.bank.account'), validBankAccountPayload())
        ->assertForbidden();
});

it('requires a name', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.bank.account'), validBankAccountPayload(['name' => null]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('requires a valid type', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.bank.account'), validBankAccountPayload(['type' => 'netcash']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

it('creates a bank account scoped to the organization', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.bank.account'), validBankAccountPayload(['community_id' => $community->id]))
        ->assertOk()
        ->assertJsonPath('message', 'Created successfully')
        ->assertJsonPath('data.name', 'Standard Bank Current');

    $this->assertDatabaseHas('bank_accounts', [
        'name'            => 'Standard Bank Current',
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
    ]);
});

// ──────────────────────────────────────────────────────────────────────────────
// GL ledger auto-creation on cashbook create
// ──────────────────────────────────────────────────────────────────────────────

it('assigns an 8000/001 bank ledger when creating the first cashbook', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.bank.account'), validBankAccountPayload(['community_id' => $community->id]))
        ->assertOk();

    $account = BankAccount::where('community_id', $community->id)->firstOrFail();

    expect($account->ledger_id)->not->toBeNull();

    $ledger = Ledger::findOrFail($account->ledger_id);

    expect($ledger->code)->toBe('8000/001')
        ->and($ledger->financial_category)->toBe(FinancialCategory::BANK)
        ->and($ledger->fund)->toBe('main')
        ->and($ledger->account_type)->toBe('balance_sheet')
        ->and($ledger->is_system)->toBeTrue()
        ->and($ledger->organization_id)->toBe($user->organization_id);
});

it('assigns 8000/002 to the second cashbook', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.bank.account'), validBankAccountPayload(['community_id' => $community->id]))
        ->assertOk();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.bank.account'), validBankAccountPayload(['name' => 'FNB Cheque', 'community_id' => $community->id]))
        ->assertOk();

    $codes = BankAccount::where('community_id', $community->id)
        ->with('ledger')
        ->get()
        ->map(fn ($a) => $a->ledger->code)
        ->sort()
        ->values()
        ->all();

    expect($codes)->toBe(['8000/001', '8000/002']);
});

it('makes the first cashbook the default and enforces a single default per community', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.bank.account'), validBankAccountPayload(['community_id' => $community->id]))
        ->assertOk();

    $first = BankAccount::where('community_id', $community->id)->firstOrFail();
    expect($first->is_default)->toBeTrue();

    // A second cashbook flagged default must steal the flag from the first.
    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.bank.account'), validBankAccountPayload([
            'name'         => 'FNB Cheque',
            'community_id' => $community->id,
            'is_default'   => true,
        ]))
        ->assertOk();

    $defaults = BankAccount::where('community_id', $community->id)->where('is_default', true)->count();
    expect($defaults)->toBe(1);
    expect($first->fresh()->is_default)->toBeFalse();
});

it('exposes the real general_ledger_account label from the ledger relation', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.bank.account'), validBankAccountPayload(['community_id' => $community->id]))
        ->assertOk();

    $account = BankAccount::where('community_id', $community->id)->with('ledger')->firstOrFail();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.bank.account', $account))
        ->assertOk()
        ->assertJsonPath('data.ledger_id', $account->ledger_id)
        ->assertJsonPath('data.general_ledger_account', $account->ledger->code . ' - ' . $account->ledger->name);
});

// ──────────────────────────────────────────────────────────────────────────────
// Backfill command — gl:backfill-bank-ledgers
// ──────────────────────────────────────────────────────────────────────────────

it('backfills ledgers for pre-existing bank accounts idempotently', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    // Rows created directly (no ledger) simulate pre-migration data. The factory
    // auto-creates a bank ledger, so strip it back off to reproduce that state.
    $a = BankAccount::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
    ]);
    $b = BankAccount::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
    ]);
    Ledger::whereIn('id', [$a->ledger_id, $b->ledger_id])->delete();
    $a->forceFill(['ledger_id' => null])->save();
    $b->forceFill(['ledger_id' => null])->save();

    // Only the seeded chart of accounts exists (incl. the 8000/000 - BANK main);
    // count only bank sub-accounts, not the group header.
    $bankLedgers = fn () => Ledger::where('financial_category', \App\Enums\FinancialCategory::BANK)
        ->whereNotNull('parent_id')->count();

    $this->artisan('gl:backfill-bank-ledgers')->assertSuccessful();

    expect($a->fresh()->ledger_id)->not->toBeNull()
        ->and($b->fresh()->ledger_id)->not->toBeNull();

    $firstLedgerId  = $a->fresh()->ledger_id;
    $secondLedgerId = $b->fresh()->ledger_id;

    expect($bankLedgers())->toBe(2);

    // Running again must not create new ledgers or reassign existing ones.
    $this->artisan('gl:backfill-bank-ledgers')->assertSuccessful();

    expect($bankLedgers())->toBe(2)
        ->and($a->fresh()->ledger_id)->toBe($firstLedgerId)
        ->and($b->fresh()->ledger_id)->toBe($secondLedgerId);
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /v1/bank-accounts/{bankAccount} — show + gl_account label
// ──────────────────────────────────────────────────────────────────────────────

// BankAccountPolicy::view() → Tier 1 (return true, org-scoped 404) — no 403 test needed.

it('returns a single bank account with a derived gl_account label', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    // First account for this community → position 1 → 8000/001.
    $first = BankAccount::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'created_at'      => now()->subDay(),
    ]);
    // Second account → position 2 → 8000/002.
    $second = BankAccount::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'created_at'      => now(),
    ]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.bank.account', $first))
        ->assertOk()
        ->assertJsonPath('data.id', $first->id)
        ->assertJsonPath('data.gl_account', '8000/001')
        ->assertJsonPath('data.general_ledger_code', '8000/001');

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.bank.account', $second))
        ->assertOk()
        ->assertJsonPath('data.gl_account', '8000/002');
});

it('returns 404 for a cross-organization bank account', function () {
    $user = adminUser();
    $foreignOrg = createOrganization();
    $foreignCommunity = Community::factory()->create(['organization_id' => $foreignOrg->id]);
    $foreign = BankAccount::factory()->create(['organization_id' => $foreignOrg->id, 'community_id' => $foreignCommunity->id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.bank.account', $foreign))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /v1/bank-accounts — list / filters
// ──────────────────────────────────────────────────────────────────────────────

it('lists only active cashbooks by default', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    BankAccount::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id, 'is_active' => true]);
    BankAccount::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id, 'is_active' => false]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.bank.accounts', ['community_id' => $community->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('returns all cashbooks (active and inactive) when is_active is empty', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    BankAccount::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id, 'is_active' => true]);
    BankAccount::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id, 'is_active' => false]);

    // The Financial Setup page sends is_active='' to mean "show all".
    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.bank.accounts', ['community_id' => $community->id, 'is_active' => '']))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('filters cashbooks by an explicit is_active flag', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    BankAccount::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id, 'is_active' => true]);
    BankAccount::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id, 'is_active' => false]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.bank.accounts', ['community_id' => $community->id, 'is_active' => '0']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.is_active', false);
});

// ──────────────────────────────────────────────────────────────────────────────
// PUT /v1/bank-accounts/{bankAccount} — update
// ──────────────────────────────────────────────────────────────────────────────

it('blocks users without permission from updating a bank account', function () {
    $user = bankAccountRestrictedUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $account = BankAccount::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.bank.account', $account), ['name' => 'New'])
        ->assertForbidden();
});

it('updates a bank account', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $account   = BankAccount::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id, 'name' => 'Old']);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.bank.account', $account), ['name' => 'Renamed', 'is_active' => false])
        ->assertOk()
        ->assertJsonPath('message', 'Updated successfully')
        ->assertJsonPath('data.name', 'Renamed');

    $this->assertDatabaseHas('bank_accounts', ['id' => $account->id, 'name' => 'Renamed', 'is_active' => false]);
});

// ──────────────────────────────────────────────────────────────────────────────
// DELETE /v1/bank-accounts/{bankAccount} — single
// ──────────────────────────────────────────────────────────────────────────────

it('blocks users without permission from deleting a bank account', function () {
    $user      = bankAccountRestrictedUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $account   = BankAccount::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.bank.account', $account))
        ->assertForbidden();
});

it('deletes a bank account', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $account   = BankAccount::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.bank.account', $account))
        ->assertOk()
        ->assertJson(['deleted' => true, 'message' => 'Bank account deleted']);

    $this->assertDatabaseMissing('bank_accounts', ['id' => $account->id]);
});

// ──────────────────────────────────────────────────────────────────────────────
// WeConnectU parity — Savings type + title-cased GL description
// ──────────────────────────────────────────────────────────────────────────────

it('accepts the WeConnectU "savings" account type', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.bank.account'), validBankAccountPayload([
            'type'         => 'savings',
            'community_id' => $community->id,
        ]))
        ->assertOk()
        ->assertJsonPath('data.type', 'savings');
});

it('builds a title-cased GL account label "code - Bank Type AccountNumber" like WeConnectU', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $account = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.bank.account'), validBankAccountPayload([
            'name'           => 'FNB',
            'bank_name'      => 'First National Bank',
            'type'           => 'current',
            'account_number' => '0500000000123',
            'community_id'   => $community->id,
        ]))
        ->assertOk()
        ->json('data');

    expect($account['general_ledger_account'])
        ->toBe('8000/001 - First National Bank Current 0500000000123');
});
