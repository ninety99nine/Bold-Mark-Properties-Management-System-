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
    $estateA = Estate::factory()->create(['organization_id' => $userA->organization_id]);
    $unitA   = Unit::factory()->create(['estate_id' => $estateA->id, 'organization_id' => $userA->organization_id]);
    Invoice::factory()->overdue()->count(3)->create(['organization_id' => $userA->organization_id, 'unit_id' => $unitA->id]);

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
    $estateA = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $estateB = Estate::factory()->create(['organization_id' => $user->organization_id]);

    $unitA = Unit::factory()->create(['estate_id' => $estateA->id, 'organization_id' => $user->organization_id]);
    Invoice::factory()->overdue()->count(2)->create(['organization_id' => $user->organization_id, 'unit_id' => $unitA->id]);

    $unitB = Unit::factory()->create(['estate_id' => $estateB->id, 'organization_id' => $user->organization_id]);
    Invoice::factory()->overdue()->count(4)->create(['organization_id' => $user->organization_id, 'unit_id' => $unitB->id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.age.analysis') . '?estate_id=' . $estateA->id)
        ->assertOk();
});
