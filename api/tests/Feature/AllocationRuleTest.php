<?php

use App\Models\AllocationRule;
use App\Models\BankAccount;
use App\Models\CashbookEntry;
use App\Models\Community;
use App\Models\Ledger;
use App\Models\Owner;
use App\Models\Unit;
use App\Services\AllocationRuleService;

// ──────────────────────────────────────────────────────────────────────────────
// Unauthenticated access
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on allocation rule routes when unauthenticated', function (string $method, string $route) {
    $community = Community::factory()->create(['organization_id' => createOrganization()->id]);

    $this->{$method . 'Json'}(route($route, ['community' => $community->id]))->assertUnauthorized();
})->with([
    ['get',  'api.v1.show.allocation.rules'],
    ['post', 'api.v1.create.allocation.rule'],
]);

// ──────────────────────────────────────────────────────────────────────────────
// GET /communities/{community}/allocation-rules
// ──────────────────────────────────────────────────────────────────────────────

it('lists allocation rules scoped to the community ordered by sort order', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ledger    = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $other     = Community::factory()->create(['organization_id' => $user->organization_id]);

    AllocationRule::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
        'ledger_id' => $ledger->id, 'description_starts_with' => 'B', 'sort_order' => 2,
    ]);
    AllocationRule::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
        'ledger_id' => $ledger->id, 'description_starts_with' => 'A', 'sort_order' => 1,
    ]);
    AllocationRule::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $other->id,
        'ledger_id' => $ledger->id, 'description_starts_with' => 'Z', 'sort_order' => 1,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.allocation.rules', ['community' => $community->id]))
        ->assertOk()
        ->assertJsonStructure(['data' => ['*' => ['id', 'ledger_type', 'sort_order', 'account_label']]]);

    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('data.0.description_starts_with'))->toBe('A');
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /communities/{community}/allocation-rules
// ──────────────────────────────────────────────────────────────────────────────

it('creates an allocation rule for a community', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ledger    = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.allocation.rule', ['community' => $community->id]), [
            'description_starts_with' => 'SERVICE FEE',
            'description_contains'    => 'SERVICE FEE',
            'ledger_type'             => 'general',
            'ledger_id'               => $ledger->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.ledger_type', 'general')
        ->assertJsonPath('data.community_id', $community->id);

    $this->assertDatabaseHas('allocation_rules', [
        'community_id' => $community->id, 'ledger_id' => $ledger->id, 'description_starts_with' => 'SERVICE FEE',
    ]);
});

it('rejects an allocation rule with no text criteria', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ledger    = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.allocation.rule', ['community' => $community->id]), [
            'ledger_type' => 'general',
            'ledger_id'   => $ledger->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['description_starts_with']);
});

it('requires an account for a general ledger type rule', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.allocation.rule', ['community' => $community->id]), [
            'description_starts_with' => 'X',
            'ledger_type'             => 'general',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ledger_id']);
});

// ──────────────────────────────────────────────────────────────────────────────
// PUT / DELETE single
// ──────────────────────────────────────────────────────────────────────────────

it('updates an allocation rule', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ledger    = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $rule      = AllocationRule::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
        'ledger_id' => $ledger->id, 'description_starts_with' => 'OLD',
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.allocation.rule', ['community' => $community->id, 'allocationRule' => $rule->id]), [
            'description_starts_with' => 'NEW',
            'ledger_type'             => 'general',
            'ledger_id'               => $ledger->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.description_starts_with', 'NEW');

    $this->assertDatabaseHas('allocation_rules', ['id' => $rule->id, 'description_starts_with' => 'NEW']);
});

it('deletes an allocation rule', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ledger    = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $rule      = AllocationRule::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id, 'ledger_id' => $ledger->id,
    ]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.allocation.rule', ['community' => $community->id, 'allocationRule' => $rule->id]))
        ->assertOk()
        ->assertJsonPath('deleted', true);

    $this->assertDatabaseMissing('allocation_rules', ['id' => $rule->id]);
});

it('returns 404 for an allocation rule from another organisation', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $otherOrg  = createOrganization();
    $otherComm = Community::factory()->create(['organization_id' => $otherOrg->id]);
    $ledger    = Ledger::factory()->create(['organization_id' => $otherOrg->id]);
    $rule      = AllocationRule::factory()->create([
        'organization_id' => $otherOrg->id, 'community_id' => $otherComm->id, 'ledger_id' => $ledger->id,
    ]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.allocation.rule', ['community' => $community->id, 'allocationRule' => $rule->id]))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// Matching engine
// ──────────────────────────────────────────────────────────────────────────────

it('matches on starts_with and contains case-insensitively', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $rule      = AllocationRule::factory()->make([
        'description_starts_with' => 'service fee', 'description_contains' => 'FEE',
    ]);

    $match = CashbookEntry::factory()->make(['description' => 'SERVICE FEE July', 'type' => 'credit']);
    $miss  = CashbookEntry::factory()->make(['description' => 'Levy payment', 'type' => 'credit']);

    $service = app(AllocationRuleService::class);

    expect($service->matches($rule, $match))->toBeTrue();
    expect($service->matches($rule, $miss))->toBeFalse();
});

it('respects the amount sign flags', function () {
    $positiveOnly = AllocationRule::factory()->make([
        'description_contains' => 'X', 'apply_to_positive' => true, 'apply_to_negative' => false,
    ]);

    $credit = CashbookEntry::factory()->make(['description' => 'X payment', 'type' => 'credit']);
    $debit  = CashbookEntry::factory()->make(['description' => 'X payment', 'type' => 'debit']);

    $service = app(AllocationRuleService::class);

    expect($service->matches($positiveOnly, $credit))->toBeTrue();
    expect($service->matches($positiveOnly, $debit))->toBeFalse();
});

it('respects the bank account filter', function () {
    $org       = createOrganization();
    $community = Community::factory()->create(['organization_id' => $org->id]);
    $bankA     = BankAccount::factory()->create(['organization_id' => $org->id, 'community_id' => $community->id]);
    $bankB     = BankAccount::factory()->create(['organization_id' => $org->id, 'community_id' => $community->id]);

    $rule = AllocationRule::factory()->make([
        'description_contains' => 'X', 'bank_account_ids' => [$bankA->id],
    ]);

    $onA = CashbookEntry::factory()->make(['description' => 'X', 'type' => 'credit', 'bank_account_id' => $bankA->id]);
    $onB = CashbookEntry::factory()->make(['description' => 'X', 'type' => 'credit', 'bank_account_id' => $bankB->id]);

    $service = app(AllocationRuleService::class);

    expect($service->matches($rule, $onA))->toBeTrue();
    expect($service->matches($rule, $onB))->toBeFalse();
});

it('never matches a rule with no text criteria', function () {
    $rule  = AllocationRule::factory()->make(['description_starts_with' => null, 'description_contains' => null]);
    $entry = CashbookEntry::factory()->make(['description' => 'anything', 'type' => 'credit']);

    expect(app(AllocationRuleService::class)->matches($rule, $entry))->toBeFalse();
});

it('applies rules to unallocated entries in a community and marks them allocated', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $ledger    = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $bank      = BankAccount::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
    ]);

    AllocationRule::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
        'ledger_id' => $ledger->id, 'ledger_type' => 'general', 'description_starts_with' => 'SERVICE FEE',
    ]);

    $matching = CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
        'bank_account_id' => $bank->id, 'type' => 'credit', 'description' => 'SERVICE FEE July',
    ]);
    $other = CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
        'bank_account_id' => $bank->id, 'type' => 'credit', 'description' => 'Levy',
    ]);

    $count = app(AllocationRuleService::class)->applyRulesForCommunity($community->id);

    expect($count)->toBe(1);
    $this->assertDatabaseHas('cashbook_entries', [
        'id' => $matching->id, 'allocation_ledger_type' => 'general', 'ledger_id' => $ledger->id,
    ]);
    $this->assertDatabaseHas('cashbook_entries', [
        'id' => $other->id, 'allocation_ledger_type' => null,
    ]);
});

it('returns a rule hint shape with the target account label', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit      = Unit::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id]);
    Owner::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
        'unit_id' => $unit->id, 'customer_code' => 'CUS001', 'full_name' => 'Jane Doe', 'is_primary' => true,
    ]);

    AllocationRule::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
        'ledger_type' => 'customer', 'unit_id' => $unit->id,
        'description_starts_with' => 'RENT', 'description_contains' => 'RENT',
    ]);

    $entry = CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
        'type' => 'credit', 'description' => 'RENT August',
    ]);

    $hint = app(AllocationRuleService::class)->ruleHintFor($entry);

    expect($hint)->toBeArray();
    expect($hint['starts_with'])->toBe('RENT');
    expect($hint['contains'])->toBe('RENT');
    expect($hint['account_label'])->toBe('CUS001 - Jane Doe');
});
