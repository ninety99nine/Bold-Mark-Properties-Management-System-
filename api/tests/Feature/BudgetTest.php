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
            'data' => [['ledger_id', 'code', 'name', 'jan', 'dec', 'per_year', 'equal_monthly']],
            'year', 'fund', 'locked',
        ]);

    // The seeded main-fund income + expense chart yields several rows.
    expect(count($resp->json('data')))->toBeGreaterThan(0);
    expect($resp->json('locked'))->toBeFalse();
    expect((float) $resp->json('data.0.per_year'))->toBe(0.0);
    // Every row is a main-fund income_statement account.
    expect(collect($resp->json('data'))->pluck('fund')->every(fn ($f) => $f === 'main'))->toBeTrue();
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

it('imports a budget spreadsheet matched by account code', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $income  = budgetIncomeLedger($user->organization_id);

    // Build a CSV: heading + one data row keyed by the ledger code.
    $csv  = "Account,Description,Jan,Feb,Mar,Apr,May,Jun,Jul,Aug,Sep,Oct,Nov,Dec\n";
    $csv .= "1000/001,Levy,10,10,10,10,10,10,10,10,10,10,10,10\n";
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
        ->assertJsonPath('imported', 1);

    $budget = CommunityBudget::where('ledger_id', $income->id)->where('year', 2026)->first();
    expect((float) $budget->per_year)->toBe(120.0);
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
