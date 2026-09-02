<?php

use App\Models\BankAccount;
use App\Models\Community;
use App\Models\ComplianceChecklist;
use App\Models\ComplianceChecklistItem;

// ──────────────────────────────────────────────────────────────────────────────
// Auth
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 for the community dashboard when unauthenticated', function () {
    $this->getJson(route('api.v1.community.dashboard', ['community' => '00000000-0000-0000-0000-000000000000']))
        ->assertUnauthorized();
});

// ──────────────────────────────────────────────────────────────────────────────
// Payload shape
// ──────────────────────────────────────────────────────────────────────────────

it('returns the WeConnectU dashboard payload shape', function () {
    $user = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.community.dashboard', ['community' => $community->id]))
        ->assertOk()
        ->assertJsonStructure([
            'financial_years',
            'selected_year',
            'planner',
            'finance' => [
                'bank_balance' => ['value', 'count', 'as_at'],
                'investments'  => ['value', 'count', 'as_at'],
                'outstanding_debt',
                'debt_trend'   => ['total', 'percent_change', 'series' => ['*' => ['label', 'date', 'value']]],
            ],
            'counts' => ['open_tasks', 'pending_transfers', 'warnings', 'penalties', 'fines'],
            'pending_transfers',
            'my_tasks',
        ]);
});

// ──────────────────────────────────────────────────────────────────────────────
// Bank balance vs investments
// ──────────────────────────────────────────────────────────────────────────────

it('sums current accounts into bank_balance and investment accounts into investments', function () {
    $user = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    BankAccount::factory()->current()->create([
        'organization_id' => $community->organization_id,
        'community_id'    => $community->id,
        'balance'         => 100000,
    ]);
    BankAccount::factory()->current()->create([
        'organization_id' => $community->organization_id,
        'community_id'    => $community->id,
        'balance'         => 7162.43,
    ]);
    BankAccount::factory()->investment()->create([
        'organization_id' => $community->organization_id,
        'community_id'    => $community->id,
        'balance'         => 6578.55,
    ]);

    $finance = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.community.dashboard', ['community' => $community->id]))
        ->assertOk()
        ->json('finance');

    expect($finance['bank_balance']['value'])->toBe(107162.43)
        ->and($finance['bank_balance']['count'])->toBe(2)
        ->and($finance['investments']['value'])->toBe(6578.55)
        ->and($finance['investments']['count'])->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// Planner is FY-scoped; pills map correctly
// ──────────────────────────────────────────────────────────────────────────────

it('scopes the planner to the requested financial year and maps status pills', function () {
    $user = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $fy2026 = ComplianceChecklist::factory()->create([
        'organization_id'      => $community->organization_id,
        'community_id'         => $community->id,
        'financial_year_label' => '2026/2027',
        'financial_year_start' => '2026-07-01',
        'financial_year_end'   => '2027-06-30',
    ]);
    $fy2025 = ComplianceChecklist::factory()->create([
        'organization_id'      => $community->organization_id,
        'community_id'         => $community->id,
        'financial_year_label' => '2025/2026',
        'financial_year_start' => '2025-07-01',
        'financial_year_end'   => '2026-06-30',
    ]);

    // Planned (pending + due date), Done (completed), Confirm Date (no due date).
    ComplianceChecklistItem::factory()->create([
        'compliance_checklist_id' => $fy2026->id,
        'organization_id'         => $community->organization_id,
        'name'                    => 'Audit (draft statement)',
        'status'                  => 'pending',
        'due_date'                => '2026-07-31',
    ]);
    ComplianceChecklistItem::factory()->create([
        'compliance_checklist_id' => $fy2026->id,
        'organization_id'         => $community->organization_id,
        'name'                    => 'Fire Safety Compliance',
        'status'                  => 'completed',
        'due_date'                => '2026-08-31',
    ]);
    ComplianceChecklistItem::factory()->create([
        'compliance_checklist_id' => $fy2026->id,
        'organization_id'         => $community->organization_id,
        'name'                    => 'Insurance',
        'status'                  => 'pending',
        'due_date'                => null,
    ]);
    // A 2025 item that must NOT appear when 2026 is selected.
    ComplianceChecklistItem::factory()->create([
        'compliance_checklist_id' => $fy2025->id,
        'organization_id'         => $community->organization_id,
        'name'                    => 'Old Year Item',
        'status'                  => 'pending',
        'due_date'                => '2025-08-01',
    ]);

    $body = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.community.dashboard', ['community' => $community->id]) . '?financial_year=2026%2F2027')
        ->assertOk()
        ->json();

    expect($body['financial_years'])->toContain('2026/2027', '2025/2026')
        ->and($body['selected_year'])->toBe('2026/2027');

    $titles = collect($body['planner'])->pluck('title');
    expect($titles)->toContain('Audit (draft statement)', 'Fire Safety Compliance', 'Insurance')
        ->and($titles)->not->toContain('Old Year Item');

    $byTitle = collect($body['planner'])->keyBy('title');
    expect($byTitle['Audit (draft statement)']['pill'])->toBe('planned')
        ->and($byTitle['Fire Safety Compliance']['pill'])->toBe('done')
        ->and($byTitle['Insurance']['pill'])->toBe('confirm_date');
});

// ──────────────────────────────────────────────────────────────────────────────
// Operational counts are zero until modules exist
// ──────────────────────────────────────────────────────────────────────────────

it('returns zero counts and empty panels for not-yet-built modules', function () {
    $user = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $body = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.community.dashboard', ['community' => $community->id]))
        ->assertOk()
        ->json();

    expect($body['counts'])->toBe([
        'open_tasks' => 0, 'pending_transfers' => 0, 'warnings' => 0, 'penalties' => 0, 'fines' => 0,
    ])
        ->and($body['pending_transfers'])->toBe([])
        ->and($body['my_tasks'])->toBe([]);
});
