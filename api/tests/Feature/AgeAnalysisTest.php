<?php

use App\Enums\InvoiceStatus;
use App\Models\ChargeType;
use App\Models\Estate;
use App\Models\Invoice;
use App\Models\Owner;
use App\Models\Unit;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Create a minimal invoice with an owner as the billed-to party.
 * Returns ['user', 'estate', 'unit', 'owner', 'chargeType', 'invoice'].
 */
function makeAgeInvoice(array $invoiceOverrides = []): array
{
    $user       = adminUser();
    $estate     = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $chargeType = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner      = Owner::factory()->create(['organization_id' => $user->organization_id, 'unit_id' => $unit->id]);

    $invoice = Invoice::factory()->create(array_merge([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'charge_type_id'  => $chargeType->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
        'status'          => InvoiceStatus::OVERDUE->value,
        'amount'          => 1000,
    ], $invoiceOverrides));

    return compact('user', 'estate', 'unit', 'chargeType', 'owner', 'invoice');
}

// ──────────────────────────────────────────────────────────────────────────────
// GET /age-analysis
// ──────────────────────────────────────────────────────────────────────────────

it('age analysis returns 401 when unauthenticated', function () {
    $this->getJson(route('api.v1.show.age.analysis'))->assertUnauthorized();
});

it('age analysis returns owners, organizations, and summary keys', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.age.analysis'))
        ->assertOk()
        ->assertJsonStructure(['owners', 'organizations', 'summary']);
});

it('age analysis summary has all bucket and count keys', function () {
    $user = adminUser();

    $summary = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.age.analysis'))
        ->assertOk()
        ->json('summary');

    expect($summary)->toHaveKeys([
        'current', '30_days', '60_days', '90_days', '120_plus',
        'total_outstanding',
        'current_count', 'd30_count', 'd60_count', 'd90_count', 'd120_count',
        'total_count', 'people_count',
        'current_people_count', 'd30_people_count', 'd60_people_count',
        'd90_people_count', 'd120_people_count',
    ]);
});

it('age analysis excludes paid invoices', function () {
    ['user' => $user] = makeAgeInvoice(['status' => InvoiceStatus::PAID->value]);

    $result = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.age.analysis'))
        ->assertOk();

    expect($result->json('owners'))->toBeEmpty();
    expect((float) $result->json('summary.total_outstanding'))->toBe(0.0);
});

it('age analysis includes unpaid and overdue invoices', function () {
    ['user' => $user] = makeAgeInvoice(['status' => InvoiceStatus::UNPAID->value, 'amount' => 500]);

    $result = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.age.analysis'))
        ->assertOk();

    expect($result->json('owners'))->not->toBeEmpty();
    expect((float) $result->json('summary.total_outstanding'))->toBe(500.0);
});

it('age analysis places a current (not-yet-due) invoice in the current bucket', function () {
    ['user' => $user] = makeAgeInvoice([
        'due_date' => now()->addDays(10)->toDateString(),
        'status'   => InvoiceStatus::UNPAID->value,
        'amount'   => 800,
    ]);

    $summary = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.age.analysis'))
        ->assertOk()
        ->json('summary');

    expect((float) $summary['current'])->toBe(800.0);
    expect((float) $summary['30_days'])->toBe(0.0);
});

it('age analysis places an invoice 15 days overdue in the 30_days bucket', function () {
    ['user' => $user] = makeAgeInvoice([
        'due_date' => now()->subDays(15)->toDateString(),
        'amount'   => 1200,
    ]);

    $summary = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.age.analysis'))
        ->assertOk()
        ->json('summary');

    expect((float) $summary['30_days'])->toBe(1200.0);
    expect((float) $summary['60_days'])->toBe(0.0);
});

it('age analysis places an invoice 45 days overdue in the 60_days bucket', function () {
    ['user' => $user] = makeAgeInvoice([
        'due_date' => now()->subDays(45)->toDateString(),
        'amount'   => 900,
    ]);

    $summary = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.age.analysis'))
        ->assertOk()
        ->json('summary');

    expect((float) $summary['60_days'])->toBe(900.0);
    expect((float) $summary['30_days'])->toBe(0.0);
});

it('age analysis places an invoice 75 days overdue in the 90_days bucket', function () {
    ['user' => $user] = makeAgeInvoice([
        'due_date' => now()->subDays(75)->toDateString(),
        'amount'   => 750,
    ]);

    $summary = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.age.analysis'))
        ->assertOk()
        ->json('summary');

    expect((float) $summary['90_days'])->toBe(750.0);
    expect((float) $summary['60_days'])->toBe(0.0);
});

it('age analysis places an invoice 100 days overdue in the 120_plus bucket', function () {
    ['user' => $user] = makeAgeInvoice([
        'due_date' => now()->subDays(100)->toDateString(),
        'amount'   => 2000,
    ]);

    $summary = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.age.analysis'))
        ->assertOk()
        ->json('summary');

    expect((float) $summary['120_plus'])->toBe(2000.0);
    expect((float) $summary['90_days'])->toBe(0.0);
});

it('age analysis total_outstanding sums across all buckets', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $chargeType = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner      = Owner::factory()->create(['organization_id' => $user->organization_id, 'unit_id' => $unit->id]);

    $base = [
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'charge_type_id'  => $chargeType->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
        'status'          => InvoiceStatus::OVERDUE->value,
    ];

    Invoice::factory()->create(array_merge($base, ['amount' => 100, 'due_date' => now()->addDays(5)->toDateString(),  'status' => InvoiceStatus::UNPAID->value,  'billing_period' => now()->startOfMonth()->toDateString()]));
    Invoice::factory()->create(array_merge($base, ['amount' => 200, 'due_date' => now()->subDays(15)->toDateString(), 'billing_period' => now()->subMonths(1)->startOfMonth()->toDateString()]));
    Invoice::factory()->create(array_merge($base, ['amount' => 300, 'due_date' => now()->subDays(45)->toDateString(), 'billing_period' => now()->subMonths(2)->startOfMonth()->toDateString()]));

    $total = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.age.analysis'))
        ->assertOk()
        ->json('summary.total_outstanding');

    expect((float) $total)->toBe(600.0);
});

it('age analysis does not include another tenants invoices', function () {
    makeAgeInvoice(['amount' => 5000]);

    $myUser = adminUser();

    $result = $this->actingAs($myUser, 'api')
        ->getJson(route('api.v1.show.age.analysis'))
        ->assertOk();

    expect($result->json('owners'))->toBeEmpty();
    expect((float) $result->json('summary.total_outstanding'))->toBe(0.0);
});

it('age analysis each owner row has expected fields', function () {
    ['user' => $user] = makeAgeInvoice(['due_date' => now()->subDays(15)->toDateString()]);

    $row = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.age.analysis'))
        ->assertOk()
        ->json('owners.0');

    expect($row)->toHaveKeys([
        'invoice_id', 'invoice_number', 'unit_id', 'unit_number',
        'charge_type', 'due_date', 'person_name', 'outstanding',
        'current', '30_days', '60_days', '90_days', '120_plus',
    ]);
});

it('age analysis filters by estate_id', function () {
    $user    = adminUser();
    $estate1 = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $estate2 = Estate::factory()->create(['organization_id' => $user->organization_id]);

    $unit1 = Unit::factory()->create(['estate_id' => $estate1->id, 'organization_id' => $user->organization_id]);
    $unit2 = Unit::factory()->create(['estate_id' => $estate2->id, 'organization_id' => $user->organization_id]);
    $ct    = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $own1  = Owner::factory()->create(['organization_id' => $user->organization_id, 'unit_id' => $unit1->id]);
    $own2  = Owner::factory()->create(['organization_id' => $user->organization_id, 'unit_id' => $unit2->id]);

    Invoice::factory()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit1->id,
        'charge_type_id'  => $ct->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $own1->id,
        'status'          => InvoiceStatus::OVERDUE->value,
        'amount'          => 500,
    ]);
    Invoice::factory()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit2->id,
        'charge_type_id'  => $ct->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $own2->id,
        'status'          => InvoiceStatus::OVERDUE->value,
        'amount'          => 900,
    ]);

    $total = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.age.analysis') . '?estate_id=' . $estate1->id)
        ->assertOk()
        ->json('summary.total_outstanding');

    expect((float) $total)->toBe(500.0);
});

it('age analysis filters by charge_type_id', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit   = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $ct1    = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $ct2    = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner  = Owner::factory()->create(['organization_id' => $user->organization_id, 'unit_id' => $unit->id]);

    Invoice::factory()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'charge_type_id'  => $ct1->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
        'status'          => InvoiceStatus::OVERDUE->value,
        'amount'          => 400,
    ]);
    Invoice::factory()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'charge_type_id'  => $ct2->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
        'status'          => InvoiceStatus::OVERDUE->value,
        'amount'          => 800,
        'billing_period'  => now()->subMonths(2)->startOfMonth()->toDateString(),
    ]);

    $total = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.age.analysis') . '?charge_type_id=' . $ct1->id)
        ->assertOk()
        ->json('summary.total_outstanding');

    expect((float) $total)->toBe(400.0);
});

it('age analysis owners are sorted by outstanding descending', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit1  = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $unit2  = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $ct     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $own1   = Owner::factory()->create(['organization_id' => $user->organization_id, 'unit_id' => $unit1->id]);
    $own2   = Owner::factory()->create(['organization_id' => $user->organization_id, 'unit_id' => $unit2->id]);

    Invoice::factory()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit1->id,
        'charge_type_id'  => $ct->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $own1->id,
        'status'          => InvoiceStatus::OVERDUE->value,
        'amount'          => 500,
        'due_date'        => now()->subDays(15)->toDateString(),
    ]);
    Invoice::factory()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit2->id,
        'charge_type_id'  => $ct->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $own2->id,
        'status'          => InvoiceStatus::OVERDUE->value,
        'amount'          => 2000,
        'due_date'        => now()->subDays(20)->toDateString(),
    ]);

    $rows = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.age.analysis'))
        ->assertOk()
        ->json('owners');

    expect((float) $rows[0]['outstanding'])->toBeGreaterThan((float) $rows[1]['outstanding']);
});

it('age analysis d30_count increments per overdue invoice in that bucket', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit1  = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $unit2  = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $ct     = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $own1   = Owner::factory()->create(['organization_id' => $user->organization_id, 'unit_id' => $unit1->id]);
    $own2   = Owner::factory()->create(['organization_id' => $user->organization_id, 'unit_id' => $unit2->id]);

    Invoice::factory()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit1->id,
        'charge_type_id'  => $ct->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $own1->id,
        'status'          => InvoiceStatus::OVERDUE->value,
        'amount'          => 100,
        'due_date'        => now()->subDays(10)->toDateString(),
    ]);
    Invoice::factory()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit2->id,
        'charge_type_id'  => $ct->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $own2->id,
        'status'          => InvoiceStatus::OVERDUE->value,
        'amount'          => 200,
        'due_date'        => now()->subDays(20)->toDateString(),
    ]);

    $count = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.age.analysis'))
        ->assertOk()
        ->json('summary.d30_count');

    expect($count)->toBe(2);
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /age-analysis/export
// ──────────────────────────────────────────────────────────────────────────────

it('age analysis export returns 401 when unauthenticated', function () {
    $this->getJson(route('api.v1.export.age.analysis'))->assertUnauthorized();
});

it('age analysis export returns a CSV file response', function () {
    ['user' => $user] = makeAgeInvoice(['due_date' => now()->subDays(15)->toDateString()]);

    $response = $this->actingAs($user, 'api')
        ->get(route('api.v1.export.age.analysis') . '?_format=csv')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});

it('age analysis export CSV contains the expected headings', function () {
    ['user' => $user] = makeAgeInvoice(['due_date' => now()->subDays(15)->toDateString()]);

    $content = $this->actingAs($user, 'api')
        ->get(route('api.v1.export.age.analysis') . '?_format=csv')
        ->assertOk()
        ->streamedContent();

    expect($content)->toContain('Current');
    expect($content)->toContain('30 Days');
    expect($content)->toContain('Total Outstanding');
});
