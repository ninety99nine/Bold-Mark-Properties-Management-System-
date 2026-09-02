<?php

use App\Models\Community;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Ledger;
use App\Models\Owner;
use App\Models\Unit;

// ──────────────────────────────────────────────────────────────────────────────
// Unauthenticated access
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on credit note routes when unauthenticated', function (string $method, string $route) {
    $this->{$method . 'Json'}(route($route))->assertUnauthorized();
})->with([
    ['get',  'api.v1.show.credit.notes'],
    ['get',  'api.v1.show.creditable.invoices'],
    ['post', 'api.v1.create.credit.note'],
]);

// ──────────────────────────────────────────────────────────────────────────────
// POST /credit-notes
// ──────────────────────────────────────────────────────────────────────────────

it('creates a multi-line credit note with rolled-up totals', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit      = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $owner     = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    $ledgerA   = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $ledgerB   = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    $response = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.credit.note'), [
            'unit_id'          => $unit->id,
            'credit_note_date' => '2026-09-02',
            'reason'           => 'Overcharged levy',
            'items'            => [
                ['ledger_id' => $ledgerA->id, 'description' => 'Levies',       'quantity' => 2, 'tax_rate' => 0,  'amount' => 500],
                ['ledger_id' => $ledgerB->id, 'description' => 'Special Levy',  'quantity' => 1, 'tax_rate' => 15, 'amount' => 100],
            ],
            'email_credit_note' => false,
        ])
        ->assertCreated();

    $creditNote = CreditNote::findOrFail($response->json('data.id'));

    // subtotal = 2*500 + 1*100 = 1100 ; VAT = 100 * 15% = 15 ; total = 1115
    expect((float) $creditNote->subtotal)->toBe(1100.0)
        ->and((float) $creditNote->vat_amount)->toBe(15.0)
        ->and((float) $creditNote->amount)->toBe(1115.0)
        ->and($creditNote->reason)->toBe('Overcharged levy')
        ->and($creditNote->billed_to_type->value)->toBe('owner')
        ->and($creditNote->billed_to_id)->toBe($owner->id)
        ->and($creditNote->credit_note_date->format('Y-m-d'))->toBe('2026-09-02')
        ->and($creditNote->items()->count())->toBe(2)
        ->and($creditNote->credit_note_number)->toStartWith('CN-');

    $first = $creditNote->items()->orderBy('sort_order')->first();
    expect((float) $first->line_total)->toBe(1000.0);
});

it('credits the unit balance by the credit note total', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit      = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    $ledger    = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.credit.note'), [
            'unit_id'          => $unit->id,
            'credit_note_date' => '2026-09-02',
            'items'            => [['ledger_id' => $ledger->id, 'quantity' => 1, 'tax_rate' => 0, 'amount' => 750]],
        ])
        ->assertCreated();

    // balance = credit_notes (750) − outstanding (0)
    expect((float) $unit->fresh()->balance)->toBe(750.0);
});

it('links a credit note to a specific invoice', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit      = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    $ledger    = Ledger::factory()->create(['organization_id' => $user->organization_id]);
    $invoice   = Invoice::factory()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'ledger_id'       => $ledger->id,
    ]);

    $id = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.credit.note'), [
            'unit_id'            => $unit->id,
            'credit_note_date'   => '2026-09-02',
            'applied_invoice_id' => $invoice->id,
            'items'              => [['ledger_id' => $ledger->id, 'quantity' => 1, 'tax_rate' => 0, 'amount' => 100]],
        ])
        ->assertCreated()
        ->json('data.id');

    expect(CreditNote::findOrFail($id)->applied_invoice_id)->toBe($invoice->id);
});

it('rejects a credit note with no line items', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit      = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.credit.note'), [
            'unit_id'          => $unit->id,
            'credit_note_date' => '2026-09-02',
            'items'            => [],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items']);
});

it('cannot credit a unit belonging to another organization', function () {
    $user  = adminUser();
    $other = createOrganization();
    $otherCommunity = Community::factory()->create(['organization_id' => $other->id]);
    $otherUnit      = Unit::factory()->create(['community_id' => $otherCommunity->id, 'organization_id' => $other->id]);
    Owner::factory()->create(['unit_id' => $otherUnit->id, 'organization_id' => $other->id]);
    $ledger = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.credit.note'), [
            'unit_id'          => $otherUnit->id,
            'credit_note_date' => '2026-09-02',
            'items'            => [['ledger_id' => $ledger->id, 'quantity' => 1, 'tax_rate' => 0, 'amount' => 100]],
        ])
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /credit-notes/creditable-invoices
// ──────────────────────────────────────────────────────────────────────────────

it('returns a customer\'s invoices with normalised creditable lines', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit      = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    $ledger    = Ledger::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Levies']);
    Invoice::factory()->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'ledger_id'       => $ledger->id,
        'amount'          => 1200,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.creditable.invoices', ['unit_id' => $unit->id]))
        ->assertOk()
        ->json('data');

    expect($response)->toHaveCount(1)
        ->and($response[0]['lines'])->toHaveCount(1)
        ->and($response[0]['lines'][0]['account'])->toBe('Levies')
        ->and((float) $response[0]['lines'][0]['total'])->toBe(1200.0);
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /credit-notes + PDF
// ──────────────────────────────────────────────────────────────────────────────

it('lists credit notes scoped to the organization', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit      = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    CreditNote::factory()->count(2)->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
    ]);

    // Another organization's credit note must not appear.
    $other          = createOrganization();
    $otherCommunity = Community::factory()->create(['organization_id' => $other->id]);
    $otherUnit      = Unit::factory()->create(['community_id' => $otherCommunity->id, 'organization_id' => $other->id]);
    CreditNote::factory()->create(['organization_id' => $other->id, 'unit_id' => $otherUnit->id]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.credit.notes'))
        ->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta']);

    expect($response->json('meta.total'))->toBe(2);
});

it('renders a PDF for a credit note', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit      = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    $ledger    = Ledger::factory()->create(['organization_id' => $user->organization_id]);

    $id = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.credit.note'), [
            'unit_id'          => $unit->id,
            'credit_note_date' => '2026-09-02',
            'items'            => [['ledger_id' => $ledger->id, 'description' => 'Consulting', 'quantity' => 3, 'tax_rate' => 15, 'amount' => 200]],
        ])
        ->assertCreated()
        ->json('data.id');

    $response = $this->actingAs($user, 'api')
        ->get(route('api.v1.download.credit.note.pdf', ['creditNote' => $id]))
        ->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf');
});
