<?php

use App\Enums\CashbookEntryType;
use App\Enums\JournalLineType;
use App\Enums\SupplierInvoiceStatus;
use App\Models\BankAccount;
use App\Models\CashbookEntry;
use App\Models\Community;
use App\Models\Supplier;
use App\Models\SupplierInvoice;

/**
 * Create a supplier for the community/org.
 */
function agSupplier(string $orgId, Community $community, string $code, string $name): Supplier
{
    return Supplier::factory()->create([
        'organization_id' => $orgId,
        'community_id'    => $community->id,
        'supplier_code'   => $code,
        'name'            => $name,
        'balance'         => 0,
    ]);
}

/**
 * A supplier-ledger DEBIT — a bank payment allocated to the supplier. Posts a GL
 * batch (Dr Accounts Payable [supplier] / Cr Bank) via the cashbook posting path.
 */
function agDebit(string $orgId, Community $community, Supplier $s, float $amount, string $date): void
{
    $bank = BankAccount::factory()->create([
        'organization_id' => $orgId,
        'community_id'    => $community->id,
    ]);

    CashbookEntry::factory()->create([
        'organization_id'        => $orgId,
        'community_id'           => $community->id,
        'bank_account_id'        => $bank->id,
        'supplier_id'            => $s->id,
        'allocation_ledger_type' => JournalLineType::SUPPLIER->value,
        'type'                   => CashbookEntryType::DEBIT->value,
        'amount'                 => $amount,
        'date'                   => $date,
    ]);
}

/** A supplier-ledger CREDIT (supplier invoice / GRV) — posts Cr Accounts Payable. */
function agGrv(string $orgId, Community $community, Supplier $s, float $amount, string $date, int $seq): SupplierInvoice
{
    return SupplierInvoice::factory()->create([
        'grv_number'      => 'GRV' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT),
        'status'          => SupplierInvoiceStatus::CREATED->value,
        'type'            => 'adhoc',
        'invoice_date'    => $date,
        'subtotal'        => $amount,
        'discount'        => 0,
        'vat_amount'      => 0,
        'total'           => $amount,
        'supplier_id'     => $s->id,
        'community_id'    => $community->id,
        'organization_id' => $orgId,
    ]);
}

function supplierAgeRoute(Community $community, array $params = []): string
{
    return route('api.v1.show.community.supplier.age.analysis', array_merge(['community' => $community->id], $params));
}

// ──────────────────────────────────────────────────────────────────────────────
// Auth
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on supplier age analysis when unauthenticated', function () {
    $community = Community::factory()->create(['organization_id' => createOrganization()->id]);

    $this->getJson(supplierAgeRoute($community))->assertUnauthorized();
});

// ──────────────────────────────────────────────────────────────────────────────
// Ageing math — WeConnectU BOD001 (Bodacious Energy) reproduced exactly
// ──────────────────────────────────────────────────────────────────────────────

it('ages a mixed supplier ledger with FIFO netting exactly like WeConnectU (BOD001)', function () {
    $user      = adminUser();
    $org       = $user->organization_id;
    $community = Community::factory()->create(['organization_id' => $org]);

    $s = agSupplier($org, $community, 'BOD001', 'Bodacious Energy');

    // Debits = bank payments to the supplier.
    agDebit($org, $community, $s, 1272.47, '2025-12-03');
    agDebit($org, $community, $s, 650.00,  '2025-12-22');
    agDebit($org, $community, $s, 1272.47, '2026-03-20');
    agDebit($org, $community, $s, 1272.47, '2026-04-07');
    agDebit($org, $community, $s, 1272.47, '2026-06-09');
    agDebit($org, $community, $s, 1272.47, '2026-06-09');
    agDebit($org, $community, $s, 1272.47, '2026-06-09');
    // Credits = supplier invoices (GRVs).
    agGrv($org, $community, $s, 1272.47, '2025-12-31', 5);
    agGrv($org, $community, $s, 1272.47, '2026-05-13', 30);
    agGrv($org, $community, $s, 1272.47, '2026-05-27', 31);

    $resp = $this->actingAs($user, 'api')
        ->getJson(supplierAgeRoute($community, ['ageing_date' => '2026-09-30', 'hide_zero' => 1]))
        ->assertOk();

    $row = collect($resp->json('rows'))->firstWhere('supplier_code', 'BOD001');

    expect((float) $row['120_plus'])->toBe(650.00)
        ->and((float) $row['90_days'])->toBe(3817.41)
        ->and((float) $row['60_days'])->toBe(0.0)
        ->and((float) $row['30_days'])->toBe(0.0)
        ->and((float) $row['current'])->toBe(0.0)
        ->and((float) $row['balance'])->toBe(4467.41);

    expect((float) $resp->json('totals.balance'))->toBe(4467.41);
});

it('shows a credit-heavy supplier as a negative balance in the oldest bucket', function () {
    $user      = adminUser();
    $org       = $user->organization_id;
    $community = Community::factory()->create(['organization_id' => $org]);

    $s = agSupplier($org, $community, 'BAC001', 'BA Construction');
    agGrv($org, $community, $s, 60892.50, '2025-11-17', 1);

    $resp = $this->actingAs($user, 'api')
        ->getJson(supplierAgeRoute($community, ['ageing_date' => '2026-09-30']))
        ->assertOk();

    $row = collect($resp->json('rows'))->firstWhere('supplier_code', 'BAC001');

    expect((float) $row['120_plus'])->toBe(-60892.50)
        ->and((float) $row['balance'])->toBe(-60892.50);
});

it('hides zero and negative balances when requested', function () {
    $user      = adminUser();
    $org       = $user->organization_id;
    $community = Community::factory()->create(['organization_id' => $org]);

    $neg = agSupplier($org, $community, 'NEG001', 'Owed Supplier');
    agGrv($org, $community, $neg, 5000, '2026-01-01', 2);

    $resp = $this->actingAs($user, 'api')
        ->getJson(supplierAgeRoute($community, ['ageing_date' => '2026-09-30', 'hide_negative' => 1]))
        ->assertOk();

    expect(collect($resp->json('rows'))->firstWhere('supplier_code', 'NEG001'))->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// Detailed-ledger drill-down
// ──────────────────────────────────────────────────────────────────────────────

it('returns a per-supplier detailed ledger with a running cumulative', function () {
    $user      = adminUser();
    $org       = $user->organization_id;
    $community = Community::factory()->create(['organization_id' => $org]);

    $s = agSupplier($org, $community, 'BOD001', 'Bodacious Energy');
    agDebit($org, $community, $s, 1272.47, '2025-12-03');
    agGrv($org, $community, $s, 1272.47, '2025-12-31', 5);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.supplier.age.analysis.ledger', [
            'community' => $community->id,
            'supplier'  => $s->id,
            'ageing_date' => '2026-09-30',
        ]))
        ->assertOk();

    expect((float) $resp->json('ledger.totals.debit'))->toBe(1272.47)
        ->and((float) $resp->json('ledger.totals.credit'))->toBe(1272.47)
        ->and((float) $resp->json('ledger.totals.cumulative'))->toBe(0.0)
        ->and($resp->json('ledger.rows'))->toHaveCount(2);
});

// ──────────────────────────────────────────────────────────────────────────────
// GRV (supplier invoice) create + posting
// ──────────────────────────────────────────────────────────────────────────────

it('creates a GRV, posts it to the supplier ledger and ages it', function () {
    $user      = adminUser();
    $org       = $user->organization_id;
    $community = Community::factory()->create(['organization_id' => $org]);

    $s = agSupplier($org, $community, 'SPM001', 'SPM Renovations');

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community.supplier.invoice', ['community' => $community->id]), [
            'supplier_id'  => $s->id,
            'status'       => 'created',
            'invoice_date' => '2026-06-04',
            'supplier_reference' => 'INV25',
            'items' => [
                ['account_name' => 'Sewerage & Plumbing', 'description' => 'Repairs', 'quantity' => 1, 'unit_price' => 10800],
            ],
        ])
        ->assertOk();

    expect($resp->json('data.grv_number'))->toBe('GRV00001')
        ->and((float) $resp->json('data.total'))->toBe(10800.0);

    // Supplier balance re-posted as a credit → negative.
    expect(round((float) $s->fresh()->balance, 2))->toBe(-10800.0);

    // Appears in the age analysis as a credit for that supplier.
    $age = $this->actingAs($user, 'api')
        ->getJson(supplierAgeRoute($community, ['ageing_date' => '2026-09-30']))
        ->assertOk();

    $row = collect($age->json('rows'))->firstWhere('supplier_code', 'SPM001');
    expect((float) $row['balance'])->toBe(-10800.0);
});

it('saves a GRV as a draft without posting to the ledger', function () {
    $user      = adminUser();
    $org       = $user->organization_id;
    $community = Community::factory()->create(['organization_id' => $org]);

    $s = agSupplier($org, $community, 'SPM001', 'SPM Renovations');

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community.supplier.invoice', ['community' => $community->id]), [
            'supplier_id'  => $s->id,
            'status'       => 'draft',
            'invoice_date' => '2026-06-04',
            'items' => [['account_name' => 'X', 'quantity' => 1, 'unit_price' => 500]],
        ])
        ->assertOk();

    // Draft does not post to the supplier balance.
    expect(round((float) $s->fresh()->balance, 2))->toBe(0.0);

    // Draft is excluded from the aged ledger (only "created" GRVs post).
    $age = $this->actingAs($user, 'api')
        ->getJson(supplierAgeRoute($community, ['ageing_date' => '2026-09-30']))
        ->assertOk();

    expect(collect($age->json('rows'))->firstWhere('supplier_code', 'SPM001'))->toBeNull();
});
