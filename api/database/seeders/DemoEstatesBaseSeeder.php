<?php

namespace Database\Seeders;

use App\Models\CashbookEntry;
use App\Models\ChargeType;
use App\Models\Estate;
use App\Models\EstateChargeType;
use App\Models\Invoice;
use App\Models\InvoiceEmailEvent;
use App\Models\Owner;
use App\Models\Organization;
use App\Models\Unit;
use App\Models\UnitActivity;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Abstract base for country-specific estate seeders.
 *
 * Sub-classes implement estateDefinitions() with their country-specific
 * data.  All billing, payment, email-event and activity-log logic lives
 * here so it is shared across regions.
 */
abstract class DemoEstatesBaseSeeder extends Seeder
{
    protected string $tenantId;
    protected array  $chargeTypes = [];
    protected int    $invoiceCounter = 0;
    protected int    $receiptIndex   = 0;

    /** 9 billing periods: July 2025 → March 2026 (billing day 25) */
    protected const PERIODS = [
        ['period' => '2025-07-01', 'sent_at' => '2025-07-25 08:00:00', 'due_date' => '2025-08-01'],
        ['period' => '2025-08-01', 'sent_at' => '2025-08-25 08:00:00', 'due_date' => '2025-09-01'],
        ['period' => '2025-09-01', 'sent_at' => '2025-09-25 08:00:00', 'due_date' => '2025-10-02'],
        ['period' => '2025-10-01', 'sent_at' => '2025-10-25 08:00:00', 'due_date' => '2025-11-01'],
        ['period' => '2025-11-01', 'sent_at' => '2025-11-25 08:00:00', 'due_date' => '2025-12-02'],
        ['period' => '2025-12-01', 'sent_at' => '2025-12-25 08:00:00', 'due_date' => '2026-01-01'],
        ['period' => '2026-01-01', 'sent_at' => '2026-01-25 08:00:00', 'due_date' => '2026-02-01'],
        ['period' => '2026-02-01', 'sent_at' => '2026-02-25 08:00:00', 'due_date' => '2026-03-04'],
        ['period' => '2026-03-01', 'sent_at' => '2026-03-25 08:00:00', 'due_date' => '2026-04-01'],
    ];

    /** Receipt samples cycling — every entry gets its own file copy */
    protected const RECEIPTS = [
        ['src' => 'samples/receipts/receipt-1.png', 'ext' => 'png'],
        ['src' => 'samples/receipts/receipt-2.png', 'ext' => 'png'],
        ['src' => 'samples/receipts/receipt-3.png', 'ext' => 'png'],
        ['src' => 'samples/receipts/receipt-4.png', 'ext' => 'png'],
        ['src' => 'samples/receipts/receipt-5.jpg', 'ext' => 'jpg'],
    ];

    /* ------------------------------------------------------------------ */
    /*  ABSTRACT — sub-classes provide these                               */
    /* ------------------------------------------------------------------ */

    abstract protected function estateDefinitions(): array;

    /* ------------------------------------------------------------------ */
    /*  ENTRY POINT                                                        */
    /* ------------------------------------------------------------------ */

    public function run(): void
    {
        $tenant = Organization::where('slug', 'boldmark')->firstOrFail();
        $this->tenantId = $tenant->id;

        ChargeType::where('organization_id', $this->tenantId)->get()
            ->each(fn ($ct) => $this->chargeTypes[$ct->code] = $ct);

        // Continue invoice numbering from any already-seeded invoices
        $this->invoiceCounter = Invoice::where('organization_id', $this->tenantId)->count();

        foreach ($this->estateDefinitions() as $def) {
            $this->command?->info("Seeding estate: {$def['name']}");
            $estate = $this->createEstate($def);
            $this->enableEstateChargeTypes($estate, $def['type']);
            $this->seedUnits($estate, $def);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  ESTATE CREATION                                                    */
    /* ------------------------------------------------------------------ */

    protected function createEstate(array $def): Estate
    {
        $estate = Estate::create([
            'name'                => $def['name'],
            'type'                => $def['type'],
            'address'             => $def['address'],
            'default_levy_amount' => $def['default_levy_amount'] ?? null,
            'default_rent_amount' => $def['default_rent_amount'] ?? null,
            'billing_day'         => $def['billing_day'],
            'country'             => $def['country'],
            'currency'            => $def['currency'],
            'is_active'           => true,
            'organization_id'           => $this->tenantId,
        ]);

        $estate->timestamps = false;
        $estate->created_at = $def['created_at'];
        $estate->updated_at = $def['created_at'];
        $estate->save();
        $estate->timestamps = true;

        return $estate;
    }

    protected function enableEstateChargeTypes(Estate $estate, string $type): void
    {
        $activeCodes = match ($type) {
            'sectional_title' => ['LEVY','WATER_RECOVERY','ELECTRICITY_RECOVERY','SEWERAGE_RECOVERY',
                                  'REFUSE_RECOVERY','PARKING_RENTAL','GYM_ACCESS','POOL_ACCESS',
                                  'PET_LEVY','GARDEN_MAINT','SECURITY_CONTRIB','SPECIAL_LEVY',
                                  'LATE_INTEREST','LATE_PENALTY','ACCESS_CARD'],
            'residential_rental' => ['RENT','DAMAGE_DEPOSIT','KEY_DEPOSIT','MOVING_IN','MOVING_OUT',
                                     'LATE_INTEREST','LATE_PENALTY','PARKING_RENTAL','PET_LEVY'],
            'commercial_rental'  => ['RENT','DAMAGE_DEPOSIT','KEY_DEPOSIT','LATE_INTEREST',
                                     'LATE_PENALTY','PARKING_RENTAL','STORAGE_RENTAL','ACCESS_CARD'],
            default /* mixed */  => ['LEVY','RENT','WATER_RECOVERY','ELECTRICITY_RECOVERY',
                                     'PARKING_RENTAL','GYM_ACCESS','POOL_ACCESS','PET_LEVY',
                                     'DAMAGE_DEPOSIT','KEY_DEPOSIT','MOVING_IN','MOVING_OUT',
                                     'LATE_INTEREST','LATE_PENALTY','SPECIAL_LEVY','ACCESS_CARD'],
        };

        foreach ($this->chargeTypes as $code => $ct) {
            EstateChargeType::updateOrCreate(
                ['estate_id' => $estate->id, 'charge_type_id' => $ct->id],
                ['is_active' => in_array($code, $activeCodes, true)]
            );
        }
    }

    /* ------------------------------------------------------------------ */
    /*  UNIT SEEDING                                                       */
    /* ------------------------------------------------------------------ */

    protected function seedUnits(Estate $estate, array $def): void
    {
        foreach ($def['units'] as $unitDef) {
            $unit  = $this->createUnit($estate, $unitDef);
            $owner = $this->createOwner($unit, $unitDef['owner']);

            $currentTenant = null;
            if ($unitDef['occupancy'] === 'tenant_occupied') {
                $currentTenant = $this->seedTenants($unit, $unitDef);
            }

            $this->seedInvoicesAndPayments($estate, $unit, $owner, $currentTenant, $unitDef, $def);
            $this->seedUnitActivities($unit, $owner, $currentTenant, $def['type']);
        }
    }

    protected function createUnit(Estate $estate, array $def): Unit
    {
        return Unit::create([
            'unit_number'    => $def['number'],
            'address'        => $estate->address . ' — Unit ' . $def['number'],
            'occupancy_type' => $def['occupancy'],
            'status'         => 'active',
            'levy_override'  => $def['levy_override'] ?? null,
            'rent_amount'    => $def['rent_amount'] ?? $estate->default_rent_amount,
            'balance'        => 0,
            'estate_id'      => $estate->id,
            'organization_id'      => $this->tenantId,
        ]);
    }

    protected function createOwner(Unit $unit, array $data): Owner
    {
        return Owner::create(array_merge($data, [
            'unit_id'   => $unit->id,
            'organization_id' => $this->tenantId,
        ]));
    }

    /* ------------------------------------------------------------------ */
    /*  TENANT SEEDING                                                     */
    /* ------------------------------------------------------------------ */

    protected function seedTenants(Unit $unit, array $def): ?Tenant
    {
        $organizations = $def['organizations'] ?? [];
        if (empty($organizations)) {
            return null;
        }

        $currentData  = array_pop($organizations);
        $previousData = $organizations;

        foreach ($previousData as $prev) {
            $ut = Tenant::create(array_merge($prev, [
                'unit_id'   => $unit->id,
                'organization_id' => $this->tenantId,
                'is_active' => false,
            ]));
            $this->attachLeaseDocument($ut);
        }

        $current = Tenant::create(array_merge($currentData, [
            'unit_id'         => $unit->id,
            'organization_id'       => $this->tenantId,
            'is_active'       => true,
            'move_out_date'   => null,
            'move_out_reason' => null,
            'move_out_notes'  => null,
        ]));
        $this->attachLeaseDocument($current);

        return $current;
    }

    protected function attachLeaseDocument(Tenant $ut): void
    {
        $srcPath = base_path('samples/documents/lease-agreement.pdf');
        $destRel = "tenants/{$ut->id}/lease-agreement.pdf";

        if (file_exists($srcPath)) {
            Storage::disk('public')->put($destRel, file_get_contents($srcPath));
            $ut->update([
                'lease_document_url'  => Storage::disk('public')->url($destRel),
                'lease_document_name' => "Lease_Agreement_{$ut->full_name}.pdf",
            ]);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  INVOICE & PAYMENT SEEDING                                          */
    /* ------------------------------------------------------------------ */

    protected function seedInvoicesAndPayments(
        Estate      $estate,
        Unit        $unit,
        Owner       $owner,
        ?Tenant $currentTenant,
        array       $unitDef,
        array       $estateDef
    ): void {
        $startPeriod = $estateDef['start_period'];
        $periods     = array_slice(self::PERIODS, $startPeriod);
        $isDebtor    = $unitDef['debtor'] ?? false;
        $debtorFrom  = $unitDef['debtor_from'] ?? 6;
        $estateType  = $estateDef['type'];

        $primaryCodes = $this->primaryCodes($estateType, $unit->occupancy_type->value);
        $extraCode    = $unitDef['extra_ct'] ?? null;

        $invoiceCount = 0;
        $maxInvoices  = 10;

        foreach ($primaryCodes as $code) {
            $ct = $this->chargeTypes[$code] ?? null;
            if (! $ct) continue;

            foreach ($periods as $periodIndex => $periodDef) {
                if ($invoiceCount >= $maxInvoices) break 2;

                $absolutePeriodIndex = $startPeriod + $periodIndex;
                $amount = $this->resolveAmount($code, $unit, $owner, $currentTenant, $estate);
                if ($amount <= 0) continue;

                [$billedToType, $billedToId, $recipientEmail, $recipientName] =
                    $this->resolveRecipient($code, $unit, $owner, $currentTenant);
                if (! $billedToId) continue;

                $status  = $this->resolveStatus($absolutePeriodIndex, $isDebtor, $debtorFrom);
                $invoice = $this->createInvoice($unit, $ct, $billedToType, $billedToId, $amount, $periodDef, $status, $estate);

                $this->createEmailEvents($invoice, $status, $recipientEmail);

                if ($status === 'paid') {
                    $this->createCashbookEntry($invoice, $unit, $estate, $recipientName, $ct);
                }

                $invoiceCount++;
            }
        }

        if ($extraCode && $invoiceCount < $maxInvoices) {
            $extraCt = $this->chargeTypes[$extraCode] ?? null;
            if ($extraCt) {
                $extraPeriods = array_slice($periods, -min(2, $maxInvoices - $invoiceCount));
                foreach ($extraPeriods as $periodDef) {
                    if ($invoiceCount >= $maxInvoices) break;
                    $amount = $this->resolveExtraAmount($extraCode);
                    [$billedToType, $billedToId, $recipientEmail, $recipientName] =
                        $this->resolveRecipient($extraCode, $unit, $owner, $currentTenant);
                    if (! $billedToId) continue;

                    $invoice = $this->createInvoice($unit, $extraCt, $billedToType, $billedToId, $amount, $periodDef, 'paid', $estate);
                    $this->createEmailEvents($invoice, 'paid', $recipientEmail);
                    $this->createCashbookEntry($invoice, $unit, $estate, $recipientName, $extraCt);
                    $invoiceCount++;
                }
            }
        }
    }

    protected function primaryCodes(string $estateType, string $occupancy): array
    {
        return match ($estateType) {
            'sectional_title'    => ['LEVY'],
            'residential_rental' => ['RENT'],
            'commercial_rental'  => ['RENT'],
            'mixed' => match ($occupancy) {
                'tenant_occupied' => ['LEVY', 'RENT'],
                default           => ['LEVY'],
            },
            default => ['LEVY'],
        };
    }

    protected function resolveAmount(string $code, Unit $unit, Owner $owner, ?Tenant $tenant, Estate $estate): float
    {
        return match ($code) {
            'LEVY' => $unit->levy_override ?? $estate->default_levy_amount ?? 0,
            'RENT' => $unit->rent_amount   ?? $estate->default_rent_amount  ?? 0,
            default => 0,
        };
    }

    protected function resolveExtraAmount(string $code): float
    {
        return match ($code) {
            'PARKING_RENTAL'       => 450.00,
            'GYM_ACCESS'           => 300.00,
            'POOL_ACCESS'          => 250.00,
            'WATER_RECOVERY'       => 380.00,
            'ELECTRICITY_RECOVERY' => 620.00,
            'STORAGE_RENTAL'       => 350.00,
            default                => 200.00,
        };
    }

    protected function resolveRecipient(string $code, Unit $unit, Owner $owner, ?Tenant $tenant): array
    {
        $ct = $this->chargeTypes[$code] ?? null;
        if (! $ct) return ['owner', null, null, null];

        $appliesTo = $ct->applies_to instanceof \App\Enums\ChargeTypeAppliesTo
            ? $ct->applies_to->value
            : (string) $ct->applies_to;

        if ($appliesTo === 'organization') {
            if (! $tenant) return ['organization', null, null, null];
            return ['organization', $tenant->id, $tenant->email, $tenant->full_name];
        }

        if ($appliesTo === 'owner') {
            return ['owner', $owner->id, $owner->email, $owner->full_name];
        }

        if ($unit->occupancy_type->value === 'tenant_occupied' && $tenant) {
            return ['organization', $tenant->id, $tenant->email, $tenant->full_name];
        }
        return ['owner', $owner->id, $owner->email, $owner->full_name];
    }

    protected function resolveStatus(int $absolutePeriodIndex, bool $isDebtor, int $debtorFrom): string
    {
        if ($isDebtor && $absolutePeriodIndex >= $debtorFrom) {
            return 'overdue';
        }
        return 'paid';
    }

    protected function createInvoice(
        Unit $unit, $chargeType, string $billedToType, string $billedToId,
        float $amount, array $periodDef, string $status, Estate $estate
    ): Invoice {
        $this->invoiceCounter++;
        $year      = substr($periodDef['period'], 0, 4);
        $num       = str_pad($this->invoiceCounter, 4, '0', STR_PAD_LEFT);
        $invNumber = "INV-{$year}-{$num}";

        return Invoice::create([
            'invoice_number'    => $invNumber,
            'status'            => $status,
            'billed_to_type'    => $billedToType,
            'billed_to_id'      => $billedToId,
            'amount'            => $amount,
            'billing_period'    => $periodDef['period'],
            'due_date'          => $periodDef['due_date'],
            'sent_at'           => $periodDef['sent_at'] ?? null,
            'issued_by_type'    => 'system',
            'issued_by_user_id' => null,
            'unit_id'           => $unit->id,
            'charge_type_id'    => $chargeType->id,
            'organization_id'         => $this->tenantId,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  EMAIL EVENTS                                                       */
    /* ------------------------------------------------------------------ */

    protected function createEmailEvents(Invoice $invoice, string $status, string $email): void
    {
        if (! $invoice->sent_at) return;

        $sentAt = Carbon::parse($invoice->sent_at);

        InvoiceEmailEvent::create([
            'invoice_id'      => $invoice->id,
            'organization_id'       => $this->tenantId,
            'event_type'      => 'sent',
            'email'           => $email,
            'resend_email_id' => 'demo_' . Str::random(20),
            'occurred_at'     => $sentAt,
            'metadata'        => ['source' => 'demo_seed'],
        ]);

        $deliveredAt = $sentAt->copy()->addMinutes(rand(8, 15));
        InvoiceEmailEvent::create([
            'invoice_id'      => $invoice->id,
            'organization_id'       => $this->tenantId,
            'event_type'      => 'delivered',
            'email'           => $email,
            'resend_email_id' => 'demo_' . Str::random(20),
            'occurred_at'     => $deliveredAt,
            'metadata'        => ['source' => 'demo_seed'],
        ]);

        if ($status === 'paid') {
            $openedAt = $deliveredAt->copy()->addHours(rand(2, 5))->addMinutes(rand(0, 30));
            InvoiceEmailEvent::create([
                'invoice_id'      => $invoice->id,
                'organization_id'       => $this->tenantId,
                'event_type'      => 'opened',
                'email'           => $email,
                'resend_email_id' => 'demo_' . Str::random(20),
                'occurred_at'     => $openedAt,
                'metadata'        => ['source' => 'demo_seed'],
            ]);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  CASHBOOK ENTRIES                                                    */
    /* ------------------------------------------------------------------ */

    protected function createCashbookEntry(Invoice $invoice, Unit $unit, Estate $estate, string $payerName, $chargeType): void
    {
        $sentAt      = Carbon::parse($invoice->sent_at);
        $openedAt    = $sentAt->copy()->addHours(rand(3, 6));
        $paymentDate = $openedAt->copy()->addDays(rand(1, 5));

        $today = Carbon::parse('2026-04-20');
        if ($paymentDate->gt($today)) {
            $paymentDate = $today->copy()->subDays(rand(0, 3));
        }

        $nameParts = explode(' ', strtoupper($payerName));
        $surname   = end($nameParts);
        $ctName    = strtoupper(str_replace(' ', '_', $chargeType->name));
        $period    = Carbon::parse($invoice->billing_period)->format('M Y');
        $desc      = "EFT – {$surname} {$ctName} {$period}";

        $proofPath = $this->storeReceipt();

        CashbookEntry::create([
            'description'           => $desc,
            'amount'                => $invoice->amount,
            'type'                  => 'credit',
            'date'                  => $paymentDate->toDateString(),
            'notes'                 => "Payment received for {$invoice->invoice_number}",
            'proof_of_payment_path' => $proofPath,
            'estate_id'             => $estate->id,
            'organization_id'             => $this->tenantId,
            'charge_type_id'        => $chargeType->id,
            'unit_id'               => $unit->id,
            'invoice_id'            => $invoice->id,
            'parent_entry_id'       => null,
        ]);
    }

    protected function storeReceipt(): ?string
    {
        $receipt = self::RECEIPTS[$this->receiptIndex % count(self::RECEIPTS)];
        $this->receiptIndex++;

        $srcPath = base_path($receipt['src']);
        if (! file_exists($srcPath)) return null;

        $uniqueId = Str::uuid()->toString();
        $destRel  = "proof_of_payment/{$this->tenantId}/{$uniqueId}.{$receipt['ext']}";
        Storage::disk('public')->put($destRel, file_get_contents($srcPath));

        return $destRel;
    }

    /* ------------------------------------------------------------------ */
    /*  UNIT ACTIVITIES (CHANGE LOG)                                       */
    /* ------------------------------------------------------------------ */

    protected function seedUnitActivities(Unit $unit, Owner $owner, ?Tenant $tenant, string $estateType): void
    {
        $unitNum = (int) preg_replace('/[^0-9]/', '', $unit->unit_number);
        if (($unitNum % 5) !== 0 && ($unitNum % 7) !== 0) return;

        $batchId    = (string) Str::uuid();
        $activities = [];

        $activities[] = [
            'unit_id'         => $unit->id,
            'organization_id'       => $this->tenantId,
            'batch_id'        => $batchId,
            'changed_by_name' => 'Justin Sobhee',
            'event'           => 'Updated owner details',
            'category'        => 'owner',
            'changes'         => json_encode([
                ['field' => 'full_name', 'old' => $owner->full_name . ' (Maiden)', 'new' => $owner->full_name],
            ]),
            'created_at' => Carbon::parse('2025-08-10 10:23:00'),
            'updated_at' => Carbon::parse('2025-08-10 10:23:00'),
        ];

        $activities[] = [
            'unit_id'         => $unit->id,
            'organization_id'       => $this->tenantId,
            'batch_id'        => null,
            'changed_by_name' => 'Thabo Ndlovu',
            'event'           => 'Updated owner details',
            'category'        => 'owner',
            'changes'         => json_encode([
                ['field' => 'email', 'old' => 'old.' . $owner->email, 'new' => $owner->email],
            ]),
            'created_at' => Carbon::parse('2025-09-05 14:11:00'),
            'updated_at' => Carbon::parse('2025-09-05 14:11:00'),
        ];

        $activities[] = [
            'unit_id'         => $unit->id,
            'organization_id'       => $this->tenantId,
            'batch_id'        => null,
            'changed_by_name' => 'Naledi Khumalo',
            'event'           => 'Updated owner details',
            'category'        => 'owner',
            'changes'         => json_encode([
                ['field' => 'phone', 'old' => '+267 71 000 0000', 'new' => $owner->phone],
            ]),
            'created_at' => Carbon::parse('2025-10-20 09:45:00'),
            'updated_at' => Carbon::parse('2025-10-20 09:45:00'),
        ];

        $activities[] = [
            'unit_id'         => $unit->id,
            'organization_id'       => $this->tenantId,
            'batch_id'        => $batchId,
            'changed_by_name' => 'Justin Sobhee',
            'event'           => 'Unit created',
            'category'        => 'unit',
            'changes'         => json_encode([
                ['field' => 'unit_number',    'old' => null, 'new' => $unit->unit_number],
                ['field' => 'occupancy_type', 'old' => null, 'new' => $unit->occupancy_type->value],
            ]),
            'created_at' => Carbon::parse('2025-07-01 09:30:00'),
            'updated_at' => Carbon::parse('2025-07-01 09:30:00'),
        ];

        if ($tenant && in_array($estateType, ['mixed', 'residential_rental', 'commercial_rental'], true)) {
            $activities[] = [
                'unit_id'         => $unit->id,
                'organization_id'       => $this->tenantId,
                'batch_id'        => null,
                'changed_by_name' => 'Lerato Pillay',
                'event'           => 'Moved in tenant',
                'category'        => 'tenant',
                'changes'         => json_encode([
                    ['field' => 'tenant',      'old' => null,              'new' => $tenant->full_name],
                    ['field' => 'lease_start', 'old' => null,              'new' => (string) $tenant->lease_start],
                    ['field' => 'lease_end',   'old' => null,              'new' => (string) $tenant->lease_end],
                    ['field' => 'rent_amount', 'old' => null,              'new' => (string) $unit->rent_amount],
                ]),
                'created_at' => Carbon::parse($tenant->lease_start)->addDays(1)->setTime(8, 30, 0),
                'updated_at' => Carbon::parse($tenant->lease_start)->addDays(1)->setTime(8, 30, 0),
            ];

            $activities[] = [
                'unit_id'         => $unit->id,
                'organization_id'       => $this->tenantId,
                'batch_id'        => null,
                'changed_by_name' => 'Thabo Ndlovu',
                'event'           => 'Updated tenant details',
                'category'        => 'tenant',
                'changes'         => json_encode([
                    ['field' => 'phone', 'old' => '+267 71 000 0099', 'new' => $tenant->phone],
                ]),
                'created_at' => Carbon::parse($tenant->lease_start)->addDays(10)->setTime(11, 15, 0),
                'updated_at' => Carbon::parse($tenant->lease_start)->addDays(10)->setTime(11, 15, 0),
            ];
        }

        $rows = array_map(fn ($a) => array_merge($a, [
            'id'         => (string) Str::uuid(),
            'created_at' => $a['created_at'] instanceof Carbon ? $a['created_at']->toDateTimeString() : $a['created_at'],
            'updated_at' => $a['updated_at'] instanceof Carbon ? $a['updated_at']->toDateTimeString() : $a['updated_at'],
        ]), $activities);

        DB::table('unit_activities')->insert($rows);
    }

    /* ------------------------------------------------------------------ */
    /*  DATA-BUILDER HELPERS                                               */
    /* ------------------------------------------------------------------ */

    /** Build an owner data array */
    protected function o(string $name, string $email, string $phone, string $idNumber, string $address): array
    {
        return compact('email', 'phone', 'address') + ['full_name' => $name, 'id_number' => $idNumber];
    }

    /** Build a current (active) tenant data array */
    protected function tc(string $name, string $email, string $phone, string $idNumber, string $leaseStart, string $leaseEnd, float $rent): array
    {
        return ['full_name' => $name, 'email' => $email, 'phone' => $phone, 'id_number' => $idNumber, 'lease_start' => $leaseStart, 'lease_end' => $leaseEnd];
    }

    /** Build a past (inactive) tenant data array */
    protected function t(string $name, string $email, string $phone, string $idNumber, string $leaseStart, string $leaseEnd, string $moveOutDate, float $rent, string $reason): array
    {
        return [
            'full_name'       => $name,
            'email'           => $email,
            'phone'           => $phone,
            'id_number'       => $idNumber,
            'lease_start'     => $leaseStart,
            'lease_end'       => $leaseEnd,
            'move_out_date'   => $moveOutDate,
            'move_out_reason' => $reason,
            'move_out_notes'  => "Organization {$name} vacated the unit on {$moveOutDate}. {$reason}.",
        ];
    }
}
