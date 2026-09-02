<?php

use App\Models\Ledger;
use App\Models\Community;
use App\Models\CommunityBudget;
use App\Enums\FinancialCategory;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * The seeded main-fund income ledger (1000/001 Levy) for a community's org.
 */
function budgetIncomeLedger(string $orgId): Ledger
{
    return Ledger::where('organization_id', $orgId)
        ->where('code', '1000/001')
        ->firstOrFail();
}

// ──────────────────────────────────────────────────────────────────────────────
// Unauthenticated
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on budget routes when unauthenticated', function () {
    $this->getJson(route('api.v1.show.community.budgets', ['community' => '00000000-0000-0000-0000-000000000000']))
        ->assertUnauthorized();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET grid
// ──────────────────────────────────────────────────────────────────────────────

it('returns the budget grid with a row per main-fund ledger', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.budgets', ['community' => $community, 'year' => 2026, 'fund' => 'main']))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['ledger_id', 'code', 'name', 'jan', 'dec', 'per_year', 'equal_monthly', 'section', 'group_code', 'group_name']],
            'year', 'fund', 'locked',
        ]);

    // The seeded main-fund income + expense chart yields several rows.
    expect(count($resp->json('data')))->toBeGreaterThan(0);
    expect($resp->json('locked'))->toBeFalse();
    expect((float) $resp->json('data.0.per_year'))->toBe(0.0);
    // Every row is a main-fund income_statement account.
    expect(collect($resp->json('data'))->pluck('fund')->every(fn ($f) => $f === 'main'))->toBeTrue();

    // Rows are the LEAF accounts only — never the /000 group parents (those are
    // rendered as computed group headers on the frontend).
    $codes = collect($resp->json('data'))->pluck('code');
    expect($codes->every(fn ($c) => !str_ends_with($c, '/000')))->toBeTrue();

    // Each row carries its group + section metadata for the grid headers.
    $levies = collect($resp->json('data'))->firstWhere('code', '1000/001');
    expect($levies['section'])->toBe('income');
    expect($levies['group_code'])->toBe('1000/000');
    expect($levies['group_name'])->toBe('INCOME');
});

// ──────────────────────────────────────────────────────────────────────────────
// Upsert + equal-monthly split
// ──────────────────────────────────────────────────────────────────────────────

it('upserts budget rows and splits per_year equally across months', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $income = budgetIncomeLedger($user->organization_id);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.upsert.community.budgets', $community), [
            'year' => 2026,
            'fund' => 'main',
            'rows' => [
                ['ledger_id' => $income->id, 'equal_monthly' => true, 'per_year' => 1200],
            ],
        ])
        ->assertOk();

    $budget = CommunityBudget::where('ledger_id', $income->id)->where('year', 2026)->first();
    expect((float) $budget->jan)->toBe(100.0);
    expect((float) $budget->dec)->toBe(100.0);
    expect((float) $budget->per_year)->toBe(1200.0);
});

it('upserts explicit monthly values and sums per_year', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $income = budgetIncomeLedger($user->organization_id);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.upsert.community.budgets', $community), [
            'year' => 2026,
            'fund' => 'main',
            'rows' => [
                ['ledger_id' => $income->id, 'jan' => 50, 'feb' => 50],
            ],
        ])
        ->assertOk();

    $budget = CommunityBudget::where('ledger_id', $income->id)->where('year', 2026)->first();
    expect((float) $budget->per_year)->toBe(100.0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Lock / unlock
// ──────────────────────────────────────────────────────────────────────────────

it('locks a budget period and rejects further upserts, then unlocks', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $income = budgetIncomeLedger($user->organization_id);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.lock.community.budget', $community), ['year' => 2026, 'fund' => 'main'])
        ->assertOk()
        ->assertJsonPath('locked', true);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.upsert.community.budgets', $community), [
            'year' => 2026, 'fund' => 'main',
            'rows' => [['ledger_id' => $income->id, 'per_year' => 100, 'equal_monthly' => true]],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['year']);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.unlock.community.budget', $community), ['year' => 2026, 'fund' => 'main'])
        ->assertOk()
        ->assertJsonPath('locked', false);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.upsert.community.budgets', $community), [
            'year' => 2026, 'fund' => 'main',
            'rows' => [['ledger_id' => $income->id, 'per_year' => 100, 'equal_monthly' => true]],
        ])
        ->assertOk();
});

// ──────────────────────────────────────────────────────────────────────────────
// Template + import
// ──────────────────────────────────────────────────────────────────────────────

it('downloads the budget template', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->get(route('api.v1.download.community.budget.template', ['community' => $community, 'fund' => 'main']))
        ->assertOk()
        ->assertHeader('content-disposition');
});

it('imports a budget spreadsheet in the WeConnectU template format', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $income  = budgetIncomeLedger($user->organization_id);

    // Build the WeConnectU layout: title/period, name, the Jan..Dec + Per Year
    // header, the year row, TOTAL INCOME, a /000 group row (skipped) and one leaf.
    $csv  = "Actual Budget,2026-01-01 to 2026-12-31\n";
    $csv .= "Test Community\n";
    $csv .= ",Jan,Feb,Mar,Apr,May,Jun,Jul,Aug,Sep,Oct,Nov,Dec,Per Year\n";
    $csv .= ",2026,2026,2026,2026,2026,2026,2026,2026,2026,2026,2026,2026, \n";
    $csv .= "TOTAL INCOME\n";
    $csv .= "1000/000 - INCOME,0,0,0,0,0,0,0,0,0,0,0,0,0\n";
    $csv .= "1000/001 - Levies,10,10,10,10,10,10,10,10,10,10,10,10,120\n";
    $path = tempnam(sys_get_temp_dir(), 'budget') . '.csv';
    file_put_contents($path, $csv);
    $file = new \Illuminate\Http\Testing\File('budget.csv', fopen($path, 'r'));

    $this->actingAs($user, 'api')
        ->post(route('api.v1.import.community.budget', $community), [
            'year' => 2026,
            'fund' => 'main',
            'file' => $file,
        ])
        ->assertOk()
        // Only the leaf 1000/001 is stored — the 1000/000 group row is skipped.
        ->assertJsonPath('imported', 1);

    $budget = CommunityBudget::where('ledger_id', $income->id)->where('year', 2026)->first();
    expect((float) $budget->per_year)->toBe(120.0);
    expect((float) $budget->jan)->toBe(10.0);

    // The /000 group parent must NOT have a stored budget row.
    $parentId = Ledger::where('organization_id', $user->organization_id)->where('code', '1000/000')->value('id');
    expect(CommunityBudget::where('ledger_id', $parentId)->where('year', 2026)->exists())->toBeFalse();
});

// ──────────────────────────────────────────────────────────────────────────────
// Reserve fund budget grid
// ──────────────────────────────────────────────────────────────────────────────

it('returns the reserve fund budget grid for RFI/RFE ledgers only', function () {
    // Seeded chart carries RFI/001 (Reserve Fund Levy) among the reserve accounts.
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.budgets', ['community' => $community, 'year' => 2026, 'fund' => 'reserve']))
        ->assertOk();

    $codes = collect($resp->json('data'))->pluck('code');
    expect($codes)->toContain('RFI/001');
    expect($codes->every(fn ($c) => str_starts_with($c, 'RF')))->toBeTrue();
});
