<?php

use App\Models\Community;
use App\Models\Invoice;
use App\Models\Ledger;
use App\Models\Owner;
use App\Models\Unit;
use App\Models\UnitStatusHistory;

/**
 * Helper: create a unit + owner + one unpaid invoice for `$amount`, overdue by
 * `$daysOverdue` days, so the customer carries a non-zero statement balance.
 */
function statementUnit(string $orgId, Community $community, float $amount, int $daysOverdue = 10): Unit
{
    $unit   = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $orgId]);
    $owner  = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $orgId]);
    $ledger = Ledger::factory()->create(['organization_id' => $orgId]);

    Invoice::factory()->create([
        'organization_id' => $orgId,
        'unit_id'         => $unit->id,
        'ledger_id'       => $ledger->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
        'amount'          => $amount,
        'status'          => 'unpaid',
        'due_date'        => now()->subDays($daysOverdue)->toDateString(),
    ]);

    return $unit;
}

function statementsRoute(Community $community, array $params = []): string
{
    return route('api.v1.show.community.customer.statements', array_merge(['community' => $community->id], $params));
}

// =============================================================================
// Auth
// =============================================================================

it('blocks unauthenticated access to customer statements', function (): void {
    $community = Community::factory()->create(['organization_id' => createOrganization()->id]);

    $this->getJson(statementsRoute($community))->assertUnauthorized();
    $this->getJson(route('api.v1.view.community.customer.statements', ['community' => $community->id]))->assertUnauthorized();
});

// =============================================================================
// List
// =============================================================================

it('lists customers with their balance as at the date and a totals sum', function (): void {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    statementUnit($user->organization_id, $community, 500.00);
    statementUnit($user->organization_id, $community, 300.00);

    $data = $this->actingAs($user, 'api')
        ->getJson(statementsRoute($community))
        ->assertOk()
        ->json();

    expect($data['rows'])->toHaveCount(2);
    expect(round($data['totals']['balance'], 2))->toBe(800.00);
    expect($data['rows'][0])->toHaveKeys(['unit_id', 'customer_code', 'customer_name', 'collection_status', 'balance']);
});

it('surfaces the acting user (not the customer) who applied the collection status', function (): void {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $unit = statementUnit($user->organization_id, $community, 16000.00);
    $unit->update(['collection_status' => 'handed_over']);

    UnitStatusHistory::create([
        'unit_id'         => $unit->id,
        'community_id'    => $community->id,
        'organization_id' => $user->organization_id,
        'status'          => 'handed_over',
        'status_date'     => now()->toDateString(),
        'changed_by_name' => 'Bold Mark Admin',
    ]);

    $row = collect($this->actingAs($user, 'api')
        ->getJson(statementsRoute($community))
        ->assertOk()
        ->json('rows'))
        ->firstWhere('unit_id', $unit->id);

    expect($row['collection_status'])->toBe('handed_over');
    // The flag tooltip name must be the acting user, never the customer.
    expect($row['status_changed_by'])->toBe('Bold Mark Admin');
    expect($row['status_changed_by'])->not->toBe($row['customer_name']);
});

it('respects the hide-zero filter', function (): void {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    statementUnit($user->organization_id, $community, 500.00);
    // A unit with no invoices carries a zero balance and is dropped by the engine.
    Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);

    $data = $this->actingAs($user, 'api')
        ->getJson(statementsRoute($community, ['hide_zero' => 'true']))
        ->assertOk()
        ->json();

    expect($data['rows'])->toHaveCount(1);
});

// =============================================================================
// View PDF (combined)
// =============================================================================

it('streams a combined statements PDF', function (): void {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    statementUnit($user->organization_id, $community, 500.00);

    $res = $this->actingAs($user, 'api')
        ->get(route('api.v1.view.community.customer.statements', ['community' => $community->id]));

    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('application/pdf');
});
