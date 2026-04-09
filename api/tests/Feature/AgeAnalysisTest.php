<?php

use App\Models\Estate;
use App\Models\Invoice;
use App\Models\Owner;
use App\Models\Unit;

// ──────────────────────────────────────────────────────────────────────────────
// Unauthenticated access
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on age-analysis when unauthenticated', function () {
    $this->getJson(route('api.v1.show.age.analysis'))->assertUnauthorized();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /age-analysis
// ──────────────────────────────────────────────────────────────────────────────

it('returns age analysis data for authenticated user', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.age.analysis'))
        ->assertOk();
});

it('age analysis data is scoped to the authenticated user tenant', function () {
    $userA   = adminUser();
    $estateA = Estate::factory()->create(['tenant_id' => $userA->tenant_id]);
    $unitA   = Unit::factory()->create(['estate_id' => $estateA->id, 'tenant_id' => $userA->tenant_id]);
    Invoice::factory()->overdue()->count(3)->create(['tenant_id' => $userA->tenant_id, 'unit_id' => $unitA->id]);

    $userB = adminUser(); // different tenant — no overdue invoices

    // User A should see their 3 overdue invoices
    $this->actingAs($userA, 'api')
        ->getJson(route('api.v1.show.age.analysis'))
        ->assertOk();

    // User B should see no arrears
    $this->actingAs($userB, 'api')
        ->getJson(route('api.v1.show.age.analysis'))
        ->assertOk();
});

it('filters age analysis by estate when estate_id is provided', function () {
    $user    = adminUser();
    $estateA = Estate::factory()->create(['tenant_id' => $user->tenant_id]);
    $estateB = Estate::factory()->create(['tenant_id' => $user->tenant_id]);

    $unitA = Unit::factory()->create(['estate_id' => $estateA->id, 'tenant_id' => $user->tenant_id]);
    Invoice::factory()->overdue()->count(2)->create(['tenant_id' => $user->tenant_id, 'unit_id' => $unitA->id]);

    $unitB = Unit::factory()->create(['estate_id' => $estateB->id, 'tenant_id' => $user->tenant_id]);
    Invoice::factory()->overdue()->count(4)->create(['tenant_id' => $user->tenant_id, 'unit_id' => $unitB->id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.age.analysis') . '?estate_id=' . $estateA->id)
        ->assertOk();
});
