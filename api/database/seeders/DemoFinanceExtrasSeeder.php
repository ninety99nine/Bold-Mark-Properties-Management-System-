<?php

namespace Database\Seeders;

use App\Enums\SupplierAccountType;
use App\Enums\SupplierInvoiceStatus;
use App\Enums\SupplierInvoiceType;
use App\Enums\SupplierPaymentType;
use App\Enums\SupplierStatus;
use App\Enums\SupplierType;
use App\Models\Community;
use App\Models\Ledger;
use App\Models\Supplier;
use App\Models\SupplierGroup;
use App\Models\SupplierInvoice;
use App\Models\Unit;
use App\Services\SupplierInvoiceService;
use App\Services\SupplierLedgerService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Finance demo extras — populates data the finance pages need but the base
 * DemoSeeder leaves empty:
 *
 *   1. Unit PQ columns (section / unit_size / pq / ratio_1) for every unit.
 *   2. 10–15 realistic property-management suppliers per community.
 *   3. 2–4 supplier groups per community (suppliers assigned to a group).
 *   4. Supplier invoices (GRVs, status = created) for the last 3 months, posted
 *      to the General Ledger (Dr expense/VAT, Cr Accounts Payable).
 *
 * Every step is idempotent (updateOrCreate on stable keys / count checks), so
 * this seeder can be re-run against an already-seeded database without creating
 * duplicates.
 */
class DemoFinanceExtrasSeeder extends Seeder
{
    /** Rotating pool of realistic floor areas (m²) for units. */
    private const UNIT_SIZE_POOL = [35, 40, 43, 52, 57, 63, 73, 96, 110, 134];

    /** Supplier definitions: name => [type, group, expense ledger codes]. */
    private const SUPPLIERS = [
        ['name' => 'Access & Perimeter Security',      'type' => SupplierType::PRIVATE_COMPANY,    'group' => 'Security & Access',     'codes' => ['2000/015', '2200/008']],
        ['name' => 'AquaClean Pool Services',           'type' => SupplierType::CLOSED_CORPORATION, 'group' => 'Maintenance',           'codes' => ['2200/012']],
        ['name' => 'Sparkle Cleaning Services',         'type' => SupplierType::CLOSED_CORPORATION, 'group' => 'Maintenance',           'codes' => ['2000/003']],
        ['name' => 'RapidFix Plumbing',                 'type' => SupplierType::CLOSED_CORPORATION, 'group' => 'Maintenance',           'codes' => ['2200/003']],
        ['name' => 'Volt Electrical',                   'type' => SupplierType::PRIVATE_COMPANY,    'group' => 'Maintenance',           'codes' => ['2200/005']],
        ['name' => 'GreenScape Gardening',              'type' => SupplierType::CLOSED_CORPORATION, 'group' => 'Maintenance',           'codes' => ['2200/009']],
        ['name' => 'FireGuard Fire Services',           'type' => SupplierType::PRIVATE_COMPANY,    'group' => 'Security & Access',     'codes' => ['2200/001']],
        ['name' => 'LiftTech Elevator Maintenance',     'type' => SupplierType::PRIVATE_COMPANY,    'group' => 'Maintenance',           'codes' => ['2200/013']],
        ['name' => 'WasteAway Refuse',                  'type' => SupplierType::PRIVATE_COMPANY,    'group' => 'Utilities',             'codes' => ['2100/002']],
        ['name' => 'SureCover Insurance Brokers',       'type' => SupplierType::PRIVATE_COMPANY,    'group' => 'Professional Services', 'codes' => ['2000/016']],
        ['name' => 'Ledger & Co Auditors',              'type' => SupplierType::INCORPORATED,       'group' => 'Professional Services', 'codes' => ['2000/011']],
        ['name' => 'MeterRead Utilities',               'type' => SupplierType::PRIVATE_COMPANY,    'group' => 'Utilities',             'codes' => ['2100/001', '2100/004']],
        ['name' => 'CamWatch Surveillance',             'type' => SupplierType::CLOSED_CORPORATION, 'group' => 'Security & Access',     'codes' => ['2200/011']],
        ['name' => 'GateGuard Intercom Systems',        'type' => SupplierType::CLOSED_CORPORATION, 'group' => 'Maintenance',           'codes' => ['2200/004']],
        ['name' => 'PrimeLaw Attorneys',                'type' => SupplierType::INCORPORATED,       'group' => 'Professional Services', 'codes' => ['2000/007']],
    ];

    /** Supplier group names created per community. */
    private const GROUPS = ['Utilities', 'Maintenance', 'Security & Access', 'Professional Services'];

    /** The three months (relative to "today" 2026-09-02) invoices span. */
    private const INVOICE_MONTHS = ['2026-06', '2026-07', '2026-08'];

    /**
     * Run the finance-extras seed for every community.
     *
     * @return void
     */
    public function run(): void
    {
        $communities = Community::all();

        foreach ($communities as $community) {
            $orgId = $community->organization_id;

            $this->command?->info("Finance extras → {$community->name}");

            $this->seedUnitPq($community);
            $groups    = $this->seedSupplierGroups($community, $orgId);
            $suppliers = $this->seedSuppliers($community, $orgId, $groups);
            $this->seedSupplierInvoices($community, $orgId, $suppliers);
        }
    }

    /**
     * 1. Populate unit PQ columns: section, unit_size, then derive ratio_1 / pq
     * from each unit's share of the community's total floor area (Σ pq ≈ 100).
     *
     * @param Community $community
     * @return void
     */
    private function seedUnitPq(Community $community): void
    {
        $units = Unit::where('community_id', $community->id)
            ->orderBy('unit_number')
            ->get();

        if ($units->isEmpty()) {
            return;
        }

        // Assign a rotating, non-zero floor area + a sequential section number.
        $index = 0;
        foreach ($units as $unit) {
            $size    = self::UNIT_SIZE_POOL[$index % count(self::UNIT_SIZE_POOL)];
            $section = (string) ($index + 1);

            $unit->forceFill([
                'unit_size' => $size,
                'section'   => $section,
            ])->save();

            $index++;
        }

        // Derive the participation quota from the total floor area.
        $total = (float) $units->sum('unit_size');

        if ($total <= 0) {
            return;
        }

        foreach ($units as $unit) {
            $share = (float) $unit->unit_size / $total * 100;

            $unit->forceFill([
                'ratio_1' => round($share, 10),
                'pq'      => round($share, 4),
            ])->save();
        }
    }

    /**
     * 3. Create the supplier groups for a community (idempotent on name).
     *
     * @param Community $community
     * @param string $orgId
     * @return array<string,SupplierGroup> group name => model
     */
    private function seedSupplierGroups(Community $community, string $orgId): array
    {
        $groups = [];

        foreach (self::GROUPS as $name) {
            $groups[$name] = SupplierGroup::updateOrCreate(
                ['community_id' => $community->id, 'name' => $name],
                ['organization_id' => $orgId],
            );
        }

        return $groups;
    }

    /**
     * 2. Create the property-management suppliers for a community and assign each
     * to its group. Idempotent on (organization_id, community_id, name).
     *
     * @param Community $community
     * @param string $orgId
     * @param array<string,SupplierGroup> $groups
     * @return \Illuminate\Support\Collection<int,Supplier>
     */
    private function seedSuppliers(Community $community, string $orgId, array $groups)
    {
        $created = collect();

        foreach (self::SUPPLIERS as $def) {
            $group = $groups[$def['group']] ?? null;

            $supplier = Supplier::updateOrCreate(
                [
                    'organization_id' => $orgId,
                    'community_id'    => $community->id,
                    'name'            => $def['name'],
                ],
                [
                    'supplier_code'     => $this->supplierCode($def['name'], $orgId, $community->id),
                    'supplier_type'     => $def['type']->value,
                    'status'            => SupplierStatus::VERIFIED->value,
                    'payment_type'      => SupplierPaymentType::EFT->value,
                    'supplier_group_id' => $group?->id,
                    'email'             => Str::slug($def['name']) . '@example.co.za',
                    'phone'             => '0' . rand(11, 87) . ' ' . rand(200, 999) . ' ' . str_pad((string) rand(0, 9999), 4, '0', STR_PAD_LEFT),
                    'bank_name'         => 'Standard Bank',
                    'account_type'      => SupplierAccountType::CURRENT->value,
                    'account_number'    => (string) rand(100000000, 999999999),
                    'branch_code'       => '051001',
                    'branch_name'       => 'Commercial',
                    'address_line_1'    => rand(1, 199) . ' ' . ['Main', 'Oak', 'Church', 'Rivonia', 'West', 'Long', 'Kloof', 'Marshall', 'Jan Smuts', 'Grayston'][rand(0, 9)] . ' ' . ['Street', 'Road', 'Avenue', 'Drive'][rand(0, 3)],
                    'suburb'            => ['Rosebank', 'Sandton', 'Morningside', 'Rivonia', 'Fourways', 'Bryanston', 'Sea Point', 'Umhlanga', 'Gaborone West'][rand(0, 8)],
                    'town'              => ['Johannesburg', 'Pretoria', 'Cape Town', 'Durban', 'Gaborone', 'Midrand', 'Centurion'][rand(0, 6)],
                    'postal_code'       => (string) rand(1000, 9999),
                    'is_active'         => true,
                ],
            );

            $created->push($supplier);
        }

        return $created;
    }

    /**
     * 4. Create ~3–6 supplier invoices per month (2026-06/07/08) across the
     * community's suppliers, referencing real expense ledgers, posted to the GL
     * (Dr expense, Cr Accounts Payable). Idempotent via a stable our_reference.
     *
     * @param Community $community
     * @param string $orgId
     * @param \Illuminate\Support\Collection<int,Supplier> $suppliers
     * @return void
     */
    private function seedSupplierInvoices(Community $community, string $orgId, $suppliers): void
    {
        if ($suppliers->isEmpty()) {
            return;
        }

        $postingService = app(SupplierInvoiceService::class);
        $ledgerService  = app(SupplierLedgerService::class);
        $touched        = collect();

        foreach (self::INVOICE_MONTHS as $monthIndex => $month) {
            $monthStart = Carbon::parse($month . '-01');
            $count      = 3 + ($monthIndex % 4); // 3–6 invoices, deterministic per month

            for ($i = 0; $i < $count; $i++) {
                /** @var Supplier $supplier */
                $supplier = $suppliers[($monthIndex * 7 + $i) % $suppliers->count()];

                // Stable reference makes the invoice idempotent per community/month/slot.
                $ourReference = 'DEMO-' . substr($community->id, 0, 8) . '-' . $month . '-' . ($i + 1);

                $existing = SupplierInvoice::where('organization_id', $orgId)
                    ->where('community_id', $community->id)
                    ->where('our_reference', $ourReference)
                    ->first();

                if ($existing) {
                    if ($existing->status === SupplierInvoiceStatus::CREATED) {
                        $touched->push($supplier->id);
                    }
                    continue;
                }

                $def         = $this->supplierDef($supplier->name);
                $codes       = $def['codes'] ?? ['2000/021'];
                $invoiceDate = $monthStart->copy()->addDays(min(27, $i * 4 + 3));

                $items = $this->buildInvoiceItems($orgId, $codes, $supplier->name);

                if (empty($items)) {
                    continue;
                }

                $subtotal = round(array_sum(array_column($items, 'net')), 2);
                $vatTotal = round(array_sum(array_column($items, 'tax_amount')), 2);
                $total    = round($subtotal + $vatTotal, 2);

                $invoice = SupplierInvoice::create([
                    'grv_number'         => $this->grvNumber($orgId),
                    'status'             => SupplierInvoiceStatus::CREATED->value,
                    'type'               => SupplierInvoiceType::ADHOC->value,
                    'supplier_reference' => 'INV' . rand(1000, 9999),
                    'our_reference'      => $ourReference,
                    'description'        => $supplier->name . ' — ' . $invoiceDate->format('F Y'),
                    'invoice_date'       => $invoiceDate->toDateString(),
                    'due_date'           => $invoiceDate->copy()->addDays(30)->toDateString(),
                    'financial_year'     => (int) $invoiceDate->year,
                    'subtotal'           => $subtotal,
                    'discount'           => 0,
                    'vat_amount'         => $vatTotal,
                    'total'              => $total,
                    'supplier_id'        => $supplier->id,
                    'community_id'       => $community->id,
                    'organization_id'    => $orgId,
                ]);

                foreach ($items as $sort => $item) {
                    $invoice->items()->create([
                        'account_name' => $item['account_name'],
                        'description'  => $item['description'],
                        'quantity'     => 1,
                        'unit_price'   => $item['line_total'],
                        'discount'     => 0,
                        'tax_rate'     => $item['tax_rate'],
                        'tax_amount'   => $item['tax_amount'],
                        'line_total'   => $item['line_total'],
                        'sort_order'   => $sort,
                        'ledger_id'    => $item['ledger_id'],
                    ]);
                }

                // Post the balanced GL batch (Dr expense/VAT, Cr Accounts Payable).
                $postingService->postSupplierInvoiceLedger($invoice);
                $touched->push($supplier->id);
            }
        }

        // Re-derive supplier balances from the posted subledger.
        foreach ($touched->unique() as $supplierId) {
            $supplier = $suppliers->firstWhere('id', $supplierId);
            if ($supplier) {
                $ledgerService->recalculate($supplier);
            }
        }
    }

    /**
     * Build VAT-inclusive line items resolved to real expense ledgers.
     *
     * @param string $orgId
     * @param array<string> $codes
     * @param string $supplierName
     * @return array<array{account_name:string,description:string,ledger_id:string,net:float,tax_rate:float,tax_amount:float,line_total:float}>
     */
    private function buildInvoiceItems(string $orgId, array $codes, string $supplierName): array
    {
        $items = [];

        foreach ($codes as $code) {
            $ledger = Ledger::where('organization_id', $orgId)->where('code', $code)->first();

            if (! $ledger) {
                continue;
            }

            $gross     = (float) rand(4500, 380000) / 100; // R45 – R3 800
            $taxRate   = 15.0;
            $taxAmount = round($gross - ($gross / (1 + $taxRate / 100)), 2);
            $net       = round($gross - $taxAmount, 2);

            $items[] = [
                'account_name' => $ledger->name,
                'description'  => $ledger->name . ' — ' . $supplierName,
                'ledger_id'    => $ledger->id,
                'net'          => $net,
                'tax_rate'     => $taxRate,
                'tax_amount'   => $taxAmount,
                'line_total'   => round($gross, 2),
            ];
        }

        return $items;
    }

    /**
     * Look up the supplier definition by name.
     *
     * @param string $name
     * @return array
     */
    private function supplierDef(string $name): array
    {
        foreach (self::SUPPLIERS as $def) {
            if ($def['name'] === $name) {
                return $def;
            }
        }

        return [];
    }

    /**
     * Generate a unique supplier code (3-letter prefix + zero-padded sequence),
     * mirroring SupplierService::generateSupplierCode. Idempotent-safe: reuses an
     * existing code when this supplier already exists.
     *
     * @param string $name
     * @param string $orgId
     * @param string $communityId
     * @return string
     */
    private function supplierCode(string $name, string $orgId, string $communityId): string
    {
        $existing = Supplier::where('organization_id', $orgId)
            ->where('community_id', $communityId)
            ->where('name', $name)
            ->value('supplier_code');

        if ($existing) {
            return $existing;
        }

        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name) . 'XXX', 0, 3));

        $sequence = Supplier::where('organization_id', $orgId)
            ->where('supplier_code', 'like', $prefix . '%')
            ->count();

        do {
            $sequence++;
            $code = $prefix . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
        } while (
            Supplier::where('organization_id', $orgId)->where('supplier_code', $code)->exists()
        );

        return $code;
    }

    /**
     * Generate a sequential GRV number for the organization (e.g. "GRV00005"),
     * mirroring SupplierInvoiceService::generateGrvNumber.
     *
     * @param string $orgId
     * @return string
     */
    private function grvNumber(string $orgId): string
    {
        $max = SupplierInvoice::where('organization_id', $orgId)
            ->where('grv_number', 'like', 'GRV%')
            ->max('grv_number');

        $next = $max ? ((int) substr($max, 3)) + 1 : 1;

        return 'GRV' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
