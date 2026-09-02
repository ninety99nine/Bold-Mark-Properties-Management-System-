<?php

use App\Models\Community;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Ledger;
use App\Models\Owner;
use App\Models\Unit;
use App\Services\InvoiceService;

/**
 * Helper: a unit + owner + one invoice (with `$lines` line items) billed on
 * `$period`, so the customer carries a detailed ledger. The invoice posts a
 * balanced GL batch with one Accounts-Receivable debit PER item (carrying the
 * item description), which is what the Detailed Ledger reads.
 */
function ledgerUnit(string $orgId, Community $community, string $period, array $lines): Unit
{
    $unit   = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $orgId]);
    $owner  = Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $orgId]);
    $ledger = Ledger::factory()->create(['organization_id' => $orgId]);

    $invoice = Invoice::factory()->create([
        'organization_id' => $orgId,
        'unit_id'         => $unit->id,
        'ledger_id'       => $ledger->id,
        'billed_to_type'  => 'owner',
        'billed_to_id'    => $owner->id,
        'amount'          => array_sum(array_column($lines, 'amount')),
        'status'          => 'unpaid',
        'billing_period'  => $period,
        'invoice_date'    => $period,
        'due_date'        => $period,
    ]);

    $n = 1;
    foreach ($lines as $line) {
        InvoiceItem::create([
            'invoice_id'  => $invoice->id,
            'ledger_id'   => $ledger->id,
            'description' => $line['description'],
            'quantity'    => 1,
            'amount'      => $line['amount'],
            'line_total'  => $line['amount'],
            'sort_order'  => $n++,
        ]);
    }

    // Re-post the GL batch now that the line items exist, so the customer
    // subledger carries one AR debit per item (with the item's description).
    app(InvoiceService::class)->postInvoiceLedger($invoice->fresh('items'));

    return $unit;
}

function ledgerRoute(Community $community, array $params = []): string
{
    return route('api.v1.show.community.detailed.ledger', array_merge(['community' => $community->id], $params));
}

// =============================================================================
// Auth
// =============================================================================

it('blocks unauthenticated access to the detailed ledger', function (): void {
    $community = Community::factory()->create(['organization_id' => createOrganization()->id]);

    $this->getJson(ledgerRoute($community))->assertUnauthorized();
    $this->getJson(route('api.v1.export.community.detailed.ledger', ['community' => $community->id]))->assertUnauthorized();
});

// =============================================================================
// Run
// =============================================================================

it('builds a per-customer ledger with a balance b/f, invoice line items and totals', function (): void {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    ledgerUnit($user->organization_id, $community, '2026-07-01', [
        ['description' => 'Levies',       'amount' => 1000.00],
        ['description' => 'CSOS Levies',  'amount' => 15.00],
    ]);

    $data = $this->actingAs($user, 'api')
        ->getJson(ledgerRoute($community, ['date_from' => '2026-07-01', 'date_to' => '2026-09-30', 'show_line_items' => 'true']))
        ->assertOk()
        ->json();

    expect($data['ledgers'])->toHaveCount(1);
    $rows = $data['ledgers'][0]['rows'];
    // Balance b/f + one AR-debit row per invoice line item (from the GL).
    expect($rows[0]['description'])->toBe('Balance b/f');
    // WeConnectU: Source reads "Invoice INV… (Line N)", Description carries the item.
    expect($rows[1]['description'])->toBe('Levies');
    expect($rows[1]['source'])->toStartWith('Invoice ')
        ->and($rows[1]['source'])->toContain('(Line 1)')
        ->and($rows[1]['invoice_id'])->not->toBeNull()
        ->and($rows[1]['source'])->toContain($rows[1]['invoice_number']);
    expect($rows[2]['description'])->toBe('CSOS Levies');
    expect($rows[2]['source'])->toContain('(Line 2)');
    expect(round($data['ledgers'][0]['totals']['debit'], 2))->toBe(1015.00);
    expect(round($data['ledgers'][0]['totals']['balance'], 2))->toBe(1015.00);
});

it('collapses each invoice to a single row when line items are hidden', function (): void {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    ledgerUnit($user->organization_id, $community, '2026-07-01', [
        ['description' => 'Levies',      'amount' => 1000.00],
        ['description' => 'CSOS Levies', 'amount' => 15.00],
    ]);

    $data = $this->actingAs($user, 'api')
        ->getJson(ledgerRoute($community, ['date_from' => '2026-07-01', 'date_to' => '2026-09-30']))
        ->assertOk()
        ->json();

    // Balance b/f + a single invoice row (no per-line rows).
    expect($data['ledgers'][0]['rows'])->toHaveCount(2);

    // WeConnectU: collapsed invoice shows its number (link) in the Description.
    $invRow = $data['ledgers'][0]['rows'][1];
    expect($invRow['source'])->toBe('Invoice')
        ->and($invRow['invoice_id'])->not->toBeNull()
        ->and($invRow['invoice_number'])->not->toBeNull()
        ->and($invRow['description'])->toBe($invRow['invoice_number']);
});

it('shows the cashbook bank source, an allocation tooltip and a Balance Paid marker', function (): void {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit      = ledgerUnit($user->organization_id, $community, '2026-07-01', [['description' => 'Levies', 'amount' => 500.00]]);

    $bank = \App\Models\BankAccount::factory()->create([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'bank_name'       => 'Standard Bank',
        'account_number'  => '282475699',
    ]);
    $entry = \App\Models\CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id, 'community_id' => $community->id,
        'bank_account_id' => $bank->id, 'unit_id' => null, 'invoice_id' => null,
        'type' => 'credit', 'amount' => 500, 'date' => '2026-07-15',
    ]);
    app(\App\Services\AllocationPostingService::class)->post($entry, [
        'ledger_type' => 'customer', 'unit_id' => $unit->id,
    ]);
    // Stamp the allocation metadata WeConnectU surfaces in the info tooltip.
    $entry->forceFill(['allocated_by_name' => 'Justin, K.', 'allocated_at' => '2026-07-15 09:06:39'])->save();

    $rows = $this->actingAs($user, 'api')
        ->getJson(ledgerRoute($community, ['date_from' => '2026-07-01', 'date_to' => '2026-09-30']))
        ->assertOk()
        ->json('ledgers.0.rows');

    // Cashbook receipt row: bank "STANDARD BANK: 282475699" Source + tooltip fields.
    $cashRow = collect($rows)->firstWhere('source', 'STANDARD BANK: 282475699');
    expect($cashRow)->not->toBeNull()
        ->and($cashRow['allocated_by'])->toBe('Justin, K.')
        ->and($cashRow['allocated_at'])->toBe('15/07/2026 09:06:39')
        ->and((float) $cashRow['credit'])->toBe(500.00);

    // WeConnectU "Balance-paid / Balance Paid" marker once the receipt settles it.
    $marker = collect($rows)->firstWhere('source', 'Balance-paid');
    expect($marker)->not->toBeNull()
        ->and($marker['description'])->toBe('Balance Paid')
        ->and((float) $marker['balance'])->toBe(0.00);
});

it('exposes an invoice_id on ledger rows that downloads the invoice PDF', function (): void {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    ledgerUnit($user->organization_id, $community, '2026-07-01', [['description' => 'Levies', 'amount' => 500.00]]);

    $row = $this->actingAs($user, 'api')
        ->getJson(ledgerRoute($community, ['date_from' => '2026-07-01', 'date_to' => '2026-09-30']))
        ->assertOk()
        ->json('ledgers.0.rows.1');

    expect($row['invoice_id'])->not->toBeNull();

    // The invoice number links to the branded invoice PDF (WeConnectU behaviour).
    $this->actingAs($user, 'api')
        ->get(route('api.v1.download.invoice.pdf', ['invoice' => $row['invoice_id']]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

// =============================================================================
// Export
// =============================================================================

// =============================================================================
// Recent Email Reports
// =============================================================================

it('logs an emailed report and lists it under recent reports, then re-downloads it', function (): void {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    ledgerUnit($user->organization_id, $community, '2026-07-01', [['description' => 'Levies', 'amount' => 500.00]]);

    // Request an emailed report → creates a Recent Email Reports row.
    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.email.community.detailed.ledger', ['community' => $community->id]), [
            'date_from'     => '2026-07-01',
            'date_to'       => '2026-09-30',
            'all_customers' => true,
        ])
        ->assertOk();

    $reports = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.ledger.reports', ['community' => $community->id]))
        ->assertOk()
        ->json('data');

    expect($reports)->toHaveCount(1);
    expect($reports[0]['date_range'])->toBe('2026-07-01 - 2026-09-30');
    expect($reports[0]['status'])->toBe('1 Account(s)');

    $res = $this->actingAs($user, 'api')
        ->get(route('api.v1.download.community.ledger.report', ['community' => $community->id, 'ledgerReport' => $reports[0]['id']]));
    $res->assertOk();
    expect($res->headers->get('content-disposition'))->toContain('.xlsx');
});

it('downloads the detailed ledger as an xlsx', function (): void {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    ledgerUnit($user->organization_id, $community, '2026-07-01', [['description' => 'Levies', 'amount' => 500.00]]);

    $res = $this->actingAs($user, 'api')
        ->get(route('api.v1.export.community.detailed.ledger', [
            'community'       => $community->id,
            'date_from'       => '2026-07-01',
            'date_to'         => '2026-09-30',
            'show_line_items' => 'true',
        ]));

    $res->assertOk();
    expect($res->headers->get('content-disposition'))->toContain('.xlsx');
});
