<?php

use App\Enums\JournalEntryType;
use App\Enums\JournalLineType;
use App\Models\Community;
use App\Models\JournalBatch;
use App\Models\JournalLine;
use App\Models\Supplier;

/**
 * Post a SUPPLIER journal line (Accounts Payable subledger) for a supplier so it
 * has a real GL-derived balance. A CREDIT increases what the community owes.
 */
function postSupplierLine(Supplier $supplier, Community $community, string $date, float $amount, JournalEntryType $type, int $batchNumber = 10, string $description = 'INV1001'): void
{
    $batch = JournalBatch::create([
        'organization_id' => $supplier->organization_id,
        'community_id'    => $community->id,
        'batch_number'    => $batchNumber,
        'journal_group'   => 'Accrual',
        'date'            => $date,
        'financial_year'  => 2026,
    ]);

    JournalLine::create([
        'organization_id'  => $supplier->organization_id,
        'journal_batch_id' => $batch->id,
        'supplier_id'      => $supplier->id,
        'line_type'        => JournalLineType::SUPPLIER,
        'entry_type'       => $type,
        'amount'           => $amount,
        'description'      => $description,
        'sort_order'       => 1,
    ]);
}

function supplierStatementsRoute(Community $community, array $params = []): string
{
    return route('api.v1.show.community.supplier.statements', array_merge(['community' => $community->id], $params));
}

// =============================================================================
// Auth
// =============================================================================

it('blocks unauthenticated access to supplier statements', function (): void {
    $community = Community::factory()->create(['organization_id' => createOrganization()->id]);

    $this->getJson(supplierStatementsRoute($community))->assertUnauthorized();
    $this->getJson(route('api.v1.view.community.supplier.statements', ['community' => $community->id]))->assertUnauthorized();
});

// =============================================================================
// Listing
// =============================================================================

it('lists suppliers with the ledger balance as at the date to', function (): void {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $supplier = Supplier::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'supplier_code'   => 'BAC001',
        'name'            => 'BA CONSTRUCTION',
        'reference'       => 'BAC-REF',
    ]);

    // Credit → we owe the supplier → negative balance (creditor sign).
    postSupplierLine($supplier, $community, '2026-06-01', 60892.50, JournalEntryType::CREDIT);

    $data = $this->actingAs($user, 'api')
        ->getJson(supplierStatementsRoute($community, ['date_to' => '2026-10-01']))
        ->assertOk()
        ->json();

    expect($data['rows'])->toHaveCount(1);
    expect($data['rows'][0]['supplier_code'])->toBe('BAC001');
    expect($data['rows'][0]['reference'])->toBe('BAC-REF');
    expect($data['rows'][0]['balance'])->toEqual(-60892.50);
    expect($data['totals']['balance'])->toEqual(-60892.50);
    expect($data['date_to'])->toBe('2026-10-01');
});

it('hides zero-balance suppliers when hide_zero is set', function (): void {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $owed = Supplier::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id]);
    postSupplierLine($owed, $community, '2026-06-01', 100.0, JournalEntryType::CREDIT);

    // A supplier with no ledger activity nets to zero.
    Supplier::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id]);

    $data = $this->actingAs($user, 'api')
        ->getJson(supplierStatementsRoute($community, ['hide_zero' => true]))
        ->assertOk()
        ->json();

    expect($data['rows'])->toHaveCount(1);
});

it('hides negative-balance suppliers when hide_negative is set', function (): void {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $owed = Supplier::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id]);
    postSupplierLine($owed, $community, '2026-06-01', 100.0, JournalEntryType::CREDIT);

    $data = $this->actingAs($user, 'api')
        ->getJson(supplierStatementsRoute($community, ['hide_negative' => true]))
        ->assertOk()
        ->json();

    expect($data['rows'])->toHaveCount(0);
});

it('filters suppliers by the search term', function (): void {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    Supplier::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id, 'supplier_code' => 'BAC001', 'name' => 'BA Construction']);
    Supplier::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id, 'supplier_code' => 'EKU002', 'name' => 'Ekurhuleni Provision']);

    $data = $this->actingAs($user, 'api')
        ->getJson(supplierStatementsRoute($community, ['_search' => 'Ekurhuleni']))
        ->assertOk()
        ->json();

    expect($data['rows'])->toHaveCount(1);
    expect($data['rows'][0]['supplier_code'])->toBe('EKU002');
});

// =============================================================================
// PDFs
// =============================================================================

it('streams the combined supplier statements summary PDF', function (): void {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $supplier  = Supplier::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id]);
    postSupplierLine($supplier, $community, '2026-06-01', 500.0, JournalEntryType::CREDIT);

    $res = $this->actingAs($user, 'api')
        ->get(route('api.v1.view.community.supplier.statements', ['community' => $community->id, 'date_to' => '2026-10-01']));

    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('application/pdf');
});

it('downloads a single supplier statement PDF', function (): void {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $supplier  = Supplier::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'supplier_code'   => 'BAC001',
        'name'            => 'BA CONSTRUCTION',
    ]);
    postSupplierLine($supplier, $community, '2026-06-01', 60892.50, JournalEntryType::CREDIT);

    $res = $this->actingAs($user, 'api')
        ->get(route('api.v1.download.community.supplier.statement', [
            'community' => $community->id,
            'supplier'  => $supplier->id,
            '_format'   => 'pdf',
            'from'      => '2026-06-01',
            'to'        => '2026-10-01',
        ]));

    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('application/pdf');
    expect($res->headers->get('content-disposition'))->toContain('SupplierStatement-BAC001.pdf');
});

// =============================================================================
// E-mail
// =============================================================================

it('e-mails a supplier statement to the supplier and reports it', function (): void {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $supplier  = Supplier::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'email'           => 'accounts@bac.co.za',
    ]);
    postSupplierLine($supplier, $community, '2026-06-01', 100.0, JournalEntryType::CREDIT);

    $data = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.email.community.supplier.statement', [
            'community' => $community->id,
            'supplier'  => $supplier->id,
        ]), ['from' => '2026-06-01', 'to' => '2026-10-01'])
        ->assertOk()
        ->json();

    expect($data['message'])->toContain('accounts@bac.co.za');
});

it('reports when the supplier has no e-mail on file', function (): void {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $supplier  = Supplier::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'email'           => null,
    ]);

    $data = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.email.community.supplier.statement', [
            'community' => $community->id,
            'supplier'  => $supplier->id,
        ]))
        ->assertOk()
        ->json();

    expect($data['message'])->toContain('No e-mail');
});
