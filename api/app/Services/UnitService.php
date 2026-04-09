<?php

namespace App\Services;

use Exception;
use App\Models\Estate;
use App\Models\Owner;
use App\Models\Unit;
use App\Models\UnitActivity;
use App\Models\UnitTenant;
use App\Enums\OccupancyType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\CashbookEntry;
use App\Models\Invoice;
use App\Http\Resources\UnitResource;
use App\Http\Resources\UnitResources;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\IOFactory;

class UnitService extends BaseService
{
    /**
     * Return a paginated, filtered list of units for the given estate.
     *
     * Supported query parameters:
     *   _search           → unit_number or owner name full-text search
     *   _sort             → unit_number:asc | unit_number:desc | owner_name:asc |
     *                       owner_name:desc | outstanding_amount:asc | outstanding_amount:desc
     *   _date_range       → today | this_week | this_month | this_year | custom | all_time
     *   _date_range_start → Y-m-d (used when _date_range = custom)
     *   _date_range_end   → Y-m-d (used when _date_range = custom)
     *   occupancy_type    → owner_occupied | tenant_occupied | vacant
     *   status            → active | suspended | vacated
     *   balance           → in_arrears | clear
     *
     * @param Estate $estate
     * @param array  $data
     * @return UnitResources
     */
    public function showUnits(Estate $estate, array $data): UnitResources
    {
        $query = Unit::where('units.estate_id', $estate->id)
            ->with(['owner', 'currentTenant'])
            ->withCount(['unitTenants as total_tenants_count']);

        // --- Filters ---

        if (!empty($data['occupancy_type'])) {
            $query->where('units.occupancy_type', $data['occupancy_type']);
        }

        if (!empty($data['status'])) {
            $query->where('units.status', $data['status']);
        }

        // --- Balance subqueries (always added so resource/sort work correctly) -----------
        //
        // outstanding_amount = sum of truly-owed amounts per invoice:
        //   invoice.amount minus any cashbook entries already allocated to that invoice.
        //   GREATEST(0,...) prevents a fully-paid invoice that still sits in partially_paid
        //   status from contributing a negative number.
        //
        // unallocated_credits = sum of cashbook credit entries for this unit that have
        //   not yet been matched to any invoice (advance payments, overpayments, etc.).
        //
        // balance  = unallocated_credits - outstanding_amount
        //   negative  → unit is in arrears
        //   zero      → unit is clear
        //   positive  → unit has a credit on account

        $query->addSelect([
            'units.*',
            'outstanding_amount' => Invoice::selectRaw(
                "COALESCE(SUM(GREATEST(0, invoices.amount - COALESCE((SELECT SUM(ce.amount) FROM cashbook_entries ce WHERE ce.invoice_id = invoices.id), 0))), 0)"
            )
                ->whereColumn('invoices.unit_id', 'units.id')
                ->whereIn('invoices.status', ['unpaid', 'overdue', 'partially_paid']),
            'unallocated_credits' => CashbookEntry::selectRaw('COALESCE(SUM(amount), 0)')
                ->whereColumn('unit_id', 'units.id')
                ->whereNull('invoice_id')
                ->where('type', 'credit'),
        ]);

        // --- Balance filter (uses stored units.balance column — indexed, no subquery) ---
        //
        // units.balance = unallocated_credits − outstanding_amount (kept in sync by UnitBalanceService)
        // 'in_arrears' → balance < 0  (owes more than credits cover)
        // 'clear'      → balance >= 0 (credits cover everything or nothing owed)

        if (!empty($data['balance'])) {
            if ($data['balance'] === 'in_arrears') {
                $query->where('units.balance', '<', 0);
            } elseif ($data['balance'] === 'clear') {
                $query->where('units.balance', '>=', 0);
            }
        }

        // Default sort when no _sort param is sent
        if (!request()->has('_sort')) {
            $query->orderBy('units.unit_number', 'asc');
        }

        // Run the filter/search/sort pipeline manually so we can snapshot filtered
        // unit IDs before pagination (used to compute filter-aware chart stats).
        $this->setQuery($query);
        $this->applyDateRangeFromRequest();
        $this->applySearchOnQuery();
        $this->applySortOnQuery();

        // Snapshot all matching unit IDs (pre-pagination) for chart aggregation.
        $filteredIds = (clone $this->query)
            ->reorder()
            ->select('units.id')
            ->pluck('units.id');

        $chartStats = $this->computeFilteredChartStats($filteredIds);

        $perPage   = max(1, (int) request()->input('_per_page', $this->defaultPerPage));
        $paginated = $this->query->paginate($perPage)->withQueryString();

        return (new UnitResources($paginated))->additional(['charts' => $chartStats]);
    }

    /**
     * Export units for an estate as CSV, Excel, or PDF — same filters as showUnits().
     *
     * Extra parameters in $data:
     *   _format  — 'csv' | 'xlsx' | 'pdf'  (required)
     *   _limit   — integer record cap, or 'current' (= 15)
     *
     * @param Estate $estate
     * @param array  $data
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function exportUnits(Estate $estate, array $data): \Symfony\Component\HttpFoundation\Response
    {
        $query = Unit::where('units.estate_id', $estate->id)
            ->with(['owner', 'currentTenant']);

        if (!empty($data['occupancy_type'])) {
            $query->where('units.occupancy_type', $data['occupancy_type']);
        }
        if (!empty($data['status'])) {
            $query->where('units.status', $data['status']);
        }
        if (!empty($data['balance'])) {
            if ($data['balance'] === 'in_arrears') {
                $query->where('units.balance', '<', 0);
            } elseif ($data['balance'] === 'clear') {
                $query->where('units.balance', '>=', 0);
            }
        }
        if (!empty($data['search'])) {
            $term = '%' . $data['search'] . '%';
            $query->where(function ($q) use ($term) {
                $q->where('units.unit_number', 'ilike', $term)
                  ->orWhereHas('owner', fn($o) => $o->where('full_name', 'ilike', $term));
            });
        }
        if (!empty($data['date_range'])) {
            $query = $this->applyDateRange(
                $query,
                $data['date_range'],
                $data['date_range_start'] ?? null,
                $data['date_range_end'] ?? null,
                'units.created_at'
            );
        }

        if (!$this->request->has('_sort')) {
            $query->orderBy('units.unit_number', 'asc');
        }

        $this->setQuery($query);
        $this->applySortOnQuery();

        $limit = $this->resolveExportLimit($data['_limit'] ?? 'current');
        $units = $this->query->limit($limit)->get();

        $headings = ['Unit #', 'Occupancy', 'Owner Name', 'Owner Email', 'Tenant Name', 'Tenant Email', 'Balance'];

        $rows = $units->map(function ($unit) {
            $rawOccupancy = $unit->occupancy_type instanceof \BackedEnum
                ? $unit->occupancy_type->value
                : (string) $unit->occupancy_type;

            $occupancy = match ($rawOccupancy) {
                'owner_occupied'  => 'Owner Occupied',
                'tenant_occupied' => 'Tenant Occupied',
                'vacant'          => 'Vacant',
                default           => ucfirst($rawOccupancy),
            };

            return [
                $unit->unit_number,
                $occupancy,
                $unit->owner?->full_name ?? '—',
                $unit->owner?->email ?? '—',
                $unit->currentTenant?->full_name ?? '—',
                $unit->currentTenant?->email ?? '—',
                number_format((float) ($unit->balance ?? 0), 2),
            ];
        })->toArray();

        $format = $data['_format'] ?? 'csv';

        return $this->buildFileResponse(
            $rows,
            $headings,
            'units-' . str_replace(' ', '-', strtolower($estate->name)) . '-' . now()->format('Y-m-d'),
            $format,
            'Units Export — ' . $estate->name,
            ['Estate' => $estate->name, 'Generated' => now()->format('d M Y'), 'Records' => count($rows)]
        );
    }

    /**
     * Compute occupancy, invoice status, top-arrears, and tenant chart data
     * for a given set of unit IDs (the pre-pagination filtered set).
     *
     * @param Collection $unitIds
     * @return array
     */
    private function computeFilteredChartStats(Collection $unitIds): array
    {
        if ($unitIds->isEmpty()) {
            return [
                'occupancy'           => ['owner_occupied' => 0, 'tenant_occupied' => 0, 'vacant' => 0],
                'invoice_status'      => ['paid' => 0, 'overdue' => 0, 'partial' => 0],
                'top_arrears'         => [],
                'tenant_lease_expiry' => ['expired' => 0, 'this_month' => 0, 'next_month' => 0, 'in_3_months' => 0, 'beyond' => 0],
                'top_tenant_arrears'  => [],
            ];
        }

        $occ = Unit::whereIn('id', $unitIds)
            ->selectRaw("
                SUM(CASE WHEN occupancy_type = 'owner_occupied'  THEN 1 ELSE 0 END) AS owner_count,
                SUM(CASE WHEN occupancy_type = 'tenant_occupied' THEN 1 ELSE 0 END) AS tenant_count,
                SUM(CASE WHEN occupancy_type = 'vacant'          THEN 1 ELSE 0 END) AS vacant_count
            ")
            ->first();

        $inv = Invoice::whereIn('unit_id', $unitIds)
            ->selectRaw("
                SUM(CASE WHEN status = 'paid'           THEN 1 ELSE 0 END) AS paid_count,
                SUM(CASE WHEN status = 'overdue'        THEN 1 ELSE 0 END) AS overdue_count,
                SUM(CASE WHEN status = 'partially_paid' THEN 1 ELSE 0 END) AS partial_count
            ")
            ->first();

        $topArrears = Unit::whereIn('id', $unitIds)
            ->with('owner:id,unit_id,full_name')
            ->addSelect([
                'units.*',
                'outstanding_amount' => Invoice::selectRaw('COALESCE(SUM(amount), 0)')
                    ->whereColumn('unit_id', 'units.id')
                    ->whereIn('status', ['unpaid', 'overdue', 'partially_paid']),
            ])
            ->orderByDesc('outstanding_amount')
            ->limit(5)
            ->get()
            ->filter(fn ($u) => ($u->outstanding_amount ?? 0) > 0)
            ->map(fn ($u) => [
                'unit_id'     => $u->id,
                'unit_number' => $u->unit_number,
                'owner_name'  => $u->owner?->full_name ?? '—',
                'outstanding' => (float) ($u->outstanding_amount ?? 0),
            ])
            ->values()
            ->toArray();

        // ── Tenant lease expiry buckets ──────────────────────────────────
        $today     = now()->startOfDay();
        $endOfMonth     = now()->endOfMonth();
        $endOfNextMonth = now()->addMonthNoOverflow()->endOfMonth();
        $in3Months      = now()->addMonths(3)->endOfDay();

        $leaseRows = UnitTenant::whereIn('unit_id', $unitIds)
            ->where('is_active', true)
            ->whereNotNull('lease_end')
            ->pluck('lease_end');

        $leaseExpiry = ['expired' => 0, 'this_month' => 0, 'next_month' => 0, 'in_3_months' => 0, 'beyond' => 0];
        foreach ($leaseRows as $leaseEnd) {
            $date = \Carbon\Carbon::parse($leaseEnd)->startOfDay();
            if ($date->lt($today)) {
                $leaseExpiry['expired']++;
            } elseif ($date->lte($endOfMonth)) {
                $leaseExpiry['this_month']++;
            } elseif ($date->lte($endOfNextMonth)) {
                $leaseExpiry['next_month']++;
            } elseif ($date->lte($in3Months)) {
                $leaseExpiry['in_3_months']++;
            } else {
                $leaseExpiry['beyond']++;
            }
        }

        // ── Top tenant arrears (top 5 tenants by outstanding invoices) ───
        $topTenantArrears = UnitTenant::whereIn('unit_id', $unitIds)
            ->where('is_active', true)
            ->with('unit:id,unit_number')
            ->addSelect([
                'unit_tenants.*',
                'outstanding_amount' => Invoice::selectRaw('COALESCE(SUM(amount), 0)')
                    ->whereColumn('unit_id', 'unit_tenants.unit_id')
                    ->where('billed_to_type', 'tenant')
                    ->whereIn('status', ['unpaid', 'overdue', 'partially_paid']),
            ])
            ->orderByDesc('outstanding_amount')
            ->limit(5)
            ->get()
            ->filter(fn ($t) => ($t->outstanding_amount ?? 0) > 0)
            ->map(fn ($t) => [
                'tenant_id'   => $t->id,
                'tenant_name' => $t->full_name ?? '—',
                'unit_number' => $t->unit?->unit_number ?? '—',
                'outstanding' => (float) ($t->outstanding_amount ?? 0),
            ])
            ->values()
            ->toArray();

        return [
            'occupancy' => [
                'owner_occupied'  => (int) ($occ?->owner_count  ?? 0),
                'tenant_occupied' => (int) ($occ?->tenant_count ?? 0),
                'vacant'          => (int) ($occ?->vacant_count ?? 0),
            ],
            'invoice_status' => [
                'paid'    => (int) ($inv?->paid_count    ?? 0),
                'overdue' => (int) ($inv?->overdue_count ?? 0),
                'partial' => (int) ($inv?->partial_count ?? 0),
            ],
            'top_arrears'         => $topArrears,
            'tenant_lease_expiry' => $leaseExpiry,
            'top_tenant_arrears'  => $topTenantArrears,
        ];
    }

    /**
     * Override BaseService::applySortOnQuery() to handle unit-specific sort fields
     * that require a join (owner_name) or the stored balance column.
     *
     * Sort key → DB mapping:
     *   unit_number:asc/desc     → units.unit_number
     *   owner_name:asc/desc      → owners.full_name (left-joins owners table)
     *   outstanding_amount:asc   → units.balance asc  (most in-arrears first)
     *   outstanding_amount:desc  → units.balance desc (highest credit first)
     */
    public function applySortOnQuery(): static
    {
        $sort = $this->request->input('_sort');

        if (! $sort) {
            return $this;
        }

        $parts     = explode(':', $sort, 2);
        $field     = $parts[0] ?? null;
        $direction = isset($parts[1]) && strtolower($parts[1]) === 'desc' ? 'desc' : 'asc';

        switch ($field) {
            case 'unit_number':
                $this->query->orderBy('units.unit_number', $direction);
                break;

            case 'owner_name':
                // Left-join so vacant units (no owner yet) still appear in results.
                // Must select units.* explicitly so the join's columns (id, email, etc.)
                // don't overwrite unit columns and break eager-loaded relationships.
                $this->query
                    ->select('units.*')
                    ->leftJoin('owners', 'owners.unit_id', '=', 'units.id')
                    ->orderBy('owners.full_name', $direction);
                break;

            case 'outstanding_amount':
                // Sort on the stored balance column (indexed — no subquery required)
                $this->query->orderBy('units.balance', $direction);
                break;

            default:
                // Fall back to the parent's generic sort for any unrecognised field
                parent::applySortOnQuery();
        }

        return $this;
    }

    /**
     * Create a new unit with its owner, and optionally a tenant.
     *
     * @param Estate $estate
     * @param array  $data
     * @return array
     * @throws Exception
     */
    public function createUnit(Estate $estate, array $data): array
    {
        $user = Auth::user();

        $unitData = collect($data)
            ->only(['unit_number', 'address', 'occupancy_type', 'status', 'levy_override', 'rent_amount'])
            ->toArray();

        $unit = Unit::create(array_merge($unitData, [
            'estate_id' => $estate->id,
            'tenant_id' => $user->tenant_id,
            'status'    => $unitData['status'] ?? 'active',
        ]));

        // Always create the owner record
        if (!empty($data['owner'])) {
            $ownerData = collect($data['owner'])
                ->only(['full_name', 'email', 'phone', 'id_number', 'address'])
                ->toArray();

            Owner::create(array_merge($ownerData, [
                'unit_id'   => $unit->id,
                'tenant_id' => $user->tenant_id,
            ]));
        }

        // Create tenant record if occupancy_type is tenant_occupied and tenant data is provided
        $occupancyType = $unitData['occupancy_type'] ?? null;

        if ($occupancyType === OccupancyType::TENANT_OCCUPIED->value && !empty($data['tenant'])) {
            $tenantData = collect($data['tenant'])
                ->only(['full_name', 'email', 'phone', 'id_number', 'lease_start', 'lease_end', 'rent_amount'])
                ->toArray();

            UnitTenant::create(array_merge($tenantData, [
                'unit_id'   => $unit->id,
                'tenant_id' => $user->tenant_id,
                'is_active' => true,
            ]));
        }

        return $this->showCreatedResource($unit);
    }

    /**
     * Return paginated activity entries for a unit, newest first.
     *
     * @param Estate $estate
     * @param Unit   $unit
     * @return array
     */
    public function showUnitActivities(Estate $estate, Unit $unit): array
    {
        $perPage = max(1, (int) request()->input('_per_page', 20));

        $logs = UnitActivity::where('unit_id', $unit->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return [
            'data' => $logs->map(fn ($log) => [
                'id'              => $log->id,
                'batch_id'        => $log->batch_id,
                'event'           => $log->event,
                'category'        => $log->category,
                'changes'         => $log->changes ?? [],
                'changed_by_name' => $log->changed_by_name,
                'created_at'      => $log->created_at?->toISOString(),
            ])->values(),
            'meta' => [
                'total'        => $logs->total(),
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'per_page'     => $logs->perPage(),
            ],
        ];
    }

    /**
     * Return a single unit resource with its relationships loaded.
     *
     * @param Estate $estate
     * @param Unit   $unit
     * @return UnitResource
     */
    public function showUnit(Estate $estate, Unit $unit): UnitResource
    {
        // Re-query so the balance subqueries are applied (Route Model Binding loads
        // the bare model without them, which would always return balance = 0).
        $loaded = Unit::where('units.id', $unit->id)
            ->with(['owner', 'currentTenant', 'chargeConfigs.chargeType', 'estate'])
            ->addSelect([
                'units.*',
                'outstanding_amount' => Invoice::selectRaw(
                    "COALESCE(SUM(GREATEST(0, invoices.amount - COALESCE((SELECT SUM(ce.amount) FROM cashbook_entries ce WHERE ce.invoice_id = invoices.id), 0))), 0)"
                )
                    ->whereColumn('invoices.unit_id', 'units.id')
                    ->whereIn('invoices.status', ['unpaid', 'overdue', 'partially_paid']),
                'unallocated_credits' => CashbookEntry::selectRaw('COALESCE(SUM(amount), 0)')
                    ->whereColumn('unit_id', 'units.id')
                    ->whereNull('invoice_id')
                    ->where('type', 'credit'),
            ])
            ->firstOrFail();

        return $this->showResource($loaded);
    }

    /**
     * Update a unit and optionally its owner's and tenant's details.
     * Records a detailed activity entry for every field that changed.
     *
     * @param Estate $estate
     * @param Unit   $unit
     * @param array  $data
     * @return array
     */
    public function updateUnit(Estate $estate, Unit $unit, array $data): array
    {
        $user = Auth::user();

        // ── Snapshot "before" state ──────────────────────────────────────
        $unit->loadMissing(['owner', 'currentTenant']);

        $beforeUnit = [
            'unit_number'    => $unit->unit_number,
            'occupancy_type' => $unit->occupancy_type?->value ?? (string) $unit->occupancy_type,
            'levy_override'  => $unit->levy_override,
            'rent_amount'    => $unit->rent_amount,
            'address'        => $unit->address,
        ];

        $beforeOwner = $unit->owner ? [
            'full_name' => $unit->owner->full_name,
            'email'     => $unit->owner->email,
            'phone'     => $unit->owner->phone,
            'id_number' => $unit->owner->id_number,
            'address'   => $unit->owner->address,
        ] : null;

        $beforeTenant = $unit->currentTenant ? [
            'full_name'   => $unit->currentTenant->full_name,
            'email'       => $unit->currentTenant->email,
            'phone'       => $unit->currentTenant->phone,
            'lease_start' => $unit->currentTenant->lease_start,
            'lease_end'   => $unit->currentTenant->lease_end,
        ] : null;

        // ── Apply updates ────────────────────────────────────────────────
        $unitData = collect($data)
            ->only(['unit_number', 'address', 'occupancy_type', 'status', 'levy_override', 'rent_amount'])
            ->filter(fn($v) => !is_null($v))
            ->toArray();

        $unit->update($unitData);

        if (!empty($data['owner'])) {
            $ownerData = collect($data['owner'])
                ->only(['full_name', 'email', 'phone', 'id_number', 'address'])
                ->filter(fn($v) => !is_null($v))
                ->toArray();

            if ($unit->owner) {
                $unit->owner->update($ownerData);
            }
        }

        $newTenantCreated = false;

        if (!empty($data['tenant'])) {
            $tenantData = collect($data['tenant'])
                ->only(['full_name', 'email', 'phone', 'id_number', 'lease_start', 'lease_end'])
                ->filter(fn($v) => !is_null($v))
                ->toArray();

            $unit->loadMissing('currentTenant');

            if ($unit->currentTenant) {
                $unit->currentTenant->update($tenantData);
            } else {
                UnitTenant::create(array_merge($tenantData, [
                    'unit_id'   => $unit->id,
                    'tenant_id' => $user->tenant_id,
                    'is_active' => true,
                ]));

                $newTenantCreated = true;

                // Ensure the unit's occupancy reflects having a tenant
                $unit->update(['occupancy_type' => OccupancyType::TENANT_OCCUPIED->value]);

                // Refresh so the response includes the newly created tenant
                $unit->unsetRelation('currentTenant');
                $unit->load('currentTenant');
            }
        }

        // ── Snapshot "after" state and build diffs ───────────────────────
        $unit->refresh();
        $unit->loadMissing(['owner', 'currentTenant']);

        $this->recordUnitActivities(
            unit:            $unit,
            user:            $user,
            beforeUnit:      $beforeUnit,
            beforeOwner:     $beforeOwner,
            beforeTenant:    $beforeTenant,
            submittedOwner:  $data['owner'] ?? null,
            submittedTenant: $data['tenant'] ?? null,
            newTenantCreated: $newTenantCreated,
        );

        return $this->showUpdatedResource($unit);
    }

    /**
     * Human-readable field labels for activity display.
     */
    private const UNIT_FIELD_LABELS = [
        'unit_number'    => 'Unit Number',
        'occupancy_type' => 'Occupancy Type',
        'levy_override'  => 'Levy Override',
        'rent_amount'    => 'Rent Amount',
        'address'        => 'Address',
        'status'         => 'Status',
    ];

    private const OWNER_FIELD_LABELS = [
        'full_name' => 'Full Name',
        'email'     => 'Email',
        'phone'     => 'Phone',
        'id_number' => 'ID Number',
        'address'   => 'Address',
    ];

    private const TENANT_FIELD_LABELS = [
        'full_name'   => 'Full Name',
        'email'       => 'Email',
        'phone'       => 'Phone',
        'lease_start' => 'Lease Start',
        'lease_end'   => 'Lease End',
    ];

    /**
     * Compare two associative arrays and return field-level diffs.
     * Only fields present in $after (from the submitted payload) are checked.
     *
     * @param array  $before      State before the update
     * @param array  $after       State after the update (from the freshly reloaded model)
     * @param array  $labels      Human-readable field labels
     * @param array|null $submitted  Only fields the caller actually sent (limits noise)
     * @return array  [{ field, old, new }, ...]
     */
    private function buildDiff(array $before, array $after, array $labels, ?array $submitted = null): array
    {
        $changes = [];

        foreach ($labels as $key => $label) {
            // Skip fields the caller did not submit
            if ($submitted !== null && !array_key_exists($key, $submitted)) {
                continue;
            }

            $oldRaw = $before[$key] ?? null;
            $newRaw = $after[$key]  ?? null;

            // Normalise to string for comparison (handles nulls, floats, enums, etc.)
            $oldStr = ($oldRaw === null || $oldRaw === '') ? '' : (string) $oldRaw;
            $newStr = ($newRaw === null || $newRaw === '') ? '' : (string) $newRaw;

            if ($oldStr !== $newStr) {
                $changes[] = [
                    'field' => $label,
                    'old'   => $oldStr ?: null,
                    'new'   => $newStr ?: null,
                ];
            }
        }

        return $changes;
    }

    /**
     * Record activity entries for a unit update.
     * One entry per changed category (unit, owner, tenant).
     */
    private function recordUnitActivities(
        Unit  $unit,
        mixed $user,
        array $beforeUnit,
        ?array $beforeOwner,
        ?array $beforeTenant,
        ?array $submittedOwner,
        ?array $submittedTenant,
        bool  $newTenantCreated,
    ): void {
        $batchId = (string) Str::uuid();

        $commonAttrs = [
            'unit_id'          => $unit->id,
            'tenant_id'        => $unit->tenant_id,
            'batch_id'         => $batchId,
            'user_id'          => $user?->id,
            'changed_by_name'  => $user?->name ?? $user?->full_name ?? 'System',
        ];

        // ── Unit fields diff ─────────────────────────────────────────────
        $afterUnit = [
            'unit_number'    => $unit->unit_number,
            'occupancy_type' => $unit->occupancy_type?->value ?? (string) $unit->occupancy_type,
            'levy_override'  => $unit->levy_override,
            'rent_amount'    => $unit->rent_amount,
            'address'        => $unit->address,
        ];

        $unitChanges = $this->buildDiff($beforeUnit, $afterUnit, self::UNIT_FIELD_LABELS);

        if (!empty($unitChanges)) {
            UnitActivity::create(array_merge($commonAttrs, [
                'event'    => 'Updated unit details',
                'category' => 'unit',
                'changes'  => $unitChanges,
            ]));
        }

        // ── Owner fields diff ────────────────────────────────────────────
        if ($submittedOwner && $unit->owner) {
            $afterOwner = [
                'full_name' => $unit->owner->full_name,
                'email'     => $unit->owner->email,
                'phone'     => $unit->owner->phone,
                'id_number' => $unit->owner->id_number,
                'address'   => $unit->owner->address,
            ];

            $ownerChanges = $this->buildDiff(
                $beforeOwner ?? array_fill_keys(array_keys(self::OWNER_FIELD_LABELS), null),
                $afterOwner,
                self::OWNER_FIELD_LABELS,
                $submittedOwner,
            );

            if (!empty($ownerChanges)) {
                UnitActivity::create(array_merge($commonAttrs, [
                    'event'    => 'Updated owner details',
                    'category' => 'owner',
                    'changes'  => $ownerChanges,
                ]));
            }
        }

        // ── Tenant fields diff ───────────────────────────────────────────
        if ($submittedTenant) {
            if ($newTenantCreated) {
                // Brand-new tenant moved in — log as a dedicated "Moved in tenant" event
                UnitActivity::create(array_merge($commonAttrs, [
                    'event'    => 'Moved in tenant',
                    'category' => 'tenant',
                    'changes'  => array_filter([
                        !empty($unit->currentTenant?->full_name) ? ['field' => 'Full Name', 'old' => null, 'new' => $unit->currentTenant->full_name] : null,
                        !empty($unit->currentTenant?->email)     ? ['field' => 'Email',     'old' => null, 'new' => $unit->currentTenant->email]     : null,
                        !empty($unit->currentTenant?->phone)     ? ['field' => 'Phone',     'old' => null, 'new' => $unit->currentTenant->phone]     : null,
                        !empty($unit->currentTenant?->lease_start) ? ['field' => 'Lease Start', 'old' => null, 'new' => $unit->currentTenant->lease_start] : null,
                        !empty($unit->currentTenant?->lease_end)   ? ['field' => 'Lease End',   'old' => null, 'new' => $unit->currentTenant->lease_end]   : null,
                    ]),
                ]));
            } elseif ($unit->currentTenant) {
                $afterTenant = [
                    'full_name'   => $unit->currentTenant->full_name,
                    'email'       => $unit->currentTenant->email,
                    'phone'       => $unit->currentTenant->phone,
                    'lease_start' => $unit->currentTenant->lease_start,
                    'lease_end'   => $unit->currentTenant->lease_end,
                ];

                $tenantChanges = $this->buildDiff(
                    $beforeTenant ?? array_fill_keys(array_keys(self::TENANT_FIELD_LABELS), null),
                    $afterTenant,
                    self::TENANT_FIELD_LABELS,
                    $submittedTenant,
                );

                if (!empty($tenantChanges)) {
                    UnitActivity::create(array_merge($commonAttrs, [
                        'event'    => 'Updated tenant details',
                        'category' => 'tenant',
                        'changes'  => $tenantChanges,
                    ]));
                }
            }
        }
    }

    /**
     * Bulk delete units by an array of IDs.
     *
     * @param Estate $estate
     * @param array  $unitIds
     * @return array
     * @throws Exception
     */
    public function deleteUnits(Estate $estate, array $unitIds): array
    {
        $units = Unit::whereIn('id', $unitIds)
            ->where('estate_id', $estate->id)
            ->get();

        $total = $units->count();

        if ($total === 0) {
            throw new Exception('No Units deleted');
        }

        foreach ($units as $unit) {
            $unit->delete();
        }

        $label = $total === 1 ? 'Unit' : 'Units';

        return ['message' => "{$total} {$label} deleted"];
    }

    /**
     * Delete a single unit.
     *
     * @param Estate $estate
     * @param Unit   $unit
     * @return array
     */
    public function deleteUnit(Estate $estate, Unit $unit): array
    {
        $deleted = $unit->delete();

        return [
            'deleted' => $deleted,
            'message' => $deleted ? 'Unit deleted' : 'Unit delete unsuccessful',
        ];
    }

    /**
     * Generate and stream a bulk import template file (CSV or XLSX).
     *
     * @param Estate $estate
     * @param string $format  'csv' | 'xlsx'
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\Response
     */
    public function downloadImportTemplate(Estate $estate, string $format)
    {
        $headers = [
            'unit_number',
            'occupancy_type',
            'levy_override',
            'rent_amount',
            'owner_full_name',
            'owner_id_number',
            'owner_email',
            'owner_phone',
            'owner_address',
            'tenant_full_name',
            'tenant_email',
            'tenant_phone',
            'tenant_lease_start',
            'tenant_lease_end',
        ];

        $example = [
            'A01',
            'owner_occupied',
            '',
            '',
            'John Smith',
            '8001015009087',
            'john@example.com',
            '+27821234567',
            '123 Main Street, Gaborone',
            '',
            '',
            '',
            '',
            '',
        ];

        $filename = 'units-import-template';

        if ($format === 'xlsx') {
            $spreadsheet = new Spreadsheet();
            $sheet       = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Units Import');

            // Style header row
            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col . '1', $header);
                $sheet->getColumnDimension($col)->setAutoSize(true);
                $sheet->getStyle($col . '1')->getFont()->setBold(true);
                $col++;
            }

            // Example row
            $col = 'A';
            foreach ($example as $value) {
                $sheet->setCellValue($col . '2', $value);
                $col++;
            }

            $writer = new XlsxWriter($spreadsheet);

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename . '.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        }

        // CSV
        $csvContent  = implode(',', $headers) . "\n";
        $csvContent .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $example)) . "\n";

        return response($csvContent, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.csv"',
        ]);
    }

    /**
     * Parse an uploaded CSV or XLSX file and return its columns + rows.
     *
     * @param Estate $estate
     * @param mixed  $file   Uploaded file instance
     * @return array { columns: string[], rows: array[], total_rows: int }
     * @throws Exception
     */
    public function parseImportFile(Estate $estate, mixed $file): array
    {
        if (!$file) {
            throw new Exception('No file provided');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $path      = $file->getRealPath();
        $rows      = [];
        $columns   = [];

        if ($extension === 'csv') {
            $handle = fopen($path, 'r');

            if (!$handle) {
                throw new Exception('Could not open the uploaded file.');
            }

            $lineNumber = 0;

            while (($row = fgetcsv($handle)) !== false) {
                $lineNumber++;

                if ($lineNumber === 1) {
                    $columns = array_map('trim', $row);
                    continue;
                }

                // Map columns to associative array
                $assoc = [];
                foreach ($columns as $i => $col) {
                    $assoc[$col] = trim($row[$i] ?? '');
                }

                $rows[] = $assoc;
            }

            fclose($handle);
        } else {
            // XLSX / XLS
            $spreadsheet = IOFactory::load($path);
            $sheet       = $spreadsheet->getActiveSheet();
            $sheetData   = $sheet->toArray(null, true, true, false);

            if (empty($sheetData)) {
                throw new Exception('The uploaded file is empty.');
            }

            $columns = array_map('trim', array_map('strval', $sheetData[0]));

            foreach (array_slice($sheetData, 1) as $row) {
                // Skip entirely empty rows
                $values = array_map(fn($v) => trim((string) $v), $row);
                if (empty(array_filter($values))) {
                    continue;
                }

                $assoc = [];
                foreach ($columns as $i => $col) {
                    $assoc[$col] = $values[$i] ?? '';
                }

                $rows[] = $assoc;
            }
        }

        return [
            'columns'    => $columns,
            'rows'       => $rows,
            'total_rows' => count($rows),
        ];
    }

    /**
     * Import pre-mapped, validated rows into the database.
     *
     * Each row is expected to already have system field keys (unit_number, owner_email, etc.)
     * applied from the column mapping step on the frontend.
     *
     * @param Estate $estate
     * @param array  $rows
     * @return array
     */
    public function bulkImportUnits(Estate $estate, array $rows): array
    {
        $user      = Auth::user();
        $tenantId  = $user->tenant_id;
        $imported  = 0;
        $duplicates = 0;
        $errors    = [];

        // Pre-fetch existing unit numbers for this estate to detect duplicates
        $existingUnitNumbers = Unit::where('estate_id', $estate->id)
            ->pluck('unit_number')
            ->map(fn($n) => strtolower(trim($n)))
            ->flip()
            ->toArray();

        foreach ($rows as $index => $row) {
            $rowNum = $index + 1;

            // --- Server-side validation ---
            $rowErrors = [];

            $unitNumber    = trim($row['unit_number'] ?? '');
            $occupancyType = trim($row['occupancy_type'] ?? '');
            $ownerName     = trim($row['owner_full_name'] ?? '');
            $ownerEmail    = trim($row['owner_email'] ?? '');

            if (!$unitNumber) {
                $rowErrors[] = 'Unit number is required.';
            }

            if (!in_array($occupancyType, ['owner_occupied', 'tenant_occupied', 'vacant'])) {
                $rowErrors[] = 'Occupancy type must be owner_occupied, tenant_occupied, or vacant.';
            }

            if (!$ownerName) {
                $rowErrors[] = 'Owner full name is required.';
            }

            if (!$ownerEmail) {
                $rowErrors[] = 'Owner email is required.';
            } elseif (!filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
                $rowErrors[] = 'Owner email is invalid.';
            }

            $tenantEmail = trim($row['tenant_email'] ?? '');
            if ($tenantEmail && !filter_var($tenantEmail, FILTER_VALIDATE_EMAIL)) {
                $rowErrors[] = 'Tenant email is invalid.';
            }

            if (!empty($rowErrors)) {
                $errors[] = [
                    'row'     => $rowNum,
                    'errors'  => $rowErrors,
                    'data'    => $row,
                ];
                continue;
            }

            // --- Duplicate check ---
            if (isset($existingUnitNumbers[strtolower($unitNumber)])) {
                $duplicates++;
                continue;
            }

            // --- Create records ---
            $unit = Unit::create([
                'estate_id'      => $estate->id,
                'tenant_id'      => $tenantId,
                'unit_number'    => $unitNumber,
                'address'        => trim($row['address'] ?? ''),
                'occupancy_type' => $occupancyType,
                'status'         => 'active',
                'levy_override'  => ($row['levy_override'] ?? '') !== '' ? (float) $row['levy_override'] : null,
                'rent_amount'    => ($row['rent_amount'] ?? '') !== '' ? (float) $row['rent_amount'] : null,
            ]);

            // Mark as existing so later rows with same unit number are flagged as duplicates
            $existingUnitNumbers[strtolower($unitNumber)] = true;

            Owner::create([
                'unit_id'    => $unit->id,
                'tenant_id'  => $tenantId,
                'full_name'  => $ownerName,
                'email'      => $ownerEmail,
                'phone'      => trim($row['owner_phone'] ?? '') ?: null,
                'id_number'  => trim($row['owner_id_number'] ?? '') ?: null,
                'address'    => trim($row['owner_address'] ?? '') ?: null,
            ]);

            if ($occupancyType === OccupancyType::TENANT_OCCUPIED->value) {
                $tenantName = trim($row['tenant_full_name'] ?? '');
                if ($tenantName || $tenantEmail) {
                    UnitTenant::create([
                        'unit_id'     => $unit->id,
                        'tenant_id'   => $tenantId,
                        'full_name'   => $tenantName ?: 'Unknown Tenant',
                        'email'       => $tenantEmail ?: null,
                        'phone'       => trim($row['tenant_phone'] ?? '') ?: null,
                        'lease_start' => $this->parseDate($row['tenant_lease_start'] ?? ''),
                        'lease_end'   => $this->parseDate($row['tenant_lease_end'] ?? ''),
                        'is_active'   => true,
                    ]);
                }
            }

            $imported++;
        }

        $total       = count($rows);
        $errorCount  = count($errors);
        $label       = $imported === 1 ? 'unit' : 'units';

        return [
            'imported'    => $imported,
            'duplicates'  => $duplicates,
            'error_count' => $errorCount,
            'errors'      => $errors,
            'total'       => $total,
            'message'     => "{$imported} {$label} imported successfully.",
        ];
    }

    /**
     * Attempt to parse a date string into Y-m-d format, returning null if invalid.
     */
    private function parseDate(string $value): ?string
    {
        $value = trim($value);

        if (!$value) {
            return null;
        }

        try {
            return (new \DateTime($value))->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }
}
