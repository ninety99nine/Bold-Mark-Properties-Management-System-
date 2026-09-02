<?php

use App\Enums\CashbookEntryType;
use App\Enums\JournalEntryType;
use App\Enums\JournalLineType;
use App\Models\BankAccount;
use App\Models\CashbookEntry;
use App\Models\Community;
use App\Models\JournalBatch;
use App\Models\JournalLine;
use App\Models\Supplier;

function supplierLedgerRoute(Community $community, array $params = []): string
{
    return route('api.v1.show.community.detailed.supplier.ledger', array_merge(['community' => $community->id], $params));
}

// =============================================================================
// Auth
// =============================================================================

it('blocks unauthenticated access to the detailed supplier ledger', function (): void {
    $community = Community::factory()->create(['organization_id' => createOrganization()->id]);

    $this->getJson(supplierLedgerRoute($community))->assertUnauthorized();
    $this->getJson(route('api.v1.export.community.detailed.supplier.ledger', ['community' => $community->id]))->assertUnauthorized();
});

// =============================================================================
// Run
// =============================================================================

it('opens each supplier with a credit balance b/f from the take-on balance', function (): void {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    // Negative stored balance = we owe the supplier (creditor).
    Supplier::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'supplier_code'   => 'BAC001',
        'name'            => 'BA CONSTRUCTION',
        'balance'         => -60892.50,
    ]);

    $data = $this->actingAs($user, 'api')
        ->getJson(supplierLedgerRoute($community, ['date_from' => '2026-07-01', 'date_to' => '2026-09-30', 'all_suppliers' => true]))
        ->assertOk()
        ->json();

    expect($data['ledgers'])->toHaveCount(1);
    $ledger = $data['ledgers'][0];
    expect($ledger['heading'])->toBe('BAC001 - BA CONSTRUCTION');

    $bf = $ledger['rows'][0];
    expect($bf['description'])->toBe('Balance b/f');
    expect($bf['credit'])->toEqual(60892.50);
    expect($bf['debit'])->toEqual(0.0);
    expect($bf['balance'])->toEqual(-60892.50);
    expect($ledger['totals']['balance'])->toEqual(-60892.50);
});

it('lists a supplier invoice (journal credit) and a cashbook payment (debit) with a running balance', function (): void {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    $supplier = Supplier::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'supplier_code'   => 'BOL002',
        'name'            => 'Bold Mark Properties',
        'balance'         => -15445.70,
    ]);

    $bank = BankAccount::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'bank_name'       => 'Standard Bank',
        'account_number'  => '401794555',
    ]);

    // Supplier invoice via a journal line (credit → increases what we owe).
    $batch = JournalBatch::create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'batch_number'    => 44,
        'journal_group'   => 'Accrual',
        'date'            => '2026-07-20',
        'financial_year'  => 2026,
    ]);
    JournalLine::create([
        'organization_id'  => $user->organization_id,
        'journal_batch_id' => $batch->id,
        'supplier_id'      => $supplier->id,
        'line_type'        => JournalLineType::SUPPLIER,
        'entry_type'       => JournalEntryType::CREDIT,
        'amount'           => 3846.30,
        'description'      => 'INV1275PAY',
        'sort_order'       => 1,
    ]);

    // Payment via a cashbook entry allocated to the supplier (debit → reduces
    // owed). The factory posts the GL batch (Dr Accounts Payable [supplier] / Cr
    // Bank, source = cashbook), so it reads back as a supplier debit.
    CashbookEntry::factory()->create([
        'organization_id'        => $user->organization_id,
        'community_id'           => $community->id,
        'bank_account_id'        => $bank->id,
        'supplier_id'            => $supplier->id,
        'allocation_ledger_type' => JournalLineType::SUPPLIER->value,
        'type'                   => CashbookEntryType::DEBIT->value,
        'amount'                 => 3846.30,
        'date'                   => '2026-08-01',
        'description'            => 'ELECTRONIC BANKING PAYMENT TO INV1258PAY-BARN',
    ]);

    $ledger = $this->actingAs($user, 'api')
        ->getJson(supplierLedgerRoute($community, ['date_from' => '2026-07-01', 'date_to' => '2026-09-30', 'all_suppliers' => true]))
        ->assertOk()
        ->json('ledgers.0');

    // b/f, invoice, payment.
    expect($ledger['rows'])->toHaveCount(3);

    expect($ledger['rows'][0]['balance'])->toEqual(-15445.70);

    expect($ledger['rows'][1]['source'])->toBe('Journal Batch 44');
    expect($ledger['rows'][1]['credit'])->toEqual(3846.30);
    expect($ledger['rows'][1]['balance'])->toEqual(-19292.00);

    expect($ledger['rows'][2]['source'])->toBe('STANDARD BANK: 401794555');
    expect($ledger['rows'][2]['debit'])->toEqual(3846.30);
    expect($ledger['rows'][2]['balance'])->toEqual(-15445.70);

    expect($ledger['totals']['debit'])->toEqual(3846.30);
    expect($ledger['totals']['credit'])->toEqual(3846.30);
    expect($ledger['totals']['balance'])->toEqual(-15445.70);
});

it('hides zero-balance suppliers when hide_zero is set', function (): void {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    Supplier::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'balance'         => 0.0,
    ]);
    Supplier::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'balance'         => -100.0,
    ]);

    $data = $this->actingAs($user, 'api')
        ->getJson(supplierLedgerRoute($community, ['all_suppliers' => true, 'hide_zero' => true]))
        ->assertOk()
        ->json();

    expect($data['ledgers'])->toHaveCount(1);
});

// =============================================================================
// Export
// =============================================================================

it('downloads the detailed supplier ledger as an xlsx', function (): void {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    Supplier::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'balance'         => -500.0,
    ]);

    $res = $this->actingAs($user, 'api')
        ->get(route('api.v1.export.community.detailed.supplier.ledger', [
            'community'     => $community->id,
            'date_from'     => '2026-07-01',
            'date_to'       => '2026-09-30',
            'all_suppliers' => true,
        ]));

    $res->assertOk();
    expect($res->headers->get('content-disposition'))->toContain('.xlsx');
});
