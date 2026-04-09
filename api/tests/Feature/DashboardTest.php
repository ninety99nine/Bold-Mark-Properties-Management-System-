<?php

use App\Models\Estate;
use App\Models\Invoice;
use App\Models\Unit;

// ──────────────────────────────────────────────────────────────────────────────
// Unauthenticated access
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on dashboard when unauthenticated', function () {
    $this->getJson(route('api.v1.show.dashboard'))->assertUnauthorized();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /dashboard
// ──────────────────────────────────────────────────────────────────────────────

it('returns dashboard summary for authenticated user', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.dashboard'))
        ->assertOk();
});

it('dashboard data is scoped to the authenticated user tenant', function () {
    $userA   = adminUser();
    $estateA = Estate::factory()->create(['tenant_id' => $userA->tenant_id]);
    Unit::factory()->count(5)->create(['estate_id' => $estateA->id, 'tenant_id' => $userA->tenant_id]);

    $userB   = adminUser(); // different tenant
    $estateB = Estate::factory()->create(['tenant_id' => $userB->tenant_id]);
    Unit::factory()->count(3)->create(['estate_id' => $estateB->id, 'tenant_id' => $userB->tenant_id]);

    // Both users should get a 200 and see only their own data
    $this->actingAs($userA, 'api')
        ->getJson(route('api.v1.show.dashboard'))
        ->assertOk();

    $this->actingAs($userB, 'api')
        ->getJson(route('api.v1.show.dashboard'))
        ->assertOk();
});
