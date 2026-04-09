<?php

namespace App\Services;

use Exception;
use App\Models\Estate;
use App\Models\Unit;
use App\Models\Invoice;
use App\Models\ChargeType;
use App\Models\InvoiceEmailEvent;
use App\Enums\InvoiceStatus;
use App\Enums\BilledToType;
use App\Enums\OccupancyType;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Resend\Laravel\Facades\Resend;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\InvoiceResources;

class InvoiceService extends BaseService
{
    public function __construct(private readonly UnitBalanceService $unitBalance)
    {
        parent::__construct();
    }


    /**
     * Return a paginated, filtered list of invoices for the authenticated tenant.
     *
     * @param array $data
     * @return InvoiceResources
     */
    public function showInvoices(array $data): InvoiceResources
    {
        $user  = Auth::user();
        $query = Invoice::where('tenant_id', $user->tenant_id)
            ->with(['unit.estate', 'chargeType', 'billedToOwner', 'billedToUnitTenant', 'emailEvents']);

        if (!empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        if (!empty($data['charge_type_id'])) {
            $query->where('charge_type_id', $data['charge_type_id']);
        }

        if (!empty($data['unit_id'])) {
            $query->where('unit_id', $data['unit_id']);
        }

        if (!empty($data['estate_id'])) {
            $query->whereHas('unit', fn($q) => $q->where('estate_id', $data['estate_id']));
        }

        if (!empty($data['billed_to_type'])) {
            $query->where('billed_to_type', $data['billed_to_type']);
        }

        if (!empty($data['billed_to_id'])) {
            $query->where('billed_to_id', $data['billed_to_id']);
        }

        if (!empty($data['billing_period'])) {
            $query->where('billing_period', Carbon::parse($data['billing_period'] . '-01')->format('Y-m-d'));
        }

        if (!empty($data['search'])) {
            $term = '%' . $data['search'] . '%';
            $query->where(function ($q) use ($term) {
                $q->where('invoice_number', 'ilike', $term)
                  ->orWhereHas('unit', fn($u) => $u->where('unit_number', 'ilike', $term))
                  ->orWhereHas('chargeType', fn($ct) => $ct->where('name', 'ilike', $term))
                  ->orWhere(function ($sub) use ($term) {
                      $sub->where('billed_to_type', 'owner')
                          ->whereHas('billedToOwner', fn($o) => $o->where('full_name', 'ilike', $term));
                  })
                  ->orWhere(function ($sub) use ($term) {
                      $sub->where('billed_to_type', 'tenant')
                          ->whereHas('billedToUnitTenant', fn($t) => $t->where('full_name', 'ilike', $term));
                  });
            });
        }

        // Date range on created_at
        if (!empty($data['date_range'])) {
            $query = $this->applyDateRange(
                $query,
                $data['date_range'],
                $data['date_range_start'] ?? null,
                $data['date_range_end'] ?? null,
                'created_at'
            );
        }

        if (!request()->has('_sort')) {
            $query = $query->latest();
        }

        return $this->setQuery($query)->getOutput();
    }

    /**
     * Export invoices as CSV, Excel, or PDF — same filters as showInvoices().
     *
     * Extra parameters in $data:
     *   _format  — 'csv' | 'xlsx' | 'pdf'  (required)
     *   _limit   — integer record cap, or 'current' (= 15)
     *
     * @param array $data
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function exportInvoices(array $data): \Symfony\Component\HttpFoundation\Response
    {
        $user  = Auth::user();
        $query = Invoice::where('tenant_id', $user->tenant_id)
            ->with(['unit.estate', 'chargeType', 'billedToOwner', 'billedToUnitTenant']);

        if (!empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (!empty($data['charge_type_id'])) {
            $query->where('charge_type_id', $data['charge_type_id']);
        }
        if (!empty($data['unit_id'])) {
            $query->where('unit_id', $data['unit_id']);
        }
        if (!empty($data['estate_id'])) {
            $query->whereHas('unit', fn($q) => $q->where('estate_id', $data['estate_id']));
        }
        if (!empty($data['billed_to_type'])) {
            $query->where('billed_to_type', $data['billed_to_type']);
        }
        if (!empty($data['billed_to_id'])) {
            $query->where('billed_to_id', $data['billed_to_id']);
        }
        if (!empty($data['billing_period'])) {
            $query->where('billing_period', Carbon::parse($data['billing_period'] . '-01')->format('Y-m-d'));
        }
        if (!empty($data['search'])) {
            $term = '%' . $data['search'] . '%';
            $query->where(function ($q) use ($term) {
                $q->where('invoice_number', 'ilike', $term)
                  ->orWhereHas('unit', fn($u) => $u->where('unit_number', 'ilike', $term))
                  ->orWhereHas('chargeType', fn($ct) => $ct->where('name', 'ilike', $term))
                  ->orWhere(function ($sub) use ($term) {
                      $sub->where('billed_to_type', 'owner')
                          ->whereHas('billedToOwner', fn($o) => $o->where('full_name', 'ilike', $term));
                  })
                  ->orWhere(function ($sub) use ($term) {
                      $sub->where('billed_to_type', 'tenant')
                          ->whereHas('billedToUnitTenant', fn($t) => $t->where('full_name', 'ilike', $term));
                  });
            });
        }
        if (!empty($data['date_range'])) {
            $query = $this->applyDateRange(
                $query,
                $data['date_range'],
                $data['date_range_start'] ?? null,
                $data['date_range_end'] ?? null,
                'created_at'
            );
        }

        if (!$this->request->has('_sort')) {
            $query->latest();
        }

        $this->setQuery($query);
        $this->applySortOnQuery();

        $limit    = $this->resolveExportLimit($data['_limit'] ?? 'current');
        $invoices = $this->query->limit($limit)->get();

        $headings = ['Invoice #', 'Estate', 'Unit', 'Charge Type', 'Billed To', 'Period', 'Amount', 'Status', 'Due Date', 'Sent At'];

        $rows = $invoices->map(function ($invoice) {
            $billedToType = $invoice->billed_to_type instanceof \BackedEnum
                ? $invoice->billed_to_type->value
                : (string) $invoice->billed_to_type;

            $billedTo = $billedToType === 'owner'
                ? $invoice->billedToOwner?->full_name
                : $invoice->billedToUnitTenant?->full_name;

            $status = $invoice->status instanceof \BackedEnum
                ? $invoice->status->value
                : (string) $invoice->status;

            return [
                $invoice->invoice_number,
                $invoice->unit?->estate?->name,
                $invoice->unit?->unit_number,
                $invoice->chargeType?->name,
                $billedTo,
                $invoice->billing_period?->format('M Y'),
                number_format((float) $invoice->amount, 2),
                ucfirst(str_replace('_', ' ', $status)),
                $invoice->due_date?->format('d M Y'),
                $invoice->sent_at?->format('d M Y H:i') ?? 'Not sent',
            ];
        })->toArray();

        $format = $data['_format'] ?? 'csv';

        return $this->buildFileResponse(
            $rows,
            $headings,
            'invoices-' . now()->format('Y-m-d'),
            $format,
            'Invoices Export',
            ['Generated' => now()->format('d M Y'), 'Records' => count($rows)]
        );
    }

    /**
     * Return aggregate summary statistics for invoices.
     *
     * @param array $data
     * @return array
     */
    public function showInvoicesSummary(array $data): array
    {
        $user  = Auth::user();
        $query = Invoice::where('tenant_id', $user->tenant_id);

        if (!empty($data['estate_id'])) {
            $query->whereHas('unit', fn($q) => $q->where('estate_id', $data['estate_id']));
        }

        if (!empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        if (!empty($data['charge_type_id'])) {
            $query->where('charge_type_id', $data['charge_type_id']);
        }

        $stats = (clone $query)->selectRaw(
            'COUNT(*) as total,
             SUM(amount) as total_amount,
             COUNT(CASE WHEN status = ? THEN 1 END) as paid_count,
             COUNT(CASE WHEN status = ? THEN 1 END) as overdue_count,
             COUNT(CASE WHEN status = ? THEN 1 END) as partially_paid_count,
             COUNT(CASE WHEN status = ? THEN 1 END) as unpaid_count',
            [
                InvoiceStatus::PAID->value,
                InvoiceStatus::OVERDUE->value,
                InvoiceStatus::PARTIALLY_PAID->value,
                InvoiceStatus::UNPAID->value,
            ]
        )->first();

        $revenueByChargeType = DB::table('invoices')
            ->join('charge_types', 'invoices.charge_type_id', '=', 'charge_types.id')
            ->where('invoices.tenant_id', $user->tenant_id)
            ->when(!empty($data['estate_id']), function ($q) use ($data) {
                $unitIds = Unit::where('estate_id', $data['estate_id'])->pluck('id');
                $q->whereIn('invoices.unit_id', $unitIds);
            })
            ->when(!empty($data['status']),         fn ($q) => $q->where('invoices.status', $data['status']))
            ->when(!empty($data['charge_type_id']), fn ($q) => $q->where('invoices.charge_type_id', $data['charge_type_id']))
            ->select('charge_types.name', DB::raw('SUM(invoices.amount) as total_amount'))
            ->groupBy('charge_types.id', 'charge_types.name')
            ->orderByDesc('total_amount')
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'total' => (float) $row->total_amount])
            ->values()
            ->toArray();

        return [
            'total'                  => (int) ($stats->total ?? 0),
            'total_amount'           => (float) ($stats->total_amount ?? 0),
            'paid_count'             => (int) ($stats->paid_count ?? 0),
            'overdue_count'          => (int) ($stats->overdue_count ?? 0),
            'partially_paid_count'   => (int) ($stats->partially_paid_count ?? 0),
            'unpaid_count'           => (int) ($stats->unpaid_count ?? 0),
            'revenue_by_charge_type' => $revenueByChargeType,
        ];
    }

    /**
     * Create a single invoice manually.
     *
     * @param array $data
     * @return array
     */
    public function createInvoice(array $data): array
    {
        $user = Auth::user();

        $invoiceData = collect($data)
            ->only(['unit_id', 'charge_type_id', 'billed_to_type', 'billed_to_id', 'amount', 'billing_period', 'due_date'])
            ->toArray();

        // Normalise billing_period to first day of month
        if (!empty($invoiceData['billing_period'])) {
            $invoiceData['billing_period'] = Carbon::parse($invoiceData['billing_period'])->startOfMonth()->format('Y-m-d');
        }

        // Duplicate check: same unit + charge type + billing period is not allowed
        $exists = Invoice::where('unit_id', $invoiceData['unit_id'])
            ->where('charge_type_id', $invoiceData['charge_type_id'])
            ->where('billing_period', $invoiceData['billing_period'])
            ->exists();

        if ($exists) {
            $chargeType    = ChargeType::find($invoiceData['charge_type_id']);
            $chargeTypeName = $chargeType?->name ?? 'this charge type';
            $period        = Carbon::parse($invoiceData['billing_period'])->format('F Y');
            throw new Exception("An invoice for {$chargeTypeName} already exists for {$period}. Duplicate invoices are not allowed.");
        }

        $invoice = Invoice::create(array_merge($invoiceData, [
            'tenant_id'          => $user->tenant_id,
            'invoice_number'     => $this->generateInvoiceNumber($user->tenant_id),
            'status'             => InvoiceStatus::UNPAID->value,
            'issued_by_type'     => 'user',
            'issued_by_user_id'  => $user->id,
        ]));

        $this->unitBalance->recalculate($invoice->unit);

        return $this->showCreatedResource($invoice);
    }

    /**
     * Execute the billing engine for an estate and billing period.
     *
     * When dry_run is true, no records are created — a preview is returned.
     * When dry_run is false (default), invoices are generated in the database.
     *
     * @param array $data
     * @return array
     * @throws Exception
     */
    public function runBilling(array $data): array
    {
        $user  = Auth::user();
        $isDryRun = (bool) ($data['dry_run'] ?? false);

        $estate = Estate::where('id', $data['estate_id'])
            ->where('tenant_id', $user->tenant_id)
            ->firstOrFail();

        $billingPeriod = Carbon::parse($data['billing_period'] . '-01');
        $billingPeriodDate = $billingPeriod->format('Y-m-d');

        // Load active units with all needed relationships
        $units = Unit::where('estate_id', $estate->id)
            ->where('status', 'active')
            ->with([
                'owner',
                'currentTenant',
                'activeChargeConfigs.chargeType',
                'estate.activeChargeTypes',
            ])
            ->get();

        $preview = [];
        $created = 0;

        // Get levy and rent system charge types for this estate (from estate's active charge types)
        $estateChargeTypes = $estate->activeChargeTypes;
        $levyChargeType    = $estateChargeTypes->firstWhere('code', 'LEVY');
        $rentChargeType    = $estateChargeTypes->firstWhere('code', 'RENT');

        foreach ($units as $unit) {
            $invoicesToCreate = [];

            // 1. Levy invoice → always to owner (if levy charge type is enabled for the estate)
            if ($levyChargeType && $unit->owner) {
                $levyAmount = $unit->levy_override ?? $estate->default_levy_amount;

                if ($levyAmount > 0) {
                    $invoicesToCreate[] = [
                        'charge_type'    => $levyChargeType,
                        'billed_to_type' => BilledToType::OWNER->value,
                        'billed_to_id'   => $unit->owner->id,
                        'recipient_name' => $unit->owner->full_name,
                        'amount'         => $levyAmount,
                        'unit'           => $unit,
                        'label'          => 'Levy',
                    ];
                }
            }

            // 2. Rent invoice → to active tenant if tenant_occupied and rent charge type is active
            $occupancyType = $unit->occupancy_type instanceof OccupancyType
                ? $unit->occupancy_type->value
                : (string) $unit->occupancy_type;

            if (
                $rentChargeType &&
                $occupancyType === OccupancyType::TENANT_OCCUPIED->value &&
                $unit->currentTenant &&
                $unit->rent_amount > 0
            ) {
                $invoicesToCreate[] = [
                    'charge_type'    => $rentChargeType,
                    'billed_to_type' => BilledToType::TENANT->value,
                    'billed_to_id'   => $unit->currentTenant->id,
                    'recipient_name' => $unit->currentTenant->full_name,
                    'amount'         => $unit->rent_amount,
                    'unit'           => $unit,
                    'label'          => 'Rent',
                ];
            }

            // 3. Per-unit recurring charge configs (parking, gym, pet levy, etc.)
            foreach ($unit->activeChargeConfigs as $config) {
                $chargeType = $config->chargeType;

                if (!$chargeType || !$chargeType->is_active || !$chargeType->is_recurring) {
                    continue;
                }

                $appliesTo = $chargeType->applies_to instanceof \App\Enums\ChargeTypeAppliesTo
                    ? $chargeType->applies_to->value
                    : (string) $chargeType->applies_to;

                // Determine recipient
                $billedToType = null;
                $billedToId   = null;

                if ($appliesTo === 'owner') {
                    if ($unit->owner) {
                        $billedToType = BilledToType::OWNER->value;
                        $billedToId   = $unit->owner->id;
                    }
                } elseif ($appliesTo === 'tenant') {
                    if ($occupancyType === OccupancyType::TENANT_OCCUPIED->value && $unit->currentTenant) {
                        $billedToType = BilledToType::TENANT->value;
                        $billedToId   = $unit->currentTenant->id;
                    }
                } elseif ($appliesTo === 'either') {
                    // Bill the current occupant: tenant if tenant_occupied, else owner
                    if ($occupancyType === OccupancyType::TENANT_OCCUPIED->value && $unit->currentTenant) {
                        $billedToType = BilledToType::TENANT->value;
                        $billedToId   = $unit->currentTenant->id;
                    } elseif ($unit->owner) {
                        $billedToType = BilledToType::OWNER->value;
                        $billedToId   = $unit->owner->id;
                    }
                }

                $recipientName = ($billedToType === BilledToType::TENANT->value)
                    ? $unit->currentTenant?->full_name
                    : $unit->owner?->full_name;

                if ($billedToType && $billedToId && $config->amount > 0) {
                    $invoicesToCreate[] = [
                        'charge_type'    => $chargeType,
                        'billed_to_type' => $billedToType,
                        'billed_to_id'   => $billedToId,
                        'recipient_name' => $recipientName,
                        'amount'         => $config->amount,
                        'unit'           => $unit,
                        'label'          => $chargeType->name,
                    ];
                }
            }

            // 4. Check for duplicates and build final list
            foreach ($invoicesToCreate as $invoiceSpec) {
                $duplicate = Invoice::where('unit_id', $unit->id)
                    ->where('charge_type_id', $invoiceSpec['charge_type']->id)
                    ->where('billing_period', $billingPeriodDate)
                    ->where('billed_to_type', $invoiceSpec['billed_to_type'])
                    ->where('billed_to_id', $invoiceSpec['billed_to_id'])
                    ->exists();

                $previewRow = [
                    'unit_number'    => $unit->unit_number,
                    'charge_type'    => $invoiceSpec['label'],
                    'billed_to_type' => $invoiceSpec['billed_to_type'],
                    'recipient_name' => $invoiceSpec['recipient_name'] ?? null,
                    'amount'         => $invoiceSpec['amount'],
                    'duplicate'      => $duplicate,
                ];

                if (!$duplicate) {
                    if (!$isDryRun) {
                        Invoice::create([
                            'unit_id'            => $unit->id,
                            'charge_type_id'     => $invoiceSpec['charge_type']->id,
                            'billed_to_type'     => $invoiceSpec['billed_to_type'],
                            'billed_to_id'       => $invoiceSpec['billed_to_id'],
                            'amount'             => $invoiceSpec['amount'],
                            'billing_period'     => $billingPeriodDate,
                            'due_date'           => $billingPeriod->copy()->addDays(7)->format('Y-m-d'),
                            'status'             => InvoiceStatus::UNPAID->value,
                            'invoice_number'     => $this->generateInvoiceNumber($user->tenant_id),
                            'tenant_id'          => $user->tenant_id,
                            'issued_by_type'     => 'user',
                            'issued_by_user_id'  => $user->id,
                        ]);
                        $created++;
                        $affectedUnits[$unit->id] = $unit;
                    }
                }

                $preview[] = $previewRow;
            }
        }

        // Recalculate stored balance for every unit that got new invoices.
        foreach ($affectedUnits ?? [] as $affectedUnit) {
            $this->unitBalance->recalculate($affectedUnit);
        }

        return [
            'preview'        => $preview,
            'created'        => $created,
            'billing_period' => $billingPeriod->format('Y-m'),
            'dry_run'        => $isDryRun,
            'message'        => $isDryRun
                ? count(array_filter($preview, fn($r) => !$r['duplicate'])) . ' invoices would be generated'
                : "{$created} invoices generated for {$billingPeriod->format('F Y')}",
        ];
    }

    /**
     * Create ad-hoc invoices for a non-recurring charge type across selected units.
     *
     * @param array $data
     * @return array
     * @throws Exception
     */
    public function createAdhocBilling(array $data): array
    {
        $user = Auth::user();

        $estate = Estate::where('id', $data['estate_id'])
            ->where('tenant_id', $user->tenant_id)
            ->firstOrFail();

        $chargeType = ChargeType::where('id', $data['charge_type_id'])
            ->where('tenant_id', $user->tenant_id)
            ->firstOrFail();

        if ($chargeType->is_recurring) {
            throw new Exception('Ad-hoc billing is only available for non-recurring charge types. Use Run Billing for recurring charges.');
        }

        $billingPeriod = Carbon::parse(($data['billing_period'] ?? now()->format('Y-m')) . '-01');
        $billingPeriodDate = $billingPeriod->format('Y-m-d');

        $query = Unit::where('estate_id', $estate->id)
            ->where('status', 'active')
            ->with(['owner', 'currentTenant']);

        if (!empty($data['unit_ids'])) {
            $query->whereIn('id', $data['unit_ids']);
        }

        $units  = $query->get();
        $count  = 0;
        $preview = [];

        $appliesTo = $chargeType->applies_to instanceof \App\Enums\ChargeTypeAppliesTo
            ? $chargeType->applies_to->value
            : (string) $chargeType->applies_to;

        foreach ($units as $unit) {
            $occupancyType = $unit->occupancy_type instanceof OccupancyType
                ? $unit->occupancy_type->value
                : (string) $unit->occupancy_type;

            $billedToType = null;
            $billedToId   = null;

            if ($appliesTo === 'owner' && $unit->owner) {
                $billedToType = BilledToType::OWNER->value;
                $billedToId   = $unit->owner->id;
            } elseif ($appliesTo === 'tenant') {
                if ($occupancyType === OccupancyType::TENANT_OCCUPIED->value && $unit->currentTenant) {
                    $billedToType = BilledToType::TENANT->value;
                    $billedToId   = $unit->currentTenant->id;
                }
            } elseif ($appliesTo === 'either') {
                if ($occupancyType === OccupancyType::TENANT_OCCUPIED->value && $unit->currentTenant) {
                    $billedToType = BilledToType::TENANT->value;
                    $billedToId   = $unit->currentTenant->id;
                } elseif ($unit->owner) {
                    $billedToType = BilledToType::OWNER->value;
                    $billedToId   = $unit->owner->id;
                }
            }

            if (!$billedToType || !$billedToId) {
                continue;
            }

            Invoice::create([
                'unit_id'            => $unit->id,
                'charge_type_id'     => $chargeType->id,
                'billed_to_type'     => $billedToType,
                'billed_to_id'       => $billedToId,
                'amount'             => $data['amount'],
                'billing_period'     => $billingPeriodDate,
                'due_date'           => $billingPeriod->copy()->addDays(7)->format('Y-m-d'),
                'status'             => InvoiceStatus::UNPAID->value,
                'invoice_number'     => $this->generateInvoiceNumber($user->tenant_id),
                'tenant_id'          => $user->tenant_id,
                'issued_by_type'     => 'user',
                'issued_by_user_id'  => $user->id,
            ]);

            $this->unitBalance->recalculate($unit);

            $preview[] = [
                'unit_number'    => $unit->unit_number,
                'charge_type'    => $chargeType->name,
                'billed_to_type' => $billedToType,
                'amount'         => $data['amount'],
            ];

            $count++;
        }

        return [
            'created'        => $count,
            'preview'        => $preview,
            'billing_period' => $billingPeriod->format('Y-m'),
            'message'        => "{$count} invoices created",
        ];
    }

    /**
     * Return a single invoice resource with its relationships loaded.
     *
     * @param Invoice $invoice
     * @return InvoiceResource
     */
    public function showInvoice(Invoice $invoice): InvoiceResource
    {
        $invoice->load(['unit.estate', 'chargeType', 'cashbookEntries', 'billedToOwner', 'billedToUnitTenant', 'emailEvents', 'issuedBy']);

        return $this->showResource($invoice);
    }

    /**
     * Update an invoice's attributes.
     *
     * @param Invoice $invoice
     * @param array   $data
     * @return array
     */
    public function updateInvoice(Invoice $invoice, array $data): array
    {
        $updateData = collect($data)
            ->only(['status', 'due_date', 'amount', 'billing_period'])
            ->filter(fn($v) => !is_null($v))
            ->toArray();

        if (!empty($updateData['billing_period'])) {
            $updateData['billing_period'] = Carbon::parse($updateData['billing_period'])->startOfMonth()->format('Y-m-d');
        }

        $invoice->update($updateData);

        $this->unitBalance->recalculate($invoice->unit);

        return $this->showUpdatedResource($invoice);
    }

    /**
     * Send (or resend) the invoice email via Resend and log the tracking event.
     *
     * @param Invoice $invoice
     * @return array
     * @throws Exception
     */
    public function resendInvoice(Invoice $invoice): array
    {
        $invoice->load(['unit.estate', 'chargeType', 'billedToOwner', 'billedToUnitTenant']);

        $billedTo = $invoice->billed_to_type->value === BilledToType::OWNER->value
            ? $invoice->billedToOwner
            : $invoice->billedToUnitTenant;

        if (!$billedTo || !$billedTo->email) {
            throw new Exception('No email address found for the invoice recipient.');
        }

        $html = view('emails.invoice', [
            'invoice'  => $invoice,
            'billedTo' => $billedTo,
        ])->render();

        $from = config('mail.from.name') . ' <' . config('mail.from.address') . '>';

        $response = Resend::emails()->send([
            'from'    => $from,
            'to'      => [$billedTo->email],
            'subject' => "Invoice {$invoice->invoice_number} — {$invoice->chargeType->name}",
            'html'    => $html,
        ]);

        $resendEmailId = $response->id ?? null;

        // Clear previous tracking events so the UI always shows the current send cycle
        InvoiceEmailEvent::where('invoice_id', $invoice->id)->delete();

        InvoiceEmailEvent::create([
            'invoice_id'      => $invoice->id,
            'tenant_id'       => $invoice->tenant_id,
            'event_type'      => 'sent',
            'email'           => $billedTo->email,
            'resend_email_id' => $resendEmailId,
            'occurred_at'     => now(),
        ]);

        $invoice->update(['sent_at' => now()]);

        return ['message' => 'Invoice sent successfully'];
    }

    /**
     * Generate and stream a branded PDF for this invoice.
     *
     * @param Invoice $invoice
     * @return Response
     */
    public function downloadPdf(Invoice $invoice): Response
    {
        $invoice->load(['unit.estate', 'chargeType', 'billedToOwner', 'billedToUnitTenant']);

        $billedTo = $invoice->billed_to_type->value === BilledToType::OWNER->value
            ? $invoice->billedToOwner
            : $invoice->billedToUnitTenant;

        $pdf = Pdf::loadView('pdfs.invoice', [
            'invoice'  => $invoice,
            'billedTo' => $billedTo,
        ])->setPaper('a4');

        return $pdf->download("{$invoice->invoice_number}.pdf");
    }

    /**
     * Return a paginated list of soft-deleted invoices for the authenticated tenant.
     *
     * @param array $data
     * @return InvoiceResources
     */
    public function showDeletedInvoices(array $data): InvoiceResources
    {
        $user  = Auth::user();
        $query = Invoice::onlyTrashed()
            ->where('tenant_id', $user->tenant_id)
            ->with(['unit.estate', 'chargeType', 'billedToOwner', 'billedToUnitTenant'])
            ->latest('deleted_at');

        if (!empty($data['search'])) {
            $term = '%' . $data['search'] . '%';
            $query->where('invoice_number', 'ilike', $term);
        }

        return $this->setQuery($query)->getOutput();
    }

    /**
     * Restore a soft-deleted invoice.
     *
     * @param Invoice $invoice
     * @return array
     */
    public function restoreInvoice(Invoice $invoice): array
    {
        $unit = $invoice->unit;

        $invoice->restore();

        $this->unitBalance->recalculate($unit);

        return ['message' => 'Invoice restored successfully'];
    }

    /**
     * Permanently delete an invoice (force delete, bypasses soft-delete).
     *
     * @param Invoice $invoice
     * @return array
     */
    public function forceDeleteInvoice(Invoice $invoice): array
    {
        $unit = $invoice->unit;

        $invoice->forceDelete();

        $this->unitBalance->recalculate($unit);

        return ['message' => 'Invoice permanently deleted'];
    }

    /**
     * Bulk delete invoices by an array of IDs.
     *
     * @param array $ids
     * @return array
     * @throws Exception
     */
    public function deleteInvoices(array $ids): array
    {
        $user     = Auth::user();
        $invoices = Invoice::whereIn('id', $ids)
            ->where('tenant_id', $user->tenant_id)
            ->with('unit')
            ->get();

        $total = $invoices->count();

        if ($total === 0) {
            throw new Exception('No Invoices deleted');
        }

        $affectedUnits = [];
        foreach ($invoices as $invoice) {
            $affectedUnits[$invoice->unit_id] = $invoice->unit;
            $invoice->delete();
        }

        foreach ($affectedUnits as $unit) {
            $this->unitBalance->recalculate($unit);
        }

        $label = $total === 1 ? 'Invoice' : 'Invoices';

        return ['message' => "{$total} {$label} deleted"];
    }

    /**
     * Delete a single invoice.
     *
     * @param Invoice $invoice
     * @return array
     */
    public function deleteInvoice(Invoice $invoice): array
    {
        $unit    = $invoice->unit;
        $deleted = $invoice->delete();

        if ($deleted) {
            $this->unitBalance->recalculate($unit);
        }

        return [
            'deleted' => $deleted,
            'message' => $deleted ? 'Invoice deleted' : 'Invoice delete unsuccessful',
        ];
    }

    /**
     * Generate a sequential invoice number for the tenant.
     * Format: INV-{YEAR}-{ZERO_PADDED_COUNT}
     *
     * @param string $tenantId
     * @return string
     */
    private function generateInvoiceNumber(string $tenantId): string
    {
        $year   = date('Y');
        $prefix = 'INV-' . $year . '-';

        $max = Invoice::where('tenant_id', $tenantId)
            ->where('invoice_number', 'like', $prefix . '%')
            ->withTrashed()
            ->max('invoice_number');

        $next = $max ? ((int) substr($max, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
