<?php

use App\Enums\FinancialCategory;
use App\Enums\JournalEntryType;
use App\Enums\JournalLineType;
use App\Enums\JournalSource;
use App\Models\Community;
use App\Models\JournalBatch;
use App\Models\Ledger;
use App\Models\Supplier;
use App\Models\SupplierInvoice;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * A user without the supplier.delete permission (registered on the api guard so
 * the check resolves to "not granted" rather than throwing).
 */
function supplierInvoiceRestrictedUser(): \App\Models\User
{
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'supplier.delete', 'guard_name' => 'api']);

    return createUser(createOrganization(), 'community-manager');
}

function siSupplier(\App\Models\User $user, Community $community): Supplier
{
    return Supplier::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'supplier_code'   => 'ACM001',
        'name'            => 'Acme Supplies',
        'balance'         => 0,
    ]);
}

function siCreatePayload(Supplier $supplier, array $overrides = []): array
{
    return array_merge([
        'supplier_id'  => $supplier->id,
        'status'       => 'created',
        'invoice_date' => '2026-06-04',
        'items'        => [
            ['account_name' => 'Maintenance', 'description' => 'Repairs', 'quantity' => 1, 'unit_price' => 1150, 'tax_rate' => 15],
        ],
    ], $overrides);
}

// ──────────────────────────────────────────────────────────────────────────────
// Unauthenticated access
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on every supplier-invoice route when unauthenticated', function (string $method, string $route) {
    $community = Community::factory()->create(['organization_id' => createOrganization()->id]);

    $params = ['community' => $community->id];
    if (str_contains($route, 'invoice') && ! str_contains($route, 'invoices')) {
        $params['supplierInvoice'] = '00000000-0000-0000-0000-000000000000';
    }

    $this->{$method . 'Json'}(route($route, $params))->assertUnauthorized();
})->with([
    ['get',    'api.v1.show.community.supplier.invoices'],
    ['post',   'api.v1.create.community.supplier.invoice'],
    ['get',    'api.v1.show.community.supplier.invoice'],
    ['put',    'api.v1.update.community.supplier.invoice'],
    ['delete', 'api.v1.delete.community.supplier.invoice'],
]);

// ──────────────────────────────────────────────────────────────────────────────
// Index
// ──────────────────────────────────────────────────────────────────────────────

it('lists supplier invoices scoped to the community + organization', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $supplier  = siSupplier($user, $community);

    SupplierInvoice::factory()->count(2)->create([
        'organization_id' => $user->organization_id,
        'community_id'     => $community->id,
        'supplier_id'      => $supplier->id,
    ]);

    // Another community's invoice must not appear.
    $otherCommunity = Community::factory()->create(['organization_id' => $user->organization_id]);
    SupplierInvoice::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'     => $otherCommunity->id,
        'supplier_id'      => $supplier->id,
    ]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.supplier.invoices', ['community' => $community->id]))
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'grv_number', 'status', 'total', 'supplier_id']], 'meta']);

    expect($resp->json('meta.total'))->toBe(2);
});

it('filters supplier invoices by status', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $supplier  = siSupplier($user, $community);

    SupplierInvoice::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'     => $community->id,
        'supplier_id'      => $supplier->id,
    ]);
    SupplierInvoice::factory()->draft()->create([
        'organization_id' => $user->organization_id,
        'community_id'     => $community->id,
        'supplier_id'      => $supplier->id,
    ]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.supplier.invoices', ['community' => $community->id, 'status' => 'draft']))
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1)
        ->and($resp->json('data.0.status'))->toBe('draft');
});

// ──────────────────────────────────────────────────────────────────────────────
// Create + GL posting
// ──────────────────────────────────────────────────────────────────────────────

it('creates a created supplier invoice and posts a balanced GL batch (Dr expense/VAT, Cr AP)', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $supplier  = siSupplier($user, $community);
    $expense   = Ledger::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Maintenance']);

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community.supplier.invoice', ['community' => $community->id]), siCreatePayload($supplier, [
            'items' => [
                ['account_name' => 'Maintenance', 'ledger_id' => $expense->id, 'quantity' => 1, 'unit_price' => 1150, 'tax_rate' => 15],
            ],
        ]))
        ->assertOk();

    $invoiceId = $resp->json('data.id');
    expect($resp->json('data.grv_number'))->toBe('GRV00001')
        ->and((float) $resp->json('data.total'))->toBe(1150.0)
        ->and((float) $resp->json('data.vat_amount'))->toBe(150.0);

    // A single supplier-invoice-source batch was posted for this invoice.
    $batch = JournalBatch::where('source', JournalSource::SUPPLIER_INVOICE->value)
        ->where('source_type', (new SupplierInvoice)->getMorphClass())
        ->where('source_id', $invoiceId)
        ->with('lines')
        ->first();

    expect($batch)->not->toBeNull()
        ->and($batch->journal_group)->toBe('Accrual');

    // The batch balances.
    $debit  = $batch->lines->where('entry_type', JournalEntryType::DEBIT)->sum('amount');
    $credit = $batch->lines->where('entry_type', JournalEntryType::CREDIT)->sum('amount');
    expect(round($debit, 2))->toBe(1150.0)
        ->and(round($credit, 2))->toBe(1150.0);

    // Dr expense (net 1000), Dr VAT control (150), Cr AP [supplier] (1150).
    $ap = Ledger::controlAccount($user->organization_id, FinancialCategory::ACCOUNTS_PAYABLE);
    $apLine = $batch->lines->firstWhere('line_type', JournalLineType::SUPPLIER);
    expect($apLine)->not->toBeNull()
        ->and($apLine->entry_type)->toBe(JournalEntryType::CREDIT)
        ->and($apLine->supplier_id)->toBe($supplier->id)
        ->and($apLine->ledger_id)->toBe($ap->id)
        ->and(round((float) $apLine->amount, 2))->toBe(1150.0);

    $expenseLine = $batch->lines->firstWhere('ledger_id', $expense->id);
    expect($expenseLine)->not->toBeNull()
        ->and($expenseLine->entry_type)->toBe(JournalEntryType::DEBIT)
        ->and(round((float) $expenseLine->amount, 2))->toBe(1000.0);

    // Supplier balance reflects the accrual (credit → negative).
    expect(round((float) $supplier->fresh()->balance, 2))->toBe(-1150.0);
});

it('does not post a GL batch for a draft supplier invoice', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $supplier  = siSupplier($user, $community);

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community.supplier.invoice', ['community' => $community->id]), siCreatePayload($supplier, [
            'status' => 'draft',
        ]))
        ->assertOk();

    $count = JournalBatch::where('source_type', (new SupplierInvoice)->getMorphClass())
        ->where('source_id', $resp->json('data.id'))
        ->count();

    expect($count)->toBe(0)
        ->and(round((float) $supplier->fresh()->balance, 2))->toBe(0.0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Show
// ──────────────────────────────────────────────────────────────────────────────

it('shows a single supplier invoice with its items', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $supplier  = siSupplier($user, $community);

    $invoice = SupplierInvoice::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'     => $community->id,
        'supplier_id'      => $supplier->id,
    ]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.supplier.invoice', ['community' => $community->id, 'supplierInvoice' => $invoice->id]))
        ->assertOk()
        ->assertJsonPath('data.id', $invoice->id)
        ->assertJsonStructure(['data' => ['id', 'grv_number', 'items']]);
});

it('returns 404 for a cross-organization supplier invoice', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $other          = createOrganization();
    $otherCommunity = Community::factory()->create(['organization_id' => $other->id]);
    $otherSupplier  = Supplier::factory()->create(['organization_id' => $other->id, 'community_id' => $otherCommunity->id]);
    $invoice = SupplierInvoice::factory()->create([
        'organization_id' => $other->id,
        'community_id'     => $otherCommunity->id,
        'supplier_id'      => $otherSupplier->id,
    ]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.supplier.invoice', ['community' => $community->id, 'supplierInvoice' => $invoice->id]))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// Update
// ──────────────────────────────────────────────────────────────────────────────

it('updates a supplier invoice and re-posts the GL batch', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $supplier  = siSupplier($user, $community);

    $create = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community.supplier.invoice', ['community' => $community->id]), siCreatePayload($supplier, [
            'items' => [['account_name' => 'X', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 0]],
        ]))
        ->assertOk();

    $invoiceId = $create->json('data.id');
    expect(round((float) $supplier->fresh()->balance, 2))->toBe(-1000.0);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.community.supplier.invoice', ['community' => $community->id, 'supplierInvoice' => $invoiceId]), [
            'items' => [['account_name' => 'X', 'quantity' => 1, 'unit_price' => 2500, 'tax_rate' => 0]],
        ])
        ->assertOk();

    // Exactly one batch remains and the balance follows the new total.
    $count = JournalBatch::where('source_id', $invoiceId)->count();
    expect($count)->toBe(1)
        ->and(round((float) $supplier->fresh()->balance, 2))->toBe(-2500.0);
});

it('reverses the GL batch when a created invoice is updated to a draft', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $supplier  = siSupplier($user, $community);

    $create = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community.supplier.invoice', ['community' => $community->id]), siCreatePayload($supplier))
        ->assertOk();

    $invoiceId = $create->json('data.id');

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.community.supplier.invoice', ['community' => $community->id, 'supplierInvoice' => $invoiceId]), [
            'status' => 'draft',
        ])
        ->assertOk();

    $count = JournalBatch::where('source_id', $invoiceId)->count();
    expect($count)->toBe(0)
        ->and(round((float) $supplier->fresh()->balance, 2))->toBe(0.0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Delete
// ──────────────────────────────────────────────────────────────────────────────

it('blocks users without permission from deleting a supplier invoice', function () {
    $user      = supplierInvoiceRestrictedUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $supplier  = Supplier::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id]);
    $invoice   = SupplierInvoice::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'     => $community->id,
        'supplier_id'      => $supplier->id,
    ]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.community.supplier.invoice', ['community' => $community->id, 'supplierInvoice' => $invoice->id]))
        ->assertForbidden();
});

it('deletes a supplier invoice, reverses its GL batch and re-derives the balance', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $supplier  = siSupplier($user, $community);

    $create = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community.supplier.invoice', ['community' => $community->id]), siCreatePayload($supplier))
        ->assertOk();

    $invoiceId = $create->json('data.id');
    expect(round((float) $supplier->fresh()->balance, 2))->toBe(-1150.0);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.community.supplier.invoice', ['community' => $community->id, 'supplierInvoice' => $invoiceId]))
        ->assertOk()
        ->assertJsonPath('deleted', true);

    $count = JournalBatch::where('source_id', $invoiceId)->count();
    expect($count)->toBe(0)
        ->and(round((float) $supplier->fresh()->balance, 2))->toBe(0.0);
});
