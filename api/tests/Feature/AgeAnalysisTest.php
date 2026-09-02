<?php

use App\Enums\CollectionStatus;
use App\Models\CashbookEntry;
use App\Models\Community;
use App\Models\Invoice;
use App\Models\Ledger;
use App\Models\Owner;
use App\Models\Unit;
use App\Models\UnitCollectionNote;
use Illuminate\Support\Facades\Storage;

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

/** Route helper for the community-scoped age analysis endpoint. */
function ageRoute(Community $community, array $params = []): string
{
    return route('api.v1.show.community.age.analysis', array_merge(['community' => $community->id], $params));
}

// ──────────────────────────────────────────────────────────────────────────────
// Auth
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on age analysis routes when unauthenticated', function () {
    $community = Community::factory()->create(['organization_id' => createOrganization()->id]);

    $this->getJson(ageRoute($community))->assertUnauthorized();
    $this->postJson(route('api.v1.run.community.notices', ['community' => $community->id]))->assertUnauthorized();
});

// ──────────────────────────────────────────────────────────────────────────────
// Financial Year / Budget Period selector
// ──────────────────────────────────────────────────────────────────────────────

it('lists past/current/future financial-year periods with the current one flagged', function () {
    $user      = adminUser();
    $community = Community::factory()->create([
        'organization_id'          => $user->organization_id,
        'financial_year_end_month' => 12,
    ]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.financial.years', ['community' => $community->id]))
        ->assertOk();

    expect($resp->json('periods'))->toHaveCount(6);

    $current = collect($resp->json('periods'))->firstWhere('is_current', true);
    expect($current)->not->toBeNull()
        ->and($current['label'])->toContain('31/12/');

    $future = collect($resp->json('periods'))->firstWhere('is_future', true);
    expect($future['is_setup'])->toBeFalse()
        ->and($future['label'])->toContain('Not Setup');
});

// ──────────────────────────────────────────────────────────────────────────────
// Shape + buckets
// ──────────────────────────────────────────────────────────────────────────────

it('returns rows and totals keyed by WeConnectU buckets', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    arrearsUnit($user->organization_id, $community, 1000, 45); // 60-day bucket

    $response = $this->actingAs($user, 'api')
        ->getJson(ageRoute($community))
        ->assertOk()
        ->assertJsonStructure([
            'rows' => [['unit_id', 'unit_number', 'unit_no', 'customer_code', 'customer_name', 'collection_status', 'debit_order', 'notes_count', '120_plus', '90_days', '60_days', '30_days', 'current', 'balance']],
            'totals' => ['120_plus', '90_days', '60_days', '30_days', 'current', 'balance', 'customer_count'],
            'ageing_date',
        ]);

    expect($response->json('totals.customer_count'))->toBe(1)
        ->and((float) $response->json('rows.0.60_days'))->toBe(1000.0)
        ->and((float) $response->json('rows.0.balance'))->toBe(1000.0);
});

it('places invoices in the correct ageing bucket', function (int $daysOverdue, string $bucket) {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    arrearsUnit($user->organization_id, $community, 500, $daysOverdue);

    $row = $this->actingAs($user, 'api')->getJson(ageRoute($community))->json('rows.0');

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

    Invoice::factory()->create([
        'organization_id' => $user->organization_id, 'unit_id' => $unit->id, 'ledger_id' => $ledger->id,
        'billed_to_type' => 'owner', 'billed_to_id' => $owner->id, 'amount' => 400, 'status' => 'unpaid',
        'due_date' => now()->addDays(5)->toDateString(),
    ]);

    $response = $this->actingAs($user, 'api')->getJson(ageRoute($community))->assertOk();

    expect($response->json('totals.customer_count'))->toBe(1)
        ->and((float) $response->json('rows.0.60_days'))->toBe(1000.0)
        ->and((float) $response->json('rows.0.current'))->toBe(400.0)
        ->and((float) $response->json('rows.0.balance'))->toBe(1400.0);
});

it('excludes fully paid units', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    // An invoice (AR debit) fully settled by an allocated receipt (AR credit)
    // nets to a zero GL balance, so the customer drops off the age analysis.
    $unit = arrearsUnit($user->organization_id, $community, 500, 45);

    $bank = \App\Models\BankAccount::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
    ]);
    $entry = CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
        'bank_account_id' => $bank->id, 'unit_id' => null, 'invoice_id' => null,
        'type' => 'credit', 'amount' => 500,
    ]);
    app(\App\Services\AllocationPostingService::class)->post($entry, [
        'ledger_type' => 'customer', 'unit_id' => $unit->id,
    ]);

    expect($this->actingAs($user, 'api')->getJson(ageRoute($community))->json('totals.customer_count'))->toBe(0);
});

it('nets credits oldest-first and can produce a negative balance', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit      = arrearsUnit($user->organization_id, $community, 1000, 120); // 120+ bucket

    $bank = \App\Models\BankAccount::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
    ]);

    // A receipt allocated to the customer posts a Cr Accounts-Receivable line —
    // the GL credit lump the age analysis nets oldest-bucket-first.
    $entry = CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'bank_account_id' => $bank->id,
        'unit_id'         => null,
        'invoice_id'      => null,
        'type'            => 'credit',
        'amount'          => 1500,
    ]);
    app(\App\Services\AllocationPostingService::class)->post($entry, [
        'ledger_type' => 'customer',
        'unit_id'     => $unit->id,
    ]);

    $row = $this->actingAs($user, 'api')->getJson(ageRoute($community))->json('rows.0');

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

    $row = $this->actingAs($user, 'api')->getJson(ageRoute($community))->json('rows.0');

    expect($row['collection_status'])->toBe('second_notice')
        ->and($row['collection_status_label'])->toBe('2nd Notice')
        ->and($row['debit_order'])->toBeTrue()
        ->and($row['notes_count'])->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// Filters
// ──────────────────────────────────────────────────────────────────────────────

it('filters by debt status, debit order and hide-negative', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    arrearsUnit($user->organization_id, $community, 500, 45, ['collection_status' => CollectionStatus::FIRST_NOTICE->value, 'debit_order' => true]);
    arrearsUnit($user->organization_id, $community, 500, 45, ['collection_status' => CollectionStatus::HANDED_OVER->value, 'debit_order' => false]);

    expect($this->actingAs($user, 'api')->getJson(ageRoute($community, ['debt_status' => 'first_notice']))->json('totals.customer_count'))->toBe(1);
    expect($this->actingAs($user, 'api')->getJson(ageRoute($community, ['filter_type' => 'handed_over']))->json('totals.customer_count'))->toBe(1);
    expect($this->actingAs($user, 'api')->getJson(ageRoute($community, ['debit_order' => 'true']))->json('totals.customer_count'))->toBe(1);
});

it('scopes results to the community', function () {
    $user  = adminUser();
    $mine  = Community::factory()->create(['organization_id' => $user->organization_id]);
    $other = Community::factory()->create(['organization_id' => $user->organization_id]);
    arrearsUnit($user->organization_id, $mine, 500, 45);
    arrearsUnit($user->organization_id, $other, 900, 45);

    expect($this->actingAs($user, 'api')->getJson(ageRoute($mine))->json('totals.customer_count'))->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// Excel export
// ──────────────────────────────────────────────────────────────────────────────

it('exports a WeConnectU-named Excel workbook', function () {
    $user      = adminUser();
    $community = Community::factory()->create([
        'organization_id' => $user->organization_id,
        'name'            => 'Lyndhurst Estate',
    ]);
    arrearsUnit($user->organization_id, $community, 500, 45);

    $resp = $this->actingAs($user, 'api')
        ->get(route('api.v1.export.community.age.analysis', ['community' => $community->id]));

    $resp->assertOk();
    expect($resp->headers->get('content-disposition'))
        ->toContain('customer age analysis-lyndhurst estate');
});

// ──────────────────────────────────────────────────────────────────────────────
// Run Automatic Notices
// ──────────────────────────────────────────────────────────────────────────────

it('runs automatic notices — escalating status, logging a note and rendering a downloadable letter', function () {
    Storage::fake('local');

    $user      = superAdminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit      = arrearsUnit($user->organization_id, $community, 2000, 120); // status none

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.run.community.notices', ['community' => $community->id]), [])
        ->assertOk();

    expect($resp->json('batch.total'))->toBe(1);
    expect($unit->fresh()->collection_status->value)->toBe(CollectionStatus::FIRST_NOTICE->value)
        ->and(UnitCollectionNote::where('unit_id', $unit->id)->count())->toBe(1);

    $batchId = $resp->json('batch.id');
    $itemId  = $resp->json('batch.sections.0.rows.0.id');

    $this->actingAs($user, 'api')
        ->get(route('api.v1.download.community.notice.item', [
            'community'       => $community->id,
            'noticeBatch'     => $batchId,
            'noticeBatchItem' => $itemId,
        ]))
        ->assertOk();
});

it('does not chase handed-over customers when running notices', function () {
    Storage::fake('local');

    $user      = superAdminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    arrearsUnit($user->organization_id, $community, 1000, 120, ['collection_status' => CollectionStatus::HANDED_OVER->value]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.run.community.notices', ['community' => $community->id]), [])
        ->assertStatus(500); // no chaseable customers → "No overdue customers to notice."
});

it('shows the last notice batch', function () {
    Storage::fake('local');

    $user      = superAdminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    arrearsUnit($user->organization_id, $community, 2000, 120);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.run.community.notices', ['community' => $community->id]), [])
        ->assertOk();

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.notices.last-batch', ['community' => $community->id]))
        ->assertOk();

    expect($resp->json('batch.total'))->toBe(1)
        ->and($resp->json('batch.sections'))->toHaveCount(1)
        ->and($resp->json('batch.sections.0.rows'))->toHaveCount(1);
});

it('creates a credit note for a notice charge', function () {
    Storage::fake('local');

    $user      = superAdminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    arrearsUnit($user->organization_id, $community, 2000, 120);

    $run     = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.run.community.notices', ['community' => $community->id]), [])
        ->assertOk();
    $batchId = $run->json('batch.id');
    $itemId  = $run->json('batch.sections.0.rows.0.id');

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.credit.community.notice.item', [
            'community'       => $community->id,
            'noticeBatch'     => $batchId,
            'noticeBatchItem' => $itemId,
        ]), ['reason' => 'Test reversal', 'date_option' => 'today'])
        ->assertOk();

    $last = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.notices.last-batch', ['community' => $community->id]))
        ->assertOk();

    expect($last->json('batch.sections.0.rows.0.credited'))->toBeTrue();
});
