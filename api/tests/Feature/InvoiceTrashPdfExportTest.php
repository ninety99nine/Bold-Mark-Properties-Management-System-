<?php

use App\Models\ChargeType;
use App\Models\Estate;
use App\Models\Invoice;
use App\Models\Owner;
use App\Models\Unit;
use Illuminate\Support\Str;

// ──────────────────────────────────────────────────────────────────────────────
// Helper
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Create a fully-wired invoice with its dependencies.
 * Returns ['user', 'estate', 'unit', 'chargeType', 'owner', 'invoice'].
 */
function makeInvoiceForTrash(array $invoiceOverrides = []): array
{
    $user       = adminUser();
    $estate     = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $chargeType = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner      = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    $invoice    = Invoice::factory()->create(array_merge([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'charge_type_id'  => $chargeType->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
        'billing_period'  => '2026-03-01',
    ], $invoiceOverrides));

    return compact('user', 'estate', 'unit', 'chargeType', 'owner', 'invoice');
}

// ──────────────────────────────────────────────────────────────────────────────
// showDeletedInvoices — GET /invoices/deleted
// ──────────────────────────────────────────────────────────────────────────────

it('show deleted invoices returns 401 without auth', function () {
    $this->getJson(route('api.v1.show.deleted.invoices'))
        ->assertUnauthorized();
});

it('show deleted invoices returns an empty list when no invoices have been deleted', function () {
    ['user' => $user] = makeInvoiceForTrash(); // active invoice, not deleted

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.deleted.invoices'))
        ->assertOk();

    expect($response->json('data'))->toBeEmpty();
});

it('show deleted invoices returns soft-deleted invoices', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoiceForTrash();
    $invoice->delete();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.deleted.invoices'))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($invoice->id);
});

it('show deleted invoices does not include active invoices', function () {
    ['user' => $user] = makeInvoiceForTrash(); // active, not deleted

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.deleted.invoices'))
        ->assertOk();

    expect($response->json('data'))->toBeEmpty();
});

it('show deleted invoices does not include another tenants deleted invoices', function () {
    ['invoice' => $otherInvoice] = makeInvoiceForTrash();
    $otherInvoice->delete();

    $myUser = adminUser();

    $response = $this->actingAs($myUser, 'api')
        ->getJson(route('api.v1.show.deleted.invoices'))
        ->assertOk();

    expect($response->json('data'))->toBeEmpty();
});

it('show deleted invoices response has data and meta keys', function () {
    ['user' => $user] = makeInvoiceForTrash();

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.deleted.invoices'))
        ->assertOk();

    expect($response->json())->toHaveKeys(['data', 'meta']);
    expect($response->json('meta'))->toHaveKeys(['total', 'current_page', 'last_page', 'per_page']);
});

it('show deleted invoices payload includes id and invoice_number', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoiceForTrash();
    $invoice->delete();

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.deleted.invoices'))
        ->assertOk()
        ->json('data.0');

    expect($data['id'])->toBe($invoice->id);
    expect($data['invoice_number'])->toBe($invoice->invoice_number);
});

it('show deleted invoices payload includes deleted_at', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoiceForTrash();
    $invoice->delete();

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.deleted.invoices'))
        ->assertOk()
        ->json('data.0');

    expect($data)->toHaveKey('deleted_at');
    expect($data['deleted_at'])->not->toBeNull();
});

it('show deleted invoices paginates the list', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $chargeType = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner      = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    Invoice::factory()->count(5)->sequence(fn ($s) => [
        'billing_period' => now()->subMonths($s->index)->startOfMonth()->format('Y-m-d'),
    ])->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'charge_type_id'  => $chargeType->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
        'deleted_at'      => now(),
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.deleted.invoices') . '?_per_page=2')
        ->assertOk();

    expect($response->json('meta.total'))->toBe(5);
    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('meta.last_page'))->toBe(3);
});

// ──────────────────────────────────────────────────────────────────────────────
// restoreInvoice — POST /invoices/{deletedInvoice}/restore
// ──────────────────────────────────────────────────────────────────────────────

it('restore invoice returns 401 without auth', function () {
    $this->postJson(route('api.v1.restore.invoice', Str::uuid()))
        ->assertUnauthorized();
});

it('restore invoice clears deleted_at so the invoice is no longer soft-deleted', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoiceForTrash();
    $invoice->delete();
    expect($invoice->fresh()->trashed())->toBeTrue();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.restore.invoice', $invoice))
        ->assertOk();

    expect($invoice->fresh()->trashed())->toBeFalse();
    expect($invoice->fresh()->deleted_at)->toBeNull();
});

it('restore invoice returns a success message', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoiceForTrash();
    $invoice->delete();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.restore.invoice', $invoice))
        ->assertOk()
        ->assertJson(['message' => 'Invoice restored successfully']);
});

it('restored invoice re-appears in the active invoice list', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoiceForTrash();
    $invoice->delete();

    // Not in active list before restore
    $before = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices'))
        ->assertOk()
        ->json('data');
    $ids = array_column($before, 'id');
    expect($ids)->not->toContain($invoice->id);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.restore.invoice', $invoice))
        ->assertOk();

    // Now in active list
    $after = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices'))
        ->assertOk()
        ->json('data');
    $ids = array_column($after, 'id');
    expect($ids)->toContain($invoice->id);
});

it('restored invoice is removed from the trash list', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoiceForTrash();
    $invoice->delete();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.restore.invoice', $invoice))
        ->assertOk();

    $trash = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.deleted.invoices'))
        ->assertOk()
        ->json('data');
    expect($trash)->toBeEmpty();
});

it('restore invoice returns 404 for another tenants invoice', function () {
    // Other tenant creates and deletes an invoice
    ['invoice' => $otherInvoice] = makeInvoiceForTrash();
    $otherInvoice->delete();

    $myUser = adminUser();

    $this->actingAs($myUser, 'api')
        ->postJson(route('api.v1.restore.invoice', $otherInvoice))
        ->assertNotFound();
});

it('restore invoice returns 404 for a non-existent UUID', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.restore.invoice', Str::uuid()))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// forceDeleteInvoice — DELETE /invoices/{deletedInvoice}/force-delete
// ──────────────────────────────────────────────────────────────────────────────

it('force delete invoice returns 401 without auth', function () {
    $this->deleteJson(route('api.v1.force.delete.invoice', Str::uuid()))
        ->assertUnauthorized();
});

it('force delete permanently removes the invoice from the database', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoiceForTrash();
    $invoice->delete();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.force.delete.invoice', $invoice))
        ->assertOk();

    expect(Invoice::withTrashed()->find($invoice->id))->toBeNull();
});

it('force delete returns a success message', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoiceForTrash();
    $invoice->delete();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.force.delete.invoice', $invoice))
        ->assertOk()
        ->assertJson(['message' => 'Invoice permanently deleted']);
});

it('force deleted invoice is gone from the active invoice list', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoiceForTrash();
    $invoice->delete();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.force.delete.invoice', $invoice))
        ->assertOk();

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.invoices'))
        ->json('data');
    expect(array_column($data, 'id'))->not->toContain($invoice->id);
});

it('force deleted invoice is gone from the trash list', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoiceForTrash();
    $invoice->delete();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.force.delete.invoice', $invoice))
        ->assertOk();

    $trash = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.deleted.invoices'))
        ->json('data');
    expect($trash)->toBeEmpty();
});

it('force delete can also permanently remove an active invoice', function () {
    // The {deletedInvoice} binding uses withTrashed — active invoices are included
    ['user' => $user, 'invoice' => $invoice] = makeInvoiceForTrash();
    expect($invoice->trashed())->toBeFalse();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.force.delete.invoice', $invoice))
        ->assertOk();

    expect(Invoice::withTrashed()->find($invoice->id))->toBeNull();
});

it('force delete returns 404 for another tenants invoice', function () {
    ['invoice' => $otherInvoice] = makeInvoiceForTrash();
    $otherInvoice->delete();

    $myUser = adminUser();

    $this->actingAs($myUser, 'api')
        ->deleteJson(route('api.v1.force.delete.invoice', $otherInvoice))
        ->assertNotFound();
});

it('force delete returns 404 for a non-existent UUID', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.force.delete.invoice', Str::uuid()))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// downloadPdf — GET /invoices/{invoice}/download-pdf
// ──────────────────────────────────────────────────────────────────────────────

it('download pdf returns 401 without auth', function () {
    $this->getJson(route('api.v1.download.invoice.pdf', Str::uuid()))
        ->assertUnauthorized();
});

it('download pdf returns 200 with application/pdf content type', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoiceForTrash();

    $response = $this->actingAs($user, 'api')
        ->get(route('api.v1.download.invoice.pdf', $invoice))
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

it('download pdf Content-Disposition header contains the invoice number', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoiceForTrash();

    $response = $this->actingAs($user, 'api')
        ->get(route('api.v1.download.invoice.pdf', $invoice))
        ->assertOk();

    $disposition = $response->headers->get('Content-Disposition');
    expect($disposition)->toContain($invoice->invoice_number);
});

it('download pdf returns 404 for a non-existent invoice', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->get(route('api.v1.download.invoice.pdf', Str::uuid()))
        ->assertNotFound();
});

it('download pdf returns 404 for another tenants invoice', function () {
    ['invoice' => $otherInvoice] = makeInvoiceForTrash();
    $myUser = adminUser();

    $this->actingAs($myUser, 'api')
        ->get(route('api.v1.download.invoice.pdf', $otherInvoice))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// exportInvoices — GET /invoices/export
// ──────────────────────────────────────────────────────────────────────────────

it('export invoices returns 401 without auth', function () {
    $this->getJson(route('api.v1.export.invoices') . '?_format=csv')
        ->assertUnauthorized();
});

it('export invoices as csv returns 200 with text/csv content type', function () {
    ['user' => $user] = makeInvoiceForTrash();

    $response = $this->actingAs($user, 'api')
        ->get(route('api.v1.export.invoices') . '?_format=csv')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});

it('csv export contains the expected column headers', function () {
    ['user' => $user] = makeInvoiceForTrash();

    $response = $this->actingAs($user, 'api')
        ->get(route('api.v1.export.invoices') . '?_format=csv')
        ->assertOk();

    $firstLine = strtok($response->streamedContent(), "\n");
    expect($firstLine)->toContain('Invoice #');
    expect($firstLine)->toContain('Estate');
    expect($firstLine)->toContain('Unit');
    expect($firstLine)->toContain('Charge Type');
    expect($firstLine)->toContain('Amount');
    expect($firstLine)->toContain('Status');
});

it('csv export contains a data row for each invoice', function () {
    ['user' => $user, 'invoice' => $invoice] = makeInvoiceForTrash();

    $response = $this->actingAs($user, 'api')
        ->get(route('api.v1.export.invoices') . '?_format=csv')
        ->assertOk();

    $content = $response->streamedContent();
    $lines   = array_filter(explode("\n", trim($content)));
    // header + 1 data row
    expect(count($lines))->toBe(2);
    expect($content)->toContain($invoice->invoice_number);
});

it('csv export only includes the current tenants invoices', function () {
    // Other tenant invoice
    ['invoice' => $otherInvoice] = makeInvoiceForTrash();

    // My tenant
    ['user' => $myUser, 'invoice' => $myInvoice] = makeInvoiceForTrash();

    $content = $this->actingAs($myUser, 'api')
        ->get(route('api.v1.export.invoices') . '?_format=csv')
        ->assertOk()
        ->streamedContent();

    expect($content)->toContain($myInvoice->invoice_number);
    expect($content)->not->toContain($otherInvoice->invoice_number);
});

it('export invoices as xlsx returns a valid file response', function () {
    ['user' => $user] = makeInvoiceForTrash();

    $response = $this->actingAs($user, 'api')
        ->get(route('api.v1.export.invoices') . '?_format=xlsx')
        ->assertOk();

    expect($response->headers->get('Content-Type'))
        ->toContain('spreadsheetml');
});

it('export invoices returns 400 for an unsupported format', function () {
    ['user' => $user] = makeInvoiceForTrash();

    $this->actingAs($user, 'api')
        ->get(route('api.v1.export.invoices') . '?_format=docx')
        ->assertStatus(400);
});

it('_limit parameter caps the number of records exported', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $chargeType = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner      = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    // Each invoice needs a unique (unit_id, charge_type_id, billing_period) combination
    Invoice::factory()->count(5)->sequence(fn ($s) => [
        'billing_period' => now()->subMonths($s->index)->startOfMonth()->format('Y-m-d'),
    ])->create([
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'charge_type_id'  => $chargeType->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
    ]);

    $content = $this->actingAs($user, 'api')
        ->get(route('api.v1.export.invoices') . '?_format=csv&_limit=2')
        ->assertOk()
        ->streamedContent();

    $lines = array_filter(explode("\n", trim($content)));
    // header + 2 data rows
    expect(count($lines))->toBe(3);
});

it('csv export respects the status filter', function () {
    $user       = adminUser();
    $estate     = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $unit       = Unit::factory()->create(['estate_id' => $estate->id, 'organization_id' => $user->organization_id]);
    $chargeType = ChargeType::factory()->create(['organization_id' => $user->organization_id]);
    $owner      = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);

    $base = [
        'organization_id' => $user->organization_id,
        'unit_id'         => $unit->id,
        'charge_type_id'  => $chargeType->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
    ];

    // Different billing periods to avoid unique constraint (unit_id, charge_type_id, billing_period)
    $paid   = Invoice::factory()->create(array_merge($base, ['status' => 'paid',   'billing_period' => '2026-02-01']));
    $unpaid = Invoice::factory()->create(array_merge($base, ['status' => 'unpaid', 'billing_period' => '2026-03-01']));

    $content = $this->actingAs($user, 'api')
        ->get(route('api.v1.export.invoices') . '?_format=csv&status=paid')
        ->assertOk()
        ->streamedContent();

    expect($content)->toContain($paid->invoice_number);
    expect($content)->not->toContain($unpaid->invoice_number);
});

it('csv export Content-Disposition filename includes the current date', function () {
    ['user' => $user] = makeInvoiceForTrash();

    $response = $this->actingAs($user, 'api')
        ->get(route('api.v1.export.invoices') . '?_format=csv')
        ->assertOk();

    $disposition = $response->headers->get('Content-Disposition');
    expect($disposition)->toContain(now()->format('Y-m-d'));
});
