<?php

use App\Models\ChargeType;
use App\Models\Estate;
use App\Models\Invoice;
use App\Models\Owner;
use App\Models\RiskRule;
use App\Models\Unit;
use Illuminate\Support\Str;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Create a user + one RiskRule belonging to them.
 */
function makeRiskRule(array $overrides = []): array
{
    $user = adminUser();
    $rule = RiskRule::factory()->create(array_merge(
        ['organization_id' => $user->organization_id],
        $overrides
    ));
    return compact('user', 'rule');
}

/**
 * Wire up a unit with at least one overdue invoice ready for evaluate.
 * Returns ['user', 'estate', 'unit', 'chargeType', 'owner', 'invoice'].
 */
function makeOverdueUnitForEval(array $invoiceOverrides = [], array $unitOverrides = []): array
{
    $user       = adminUser();
    $estate     = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(array_merge(
        ['estate_id' => $estate->id, 'organization_id' => $user->organization_id],
        $unitOverrides
    ));
    $chargeType = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner      = Owner::factory()->create([
        'unit_id'         => $unit->id,
        'organization_id' => $user->organization_id,
    ]);

    $invoice = Invoice::factory()->create(array_merge([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'charge_type_id'  => $chargeType->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
        'billing_period'  => '2026-01-01',
        'status'          => 'overdue',
        'amount'          => 5000,
        'due_date'        => now()->subDays(30)->format('Y-m-d'),
    ], $invoiceOverrides));

    return compact('user', 'estate', 'unit', 'chargeType', 'owner', 'invoice');
}

// ──────────────────────────────────────────────────────────────────────────────
// showRiskRules — GET /risk-rules
// ──────────────────────────────────────────────────────────────────────────────

it('show risk rules returns 401 without auth', function () {
    $this->getJson(route('api.v1.show.risk-rules'))
        ->assertUnauthorized();
});

it('show risk rules returns a paginated list', function () {
    ['user' => $user] = makeRiskRule();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.risk-rules'))
        ->assertOk()
        ->assertJsonStructure(['data', 'meta']);
});

it('show risk rules each entry has expected fields', function () {
    ['user' => $user] = makeRiskRule(['severity' => 'warning', 'is_active' => true]);

    $item = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.risk-rules'))
        ->assertOk()
        ->json('data.0');

    expect($item)->toHaveKeys([
        'id', 'organization_id', 'name', 'description', 'severity',
        'conditions', 'is_active', 'sort_order', 'created_at', 'updated_at',
    ]);
});

it('show risk rules severity is returned as a plain string', function () {
    ['user' => $user] = makeRiskRule(['severity' => 'critical']);

    $severity = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.risk-rules'))
        ->assertOk()
        ->json('data.0.severity');

    expect($severity)->toBe('critical');
});

it('show risk rules conditions is returned as an array', function () {
    ['user' => $user, 'rule' => $rule] = makeRiskRule();

    $conditions = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.risk-rules'))
        ->assertOk()
        ->json('data.0.conditions');

    expect($conditions)->toBeArray();
    expect($conditions[0])->toHaveKeys(['type', 'operator', 'value']);
});

it('show risk rules only returns the authenticated tenants rules', function () {
    ['rule' => $otherRule] = makeRiskRule(); // different org
    ['user' => $myUser]    = makeRiskRule(); // my org (has 1 rule)

    $ids = $this->actingAs($myUser, 'api')
        ->getJson(route('api.v1.show.risk-rules'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->not->toContain($otherRule->id);
});

it('show risk rules filters by is_active=true', function () {
    $user        = adminUser();
    $activeRule  = RiskRule::factory()->create(['organization_id' => $user->organization_id, 'is_active' => true]);
    $inactiveRule = RiskRule::factory()->create(['organization_id' => $user->organization_id, 'is_active' => false]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.risk-rules') . '?is_active=true')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($activeRule->id);
    expect($ids)->not->toContain($inactiveRule->id);
});

it('show risk rules filters by is_active=false', function () {
    $user        = adminUser();
    $activeRule  = RiskRule::factory()->create(['organization_id' => $user->organization_id, 'is_active' => true]);
    $inactiveRule = RiskRule::factory()->create(['organization_id' => $user->organization_id, 'is_active' => false]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.risk-rules') . '?is_active=false')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($inactiveRule->id);
    expect($ids)->not->toContain($activeRule->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// createRiskRule — POST /risk-rules
// ──────────────────────────────────────────────────────────────────────────────

it('create risk rule returns 401 without auth', function () {
    $this->postJson(route('api.v1.create.risk-rule'), [])
        ->assertUnauthorized();
});

it('create risk rule persists the rule in the database', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.risk-rule'), [
            'name'       => 'High Arrears Alert',
            'severity'   => 'critical',
            'conditions' => [
                ['type' => 'overdue_amount', 'operator' => '>=', 'value' => 5000],
            ],
        ])
        ->assertCreated();

    expect(
        RiskRule::where('organization_id', $user->organization_id)
            ->where('name', 'High Arrears Alert')
            ->exists()
    )->toBeTrue();
});

it('create risk rule returns the created rule resource', function () {
    $user = adminUser();

    $response = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.risk-rule'), [
            'name'       => 'Test Rule',
            'severity'   => 'warning',
            'conditions' => [
                ['type' => 'overdue_invoice_count', 'operator' => '>=', 'value' => 3],
            ],
        ])
        ->assertCreated();

    expect($response->json('data'))->toHaveKeys(['id', 'name', 'severity', 'conditions', 'is_active', 'sort_order']);
    expect($response->json('data.name'))->toBe('Test Rule');
    expect($response->json('data.severity'))->toBe('warning');
});

it('create risk rule is_active defaults to true', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.risk-rule'), [
            'name'       => 'Active By Default',
            'severity'   => 'warning',
            'conditions' => [['type' => 'overdue_amount', 'operator' => '>=', 'value' => 100]],
        ])
        ->assertCreated();

    expect(
        RiskRule::where('organization_id', $user->organization_id)->first()->is_active
    )->toBeTrue();
});

it('create risk rule auto-assigns sort_order as max + 1', function () {
    $user = adminUser();
    RiskRule::factory()->create(['organization_id' => $user->organization_id, 'sort_order' => 2]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.risk-rule'), [
            'name'       => 'New Rule',
            'severity'   => 'warning',
            'conditions' => [['type' => 'overdue_amount', 'operator' => '>=', 'value' => 100]],
        ])
        ->assertCreated();

    $newRule = RiskRule::where('organization_id', $user->organization_id)
        ->where('name', 'New Rule')->first();

    expect($newRule->sort_order)->toBe(3);
});

it('create risk rule accepts an optional description', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.risk-rule'), [
            'name'        => 'Described Rule',
            'description' => 'Flag units with very high arrears',
            'severity'    => 'critical',
            'conditions'  => [['type' => 'overdue_amount', 'operator' => '>=', 'value' => 10000]],
        ])
        ->assertCreated();

    expect(
        RiskRule::where('organization_id', $user->organization_id)->first()->description
    )->toBe('Flag units with very high arrears');
});

it('create risk rule accepts multiple conditions', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.risk-rule'), [
            'name'     => 'Multi-Condition Rule',
            'severity' => 'critical',
            'conditions' => [
                ['type' => 'overdue_amount',        'operator' => '>=', 'value' => 5000],
                ['type' => 'overdue_invoice_count', 'operator' => '>=', 'value' => 3],
            ],
        ])
        ->assertCreated();

    $rule = RiskRule::where('organization_id', $user->organization_id)->first();
    expect(count($rule->conditions))->toBe(2);
});

// Validation

it('create risk rule requires name', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.risk-rule'), [
            'severity'   => 'warning',
            'conditions' => [['type' => 'overdue_amount', 'operator' => '>=', 'value' => 100]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('create risk rule requires severity', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.risk-rule'), [
            'name'       => 'No Severity',
            'conditions' => [['type' => 'overdue_amount', 'operator' => '>=', 'value' => 100]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['severity']);
});

it('create risk rule rejects invalid severity', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.risk-rule'), [
            'name'       => 'Bad Severity',
            'severity'   => 'extreme',
            'conditions' => [['type' => 'overdue_amount', 'operator' => '>=', 'value' => 100]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['severity']);
});

it('create risk rule requires at least one condition', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.risk-rule'), [
            'name'       => 'No Conditions',
            'severity'   => 'warning',
            'conditions' => [],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['conditions']);
});

it('create risk rule rejects invalid condition type', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.risk-rule'), [
            'name'       => 'Bad Condition',
            'severity'   => 'warning',
            'conditions' => [['type' => 'not_a_real_metric', 'operator' => '>=', 'value' => 100]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['conditions.0.type']);
});

it('create risk rule rejects operator other than >=', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.risk-rule'), [
            'name'       => 'Bad Operator',
            'severity'   => 'warning',
            'conditions' => [['type' => 'overdue_amount', 'operator' => '>', 'value' => 100]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['conditions.0.operator']);
});

it('create risk rule rejects negative condition value', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.risk-rule'), [
            'name'       => 'Negative Value',
            'severity'   => 'warning',
            'conditions' => [['type' => 'overdue_amount', 'operator' => '>=', 'value' => -1]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['conditions.0.value']);
});

// ──────────────────────────────────────────────────────────────────────────────
// updateRiskRule — PUT /risk-rules/{riskRule}
// ──────────────────────────────────────────────────────────────────────────────

it('update risk rule returns 401 without auth', function () {
    $rule = RiskRule::factory()->create(['organization_id' => adminUser()->organization_id]);

    $this->putJson(route('api.v1.update.risk-rule', $rule), [])
        ->assertUnauthorized();
});

it('update risk rule changes the name', function () {
    ['user' => $user, 'rule' => $rule] = makeRiskRule(['name' => 'Original Name']);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.risk-rule', $rule), ['name' => 'Updated Name'])
        ->assertOk();

    expect($rule->fresh()->name)->toBe('Updated Name');
});

it('update risk rule changes the severity', function () {
    ['user' => $user, 'rule' => $rule] = makeRiskRule(['severity' => 'warning']);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.risk-rule', $rule), ['severity' => 'critical'])
        ->assertOk();

    expect($rule->fresh()->severity->value)->toBe('critical');
});

it('update risk rule can deactivate a rule', function () {
    ['user' => $user, 'rule' => $rule] = makeRiskRule(['is_active' => true]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.risk-rule', $rule), ['is_active' => false])
        ->assertOk();

    expect($rule->fresh()->is_active)->toBeFalse();
});

it('update risk rule can replace conditions', function () {
    ['user' => $user, 'rule' => $rule] = makeRiskRule();

    $newConditions = [
        ['type' => 'overdue_invoice_count', 'operator' => '>=', 'value' => 5],
    ];

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.risk-rule', $rule), ['conditions' => $newConditions])
        ->assertOk();

    expect($rule->fresh()->conditions[0]['type'])->toBe('overdue_invoice_count');
    expect((int) $rule->fresh()->conditions[0]['value'])->toBe(5);
});

it('update risk rule supports partial updates (only provided fields change)', function () {
    ['user' => $user, 'rule' => $rule] = makeRiskRule([
        'name'     => 'Keep Me',
        'severity' => 'warning',
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.risk-rule', $rule), ['severity' => 'critical'])
        ->assertOk();

    expect($rule->fresh()->name)->toBe('Keep Me');
    expect($rule->fresh()->severity->value)->toBe('critical');
});

it('update risk rule rejects invalid severity', function () {
    ['user' => $user, 'rule' => $rule] = makeRiskRule();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.risk-rule', $rule), ['severity' => 'nuclear'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['severity']);
});

it('update risk rule returns 404 for a non-existent rule', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.risk-rule', Str::uuid()), ['name' => 'Ghost'])
        ->assertNotFound();
});

it('update risk rule cross-tenant returns 404', function () {
    ['rule' => $otherRule] = makeRiskRule(['name' => 'Other Org Rule']);
    $myUser = adminUser();

    $this->actingAs($myUser, 'api')
        ->putJson(route('api.v1.update.risk-rule', $otherRule), ['name' => 'Hijacked'])
        ->assertNotFound();

    expect($otherRule->fresh()->name)->toBe('Other Org Rule');
});

// ──────────────────────────────────────────────────────────────────────────────
// deleteRiskRule — DELETE /risk-rules/{riskRule}
// ──────────────────────────────────────────────────────────────────────────────

it('delete risk rule returns 401 without auth', function () {
    $rule = RiskRule::factory()->create(['organization_id' => adminUser()->organization_id]);

    $this->deleteJson(route('api.v1.delete.risk-rule', $rule))
        ->assertUnauthorized();
});

it('delete risk rule removes the rule from the database', function () {
    ['user' => $user, 'rule' => $rule] = makeRiskRule();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.risk-rule', $rule))
        ->assertOk();

    expect(RiskRule::find($rule->id))->toBeNull();
});

it('delete risk rule returns a success message', function () {
    ['user' => $user, 'rule' => $rule] = makeRiskRule();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.risk-rule', $rule))
        ->assertOk()
        ->assertJson(['deleted' => true, 'message' => 'Risk rule deleted']);
});

it('delete risk rule returns 404 for a non-existent rule', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.risk-rule', Str::uuid()))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// reorder — PUT /risk-rules/reorder
// ──────────────────────────────────────────────────────────────────────────────

it('reorder returns 401 without auth', function () {
    $this->putJson(route('api.v1.reorder.risk-rules'), ['rule_ids' => []])
        ->assertUnauthorized();
});

it('reorder returns 422 when rule_ids is missing', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.reorder.risk-rules'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rule_ids']);
});

it('reorder returns 422 when rule_ids is an empty array', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.reorder.risk-rules'), ['rule_ids' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rule_ids']);
});

it('reorder returns 422 when rule_ids contains non-uuid values', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.reorder.risk-rules'), ['rule_ids' => ['not-a-uuid']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rule_ids.0']);
});

it('reorder updates sort_order for each rule based on its position', function () {
    $user = adminUser();
    $ruleA = RiskRule::factory()->create(['organization_id' => $user->organization_id, 'sort_order' => 0]);
    $ruleB = RiskRule::factory()->create(['organization_id' => $user->organization_id, 'sort_order' => 1]);
    $ruleC = RiskRule::factory()->create(['organization_id' => $user->organization_id, 'sort_order' => 2]);

    // Reverse the order: C, B, A
    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.reorder.risk-rules'), [
            'rule_ids' => [$ruleC->id, $ruleB->id, $ruleA->id],
        ])
        ->assertOk()
        ->assertJson(['message' => 'Rules reordered']);

    expect($ruleC->fresh()->sort_order)->toBe(0);
    expect($ruleB->fresh()->sort_order)->toBe(1);
    expect($ruleA->fresh()->sort_order)->toBe(2);
});

it('reorder silently ignores rule ids belonging to another tenant', function () {
    ['rule' => $otherRule] = makeRiskRule(['sort_order' => 99]);
    ['user' => $myUser, 'rule' => $myRule] = makeRiskRule(['sort_order' => 0]);

    $this->actingAs($myUser, 'api')
        ->putJson(route('api.v1.reorder.risk-rules'), [
            'rule_ids' => [$otherRule->id, $myRule->id],
        ])
        ->assertOk();

    // Other org's rule sort_order is unchanged
    expect($otherRule->fresh()->sort_order)->toBe(99);
    // My rule moved to position 1
    expect($myRule->fresh()->sort_order)->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// evaluate — GET /risk-rules/evaluate
// ──────────────────────────────────────────────────────────────────────────────

it('evaluate returns 401 without auth', function () {
    $this->getJson(route('api.v1.evaluate.risk-rules'))
        ->assertUnauthorized();
});

it('evaluate returns rules, flagged_units, and total_flagged keys', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.evaluate.risk-rules'))
        ->assertOk()
        ->assertJsonStructure(['rules', 'flagged_units', 'total_flagged']);
});

it('evaluate returns an empty response when the tenant has no rules', function () {
    $user = adminUser();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.evaluate.risk-rules'))
        ->assertOk();

    expect($response->json('rules'))->toBeEmpty();
    expect($response->json('flagged_units'))->toBeEmpty();
    expect($response->json('total_flagged'))->toBe(0);
});

it('evaluate lists all rules even when no units are flagged', function () {
    $user = adminUser();
    RiskRule::factory()->create(['organization_id' => $user->organization_id, 'is_active' => true]);
    RiskRule::factory()->create(['organization_id' => $user->organization_id, 'is_active' => false]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.evaluate.risk-rules'))
        ->assertOk();

    expect(count($response->json('rules')))->toBe(2);
    expect($response->json('flagged_units'))->toBeEmpty();
});

it('evaluate rules entry has flagged_count field', function () {
    ['user' => $user] = makeRiskRule();

    $rule = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.evaluate.risk-rules'))
        ->assertOk()
        ->json('rules.0');

    expect($rule)->toHaveKey('flagged_count');
});

it('evaluate flags a unit matched by an active overdue_amount condition', function () {
    ['user' => $user, 'unit' => $unit] = makeOverdueUnitForEval(['amount' => 5000]);

    RiskRule::factory()->warning()->create([
        'organization_id' => $user->organization_id,
        'conditions'      => [['type' => 'overdue_amount', 'operator' => '>=', 'value' => 1000]],
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.evaluate.risk-rules'))
        ->assertOk();

    $unitIds = array_column($response->json('flagged_units'), 'unit_id');
    expect($unitIds)->toContain($unit->id);
    expect($response->json('total_flagged'))->toBe(1);
});

it('evaluate does not flag a unit whose overdue_amount is below the threshold', function () {
    ['user' => $user] = makeOverdueUnitForEval(['amount' => 500]);

    RiskRule::factory()->warning()->create([
        'organization_id' => $user->organization_id,
        'conditions'      => [['type' => 'overdue_amount', 'operator' => '>=', 'value' => 1000]],
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.evaluate.risk-rules'))
        ->assertOk();

    expect($response->json('flagged_units'))->toBeEmpty();
    expect($response->json('total_flagged'))->toBe(0);
});

it('evaluate flags a unit matched by an overdue_invoice_count condition', function () {
    ['user' => $user, 'unit' => $unit, 'chargeType' => $chargeType, 'owner' => $owner] = makeOverdueUnitForEval();

    // Add a second overdue invoice on the same unit (different billing period)
    Invoice::factory()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'charge_type_id'  => $chargeType->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
        'billing_period'  => '2026-02-01',
        'status'          => 'overdue',
        'amount'          => 1000,
        'due_date'        => now()->subDays(15)->format('Y-m-d'),
    ]);

    RiskRule::factory()->warning()->create([
        'organization_id' => $user->organization_id,
        'conditions'      => [['type' => 'overdue_invoice_count', 'operator' => '>=', 'value' => 2]],
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.evaluate.risk-rules'))
        ->assertOk();

    expect(array_column($response->json('flagged_units'), 'unit_id'))->toContain($unit->id);
});

it('evaluate flags a unit matched by an arrears_rate condition', function () {
    // 1 overdue out of 1 total = 100% rate
    ['user' => $user, 'unit' => $unit] = makeOverdueUnitForEval();

    RiskRule::factory()->warning()->create([
        'organization_id' => $user->organization_id,
        'conditions'      => [['type' => 'arrears_rate', 'operator' => '>=', 'value' => 50]],
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.evaluate.risk-rules'))
        ->assertOk();

    expect(array_column($response->json('flagged_units'), 'unit_id'))->toContain($unit->id);
});

it('evaluate does not flag a unit whose arrears_rate is below the threshold', function () {
    // 1 overdue out of 2 total = 50%
    ['user' => $user, 'unit' => $unit, 'chargeType' => $chargeType, 'owner' => $owner] = makeOverdueUnitForEval();

    // Add a paid invoice so arrears_rate = 50%
    Invoice::factory()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'charge_type_id'  => $chargeType->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
        'billing_period'  => '2026-02-01',
        'status'          => 'paid',
        'amount'          => 1000,
        'due_date'        => now()->subDays(5)->format('Y-m-d'),
    ]);

    RiskRule::factory()->warning()->create([
        'organization_id' => $user->organization_id,
        'conditions'      => [['type' => 'arrears_rate', 'operator' => '>=', 'value' => 75]],
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.evaluate.risk-rules'))
        ->assertOk();

    expect($response->json('flagged_units'))->toBeEmpty();
});

it('evaluate flags a unit matched by a days_overdue condition', function () {
    // due_date = 60 days ago → days_overdue = 60
    ['user' => $user, 'unit' => $unit] = makeOverdueUnitForEval([
        'due_date' => now()->subDays(60)->format('Y-m-d'),
    ]);

    RiskRule::factory()->warning()->create([
        'organization_id' => $user->organization_id,
        'conditions'      => [['type' => 'days_overdue', 'operator' => '>=', 'value' => 45]],
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.evaluate.risk-rules'))
        ->assertOk();

    expect(array_column($response->json('flagged_units'), 'unit_id'))->toContain($unit->id);
});

it('evaluate conditions within a rule are AND — unit must meet all conditions', function () {
    // Unit has overdue_amount = 5000 but only 1 overdue invoice
    ['user' => $user] = makeOverdueUnitForEval(['amount' => 5000]);

    RiskRule::factory()->warning()->create([
        'organization_id' => $user->organization_id,
        'conditions'      => [
            ['type' => 'overdue_amount',        'operator' => '>=', 'value' => 1000], // met
            ['type' => 'overdue_invoice_count', 'operator' => '>=', 'value' => 5],    // NOT met
        ],
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.evaluate.risk-rules'))
        ->assertOk();

    // Unit does NOT appear — it fails the second condition
    expect($response->json('flagged_units'))->toBeEmpty();
});

it('evaluate rules are OR — a unit flagged by ANY active rule appears in results', function () {
    ['user' => $user, 'unit' => $unit] = makeOverdueUnitForEval(['amount' => 5000]);

    // Rule 1: overdue_amount >= 10000 (NOT met)
    RiskRule::factory()->warning()->create([
        'organization_id' => $user->organization_id,
        'conditions'      => [['type' => 'overdue_amount', 'operator' => '>=', 'value' => 10000]],
    ]);

    // Rule 2: overdue_amount >= 1000 (MET)
    RiskRule::factory()->critical()->create([
        'organization_id' => $user->organization_id,
        'conditions'      => [['type' => 'overdue_amount', 'operator' => '>=', 'value' => 1000]],
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.evaluate.risk-rules'))
        ->assertOk();

    expect(array_column($response->json('flagged_units'), 'unit_id'))->toContain($unit->id);
});

it('evaluate inactive rules do not contribute to flagged_units', function () {
    ['user' => $user] = makeOverdueUnitForEval(['amount' => 5000]);

    // Only an inactive rule — should not flag anything
    RiskRule::factory()->inactive()->create([
        'organization_id' => $user->organization_id,
        'conditions'      => [['type' => 'overdue_amount', 'operator' => '>=', 'value' => 1000]],
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.evaluate.risk-rules'))
        ->assertOk();

    expect($response->json('flagged_units'))->toBeEmpty();
    expect($response->json('total_flagged'))->toBe(0);
});

it('evaluate inactive rules still show a non-zero flagged_count in the rules list', function () {
    ['user' => $user] = makeOverdueUnitForEval(['amount' => 5000]);

    $inactiveRule = RiskRule::factory()->inactive()->create([
        'organization_id' => $user->organization_id,
        'conditions'      => [['type' => 'overdue_amount', 'operator' => '>=', 'value' => 1000]],
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.evaluate.risk-rules'))
        ->assertOk();

    $ruleEntry = collect($response->json('rules'))->firstWhere('id', $inactiveRule->id);
    expect($ruleEntry['flagged_count'])->toBe(1);
});

it('evaluate flagged unit payload has all expected fields', function () {
    ['user' => $user, 'unit' => $unit] = makeOverdueUnitForEval(['amount' => 5000]);

    RiskRule::factory()->warning()->create([
        'organization_id' => $user->organization_id,
        'conditions'      => [['type' => 'overdue_amount', 'operator' => '>=', 'value' => 1000]],
    ]);

    $flagged = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.evaluate.risk-rules'))
        ->assertOk()
        ->json('flagged_units.0');

    expect($flagged)->toHaveKeys([
        'unit_id', 'unit_number', 'estate_id', 'estate_name',
        'owner_name', 'owner_email',
        'overdue_amount', 'days_overdue', 'overdue_count', 'arrears_rate',
        'matched_rules', 'severity',
    ]);
    expect($flagged['unit_id'])->toBe($unit->id);
});

it('evaluate matched_rules has id, name, and severity for each matched rule', function () {
    ['user' => $user] = makeOverdueUnitForEval(['amount' => 5000]);

    $rule = RiskRule::factory()->warning()->create([
        'organization_id' => $user->organization_id,
        'name'            => 'My Warning Rule',
        'conditions'      => [['type' => 'overdue_amount', 'operator' => '>=', 'value' => 1000]],
    ]);

    $matchedRules = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.evaluate.risk-rules'))
        ->assertOk()
        ->json('flagged_units.0.matched_rules');

    expect($matchedRules[0])->toHaveKeys(['id', 'name', 'severity']);
    expect($matchedRules[0]['id'])->toBe($rule->id);
    expect($matchedRules[0]['name'])->toBe('My Warning Rule');
    expect($matchedRules[0]['severity'])->toBe('warning');
});

it('evaluate severity is critical when matched by a critical rule', function () {
    ['user' => $user] = makeOverdueUnitForEval(['amount' => 5000]);

    RiskRule::factory()->critical()->create([
        'organization_id' => $user->organization_id,
        'conditions'      => [['type' => 'overdue_amount', 'operator' => '>=', 'value' => 1000]],
    ]);

    $severity = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.evaluate.risk-rules'))
        ->assertOk()
        ->json('flagged_units.0.severity');

    expect($severity)->toBe('critical');
});

it('evaluate severity escalates to critical when matched by both warning and critical rules', function () {
    ['user' => $user] = makeOverdueUnitForEval(['amount' => 5000]);

    RiskRule::factory()->warning()->create([
        'organization_id' => $user->organization_id,
        'conditions'      => [['type' => 'overdue_amount', 'operator' => '>=', 'value' => 1000]],
    ]);
    RiskRule::factory()->critical()->create([
        'organization_id' => $user->organization_id,
        'conditions'      => [['type' => 'overdue_amount', 'operator' => '>=', 'value' => 1000]],
    ]);

    $severity = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.evaluate.risk-rules'))
        ->assertOk()
        ->json('flagged_units.0.severity');

    expect($severity)->toBe('critical');
});

it('evaluate sorts critical units before warning units', function () {
    $user = adminUser();

    // Warning unit
    $warningEstate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $warningUnit   = Unit::factory()->create(['estate_id' => $warningEstate->id, 'organization_id' => $user->organization_id]);
    $warningCT     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $warningOwner  = Owner::factory()->create(['unit_id' => $warningUnit->id, 'organization_id' => $user->organization_id]);
    Invoice::factory()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $warningUnit->id,
        'charge_type_id'  => $warningCT->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $warningOwner->id,
        'billing_period'  => '2026-01-01',
        'status'          => 'overdue',
        'amount'          => 2000,
        'due_date'        => now()->subDays(10)->format('Y-m-d'),
    ]);

    // Critical unit
    $criticalEstate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $criticalUnit   = Unit::factory()->create(['estate_id' => $criticalEstate->id, 'organization_id' => $user->organization_id]);
    $criticalCT     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $criticalOwner  = Owner::factory()->create(['unit_id' => $criticalUnit->id, 'organization_id' => $user->organization_id]);
    Invoice::factory()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $criticalUnit->id,
        'charge_type_id'  => $criticalCT->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $criticalOwner->id,
        'billing_period'  => '2026-01-01',
        'status'          => 'overdue',
        'amount'          => 1000,  // lower amount, but critical severity
        'due_date'        => now()->subDays(10)->format('Y-m-d'),
    ]);

    RiskRule::factory()->warning()->create([
        'organization_id' => $user->organization_id,
        'conditions'      => [['type' => 'overdue_amount', 'operator' => '>=', 'value' => 500]],
    ]);
    RiskRule::factory()->critical()->create([
        'organization_id' => $user->organization_id,
        'conditions'      => [['type' => 'overdue_amount', 'operator' => '>=', 'value' => 500]],
    ]);

    $flaggedUnits = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.evaluate.risk-rules'))
        ->assertOk()
        ->json('flagged_units');

    // Both units matched both rules (warning + critical), so both are 'critical'
    // Within same severity (critical), higher overdue_amount (2000) comes first
    expect($flaggedUnits[0]['unit_id'])->toBe($warningUnit->id); // 2000 overdue
    expect($flaggedUnits[1]['unit_id'])->toBe($criticalUnit->id); // 1000 overdue
});

it('evaluate within same severity sorts by overdue_amount descending', function () {
    $user = adminUser();

    // Unit A: 3000 overdue
    $estateA = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unitA   = Unit::factory()->create(['estate_id' => $estateA->id, 'organization_id' => $user->organization_id]);
    $ctA     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $ownerA  = Owner::factory()->create(['unit_id' => $unitA->id, 'organization_id' => $user->organization_id]);
    Invoice::factory()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unitA->id,
        'charge_type_id' => $ctA->id, 'billed_to_type' => 'owner', 'billed_to_id' => $ownerA->id,
        'billing_period' => '2026-01-01', 'status' => 'overdue', 'amount' => 3000,
        'due_date' => now()->subDays(10)->format('Y-m-d'),
    ]);

    // Unit B: 1000 overdue
    $estateB = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unitB   = Unit::factory()->create(['estate_id' => $estateB->id, 'organization_id' => $user->organization_id]);
    $ctB     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $ownerB  = Owner::factory()->create(['unit_id' => $unitB->id, 'organization_id' => $user->organization_id]);
    Invoice::factory()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unitB->id,
        'charge_type_id' => $ctB->id, 'billed_to_type' => 'owner', 'billed_to_id' => $ownerB->id,
        'billing_period' => '2026-01-01', 'status' => 'overdue', 'amount' => 1000,
        'due_date' => now()->subDays(10)->format('Y-m-d'),
    ]);

    RiskRule::factory()->warning()->create([
        'organization_id' => $user->organization_id,
        'conditions'      => [['type' => 'overdue_amount', 'operator' => '>=', 'value' => 500]],
    ]);

    $flaggedUnits = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.evaluate.risk-rules'))
        ->assertOk()
        ->json('flagged_units');

    expect(count($flaggedUnits))->toBe(2);
    expect($flaggedUnits[0]['unit_id'])->toBe($unitA->id); // 3000 first
    expect($flaggedUnits[1]['unit_id'])->toBe($unitB->id); // 1000 second
});

it('evaluate total_flagged equals the count of flagged_units', function () {
    ['user' => $user] = makeOverdueUnitForEval(['amount' => 5000]);

    RiskRule::factory()->warning()->create([
        'organization_id' => $user->organization_id,
        'conditions'      => [['type' => 'overdue_amount', 'operator' => '>=', 'value' => 1000]],
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.evaluate.risk-rules'))
        ->assertOk();

    expect($response->json('total_flagged'))
        ->toBe(count($response->json('flagged_units')));
});

it('evaluate is scoped to the authenticated tenant — other orgs units are not evaluated', function () {
    // Other org has an overdue unit and a matching rule
    ['user' => $otherUser, 'unit' => $otherUnit] = makeOverdueUnitForEval(['amount' => 5000]);
    RiskRule::factory()->warning()->create([
        'organization_id' => $otherUser->organization_id,
        'conditions'      => [['type' => 'overdue_amount', 'operator' => '>=', 'value' => 1000]],
    ]);

    // My org has no rules and no overdue units
    $myUser = adminUser();

    $response = $this->actingAs($myUser, 'api')
        ->getJson(route('api.v1.evaluate.risk-rules'))
        ->assertOk();

    // My evaluate returns empty — other org's data is invisible
    expect($response->json('rules'))->toBeEmpty();
    expect($response->json('flagged_units'))->toBeEmpty();

    $unitIds = array_column($response->json('flagged_units'), 'unit_id');
    expect($unitIds)->not->toContain($otherUnit->id);
});
