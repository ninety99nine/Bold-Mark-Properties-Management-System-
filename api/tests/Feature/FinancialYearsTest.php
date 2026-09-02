<?php

use App\Models\Community;
use App\Models\CashbookEntry;

// ──────────────────────────────────────────────────────────────────────────────
// Unauthenticated
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on the financial-years route when unauthenticated', function () {
    $this->getJson(route('api.v1.show.community.financial.years', ['community' => '00000000-0000-0000-0000-000000000000']))
        ->assertUnauthorized();
});

// ──────────────────────────────────────────────────────────────────────────────
// Shape
// ──────────────────────────────────────────────────────────────────────────────

it('returns the financial-year periods, current year and allocation flag', function () {
    $user      = adminUser();
    $community = Community::factory()->create([
        'organization_id'          => $user->organization_id,
        'financial_year_end_month' => 12,
    ]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.financial.years', $community))
        ->assertOk()
        ->assertJsonStructure([
            'periods' => [['start', 'end', 'year', 'is_setup', 'is_current', 'label']],
            'current_year',
            'all_transactions_allocated',
        ]);

    // Past 3 + current + future 2 = 6 periods.
    expect($resp->json('periods'))->toHaveCount(6);
    // No cashbook entries → everything allocated.
    expect($resp->json('all_transactions_allocated'))->toBeTrue();
});

it('flags all_transactions_allocated false when an unallocated cashbook entry exists', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    CashbookEntry::factory()->create([
        'community_id'           => $community->id,
        'organization_id'        => $user->organization_id,
        'bank_account_id'        => null,
        'allocation_ledger_type' => null,
        'is_split'               => false,
    ]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.financial.years', $community))
        ->assertOk();

    expect($resp->json('all_transactions_allocated'))->toBeFalse();
});
