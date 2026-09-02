<?php

use App\Models\BankAccount;
use App\Models\Community;
use App\Models\Invoice;
use App\Models\Ledger;
use App\Models\Owner;
use App\Models\Unit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// ──────────────────────────────────────────────────────────────────────────────
// Unauthenticated access
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on customer invoice + bank account routes when unauthenticated', function (string $method, string $route) {
    $this->{$method . 'Json'}(route($route))->assertUnauthorized();
})->with([
    ['post', 'api.v1.create.customer.invoice'],
    ['get',  'api.v1.show.bank.accounts'],
]);

// ──────────────────────────────────────────────────────────────────────────────
// GET /bank-accounts
// ──────────────────────────────────────────────────────────────────────────────

it('lists bank accounts scoped to the organization', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    BankAccount::factory()->count(2)->create([
        'organization_id' => $user->organization_id,
        'community_id'     => $community->id,
    ]);

    // Another organization's bank account must not appear.
    $other        = createOrganization();
    $otherCommunity = Community::factory()->create(['organization_id' => $other->id]);
    BankAccount::factory()->create([
        'organization_id' => $other->id,
        'community_id'     => $otherCommunity->id,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.bank.accounts'))
        ->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta']);

    expect($response->json('meta.total'))->toBe(2);
});

it('filters bank accounts by community', function () {
    $user  = adminUser();
    $a     = Community::factory()->create(['organization_id' => $user->organization_id]);
    $b     = Community::factory()->create(['organization_id' => $user->organization_id]);
    BankAccount::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $a->id]);
    BankAccount::factory()->count(3)->create(['organization_id' => $user->organization_id, 'community_id' => $b->id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.bank.accounts', ['community_id' => $b->id]))
        ->assertOk();

    expect($response->json('meta.total'))->toBe(3);
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /invoices/customer-invoice
// ──────────────────────────────────────────────────────────────────────────────

it('creates a multi-line customer invoice with rolled-up totals', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit      = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $owner     = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    $bank      = BankAccount::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id]);
    $ledgerA   = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $ledgerB   = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    $response = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.customer.invoice'), [
            'unit_id'         => $unit->id,
            'bank_account_id' => $bank->id,
            'invoice_date'    => '2026-08-27',
            'due_date'        => '2026-09-03',
            'items'           => [
                ['ledger_id' => $ledgerA->id, 'description' => 'Levies',      'quantity' => 2, 'tax_rate' => 0,  'amount' => 500],
                ['ledger_id' => $ledgerB->id, 'description' => 'Special Levy', 'quantity' => 1, 'tax_rate' => 15, 'amount' => 100],
            ],
            'email_invoice'   => false,
        ])
        ->assertCreated();

    $invoice = Invoice::findOrFail($response->json('data.id'));

    // subtotal = 2*500 + 1*100 = 1100 ; VAT = 100 * 15% = 15 ; total = 1115
    expect((float) $invoice->subtotal)->toBe(1100.0)
        ->and((float) $invoice->vat_amount)->toBe(15.0)
        ->and((float) $invoice->amount)->toBe(1115.0)
        ->and($invoice->billed_to_type->value)->toBe('owner')
        ->and($invoice->billed_to_id)->toBe($owner->id)
        ->and($invoice->bank_account_id)->toBe($bank->id)
        ->and($invoice->billing_period->format('Y-m-d'))->toBe('2026-08-01')
        ->and($invoice->invoice_date->format('Y-m-d'))->toBe('2026-08-27')
        ->and($invoice->items()->count())->toBe(2);

    $first = $invoice->items()->orderBy('sort_order')->first();
    expect((float) $first->line_total)->toBe(1000.0);
});

it('moves the unit balance down by the invoice total', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit      = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    $ledger    = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.customer.invoice'), [
            'unit_id'      => $unit->id,
            'invoice_date' => '2026-08-27',
            'due_date'     => '2026-09-03',
            'items'        => [['ledger_id' => $ledger->id, 'quantity' => 1, 'tax_rate' => 0, 'amount' => 750]],
        ])
        ->assertCreated();

    // balance = unallocated_credits (0) − outstanding (750)
    expect((float) $unit->fresh()->balance)->toBe(-750.0);
});

it('rejects a customer invoice with no line items', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit      = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.customer.invoice'), [
            'unit_id'      => $unit->id,
            'invoice_date' => '2026-08-27',
            'due_date'     => '2026-09-03',
            'items'        => [],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items']);
});

it('rejects a due date earlier than the invoice date', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit      = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    $ledger    = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.customer.invoice'), [
            'unit_id'      => $unit->id,
            'invoice_date' => '2026-08-27',
            'due_date'     => '2026-08-01',
            'items'        => [['ledger_id' => $ledger->id, 'quantity' => 1, 'tax_rate' => 0, 'amount' => 100]],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['due_date']);
});

it('stores an uploaded source document', function () {
    Storage::fake('public');

    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit      = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    $ledger    = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    $response = $this->actingAs($user, 'api')
        ->post(route('api.v1.create.customer.invoice'), [
            'unit_id'      => $unit->id,
            'invoice_date' => '2026-08-27',
            'due_date'     => '2026-09-03',
            'items'        => [['ledger_id' => $ledger->id, 'quantity' => 1, 'tax_rate' => 0, 'amount' => 50]],
            'attachment'   => UploadedFile::fake()->create('invoice.pdf', 40, 'application/pdf'),
        ])
        ->assertCreated();

    $path = Invoice::findOrFail($response->json('data.id'))->attachment_path;

    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

it('marks a customer invoice as source=manual', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit      = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    $ledger    = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    $id = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.customer.invoice'), [
            'unit_id'      => $unit->id,
            'invoice_date' => '2026-08-27',
            'due_date'     => '2026-09-03',
            'items'        => [['ledger_id' => $ledger->id, 'quantity' => 1, 'tax_rate' => 0, 'amount' => 100]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.source', 'manual')
        ->json('data.id');

    expect(Invoice::findOrFail($id)->source->value)->toBe('manual')
        ->and(Invoice::findOrFail($id)->ledger_id)->toBeNull();
});

it('attributes customer-invoice revenue to line-item ledgers in the summary', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit      = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    $rent      = Ledger::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Rental Income']);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.customer.invoice'), [
            'unit_id'      => $unit->id,
            'invoice_date' => '2026-08-27',
            'due_date'     => '2026-09-03',
            'items'        => [['ledger_id' => $rent->id, 'description' => 'Rent', 'quantity' => 1, 'tax_rate' => 0, 'amount' => 1200]],
        ])
        ->assertCreated();

    $summary = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices.summary'))
        ->assertOk()
        ->json();

    $rentRow = collect($summary['revenue_by_ledger'])->firstWhere('name', 'Rental Income');
    expect($rentRow)->not->toBeNull()
        ->and((float) $rentRow['total'])->toBe(1200.0);
});

it('renders a PDF for a multi-line customer invoice (null header ledger)', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit      = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    $ledger    = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    $created = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.customer.invoice'), [
            'unit_id'      => $unit->id,
            'invoice_date' => '2026-08-27',
            'due_date'     => '2026-09-03',
            'items'        => [
                ['ledger_id' => $ledger->id, 'description' => 'Consulting', 'quantity' => 3, 'tax_rate' => 15, 'amount' => 200],
            ],
        ])
        ->assertCreated()
        ->json('data.id');

    $response = $this->actingAs($user, 'api')
        ->get(route('api.v1.download.invoice.pdf', ['invoice' => $created]))
        ->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('cannot invoice a unit belonging to another organization', function () {
    $user  = adminUser();
    $other = createOrganization();
    $otherCommunity = Community::factory()->create(['organization_id' => $other->id]);
    $otherUnit      = Unit::factory()->create(['community_id' => $otherCommunity->id, 'organization_id' => $other->id]);
    Owner::factory()->create(['unit_id' => $otherUnit->id, 'organization_id' => $other->id]);
    $ledger = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.customer.invoice'), [
            'unit_id'      => $otherUnit->id,
            'invoice_date' => '2026-08-27',
            'due_date'     => '2026-09-03',
            'items'        => [['ledger_id' => $ledger->id, 'quantity' => 1, 'tax_rate' => 0, 'amount' => 100]],
        ])
        ->assertNotFound();
});
