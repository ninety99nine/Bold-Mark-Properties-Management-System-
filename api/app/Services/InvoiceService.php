<?php

namespace App\Services;

use Exception;
use App\Models\Community;
use App\Models\Unit;
use App\Models\Invoice;
use App\Models\Ledger;
use App\Models\InvoiceEmailEvent;
use App\Enums\InvoiceStatus;
use App\Enums\BilledToType;
use App\Enums\OccupancyType;
use App\Enums\SystemLedger;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendInvoiceEmail;
use Resend\Laravel\Facades\Resend;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\InvoiceResources;
use App\Notifications\BillingRunCompleted;
use Illuminate\Support\Facades\Notification;

class InvoiceService extends BaseService
{
    protected array $allowedRelationships = ['unit', 'ledger', 'bankAccount', 'items', 'billedToOwner', 'billedToUnitOccupant', 'cashbookEntries'];

    protected array $allowedCountableRelationships = ['cashbookEntries'];

    public function __construct(private readonly UnitBalanceService $unitBalance)
    {
        parent::__construct();
    }


    /**
     * Return a paginated, filtered list of invoices for the authenticated occupant.
     *
     * @param array $data
     * @return InvoiceResources
     */
    public function showInvoices(array $data): InvoiceResources
    {
        $user  = Auth::user();
        $query = Invoice::where('organization_id', $user->organization_id)
            ->with(['unit.community', 'ledger', 'billedToOwner', 'billedToUnitOccupant', 'emailEvents']);

        if (!empty($data['country'])) {
            $query->whereHas('unit.community', fn($q) => $q->where('country', $data['country']));
        }

        if (!empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        if (!empty($data['ledger_id'])) {
            $query->where('ledger_id', $data['ledger_id']);
        }

        if (!empty($data['unit_id'])) {
            $query->where('unit_id', $data['unit_id']);
        }

        if (!empty($data['community_id'])) {
            $query->whereHas('unit', fn($q) => $q->where('community_id', $data['community_id']));
        }

        if (!empty($data['billed_to_type'])) {
            $query->where('billed_to_type', $data['billed_to_type']);
        }

        if (!empty($data['billed_to_id'])) {
            $query->where('billed_to_id', $data['billed_to_id']);
        }

        if (!empty($data['billing_period'])) {
            $query->whereDate('billing_period', Carbon::parse($data['billing_period'] . '-01')->format('Y-m-d'));
        }

        if (!empty($data['search'])) {
            $search = $data['search'];
            $query->where(function ($q) use ($search) {
                $q->whereLike('invoice_number', $search)
                  ->orWhereHas('unit', fn($u) => $u->whereLike('unit_number', $search))
                  ->orWhereHas('ledger', fn($ct) => $ct->whereLike('name', $search))
                  ->orWhere(function ($sub) use ($search) {
                      $sub->where('billed_to_type', 'owner')
                          ->whereHas('billedToOwner', fn($o) => $o->whereLike('full_name', $search));
                  })
                  ->orWhere(function ($sub) use ($search) {
                      $sub->where('billed_to_type', 'occupant')
                          ->whereHas('billedToUnitOccupant', fn($t) => $t->whereLike('full_name', $search));
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
        $query = Invoice::where('organization_id', $user->organization_id)
            ->with(['unit.community', 'ledger', 'billedToOwner', 'billedToUnitOccupant']);

        if (!empty($data['country'])) {
            $query->whereHas('unit.community', fn($q) => $q->where('country', $data['country']));
        }

        if (!empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (!empty($data['ledger_id'])) {
            $query->where('ledger_id', $data['ledger_id']);
        }
        if (!empty($data['unit_id'])) {
            $query->where('unit_id', $data['unit_id']);
        }
        if (!empty($data['community_id'])) {
            $query->whereHas('unit', fn($q) => $q->where('community_id', $data['community_id']));
        }
        if (!empty($data['billed_to_type'])) {
            $query->where('billed_to_type', $data['billed_to_type']);
        }
        if (!empty($data['billed_to_id'])) {
            $query->where('billed_to_id', $data['billed_to_id']);
        }
        if (!empty($data['billing_period'])) {
            $query->whereDate('billing_period', Carbon::parse($data['billing_period'] . '-01')->format('Y-m-d'));
        }
        if (!empty($data['search'])) {
            $search = $data['search'];
            $query->where(function ($q) use ($search) {
                $q->whereLike('invoice_number', $search)
                  ->orWhereHas('unit', fn($u) => $u->whereLike('unit_number', $search))
                  ->orWhereHas('ledger', fn($ct) => $ct->whereLike('name', $search))
                  ->orWhere(function ($sub) use ($search) {
                      $sub->where('billed_to_type', 'owner')
                          ->whereHas('billedToOwner', fn($o) => $o->whereLike('full_name', $search));
                  })
                  ->orWhere(function ($sub) use ($search) {
                      $sub->where('billed_to_type', 'occupant')
                          ->whereHas('billedToUnitOccupant', fn($t) => $t->whereLike('full_name', $search));
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

        $headings = ['Invoice #', 'Community', 'Unit', 'Ledger', 'Billed To', 'Period', 'Amount', 'Status', 'Due Date', 'Sent At'];

        $rows = $invoices->map(function ($invoice) {
            $billedToType = $invoice->billed_to_type instanceof \BackedEnum
                ? $invoice->billed_to_type->value
                : (string) $invoice->billed_to_type;

            $billedTo = $billedToType === 'owner'
                ? $invoice->billedToOwner?->full_name
                : $invoice->billedToUnitOccupant?->full_name;

            $status = $invoice->status instanceof \BackedEnum
                ? $invoice->status->value
                : (string) $invoice->status;

            return [
                $invoice->invoice_number,
                $invoice->unit?->community?->name,
                $invoice->unit?->unit_number,
                $invoice->ledger?->name,
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
        $query = Invoice::where('organization_id', $user->organization_id);

        if (!empty($data['country'])) {
            $query->whereHas('unit.community', fn($q) => $q->where('country', $data['country']));
        }

        if (!empty($data['community_id'])) {
            $query->whereHas('unit', fn($q) => $q->where('community_id', $data['community_id']));
        }

        if (!empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        if (!empty($data['ledger_id'])) {
            $query->where('ledger_id', $data['ledger_id']);
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

        // Revenue-by-ledger comes from two sources that we merge by account:
        //   1. System / single-charge invoices → the header ledger + header amount.
        //   2. Manual (multi-line) customer invoices → each invoice_item's ledger +
        //      line_total, since a manual invoice has no single header account.
        $applyScopeFilters = function ($q, string $unitColumn) use ($data) {
            if (!empty($data['country'])) {
                $unitIds = Unit::whereHas('community', fn ($c) => $c->where('country', $data['country']))->pluck('id');
                $q->whereIn($unitColumn, $unitIds);
            }
            if (!empty($data['community_id'])) {
                $unitIds = Unit::where('community_id', $data['community_id'])->pluck('id');
                $q->whereIn($unitColumn, $unitIds);
            }
            if (!empty($data['status'])) {
                $q->where('invoices.status', $data['status']);
            }
        };

        $systemRevenue = DB::table('invoices')
            ->join('ledgers', 'invoices.ledger_id', '=', 'ledgers.id')
            ->where('invoices.organization_id', $user->organization_id)
            ->tap(fn ($q) => $applyScopeFilters($q, 'invoices.unit_id'))
            ->when(!empty($data['ledger_id']), fn ($q) => $q->where('invoices.ledger_id', $data['ledger_id']))
            ->select('ledgers.id as ledger_id', 'ledgers.name', DB::raw('SUM(invoices.amount) as total_amount'))
            ->groupBy('ledgers.id', 'ledgers.name')
            ->get();

        $manualRevenue = DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('ledgers', 'invoice_items.ledger_id', '=', 'ledgers.id')
            ->where('invoices.organization_id', $user->organization_id)
            ->whereNull('invoices.deleted_at')
            ->tap(fn ($q) => $applyScopeFilters($q, 'invoices.unit_id'))
            ->when(!empty($data['ledger_id']), fn ($q) => $q->where('invoice_items.ledger_id', $data['ledger_id']))
            ->select('ledgers.id as ledger_id', 'ledgers.name', DB::raw('SUM(invoice_items.line_total) as total_amount'))
            ->groupBy('ledgers.id', 'ledgers.name')
            ->get();

        // Merge the two streams by ledger, then sort by combined total desc.
        $merged = [];
        foreach ($systemRevenue->concat($manualRevenue) as $row) {
            $key = $row->ledger_id;
            if (!isset($merged[$key])) {
                $merged[$key] = ['name' => $row->name, 'total' => 0.0];
            }
            $merged[$key]['total'] += (float) $row->total_amount;
        }

        $revenueByLedger = collect($merged)
            ->sortByDesc('total')
            ->values()
            ->all();

        return [
            'total'                  => (int) ($stats->total ?? 0),
            'total_amount'           => (float) ($stats->total_amount ?? 0),
            'paid_count'             => (int) ($stats->paid_count ?? 0),
            'overdue_count'          => (int) ($stats->overdue_count ?? 0),
            'partially_paid_count'   => (int) ($stats->partially_paid_count ?? 0),
            'unpaid_count'           => (int) ($stats->unpaid_count ?? 0),
            'revenue_by_ledger' => $revenueByLedger,
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
            ->only(['unit_id', 'ledger_id', 'billed_to_type', 'billed_to_id', 'amount', 'billing_period', 'due_date'])
            ->toArray();

        // Normalise billing_period to first day of month
        if (!empty($invoiceData['billing_period'])) {
            $invoiceData['billing_period'] = Carbon::parse($invoiceData['billing_period'])->startOfMonth()->format('Y-m-d');
        }

        // Duplicate check: same unit + ledger + billing period is not allowed
        $exists = Invoice::where('unit_id', $invoiceData['unit_id'])
            ->where('ledger_id', $invoiceData['ledger_id'])
            ->whereDate('billing_period', $invoiceData['billing_period'])
            ->exists();

        if ($exists) {
            $ledger    = Ledger::find($invoiceData['ledger_id']);
            $ledgerName = $ledger?->name ?? 'this ledger';
            $period        = Carbon::parse($invoiceData['billing_period'])->format('F Y');
            throw new Exception("An invoice for {$ledgerName} already exists for {$period}. Duplicate invoices are not allowed.");
        }

        $invoice = Invoice::create(array_merge($invoiceData, [
            'organization_id'          => $user->organization_id,
            'invoice_number'     => $this->generateInvoiceNumber($user->organization_id),
            'status'             => InvoiceStatus::UNPAID->value,
            'issued_by_type'     => 'user',
            'issued_by_user_id'  => $user->id,
        ]));

        $this->unitBalance->recalculate($invoice->unit);

        return $this->showCreatedResource($invoice);
    }

    /**
     * Create a WeConnectU-style multi-line customer invoice.
     *
     * The invoice header caches the rolled-up subtotal / VAT / total so downstream
     * finance code keeps reading the single `amount` column, while the individual
     * account lines are stored as invoice_items. The invoice is billed to the unit's
     * primary owner, falling back to the current occupant.
     *
     * @param array $data
     * @return array
     * @throws Exception
     */
    public function createCustomerInvoice(array $data): array
    {
        $user = Auth::user();

        $unit = Unit::where('id', $data['unit_id'])
            ->where('organization_id', $user->organization_id)
            ->with(['owner', 'currentOccupant'])
            ->firstOrFail();

        [$billedToType, $billedToId] = $this->resolveBilledTo($unit);

        if (!$billedToType || !$billedToId) {
            throw new Exception('This unit has no owner or occupant to bill.');
        }

        // Validate the bank account (when supplied) belongs to the organization.
        if (!empty($data['bank_account_id'])) {
            \App\Models\BankAccount::where('id', $data['bank_account_id'])
                ->where('organization_id', $user->organization_id)
                ->firstOrFail();
        }

        $invoiceDate   = Carbon::parse($data['invoice_date']);
        $billingPeriod = $invoiceDate->copy()->startOfMonth()->format('Y-m-d');

        // Roll up line-item totals.
        $subtotal = 0.0;
        $vatTotal = 0.0;
        $items    = [];

        foreach (array_values($data['items']) as $index => $row) {
            $quantity = (float) $row['quantity'];
            $unitAmt  = (float) $row['amount'];
            $taxRate  = (float) ($row['tax_rate'] ?? 0);

            $lineSubtotal = round($quantity * $unitAmt, 2);
            $taxAmount    = round($lineSubtotal * $taxRate / 100, 2);
            $lineTotal    = round($lineSubtotal + $taxAmount, 2);

            $subtotal += $lineSubtotal;
            $vatTotal += $taxAmount;

            $items[] = [
                'ledger_id'   => $row['ledger_id'],
                'description' => $row['description'] ?? null,
                'quantity'    => $quantity,
                'amount'      => $unitAmt,
                'tax_rate'    => $taxRate,
                'tax_amount'  => $taxAmount,
                'line_total'  => $lineTotal,
                'sort_order'  => $index,
            ];
        }

        $subtotal   = round($subtotal, 2);
        $vatTotal   = round($vatTotal, 2);
        $grandTotal = round($subtotal + $vatTotal, 2);

        // Persist the optional uploaded source document.
        $attachmentPath = null;
        if (request()->hasFile('attachment')) {
            $attachmentPath = request()->file('attachment')
                ->store("invoice-attachments/{$user->organization_id}", 'public');
        }

        $invoice = DB::transaction(function () use (
            $user, $unit, $data, $billedToType, $billedToId, $billingPeriod,
            $invoiceDate, $subtotal, $vatTotal, $grandTotal, $attachmentPath, $items
        ) {
            $invoice = Invoice::create([
                'unit_id'           => $unit->id,
                'ledger_id'         => null, // multi-line: accounts live on invoice_items
                'bank_account_id'   => $data['bank_account_id'] ?? null,
                'billed_to_type'    => $billedToType,
                'billed_to_id'      => $billedToId,
                'amount'            => $grandTotal,
                'subtotal'          => $subtotal,
                'vat_amount'        => $vatTotal,
                'billing_period'    => $billingPeriod,
                'invoice_date'      => $invoiceDate->format('Y-m-d'),
                'due_date'          => Carbon::parse($data['due_date'])->format('Y-m-d'),
                'attachment_path'   => $attachmentPath,
                'status'            => InvoiceStatus::UNPAID->value,
                'source'            => \App\Enums\InvoiceSource::MANUAL->value,
                'invoice_number'    => $this->generateInvoiceNumber($user->organization_id),
                'organization_id'   => $user->organization_id,
                'issued_by_type'    => 'user',
                'issued_by_user_id' => $user->id,
            ]);

            foreach ($items as $item) {
                $invoice->items()->create($item);
            }

            return $invoice;
        });

        $this->unitBalance->recalculate($unit);

        // Optionally e-mail the invoice to the billed-to recipient.
        if (!empty($data['email_invoice'])) {
            SendInvoiceEmail::dispatch($invoice->id);
        }

        $invoice->load([
            'unit.community', 'ledger', 'bankAccount', 'items.ledger',
            'billedToOwner', 'billedToUnitOccupant',
        ]);

        return $this->showCreatedResource($invoice);
    }

    /**
     * Resolve the billed-to entity for a customer invoice: primary owner first,
     * then the current occupant.
     *
     * @param Unit $unit
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveBilledTo(Unit $unit): array
    {
        if ($unit->owner) {
            return [BilledToType::OWNER->value, $unit->owner->id];
        }

        if ($unit->currentOccupant) {
            return [BilledToType::OCCUPANT->value, $unit->currentOccupant->id];
        }

        return [null, null];
    }

    /**
     * Execute the billing engine for an community and billing period.
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

        $community = Community::where('id', $data['community_id'])
            ->where('organization_id', $user->organization_id)
            ->firstOrFail();

        return $this->runBillingForCommunity($community, $data['billing_period'], $isDryRun, $user);
    }

    public function runBillingForCommunity(Community $community, string $billingPeriodYearMonth, bool $isDryRun = false, ?\App\Models\User $actor = null): array
    {
        $user = $actor ?? Auth::user();

        $billingPeriod = Carbon::parse($billingPeriodYearMonth . '-01');
        $billingPeriodDate = $billingPeriod->format('Y-m-d');
        $paymentTermsDays = $community->payment_terms_days ?? 7;

        // Load active units with all needed relationships
        $units = Unit::where('community_id', $community->id)
            ->where('status', 'active')
            ->with([
                'owner',
                'currentOccupant',
                'activeChargeConfigs.ledger',
                'community.activeLedgers',
            ])
            ->get();

        $preview     = [];
        $created     = 0;
        $createdIds  = [];

        // Get system ledgers for this community (from community's active ledgers)
        $communityLedgers  = $community->activeLedgers;
        $levyLedger     = $communityLedgers->firstWhere('type', SystemLedger::ADMIN_LEVY->value);
        $reserveLevyType    = $communityLedgers->firstWhere('type', SystemLedger::RESERVE_LEVY->value);
        $csosLevyType       = $communityLedgers->firstWhere('type', SystemLedger::CSOS_LEVY->value);
        $rentLedger     = $communityLedgers->firstWhere('type', SystemLedger::RENT->value);

        // Pre-compute PQ totals for the billing run to avoid N+1 queries
        $totalPq         = $units->whereNotNull('pq')->sum('pq');
        $adminBudget     = (float) ($community->admin_fund_amount ?? 0);
        $reserveBudget   = (float) ($community->reserve_fund_amount ?? 0);
        $csosPerUnit     = (float) ($community->csos_levy_amount ?? 0);

        foreach ($units as $unit) {
            $invoicesToCreate = [];

            // 1a. Admin levy invoice → owner, PQ-based or equal-share fallback
            if ($levyLedger && $unit->owner) {
                $levyAmount = $unit->levy_override;

                if ($levyAmount === null && $adminBudget > 0) {
                    $levyAmount = ($totalPq > 0 && $unit->pq !== null)
                        ? round(($unit->pq / $totalPq) * $adminBudget, 2)
                        : ($units->count() > 0 ? round($adminBudget / $units->count(), 2) : 0);
                }

                if ($levyAmount > 0) {
                    $invoicesToCreate[] = [
                        'ledger'    => $levyLedger,
                        'billed_to_type' => BilledToType::OWNER->value,
                        'billed_to_id'   => $unit->owner->id,
                        'recipient_name' => $unit->owner->full_name,
                        'amount'         => $levyAmount,
                        'unit'           => $unit,
                        'label'          => 'Admin Levy',
                    ];
                }
            }

            // 1b. Reserve levy invoice → owner, PQ-based only (no equal-share fallback)
            if ($reserveLevyType && $unit->owner && $reserveBudget > 0 && $totalPq > 0 && $unit->pq !== null) {
                $reserveAmount = round(($unit->pq / $totalPq) * $reserveBudget, 2);

                if ($reserveAmount > 0) {
                    $invoicesToCreate[] = [
                        'ledger'    => $reserveLevyType,
                        'billed_to_type' => BilledToType::OWNER->value,
                        'billed_to_id'   => $unit->owner->id,
                        'recipient_name' => $unit->owner->full_name,
                        'amount'         => $reserveAmount,
                        'unit'           => $unit,
                        'label'          => 'Reserve Levy',
                    ];
                }
            }

            // 1c. CSOS levy invoice → owner, flat per-unit amount set on the community
            if ($csosLevyType && $unit->owner && $csosPerUnit > 0) {
                $invoicesToCreate[] = [
                    'ledger'    => $csosLevyType,
                    'billed_to_type' => BilledToType::OWNER->value,
                    'billed_to_id'   => $unit->owner->id,
                    'recipient_name' => $unit->owner->full_name,
                    'amount'         => $csosPerUnit,
                    'unit'           => $unit,
                    'label'          => 'CSOS Levy',
                ];
            }

            // 2. Rent invoice → to active occupant if occupant_occupied and rent ledger is active
            $occupancyType = $unit->occupancy_type instanceof OccupancyType
                ? $unit->occupancy_type->value
                : (string) $unit->occupancy_type;

            if (
                $rentLedger &&
                $occupancyType === OccupancyType::OCCUPANT_OCCUPIED->value &&
                $unit->currentOccupant &&
                $unit->rent_amount > 0
            ) {
                $invoicesToCreate[] = [
                    'ledger'    => $rentLedger,
                    'billed_to_type' => BilledToType::OCCUPANT->value,
                    'billed_to_id'   => $unit->currentOccupant->id,
                    'recipient_name' => $unit->currentOccupant->full_name,
                    'amount'         => $unit->rent_amount,
                    'unit'           => $unit,
                    'label'          => 'Rent',
                ];
            }

            // 3. Per-unit recurring charge configs (parking, gym, pet levy, etc.)
            foreach ($unit->activeChargeConfigs as $config) {
                $ledger = $config->ledger;

                if (!$ledger || !$ledger->is_active || !$ledger->is_recurring) {
                    continue;
                }

                $appliesTo = $ledger->applies_to instanceof \App\Enums\LedgerAppliesTo
                    ? $ledger->applies_to->value
                    : (string) $ledger->applies_to;

                // Determine recipient
                $billedToType = null;
                $billedToId   = null;

                if ($appliesTo === 'owner') {
                    if ($unit->owner) {
                        $billedToType = BilledToType::OWNER->value;
                        $billedToId   = $unit->owner->id;
                    }
                } elseif ($appliesTo === 'occupant') {
                    if ($occupancyType === OccupancyType::OCCUPANT_OCCUPIED->value && $unit->currentOccupant) {
                        $billedToType = BilledToType::OCCUPANT->value;
                        $billedToId   = $unit->currentOccupant->id;
                    }
                } elseif ($appliesTo === 'either') {
                    // Bill the current occupant: occupant if occupant_occupied, else owner
                    if ($occupancyType === OccupancyType::OCCUPANT_OCCUPIED->value && $unit->currentOccupant) {
                        $billedToType = BilledToType::OCCUPANT->value;
                        $billedToId   = $unit->currentOccupant->id;
                    } elseif ($unit->owner) {
                        $billedToType = BilledToType::OWNER->value;
                        $billedToId   = $unit->owner->id;
                    }
                }

                $recipientName = ($billedToType === BilledToType::OCCUPANT->value)
                    ? $unit->currentOccupant?->full_name
                    : $unit->owner?->full_name;

                if ($billedToType && $billedToId && $config->amount > 0) {
                    $invoicesToCreate[] = [
                        'ledger'    => $ledger,
                        'billed_to_type' => $billedToType,
                        'billed_to_id'   => $billedToId,
                        'recipient_name' => $recipientName,
                        'amount'         => $config->amount,
                        'unit'           => $unit,
                        'label'          => $ledger->name,
                    ];
                }
            }

            // 4. Check for duplicates and build final list
            foreach ($invoicesToCreate as $invoiceSpec) {
                $duplicate = Invoice::where('unit_id', $unit->id)
                    ->where('ledger_id', $invoiceSpec['ledger']->id)
                    ->whereDate('billing_period', $billingPeriodDate)
                    ->where('billed_to_type', $invoiceSpec['billed_to_type'])
                    ->where('billed_to_id', $invoiceSpec['billed_to_id'])
                    ->exists();

                $previewRow = [
                    'unit_number'    => $unit->unit_number,
                    'ledger'    => $invoiceSpec['label'],
                    'billed_to_type' => $invoiceSpec['billed_to_type'],
                    'recipient_name' => $invoiceSpec['recipient_name'] ?? null,
                    'amount'         => $invoiceSpec['amount'],
                    'duplicate'      => $duplicate,
                ];

                if (!$duplicate) {
                    if (!$isDryRun) {
                        $invoice = Invoice::create([
                            'unit_id'            => $unit->id,
                            'ledger_id'     => $invoiceSpec['ledger']->id,
                            'billed_to_type'     => $invoiceSpec['billed_to_type'],
                            'billed_to_id'       => $invoiceSpec['billed_to_id'],
                            'amount'             => $invoiceSpec['amount'],
                            'billing_period'     => $billingPeriodDate,
                            'due_date'           => now()->addDays($paymentTermsDays)->format('Y-m-d'),
                            'status'             => InvoiceStatus::UNPAID->value,
                            'invoice_number'     => $this->generateInvoiceNumber($user->organization_id),
                            'organization_id'    => $user->organization_id,
                            'issued_by_type'     => 'user',
                            'issued_by_user_id'  => $user->id,
                        ]);
                        $created++;
                        $createdIds[]             = $invoice->id;
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

        // Dispatch one job per invoice — rate limiting is enforced globally via RateLimited middleware.
        if (!$isDryRun && !empty($createdIds)) {
            foreach ($createdIds as $invoiceId) {
                SendInvoiceEmail::dispatch($invoiceId);
            }
        }

        // Notify all users assigned to this community about the completed billing run.
        if (!$isDryRun && $created > 0) {
            $usersToNotify = $community->assignedUsers()->get();
            Notification::send($usersToNotify, new BillingRunCompleted(
                $community,
                $created,
                $billingPeriod->format('F Y'),
            ));
        }

        return [
            'preview'        => $preview,
            'created'        => $created,
            'created_ids'    => $createdIds,
            'billing_period' => $billingPeriod->format('Y-m'),
            'dry_run'        => $isDryRun,
            'message'        => $isDryRun
                ? count(array_filter($preview, fn($r) => !$r['duplicate'])) . ' invoices would be generated'
                : "{$created} invoices generated for {$billingPeriod->format('F Y')}",
        ];
    }

    /**
     * Create ad-hoc invoices for a non-recurring ledger across selected units.
     *
     * @param array $data
     * @return array
     * @throws Exception
     */
    public function createAdhocBilling(array $data): array
    {
        $user = Auth::user();

        $community = Community::where('id', $data['community_id'])
            ->where('organization_id', $user->organization_id)
            ->firstOrFail();

        $ledger = Ledger::where('id', $data['ledger_id'])
            ->where('organization_id', $user->organization_id)
            ->firstOrFail();

        if ($ledger->is_recurring) {
            throw new Exception('Ad-hoc billing is only available for non-recurring ledgers. Use Run Billing for recurring charges.');
        }

        $billingPeriod = Carbon::parse(($data['billing_period'] ?? now()->format('Y-m')) . '-01');
        $billingPeriodDate = $billingPeriod->format('Y-m-d');

        $query = Unit::where('community_id', $community->id)
            ->where('status', 'active')
            ->with(['owner', 'currentOccupant']);

        if (!empty($data['unit_ids'])) {
            $query->whereIn('id', $data['unit_ids']);
        }

        $units  = $query->get();
        $count  = 0;
        $preview = [];

        $appliesTo = $ledger->applies_to instanceof \App\Enums\LedgerAppliesTo
            ? $ledger->applies_to->value
            : (string) $ledger->applies_to;

        foreach ($units as $unit) {
            $occupancyType = $unit->occupancy_type instanceof OccupancyType
                ? $unit->occupancy_type->value
                : (string) $unit->occupancy_type;

            $billedToType = null;
            $billedToId   = null;

            if ($appliesTo === 'owner' && $unit->owner) {
                $billedToType = BilledToType::OWNER->value;
                $billedToId   = $unit->owner->id;
            } elseif ($appliesTo === 'occupant') {
                if ($occupancyType === OccupancyType::OCCUPANT_OCCUPIED->value && $unit->currentOccupant) {
                    $billedToType = BilledToType::OCCUPANT->value;
                    $billedToId   = $unit->currentOccupant->id;
                }
            } elseif ($appliesTo === 'either') {
                if ($occupancyType === OccupancyType::OCCUPANT_OCCUPIED->value && $unit->currentOccupant) {
                    $billedToType = BilledToType::OCCUPANT->value;
                    $billedToId   = $unit->currentOccupant->id;
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
                'ledger_id'     => $ledger->id,
                'billed_to_type'     => $billedToType,
                'billed_to_id'       => $billedToId,
                'amount'             => $data['amount'],
                'billing_period'     => $billingPeriodDate,
                'due_date'           => now()->addDays(7)->format('Y-m-d'),
                'status'             => InvoiceStatus::UNPAID->value,
                'invoice_number'     => $this->generateInvoiceNumber($user->organization_id),
                'organization_id'          => $user->organization_id,
                'issued_by_type'     => 'user',
                'issued_by_user_id'  => $user->id,
            ]);

            $this->unitBalance->recalculate($unit);

            $preview[] = [
                'unit_number'    => $unit->unit_number,
                'ledger'    => $ledger->name,
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
        $invoice->load(['unit.community', 'ledger', 'bankAccount', 'items.ledger', 'cashbookEntries', 'billedToOwner', 'billedToUnitOccupant', 'emailEvents', 'issuedBy']);

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
        $invoice->load(['unit.community', 'ledger', 'bankAccount', 'items.ledger', 'billedToOwner', 'billedToUnitOccupant']);

        $billedTo = $invoice->billed_to_type->value === BilledToType::OWNER->value
            ? $invoice->billedToOwner
            : $invoice->billedToUnitOccupant;

        if (!$billedTo || !$billedTo->email) {
            throw new Exception('No email address found for the invoice recipient.');
        }

        $html = view('emails.invoice', [
            'invoice'  => $invoice,
            'billedTo' => $billedTo,
        ])->render();

        $from = config('mail.from.name') . ' <' . config('mail.from.address') . '>';

        // Multi-line customer invoices have no header ledger — fall back to a generic label.
        $subject = "Invoice {$invoice->invoice_number} — " . ($invoice->ledger?->name ?? 'Customer Invoice');

        if (app()->isLocal()) {
            Log::info("[local] Invoice email suppressed — would send to {$billedTo->email}", [
                'invoice' => $invoice->invoice_number,
                'subject' => $subject,
            ]);
            $resendEmailId = null;
        } else {
            $response = Resend::emails()->send([
                'from'    => $from,
                'to'      => [$billedTo->email],
                'subject' => $subject,
                'html'    => $html,
            ]);
            $resendEmailId = $response->id ?? null;
        }

        // Clear previous tracking events so the UI always shows the current send cycle
        InvoiceEmailEvent::where('invoice_id', $invoice->id)->delete();

        InvoiceEmailEvent::create([
            'invoice_id'      => $invoice->id,
            'organization_id'       => $invoice->organization_id,
            'event_type'      => 'sent',
            'email'           => $billedTo->email,
            'resend_email_id' => $resendEmailId,
            'occurred_at'     => now(),
        ]);

        $invoice->update([
            'sent_at'         => now(),
            'email_failed_at' => null,
        ]);

        return ['message' => 'Invoice sent successfully'];
    }

    /**
     * Send a payment reminder email for an overdue/unpaid invoice and record the timestamp.
     *
     * @param Invoice $invoice
     * @return array
     */
    public function sendPaymentReminder(Invoice $invoice): array
    {
        $invoice->load(['unit.community', 'ledger', 'bankAccount', 'items.ledger', 'billedToOwner', 'billedToUnitOccupant']);

        $billedTo = $invoice->billed_to_type->value === BilledToType::OWNER->value
            ? $invoice->billedToOwner
            : $invoice->billedToUnitOccupant;

        if (!$billedTo || !$billedTo->email) {
            throw new Exception('No email address found for the invoice recipient.');
        }

        $html = view('emails.payment-reminder', [
            'invoice'  => $invoice,
            'billedTo' => $billedTo,
        ])->render();

        $from = config('mail.from.name') . ' <' . config('mail.from.address') . '>';

        if (app()->isLocal()) {
            Log::info("[local] Payment reminder email suppressed — would send to {$billedTo->email}", [
                'invoice' => $invoice->invoice_number,
                'subject' => "Payment Reminder — Invoice {$invoice->invoice_number}",
            ]);
            $resendEmailId = null;
        } else {
            $response = Resend::emails()->send([
                'from'    => $from,
                'to'      => [$billedTo->email],
                'subject' => "Payment Reminder — Invoice {$invoice->invoice_number}",
                'html'    => $html,
            ]);
            $resendEmailId = $response->id ?? null;
        }

        InvoiceEmailEvent::create([
            'invoice_id'      => $invoice->id,
            'organization_id' => $invoice->organization_id,
            'event_type'      => 'reminder_sent',
            'email'           => $billedTo->email,
            'resend_email_id' => $resendEmailId,
            'occurred_at'     => now(),
        ]);

        $invoice->update(['reminder_sent_at' => now()]);

        return ['message' => 'Payment reminder sent successfully'];
    }

    /**
     * Generate and stream a branded PDF for this invoice.
     *
     * @param Invoice $invoice
     * @return Response
     */
    public function downloadPdf(Invoice $invoice): Response
    {
        $invoice->load(['unit.community', 'ledger', 'bankAccount', 'items.ledger', 'billedToOwner', 'billedToUnitOccupant']);

        $billedTo = $invoice->billed_to_type->value === BilledToType::OWNER->value
            ? $invoice->billedToOwner
            : $invoice->billedToUnitOccupant;

        $organization = $invoice->organization;

        $pdf = Pdf::loadView('pdfs.invoice', [
            'invoice'         => $invoice,
            'billedTo'        => $billedTo,
            'organization'    => $organization,
            'companyLogoPath' => $organization?->logoFilePath(),
        ])->setPaper('a4');

        return $pdf->download("{$invoice->invoice_number}.pdf");
    }

    /**
     * Return a paginated list of soft-deleted invoices for the authenticated occupant.
     *
     * @param array $data
     * @return InvoiceResources
     */
    public function showDeletedInvoices(array $data): InvoiceResources
    {
        $user  = Auth::user();
        $query = Invoice::onlyTrashed()
            ->where('organization_id', $user->organization_id)
            ->with(['unit.community', 'ledger', 'billedToOwner', 'billedToUnitOccupant'])
            ->latest('deleted_at');

        if (!empty($data['search'])) {
            $query->whereLike('invoice_number', $data['search']);
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
            ->where('organization_id', $user->organization_id)
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
     * Generate a sequential invoice number for the occupant.
     * Format: INV-{YEAR}-{ZERO_PADDED_COUNT}
     *
     * @param string $organizationId
     * @return string
     */
    private function generateInvoiceNumber(string $organizationId): string
    {
        $year   = date('Y');
        $prefix = 'INV-' . $year . '-';

        $max = Invoice::where('organization_id', $organizationId)
            ->where('invoice_number', 'like', $prefix . '%')
            ->withTrashed()
            ->max('invoice_number');

        $next = $max ? ((int) substr($max, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
