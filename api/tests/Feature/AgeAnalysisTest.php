<?php

use App\Enums\CollectionStatus;
use App\Models\CashbookEntry;
use App\Models\Community;
use App\Models\Invoice;
use App\Models\Ledger;
use App\Models\Owner;
use App\Models\Unit;
use App\Models\UnitCollectionNote;

/**
 * Helper: create a unit + owner + one outstanding invoice due `$daysOverdue`
 * days ago (negative = not yet due) for `$amount`.
 */
function arrearsUnit(string $orgId, Community $community, float $amount, int $daysOverdue, array $unitAttrs = []): Unit
{
    $unit  = Unit::factory()->create(array_merge([
        'community_id'    => $community->id,
        'organization_id' => $orgId,
    ], $unitAttrs));
    $owner = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $orgId]);
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

// ──────────────────────────────────────────────────────────────────────────────
// Auth
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on age analysis routes when unauthenticated', function (string $method, string $route) {
    $this->{$method . 'Json'}(route($route))->assertUnauthorized();
})->with([
    ['get',  'api.v1.show.age.analysis'],
    ['post', 'api.v1.send.age.analysis.notices'],
]);

// ──────────────────────────────────────────────────────────────────────────────
// Shape + buckets
// ──────────────────────────────────────────────────────────────────────────────

it('returns rows and totals keyed by WeConnectU buckets', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    arrearsUnit($user->organization_id, $community, 1000, 45); // 60-day bucket

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.age.analysis'))
        ->assertOk()
        ->assertJsonStructure([
            'rows' => [['unit_id', 'unit_number', 'customer_code', 'customer_name', 'collection_status', 'debit_order', 'notes_count', '120_plus', '90_days', '60_days', '30_days', 'current', 'balance']],
            'totals' => ['120_plus', '90_days', '60_days', '30_days', 'current', 'balance', 'customer_count'],
        ]);

    expect($response->json('totals.customer_count'))->toBe(1)
        ->and((float) $response->json('rows.0.60_days'))->toBe(1000.0)
        ->and((float) $response->json('rows.0.balance'))->toBe(1000.0);
});

it('places invoices in the correct ageing bucket', function (int $daysOverdue, string $bucket) {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    arrearsUnit($user->organization_id, $community, 500, $daysOverdue);

    $row = $this->actingAs($user, 'api')->getJson(route('api.v1.show.age.analysis'))->json('rows.0');

    expect((float) $row[$bucket])->toBe(500.0)
        ->and((float) $row['balance'])->toBe(500.0);
})->with([
    [-5, 'current'],
    [15, '30_days'],
    [45, '60_days'],
    [75, '90_days'],
    [120, '120_plus'],
]);

it('aggregates multiple invoices for the same unit into one row', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit      = arrearsUnit($user->organization_id, $community, 1000, 45); // 60-day
    $owner     = Owner::where('unit_id', $unit->id)->first();
    $ledger    = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    // second invoice, current bucket, same unit
    Invoice::factory()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id, 'ledger_id' => $ledger->id,
        'billed_to_type' => 'owner', 'billed_to_id' => $owner->id, 'amount' => 400, 'status' => 'unpaid',
        'due_date' => now()->addDays(5)->toDateString(),
    ]);

    $response = $this->actingAs($user, 'api')->getJson(route('api.v1.show.age.analysis'))->assertOk();

    expect($response->json('totals.customer_count'))->toBe(1)
        ->and((float) $response->json('rows.0.60_days'))->toBe(1000.0)
        ->and((float) $response->json('rows.0.current'))->toBe(400.0)
        ->and((float) $response->json('rows.0.balance'))->toBe(1400.0);
});

it('excludes fully paid units', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit      = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $owner     = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    $ledger    = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    Invoice::factory()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id, 'ledger_id' => $ledger->id,
        'billed_to_type' => 'owner', 'billed_to_id' => $owner->id, 'amount' => 500, 'status' => 'paid',
        'due_date' => now()->subDays(45)->toDateString(),
    ]);

    expect($this->actingAs($user, 'api')->getJson(route('api.v1.show.age.analysis'))->json('totals.customer_count'))->toBe(0);
});

it('nets unallocated credits oldest-first and can produce a negative balance', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit      = arrearsUnit($user->organization_id, $community, 1000, 120); // 120+ bucket

    // credit of 1500 → clears the 1000 arrears, leaves 500 credit → balance -500
    CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'unit_id'         => $unit->id,
        'invoice_id'      => null,
        'type'            => 'credit',
        'amount'          => 1500,
    ]);

    $row = $this->actingAs($user, 'api')->getJson(route('api.v1.show.age.analysis'))->json('rows.0');

    expect((float) $row['120_plus'])->toBe(0.0)
        ->and((float) $row['balance'])->toBe(-500.0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Row metadata (status / debit order / notes)
// ──────────────────────────────────────────────────────────────────────────────

it('surfaces collection status, debit order and notes count on the row', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit      = arrearsUnit($user->organization_id, $community, 800, 45, [
        'collection_status' => CollectionStatus::SECOND_NOTICE->value,
        'debit_order'       => true,
    ]);
    UnitCollectionNote::create([
        'unit_id' => $unit->id, 'organization_id' => $user->organization_id, 'note' => 'Called owner',
    ]);

    $row = $this->actingAs($user, 'api')->getJson(route('api.v1.show.age.analysis'))->json('rows.0');

    expect($row['collection_status'])->toBe('second_notice')
        ->and($row['collection_status_label'])->toBe('2nd Notice')
        ->and($row['debit_order'])->toBeTrue()
        ->and($row['notes_count'])->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// Filters
// ──────────────────────────────────────────────────────────────────────────────

it('filters by debt status and debit order', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    arrearsUnit($user->organization_id, $community, 500, 45, ['collection_status' => CollectionStatus::FIRST_NOTICE->value, 'debit_order' => true]);
    arrearsUnit($user->organization_id, $community, 500, 45, ['collection_status' => CollectionStatus::HANDED_OVER->value, 'debit_order' => false]);

    expect($this->actingAs($user, 'api')->getJson(route('api.v1.show.age.analysis', ['debt_status' => 'first_notice']))->json('totals.customer_count'))->toBe(1);
    expect($this->actingAs($user, 'api')->getJson(route('api.v1.show.age.analysis', ['debit_order' => 'true']))->json('totals.customer_count'))->toBe(1);
});

it('scopes results to the authenticated organization', function () {
    $user  = adminUser();
    $mine  = Community::factory()->create(['organization_id' => $user->organization_id]);
    arrearsUnit($user->organization_id, $mine, 500, 45);

    $other = createOrganization();
    $theirs = Community::factory()->create(['organization_id' => $other->id]);
    arrearsUnit($other->id, $theirs, 900, 45);

    expect($this->actingAs($user, 'api')->getJson(route('api.v1.show.age.analysis'))->json('totals.customer_count'))->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// Export + Send Notices
// ──────────────────────────────────────────────────────────────────────────────

it('exports the age analysis as CSV with the WeConnectU columns', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    arrearsUnit($user->organization_id, $community, 500, 45);

    $response = $this->actingAs($user, 'api')
        ->get(route('api.v1.export.age.analysis', ['_format' => 'csv']))
        ->assertOk();

    $body = $response->streamedContent();
    expect($body)->toContain('120+ Days')->toContain('Balance')->toContain('Customer');
});

it('send notices advances the collection status and logs a note for arrears customers', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit      = arrearsUnit($user->organization_id, $community, 500, 45); // status none

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.send.age.analysis.notices'))
        ->assertOk()
        ->assertJsonPath('sent', 1);

    expect($unit->fresh()->collection_status->value)->toBe('first_notice')
        ->and(UnitCollectionNote::where('unit_id', $unit->id)->count())->toBe(1);
});
