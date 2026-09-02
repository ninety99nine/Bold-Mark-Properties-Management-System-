<?php

namespace App\Services;

use Exception;
use App\Models\Community;
use App\Models\Owner;
use App\Models\Unit;
use App\Models\UnitActivity;
use App\Models\Occupant;
use App\Enums\CommunityEntityType;
use App\Enums\OccupancyType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Resend\Laravel\Facades\Resend;
use Illuminate\Support\Str;
use App\Models\CashbookEntry;
use App\Models\UnitCollectionNote;
use App\Models\UnitCommunication;
use App\Models\UnitOffence;
use App\Models\UnitTask;
use App\Models\UnitTaskUpdate;
use App\Models\UnitDocument;
use App\Http\Resources\UnitCollectionNoteResource;
use App\Http\Resources\UnitCommunicationResource;
use App\Http\Resources\UnitOffenceResource;
use App\Http\Resources\UnitTaskResource;
use App\Http\Resources\UnitTaskUpdateResource;
use App\Http\Resources\UnitDocumentResource;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\UnitResource;
use App\Http\Resources\UnitResources;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\IOFactory;

class UnitService extends BaseService
{
    /**
     * Return a paginated, filtered list of units for the given community.
     *
     * Supported query parameters:
     *   _search           → unit_number or owner name full-text search
     *   _sort             → unit_number:asc | unit_number:desc | owner_name:asc |
     *                       owner_name:desc | outstanding_amount:asc | outstanding_amount:desc
     *   _date_range       → today | this_week | this_month | this_year | custom | all_time
     *   _date_range_start → Y-m-d (used when _date_range = custom)
     *   _date_range_end   → Y-m-d (used when _date_range = custom)
     *   occupancy_type    → owner_occupied | occupant_occupied | vacant
     *   status            → active | suspended | vacated
     *   balance           → in_arrears | clear
     *
     * @param Community $community
     * @param array  $data
     * @return UnitResources
     */
    public function showUnits(Community $community, array $data): UnitResources
    {
        $query = Unit::where('units.community_id', $community->id)
            ->with(['owner', 'currentOccupant', 'community'])
            ->withCount(['occupants as total_occupants_count']);

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
            $query->orderByRaw($this->unitNumberOrderRaw());
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
     * Export units for an community as CSV, Excel, or PDF — same filters as showUnits().
     *
     * Extra parameters in $data:
     *   _format  — 'csv' | 'xlsx' | 'pdf'  (required)
     *   _limit   — integer record cap, or 'current' (= 15)
     *
     * @param Community $community
     * @param array  $data
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function exportUnits(Community $community, array $data): \Symfony\Component\HttpFoundation\Response
    {
        $query = Unit::where('units.community_id', $community->id)
            ->with(['owner', 'currentOccupant']);

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
            $search = $data['search'];
            $query->where(function ($q) use ($search) {
                $q->whereLike('units.unit_number', $search)
                  ->orWhereHas('owner', fn($o) => $o->whereLike('full_name', $search));
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
            $query->orderByRaw($this->unitNumberOrderRaw());
        }

        $this->setQuery($query);
        $this->applySortOnQuery();

        $limit = $this->resolveExportLimit($data['_limit'] ?? 'current');
        $units = $this->query->limit($limit)->get();

        // Match WeConnectU's exact "unit export" column layout so files are
        // interchangeable (same headers, same order — 38 columns A→AL).
        $headings = [
            'Block', 'Section / Erf No', 'Unit No', 'Door No', 'Street No (HOA)', 'PQ',
            'Size Unit (Sq m)', 'Size Garage (Sq m)', 'Size Carport (Sq m)', 'Size Parking (Sq m)',
            'Ratio 1', 'Ratio 2', 'Ratio 3', 'Ratio 4', 'Ratio 5',
            'Owner / Contact Name', 'ID / Passport', 'Email Address', 'Cell Number', 'Landline Number',
            'Contact 2 Name', 'Contact 2 Email Address', 'Contact 2 Cell Number', 'Contact 2 Landline Number',
            'Postal Address',
            'Trust Name', 'Trust Reg', 'CC Name', 'CC Reg No.', 'PTY Name', 'PTY Reg No.',
            'Managing Body Name', 'Managing Body Reg No.', 'Rental Agent Email',
            'Customer Code', 'Customer Name', 'UNIT ID', 'OWNER ID',
        ];

        $rows = $units->map(function ($unit) {
            $owner   = $unit->owner;
            $entity  = $owner?->entity_type instanceof \BackedEnum ? $owner->entity_type->value : (string) $owner?->entity_type;

            // WeConnectU splits company/trust/CC entities into dedicated name+reg columns.
            $trustName = $trustReg = $ccName = $ccReg = $ptyName = $ptyReg = '';
            if ($owner) {
                if ($entity === 'trust')             { $trustName = $owner->full_name; $trustReg = $owner->id_number ?? ''; }
                elseif ($entity === 'close_corporation') { $ccName = $owner->full_name; $ccReg = $owner->id_number ?? ''; }
                elseif ($entity === 'company')       { $ptyName = $owner->full_name; $ptyReg = $owner->id_number ?? ''; }
            }

            return [
                $unit->block_number ?? '',
                $unit->section ?? '',
                $unit->unit_number ?? '',
                $unit->door_number ?? '',
                '',                                     // Street No (HOA)
                $unit->pq ?? 0,
                0, 0, 0, 0,                             // Size Unit/Garage/Carport/Parking
                $unit->pq ?? 0, 0, 0, 0, 0,            // Ratio 1..5
                $owner?->full_name ?? '',
                $owner?->id_number ?? '',
                $owner?->email ?? '',
                $owner?->phone ?? '',
                $owner?->landline ?? '',
                $owner?->contact2_name ?? '',
                $owner?->contact2_email ?? '',
                $owner?->contact2_phone ?? '',
                $owner?->contact2_landline ?? '',
                $owner?->address ?? '',
                $trustName, $trustReg, $ccName, $ccReg, $ptyName, $ptyReg,
                '', '',                                 // Managing Body Name / Reg No.
                '',                                     // Rental Agent Email
                $unit->customer_code ?? '',
                $owner?->full_name ?? '',               // Customer Name
                $unit->id,                              // UNIT ID
                $owner?->id ?? '',                      // OWNER ID
            ];
        })->toArray();

        // WeConnectU exports xlsx by default with a "unit export-<name> <type>-" filename.
        $format      = $data['_format'] ?? 'xlsx';
        $entityLabel = $community->entity_type instanceof CommunityEntityType ? strtolower($community->entity_type->label()) : '';
        $filename    = trim('unit export-' . strtolower($community->name) . ' ' . $entityLabel) . '-';

        return $this->buildFileResponse(
            $rows,
            $headings,
            $filename,
            $format,
            'Units Export — ' . $community->name,
            ['Community' => $community->name, 'Generated' => now()->format('d M Y'), 'Records' => count($rows)]
        );
    }

    /**
     * Export the community's units + occupant contact list as a file (default xlsx).
     * Mirrors WeConnectU's one-click "Download Occupants" ("units occupants export-…").
     *
     * @param Community $community
     * @param array  $data
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function exportOccupants(Community $community, array $data): \Symfony\Component\HttpFoundation\Response
    {
        $units = Unit::where('units.community_id', $community->id)
            ->with(['owner', 'currentOccupant'])
            ->orderByRaw($this->unitNumberOrderRaw())
            ->get();

        $headings = [
            'Block', 'Section / Erf No', 'Unit No', 'Door No', 'Customer Code',
            'Owner / Contact Name',
            'Occupant Name', 'Occupant Email', 'Occupant Cell Number', 'Occupant ID / Passport',
            'Lease Start', 'Lease End',
            'UNIT ID', 'OCCUPANT ID',
        ];

        $rows = $units->map(function ($unit) {
            $occ = $unit->currentOccupant;

            return [
                $unit->block_number ?? '',
                $unit->section ?? '',
                $unit->unit_number ?? '',
                $unit->door_number ?? '',
                $unit->customer_code ?? '',
                $unit->owner?->full_name ?? '',
                $occ?->full_name ?? '',
                $occ?->email ?? '',
                $occ?->phone ?? '',
                $occ?->id_number ?? '',
                $occ?->lease_start ? (string) $occ->lease_start : '',
                $occ?->lease_end ? (string) $occ->lease_end : '',
                $unit->id,
                $occ?->id ?? '',
            ];
        })->toArray();

        $format      = $data['_format'] ?? 'xlsx';
        $entityLabel = $community->entity_type instanceof CommunityEntityType ? strtolower($community->entity_type->label()) : '';
        $filename    = trim('units occupants export-' . strtolower($community->name) . ' ' . $entityLabel) . '-';

        return $this->buildFileResponse(
            $rows,
            $headings,
            $filename,
            $format,
            'Occupants Export — ' . $community->name,
            ['Community' => $community->name, 'Generated' => now()->format('d M Y'), 'Records' => count($rows)]
        );
    }

    /**
     * Compute occupancy, invoice status, top-arrears, and occupant chart data
     * for a given set of unit IDs (the pre-pagination filtered set).
     *
     * @param Collection $unitIds
     * @return array
     */
    private function computeFilteredChartStats(Collection $unitIds): array
    {
        if ($unitIds->isEmpty()) {
            return [
                'occupancy'           => ['owner_occupied' => 0, 'occupant_occupied' => 0, 'vacant' => 0],
                'invoice_status'      => ['paid' => 0, 'overdue' => 0, 'partial' => 0],
                'top_owner_arrears'   => [],
                'occupant_lease_expiry' => ['expired' => 0, 'this_month' => 0, 'next_month' => 0, 'in_3_months' => 0, 'beyond' => 0],
                'top_occupant_arrears'  => [],
            ];
        }

        $occ = Unit::whereIn('id', $unitIds)
            ->selectRaw("
                SUM(CASE WHEN occupancy_type = 'owner_occupied'  THEN 1 ELSE 0 END) AS owner_count,
                SUM(CASE WHEN occupancy_type = 'occupant_occupied' THEN 1 ELSE 0 END) AS occupant_count,
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

        // Use the stored balance column (kept in sync by UnitBalanceService).
        // balance < 0  → unit is in arrears; the arrears amount = ABS(balance).
        // This correctly accounts for partial payments and unallocated credits,
        // unlike summing raw invoice amounts.
        $topArrears = Unit::whereIn('id', $unitIds)
            ->with('owner:id,unit_id,full_name')
            ->where('balance', '<', 0)
            ->orderBy('balance')          // most negative first = highest arrears
            ->limit(10)
            ->get()
            ->map(fn ($u) => [
                'unit_id'     => $u->id,
                'unit_number' => $u->unit_number,
                'owner_name'  => $u->owner?->full_name ?? '—',
                'outstanding' => (float) abs($u->balance ?? 0),
            ])
            ->values()
            ->toArray();

        // ── Organization lease expiry buckets ──────────────────────────────────
        $today     = now()->startOfDay();
        $endOfMonth     = now()->endOfMonth();
        $endOfNextMonth = now()->addMonthNoOverflow()->endOfMonth();
        $in3Months      = now()->addMonths(3)->endOfDay();

        $leaseRows = Occupant::whereIn('unit_id', $unitIds)
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

        // ── Top occupant arrears (top 10 organizations by outstanding invoices) ───
        $topOccupantArrears = Occupant::whereIn('unit_id', $unitIds)
            ->where('is_active', true)
            ->with('unit:id,unit_number')
            ->addSelect([
                'occupants.*',
                'outstanding_amount' => Invoice::selectRaw('COALESCE(SUM(amount), 0)')
                    ->whereColumn('unit_id', 'occupants.unit_id')
                    ->where('billed_to_type', 'occupant')
                    ->whereIn('status', ['unpaid', 'overdue', 'partially_paid']),
            ])
            ->orderByDesc('outstanding_amount')
            ->limit(10)
            ->get()
            ->filter(fn ($t) => ($t->outstanding_amount ?? 0) > 0)
            ->map(fn ($t) => [
                'organization_id'   => $t->id,
                'occupant_name' => $t->full_name ?? '—',
                'unit_number' => $t->unit?->unit_number ?? '—',
                'outstanding' => (float) ($t->outstanding_amount ?? 0),
            ])
            ->values()
            ->toArray();

        return [
            'occupancy' => [
                'owner_occupied'  => (int) ($occ?->owner_count  ?? 0),
                'occupant_occupied' => (int) ($occ?->occupant_count ?? 0),
                'vacant'          => (int) ($occ?->vacant_count ?? 0),
            ],
            'invoice_status' => [
                'paid'    => (int) ($inv?->paid_count    ?? 0),
                'overdue' => (int) ($inv?->overdue_count ?? 0),
                'partial' => (int) ($inv?->partial_count ?? 0),
            ],
            'top_owner_arrears'   => $topArrears,
            'occupant_lease_expiry' => $leaseExpiry,
            'top_occupant_arrears'  => $topOccupantArrears,
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
                $this->query->orderByRaw($this->unitNumberOrderRaw('units.unit_number', $direction));
                break;

            case 'section':
                $this->query->orderBy('units.section', $direction);
                break;

            case 'door_number':
                $this->query->orderByRaw($this->unitNumberOrderRaw('units.door_number', $direction));
                break;

            case 'customer_code':
                $this->query->orderBy('units.customer_code', $direction);
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
     * Create a new unit with its owner, and optionally a occupant.
     *
     * @param Community $community
     * @param array  $data
     * @return array
     * @throws Exception
     */
    public function createUnit(Community $community, array $data): array
    {
        $user = Auth::user();

        $unitData = collect($data)
            ->only(['unit_number', 'block_number', 'section', 'door_number', 'address', 'pq', 'occupancy_type', 'status', 'levy_override', 'rent_amount', 'billing_pdf'])
            ->toArray();

        $unit = Unit::create(array_merge($unitData, [
            'community_id' => $community->id,
            'organization_id' => $user->organization_id,
            'status'    => $unitData['status'] ?? 'active',
        ]));

        // Always create the owner record
        $owner = null;
        if (!empty($data['owner'])) {
            $ownerData = collect($data['owner'])
                ->only(['full_name', 'email', 'phone', 'landline', 'id_number', 'entity_type', 'address', 'contact2_name', 'contact2_email', 'contact2_phone', 'contact2_landline'])
                ->toArray();

            $owner = Owner::create(array_merge($ownerData, [
                'unit_id'   => $unit->id,
                'organization_id' => $user->organization_id,
            ]));
        }

        // Stamp a stable customer code (e.g. ATL001-D1) derived from the owner surname.
        $unit->customer_code = $this->generateCustomerCode($unit, $owner);
        $unit->save();

        // Create occupant record if occupancy_type is occupant_occupied and occupant data is provided
        $occupancyType = $unitData['occupancy_type'] ?? null;

        if ($occupancyType === OccupancyType::OCCUPANT_OCCUPIED->value && !empty($data['occupant'])) {
            $occupantData = collect($data['occupant'])
                ->only(['full_name', 'email', 'phone', 'id_number', 'lease_start', 'lease_end', 'rent_amount'])
                ->toArray();

            Occupant::create(array_merge($occupantData, [
                'unit_id'   => $unit->id,
                'organization_id' => $user->organization_id,
                'is_active' => true,
            ]));
        }

        return $this->showCreatedResource($unit);
    }

    /**
     * Build a stable, human-readable customer code for a unit — e.g. "ATL001-D1".
     *
     * Format: <3-letter owner-name prefix><zero-padded per-prefix sequence>-D<door>.
     * The prefix is the first three alphabetic characters of the owner's full name
     * with spaces/punctuation stripped (mirrors WeConnectU: "A TLOWANA" → "ATL",
     * "K & D DIALE" → "KDD"). The sequence counts existing units in the same community
     * that already share the same 3-letter prefix, so codes stay unique per community.
     * Falls back to "UNT" when there is no owner name and to the unit number when there
     * is no door number. Collisions are resolved by incrementing the sequence.
     *
     * @param Unit       $unit
     * @param Owner|null $owner
     * @return string
     */
    private function generateCustomerCode(Unit $unit, ?Owner $owner): string
    {
        $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) ($owner?->full_name ?? '')));
        $prefix  = $letters !== '' ? str_pad(substr($letters, 0, 3), 3, 'X') : 'UNT';

        $door = trim((string) ($unit->door_number ?? '')) ?: trim((string) $unit->unit_number);

        // Count existing units in this community whose customer_code shares this prefix,
        // excluding the current unit so regeneration doesn't inflate the sequence.
        $seq = Unit::where('community_id', $unit->community_id)
            ->where('id', '!=', $unit->id)
            ->where('customer_code', 'like', $prefix . '%')
            ->count() + 1;

        do {
            $code   = sprintf('%s%03d-D%s', $prefix, $seq, $door);
            $exists = Unit::where('community_id', $unit->community_id)
                ->where('id', '!=', $unit->id)
                ->where('customer_code', $code)
                ->exists();
            $seq++;
        } while ($exists);

        return $code;
    }

    /**
     * Return paginated activity entries for a unit, newest first.
     *
     * @param Community $community
     * @param Unit   $unit
     * @return array
     */
    public function showUnitActivities(Community $community, Unit $unit): array
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
     * @param Community $community
     * @param Unit   $unit
     * @return UnitResource
     */
    public function showUnit(Community $community, Unit $unit): UnitResource
    {
        // Re-query so the balance subqueries are applied (Route Model Binding loads
        // the bare model without them, which would always return balance = 0).
        $loaded = Unit::where('units.id', $unit->id)
            ->with(['owner', 'owners', 'currentOccupant', 'occupants', 'ledgerConfigs.ledger', 'community', 'collectionNotes'])
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
     * Update a unit and optionally its owner's and occupant's details.
     * Records a detailed activity entry for every field that changed.
     *
     * @param Community $community
     * @param Unit   $unit
     * @param array  $data
     * @return array
     */
    public function updateUnit(Community $community, Unit $unit, array $data): array
    {
        $user = Auth::user();

        // ── Snapshot "before" state ──────────────────────────────────────
        $unit->loadMissing(['owner', 'currentOccupant']);

        $beforeUnit = [
            'unit_number'    => $unit->unit_number,
            'block_number'   => $unit->block_number,
            'section'        => $unit->section,
            'door_number'    => $unit->door_number,
            'customer_code'  => $unit->customer_code,
            'occupancy_type' => $unit->occupancy_type?->value ?? (string) $unit->occupancy_type,
            'pq'             => $unit->pq,
            'levy_override'  => $unit->levy_override,
            'rent_amount'    => $unit->rent_amount,
            'address'        => $unit->address,
        ];

        $beforeOwner = $unit->owner ? [
            'full_name'         => $unit->owner->full_name,
            'email'             => $unit->owner->email,
            'phone'             => $unit->owner->phone,
            'landline'          => $unit->owner->landline,
            'id_number'         => $unit->owner->id_number,
            'entity_type'       => $unit->owner->entity_type?->value ?? (string) $unit->owner->entity_type,
            'contact2_name'     => $unit->owner->contact2_name,
            'contact2_email'    => $unit->owner->contact2_email,
            'contact2_phone'    => $unit->owner->contact2_phone,
            'contact2_landline' => $unit->owner->contact2_landline,
            'address'           => $unit->owner->address,
        ] : null;

        $beforeOccupant = $unit->currentOccupant ? [
            'full_name'   => $unit->currentOccupant->full_name,
            'email'       => $unit->currentOccupant->email,
            'phone'       => $unit->currentOccupant->phone,
            'lease_start' => $unit->currentOccupant->lease_start,
            'lease_end'   => $unit->currentOccupant->lease_end,
        ] : null;

        // ── Apply updates ────────────────────────────────────────────────
        $unitData = collect($data)
            ->only(['unit_number', 'block_number', 'section', 'door_number', 'address', 'pq', 'occupancy_type', 'status', 'rent_amount', 'billing_pdf', 'collection_status', 'rental_agent_email', 'attorney_email', 'bondholder_email', 'unit_notes'])
            ->filter(fn($v) => !is_null($v))
            ->toArray();

        // levy_override can be explicitly set to null to clear it — keep it out of the
        // null-filter above and add it directly only when the caller sent the key.
        if (array_key_exists('levy_override', $data)) {
            $unitData['levy_override'] = $data['levy_override'];
        }

        $unit->update($unitData);

        if (!empty($data['owner'])) {
            $ownerData = collect($data['owner'])
                ->only([
                    'full_name', 'email', 'phone', 'landline', 'id_number', 'entity_type', 'address',
                    'contact2_name', 'contact2_email', 'contact2_phone', 'contact2_landline',
                    'customer_type', 'vat_no', 'alt_email', 'alt_phone', 'payment_type', 'pdf_password',
                    'customer_group', 'reference', 'old_customer_code',
                    'address_line_2', 'suburb', 'town', 'postal_code',
                    'account_holder', 'bank_name', 'account_type', 'account_number', 'branch_code', 'branch_name',
                    'notes',
                ])
                ->filter(fn($v) => !is_null($v))
                ->toArray();

            if ($unit->owner) {
                $unit->owner->update($ownerData);
            }
        }

        // Customer code is stable across ordinary edits. Regenerate it only on an
        // explicit ownership change (new owner surname) or when the door number moved.
        if (! empty($data['ownership_change']) || array_key_exists('door_number', $data)) {
            $unit->loadMissing('owner');
            $unit->customer_code = $this->generateCustomerCode($unit, $unit->owner);
            $unit->save();
        }

        $newOccupantCreated = false;

        if (!empty($data['occupant'])) {
            $occupantData = collect($data['occupant'])
                ->only(['full_name', 'email', 'phone', 'id_number', 'lease_start', 'lease_end'])
                ->filter(fn($v) => !is_null($v))
                ->toArray();

            $unit->loadMissing('currentOccupant');

            if ($unit->currentOccupant) {
                $unit->currentOccupant->update($occupantData);
            } else {
                Occupant::create(array_merge($occupantData, [
                    'unit_id'   => $unit->id,
                    'organization_id' => $user->organization_id,
                    'is_active' => true,
                ]));

                $newOccupantCreated = true;

                // Ensure the unit's occupancy reflects having a occupant
                $unit->update(['occupancy_type' => OccupancyType::OCCUPANT_OCCUPIED->value]);

                // Refresh so the response includes the newly created occupant
                $unit->unsetRelation('currentOccupant');
                $unit->load('currentOccupant');
            }
        }

        // ── Snapshot "after" state and build diffs ───────────────────────
        $unit->refresh();
        $unit->loadMissing(['owner', 'currentOccupant']);

        $this->recordUnitActivities(
            unit:            $unit,
            user:            $user,
            beforeUnit:      $beforeUnit,
            beforeOwner:     $beforeOwner,
            beforeOccupant:    $beforeOccupant,
            submittedOwner:  $data['owner'] ?? null,
            submittedOccupant: $data['occupant'] ?? null,
            newOccupantCreated: $newOccupantCreated,
        );

        return $this->showUpdatedResource($unit);
    }

    /**
     * Toggle a unit's development status (WeConnectU "Set as development unit").
     *
     * @param Community $community
     * @param Unit   $unit
     * @return array
     */
    public function toggleDevelopment(Community $community, Unit $unit): array
    {
        $unit->is_development = ! $unit->is_development;
        $unit->save();

        return [
            'is_development' => $unit->is_development,
            'message'        => $unit->is_development
                ? 'Unit set as a development unit.'
                : 'Unit is no longer a development unit.',
        ];
    }

    /**
     * Add an additional (non-primary) owner to a unit (WeConnectU "Add Owner").
     *
     * @param Community $community
     * @param Unit   $unit
     * @param array  $data
     * @return array
     */
    public function addOwner(Community $community, Unit $unit, array $data): array
    {
        $user = Auth::user();

        $ownerData = collect($data)
            ->only(['full_name', 'email', 'phone', 'landline', 'id_number', 'entity_type', 'contact2_name', 'contact2_email', 'contact2_phone', 'contact2_landline', 'user_display_name'])
            ->toArray();

        // A unit's first owner is primary; any further owner is a co-owner.
        $isFirst = ! $unit->owners()->exists();

        Owner::create(array_merge($ownerData, [
            'unit_id'         => $unit->id,
            'organization_id' => $user->organization_id,
            'is_primary'      => $isFirst,
        ]));

        return ['message' => 'Owner added.'];
    }

    /**
     * Update a single owner record.
     *
     * @param Community $community
     * @param Unit   $unit
     * @param Owner  $owner
     * @param array  $data
     * @return array
     */
    public function updateOwnerRecord(Community $community, Unit $unit, Owner $owner, array $data): array
    {
        $ownerData = collect($data)
            ->only(['full_name', 'email', 'phone', 'landline', 'id_number', 'entity_type', 'contact2_name', 'contact2_email', 'contact2_phone', 'contact2_landline', 'user_display_name', 'user_verified'])
            ->toArray();

        $owner->update($ownerData);

        return ['message' => 'Owner updated.'];
    }

    /**
     * Delete an owner. The last remaining owner cannot be removed; deleting the
     * primary owner promotes the next owner to primary.
     *
     * @param Community $community
     * @param Unit   $unit
     * @param Owner  $owner
     * @return array
     * @throws Exception
     */
    public function deleteOwner(Community $community, Unit $unit, Owner $owner): array
    {
        if ($unit->owners()->count() <= 1) {
            throw new Exception('A unit must have at least one owner.');
        }

        $wasPrimary = $owner->is_primary;
        $owner->delete();

        if ($wasPrimary) {
            $next = $unit->owners()->orderBy('created_at')->first();
            $next?->update(['is_primary' => true]);
        }

        return ['message' => 'Owner removed.'];
    }

    /**
     * Add a collection note to a unit's finances log.
     *
     * @param Community $community
     * @param Unit   $unit
     * @param string $note
     * @return array
     */
    public function addCollectionNote(Community $community, Unit $unit, string $note): array
    {
        $user = Auth::user();

        $created = UnitCollectionNote::create([
            'unit_id'         => $unit->id,
            'organization_id' => $unit->organization_id,
            'note'            => $note,
            'created_by_name' => $user?->name ?? $user?->full_name ?? 'System',
            'user_id'         => $user?->id,
        ]);

        return ['data' => new UnitCollectionNoteResource($created)];
    }

    /**
     * Return a unit's collection notes, newest first.
     *
     * @param Community $community
     * @param Unit      $unit
     * @return array
     */
    public function showCollectionNotes(Community $community, Unit $unit): array
    {
        $notes = UnitCollectionNote::where('unit_id', $unit->id)
            ->orderByDesc('created_at')
            ->get();

        return ['data' => UnitCollectionNoteResource::collection($notes)];
    }

    /**
     * Return the unit's communication (e-mail) log, paginated newest-first.
     *
     * @param Community $community
     * @param Unit   $unit
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function showCommunications(Community $community, Unit $unit)
    {
        $perPage = max(1, (int) request()->input('_per_page', 15));

        return UnitCommunicationResource::collection(
            $unit->communications()->paginate($perPage)
        );
    }

    /**
     * Compose + send a unit e-mail via Resend, logging it to the communication log.
     *
     * @param Community $community
     * @param Unit   $unit
     * @param array  $data
     * @param array  $files  Uploaded attachment files
     * @return array
     */
    public function sendCommunication(Community $community, Unit $unit, array $data, array $files = []): array
    {
        $user = Auth::user();

        $attachments     = [];
        $attachmentNames = [];
        foreach ($files as $file) {
            $attachmentNames[] = $file->getClientOriginalName();
            $attachments[] = [
                'filename' => $file->getClientOriginalName(),
                'content'  => base64_encode(file_get_contents($file->getRealPath())),
            ];
        }

        $bccList = collect(explode(',', (string) ($data['bcc'] ?? '')))
            ->map(fn ($e) => trim($e))->filter()->values()->all();

        $fromAddr = config('mail.from.name') . ' <' . config('mail.from.address') . '>';
        $resendId = null;

        if (app()->isLocal() || app()->runningUnitTests()) {
            Log::info("[local] Communication email suppressed — would send to {$data['recipient_email']}", [
                'unit' => $unit->unit_number, 'subject' => $data['subject'],
            ]);
        } else {
            $payload = [
                'from'    => $fromAddr,
                'to'      => [$data['recipient_email']],
                'subject' => $data['subject'],
                'html'    => $data['body'] ?? '',
            ];
            if (! empty($bccList))     $payload['bcc']         = $bccList;
            if (! empty($attachments)) $payload['attachments'] = $attachments;

            $response = Resend::emails()->send($payload);
            $resendId = $response->id ?? null;
        }

        $created = UnitCommunication::create([
            'unit_id'          => $unit->id,
            'organization_id'  => $unit->organization_id,
            'subject'          => $data['subject'],
            'body'             => $data['body'] ?? '',
            'recipient_name'   => $data['recipient_name'] ?? null,
            'recipient_email'  => $data['recipient_email'],
            'bcc'              => ! empty($bccList) ? implode(', ', $bccList) : null,
            'sent_by_name'     => $user?->name ?? $user?->full_name ?? config('mail.from.name'),
            'attachment_names' => ! empty($attachmentNames) ? $attachmentNames : null,
            'resend_email_id'  => $resendId,
            'user_id'          => $user?->id,
        ]);

        return ['data' => new UnitCommunicationResource($created), 'message' => 'E-mail sent.'];
    }

    /**
     * Re-send a previously sent communication, logging a fresh entry.
     *
     * @param Community $community
     * @param Unit   $unit
     * @param UnitCommunication $communication
     * @return array
     */
    public function resendCommunication(Community $community, Unit $unit, UnitCommunication $communication): array
    {
        return $this->sendCommunication($community, $unit, [
            'recipient_email' => $communication->recipient_email,
            'recipient_name'  => $communication->recipient_name,
            'bcc'             => $communication->bcc,
            'subject'         => $communication->subject,
            'body'            => $communication->body,
        ]);
    }

    /**
     * Download a single communication as a PDF.
     *
     * @param Community $community
     * @param Unit   $unit
     * @param UnitCommunication $communication
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadCommunication(Community $community, Unit $unit, UnitCommunication $communication): \Symfony\Component\HttpFoundation\Response
    {
        $rows = [
            ['Date', $communication->created_at?->toDateTimeString()],
            ['To', trim(($communication->recipient_name ?? '') . ' <' . $communication->recipient_email . '>')],
            ['Sent By', $communication->sent_by_name],
            ['Subject', $communication->subject],
            ['Message', strip_tags((string) $communication->body)],
        ];

        return $this->buildFileResponse(
            $rows,
            ['Field', 'Value'],
            'communication-' . strtolower((string) ($unit->customer_code ?: $unit->unit_number)) . '-' . ($communication->created_at?->format('Y-m-d') ?? ''),
            'pdf',
            'Communication — ' . $unit->unit_number,
            ['Subject' => $communication->subject]
        );
    }

    /**
     * List a unit's offences.
     *
     * @param Community $community
     * @param Unit   $unit
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function showOffences(Community $community, Unit $unit)
    {
        return UnitOffenceResource::collection($unit->offences()->get());
    }

    /**
     * Normalise offence input (rules array, attachment file names).
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    private function offenceAttributes(array $data, array $files): array
    {
        $attrs = collect($data)->only(['status', 'issued_date', 'description', 'rules'])->toArray();

        if (isset($attrs['rules']) && is_array($attrs['rules'])) {
            $attrs['rules'] = array_values(array_filter($attrs['rules'], fn ($r) => ! empty($r['rule']) || ! empty($r['clause'])));
        }

        if (! empty($files)) {
            $attrs['attachment_names'] = array_map(fn ($f) => $f->getClientOriginalName(), $files);
        }

        return $attrs;
    }

    /**
     * Create an offence for a unit.
     *
     * @param Community $community
     * @param Unit   $unit
     * @param array  $data
     * @param array  $files
     * @return array
     */
    public function createOffence(Community $community, Unit $unit, array $data, array $files = []): array
    {
        $user = Auth::user();

        $offence = UnitOffence::create(array_merge($this->offenceAttributes($data, $files), [
            'unit_id'         => $unit->id,
            'organization_id' => $unit->organization_id,
            'status'          => $data['status'] ?? 'warning',
            'created_by_name' => $user?->name ?? $user?->full_name ?? 'System',
            'user_id'         => $user?->id,
        ]));

        return ['data' => new UnitOffenceResource($offence), 'message' => 'Offence added.'];
    }

    /**
     * Update an offence.
     *
     * @param Community $community
     * @param Unit   $unit
     * @param UnitOffence $offence
     * @param array  $data
     * @param array  $files
     * @return array
     */
    public function updateOffence(Community $community, Unit $unit, UnitOffence $offence, array $data, array $files = []): array
    {
        $offence->update($this->offenceAttributes($data, $files));

        return ['data' => new UnitOffenceResource($offence->fresh()), 'message' => 'Offence updated.'];
    }

    /**
     * Delete an offence.
     *
     * @param Community $community
     * @param Unit   $unit
     * @param UnitOffence $offence
     * @return array
     */
    public function deleteOffence(Community $community, Unit $unit, UnitOffence $offence): array
    {
        $offence->delete();

        return ['message' => 'Offence removed.'];
    }

    /**
     * List a unit's tasks (with feedback log + update counts).
     *
     * @param Community $community
     * @param Unit   $unit
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function showTasks(Community $community, Unit $unit)
    {
        return UnitTaskResource::collection(
            $unit->tasks()->withCount('updates')->with('updates')->get()
        );
    }

    /**
     * Generate a stable per-community task code, e.g. "OAK-086".
     *
     * @param Community $community
     * @return string
     */
    private function generateTaskCode(Community $community): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $community->name ?? 'TSK'), 0, 3));
        $prefix = str_pad($prefix ?: 'TSK', 3, 'X');

        $sequence = UnitTask::where('community_id', $community->id)->count() + 1;

        return $prefix . '-' . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Normalise task input (arrays, attachment file names).
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    private function taskAttributes(array $data, array $files): array
    {
        $attrs = collect($data)->only([
            'title', 'description', 'category', 'task_type', 'area', 'recurring_type',
            'assignee_name', 'assignee_user_id', 'status', 'internal', 'due_date',
            'contacts', 'supplier_names',
        ])->toArray();

        foreach (['contacts', 'supplier_names'] as $key) {
            if (isset($attrs[$key]) && is_array($attrs[$key])) {
                $attrs[$key] = array_values(array_filter($attrs[$key], fn ($v) => filled($v)));
            }
        }

        if (! empty($files)) {
            $attrs['attachment_names'] = array_map(fn ($f) => $f->getClientOriginalName(), $files);
        }

        return $attrs;
    }

    /**
     * Create a task for a unit.
     *
     * @param Community $community
     * @param Unit   $unit
     * @param array  $data
     * @param array  $files
     * @return array
     */
    public function createTask(Community $community, Unit $unit, array $data, array $files = []): array
    {
        $user = Auth::user();

        $task = UnitTask::create(array_merge($this->taskAttributes($data, $files), [
            'code'            => $this->generateTaskCode($community),
            'unit_id'         => $unit->id,
            'community_id'    => $community->id,
            'organization_id' => $unit->organization_id,
            'status'          => $data['status'] ?? 'open',
            'created_by_name' => $user?->name ?? 'System',
            'user_id'         => $user?->id,
        ]));

        return [
            'data'    => new UnitTaskResource($task->fresh(['updates'])->loadCount('updates')),
            'message' => 'Task added.',
        ];
    }

    /**
     * Update a task; logs a status-change entry when the status changes.
     *
     * @param Community $community
     * @param Unit   $unit
     * @param UnitTask $task
     * @param array  $data
     * @param array  $files
     * @return array
     */
    public function updateTask(Community $community, Unit $unit, UnitTask $task, array $data, array $files = []): array
    {
        $user           = Auth::user();
        $previousStatus = $task->status instanceof \BackedEnum ? $task->status->value : $task->status;

        $task->update($this->taskAttributes($data, $files));

        $newStatus = $task->status instanceof \BackedEnum ? $task->status->value : $task->status;

        if (array_key_exists('status', $data) && $newStatus !== $previousStatus) {
            UnitTaskUpdate::create([
                'unit_task_id'    => $task->id,
                'organization_id' => $task->organization_id,
                'event'           => 'Status changed to: ' . $this->taskStatusLabel($newStatus),
                'created_by_name' => $user?->name ?? 'System',
                'user_id'         => $user?->id,
            ]);
        }

        return [
            'data'    => new UnitTaskResource($task->fresh(['updates'])->loadCount('updates')),
            'message' => 'Task updated.',
        ];
    }

    /**
     * Delete a task.
     *
     * @param Community $community
     * @param Unit   $unit
     * @param UnitTask $task
     * @return array
     */
    public function deleteTask(Community $community, Unit $unit, UnitTask $task): array
    {
        $task->delete();

        return ['message' => 'Task removed.'];
    }

    /**
     * Add a feedback/update entry to a task.
     *
     * @param Community $community
     * @param Unit   $unit
     * @param UnitTask $task
     * @param array  $data
     * @param array  $files
     * @return array
     */
    public function addTaskUpdate(Community $community, Unit $unit, UnitTask $task, array $data, array $files = []): array
    {
        $user = Auth::user();

        $attrs = [
            'unit_task_id'    => $task->id,
            'organization_id' => $task->organization_id,
            'feedback'        => $data['feedback'] ?? null,
            'notify'          => $data['notify'] ?? null,
            'created_by_name' => $user?->name ?? 'System',
            'user_id'         => $user?->id,
        ];

        if (! empty($files)) {
            $attrs['attachment_names'] = array_map(fn ($f) => $f->getClientOriginalName(), $files);
        }

        $update = UnitTaskUpdate::create($attrs);

        return ['data' => new UnitTaskUpdateResource($update), 'message' => 'Feedback added.'];
    }

    /**
     * Human-friendly label for a task status value.
     *
     * @param string|null $status
     * @return string
     */
    private function taskStatusLabel(?string $status): string
    {
        return match ($status) {
            'open'        => 'Open',
            'in_progress' => 'In Progress',
            'complete'    => 'Complete',
            default       => ucfirst((string) $status),
        };
    }

    /**
     * List a unit's uploaded documents.
     *
     * @param Community $community
     * @param Unit   $unit
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function showDocuments(Community $community, Unit $unit)
    {
        return UnitDocumentResource::collection($unit->documents()->get());
    }

    /**
     * Upload and store a document for a unit.
     *
     * @param Community $community
     * @param Unit   $unit
     * @param array  $data
     * @param \Illuminate\Http\UploadedFile $file
     * @return array
     */
    public function uploadDocument(Community $community, Unit $unit, array $data, $file): array
    {
        $user = Auth::user();

        $path = $file->store("unit_documents/{$unit->organization_id}", 'public');

        $document = UnitDocument::create([
            'name'             => $data['name'] ?? $file->getClientOriginalName(),
            'original_name'    => $file->getClientOriginalName(),
            'file_path'        => $path,
            'mime_type'        => $file->getClientMimeType(),
            'size'             => $file->getSize(),
            'uploaded_by_name' => $user?->name ?? 'System',
            'user_id'          => $user?->id,
            'unit_id'          => $unit->id,
            'organization_id'  => $unit->organization_id,
        ]);

        return ['data' => new UnitDocumentResource($document), 'message' => 'Document uploaded.'];
    }

    /**
     * Delete a document (and its stored file).
     *
     * @param Community $community
     * @param Unit   $unit
     * @param UnitDocument $document
     * @return array
     */
    public function deleteDocument(Community $community, Unit $unit, UnitDocument $document): array
    {
        if ($document->file_path) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return ['message' => 'Document removed.'];
    }

    /**
     * Build a customer statement (Date / Source / Description / Debit / Credit /
     * Balance) for a unit over an optional date range, with an opening "Balance b/f".
     *
     * @param Unit        $unit
     * @param string|null $from  Y-m-d
     * @param string|null $to    Y-m-d
     * @return array{headings: array, rows: array, opening: float, totals: array}
     */
    public function buildStatementData(Unit $unit, ?string $from, ?string $to): array
    {
        $invoices = Invoice::where('unit_id', $unit->id)->get();
        $payments = CashbookEntry::where('unit_id', $unit->id)->where('type', 'credit')->with('ledger')->get();

        // Opening balance = debits − credits strictly before the "from" date.
        $opening = 0.0;
        if ($from) {
            $opening += (float) $invoices->filter(fn ($i) => optional($i->billing_period)->toDateString() < $from)->sum('amount');
            $opening -= (float) $payments->filter(fn ($p) => optional($p->date)->toDateString() < $from)->sum('amount');
        }
        $opening += (new JournalPostingService())->openingBefore($unit->id, $from);

        $events = [];
        foreach ($invoices as $inv) {
            $d = optional($inv->billing_period)->toDateString() ?? optional($inv->created_at)->toDateString();
            $events[] = ['date' => $d, 'source' => 'Invoice', 'description' => $inv->invoice_number, 'debit' => (float) $inv->amount, 'credit' => 0.0];
        }
        foreach ($payments as $p) {
            $d = optional($p->date)->toDateString() ?? optional($p->created_at)->toDateString();
            $events[] = ['date' => $d, 'source' => $p->ledger?->name ?: 'Receipt', 'description' => $p->description ?: 'Payment received', 'debit' => 0.0, 'credit' => (float) $p->amount];
        }
        foreach ((new JournalPostingService())->eventsForUnit($unit->id) as $j) {
            $events[] = ['date' => $j['date'], 'source' => $j['source'], 'description' => $j['description'], 'debit' => $j['debit'], 'credit' => $j['credit']];
        }

        $events = array_values(array_filter($events, function ($e) use ($from, $to) {
            if ($from && ($e['date'] ?? '') < $from) return false;
            if ($to && ($e['date'] ?? '') > $to) return false;
            return true;
        }));
        usort($events, fn ($a, $b) => ($a['date'] ?? '') <=> ($b['date'] ?? ''));

        $balance = $opening;
        $rows = [];
        $rows[] = [$from ?: ($events[0]['date'] ?? ''), '', 'Balance b/f', '', $opening !== 0.0 ? number_format($opening, 2) : '', number_format($opening, 2)];
        $debitTotal = 0.0;
        $creditTotal = 0.0;
        foreach ($events as $e) {
            $balance += $e['debit'] - $e['credit'];
            $debitTotal += $e['debit'];
            $creditTotal += $e['credit'];
            $rows[] = [
                $e['date'], $e['source'], $e['description'],
                $e['debit'] ? number_format($e['debit'], 2) : '',
                $e['credit'] ? number_format($e['credit'], 2) : '',
                number_format($balance, 2),
            ];
        }
        $rows[] = ['Totals', '', '', number_format($debitTotal, 2), number_format($creditTotal, 2), number_format($balance, 2)];

        return [
            'headings' => ['Date', 'Source', 'Description', 'Debit', 'Credit', 'Balance'],
            'rows'     => $rows,
            'opening'  => $opening,
            'totals'   => ['debit' => $debitTotal, 'credit' => $creditTotal, 'balance' => $balance],
        ];
    }

    /**
     * Download a unit's customer statement (xlsx / pdf / csv). WeConnectU ⋮ menu.
     *
     * @param Community $community
     * @param Unit   $unit
     * @param array  $data
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadStatement(Community $community, Unit $unit, array $data): \Symfony\Component\HttpFoundation\Response
    {
        $from = $data['from'] ?? null;
        $to   = $data['to'] ?? null;
        $st   = $this->buildStatementData($unit, $from, $to);

        $format   = in_array($data['_format'] ?? 'xlsx', ['csv', 'xlsx', 'pdf'], true) ? ($data['_format'] ?? 'xlsx') : 'xlsx';

        if ($format === 'pdf') {
            return $this->renderStatementPdf($community, $unit, $from, $to);
        }

        $unit->loadMissing('owner');
        $who      = $unit->owner?->full_name ?? $unit->unit_number;
        $filename = trim('customer statement-' . strtolower((string) $unit->customer_code) . ' _ ' . strtolower($who) . '-' . ($from ?: '') . ' - ' . ($to ?: ''));

        return $this->buildFileResponse(
            $st['rows'],
            $st['headings'],
            $filename,
            $format,
            'Customer Statement — ' . $who,
            ['Unit' => $unit->unit_number, 'Customer Code' => $unit->customer_code, 'Period' => trim(($from ?: '…') . ' → ' . ($to ?: '…'))]
        );
    }

    /**
     * Render a unit's customer statement as a WeConnectU-style PDF.
     *
     * @param Community $community
     * @param Unit $unit
     * @param string|null $from
     * @param string|null $to
     * @return \Symfony\Component\HttpFoundation\Response
     */
    /**
     * Build a customer's on-screen ledger (Detailed Customer Ledger / Status Management drill-in).
     *
     * @param Community $community
     * @param Unit $unit
     * @param string|null $from
     * @param string|null $to
     * @return array
     */
    public function buildCustomerLedger(Community $community, Unit $unit, ?string $from = null, ?string $to = null): array
    {
        $unit->loadMissing('owner');
        $customer     = $unit->owner;
        $customerCode = $customer?->customer_code ?: $unit->customer_code;

        $bank = \App\Models\BankAccount::where('community_id', $community->id)
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN type = 'current' THEN 0 ELSE 1 END")
            ->first();
        $bankSource = $bank ? strtoupper((string) $bank->bank_name) . ': ' . $bank->account_number : 'Receipt';

        $invoices = Invoice::where('unit_id', $unit->id)->get();
        $payments = CashbookEntry::where('unit_id', $unit->id)->where('type', 'credit')->get();

        $opening = 0.0;
        if ($from) {
            $opening += (float) $invoices->filter(fn ($i) => optional($i->billing_period)->toDateString() < $from)->sum('amount');
            $opening -= (float) $payments->filter(fn ($p) => optional($p->date)->toDateString() < $from)->sum('amount');
        }
        $opening += (new JournalPostingService())->openingBefore($unit->id, $from);

        $events = [];
        foreach ($invoices as $inv) {
            $d = optional($inv->billing_period)->toDateString() ?? optional($inv->created_at)->toDateString();
            $events[] = ['date' => $d, 'source' => 'Invoice', 'description' => $inv->invoice_number, 'remarks' => '', 'debit' => (float) $inv->amount, 'credit' => 0.0, 'invoice_id' => $inv->id, 'invoice_number' => $inv->invoice_number];
        }
        foreach ($payments as $p) {
            $d = optional($p->date)->toDateString() ?? optional($p->created_at)->toDateString();
            // WeConnectU splits a quoted remark (e.g. "Payment - Thank you") out of the description.
            $desc = (string) ($p->description ?: 'Payment received');
            $remarks = '';
            if (preg_match('/"([^"]+)"/', $desc, $m)) {
                $remarks = $m[1];
                $desc = trim(preg_replace('/\s*-?\s*"[^"]+"/', '', $desc));
            }
            $events[] = ['date' => $d, 'source' => $bankSource, 'description' => $desc, 'remarks' => $remarks, 'debit' => 0.0, 'credit' => (float) $p->amount, 'invoice_id' => null, 'invoice_number' => null];
        }
        foreach ((new JournalPostingService())->eventsForUnit($unit->id) as $j) {
            $events[] = ['date' => $j['date'], 'source' => $j['source'], 'description' => $j['description'], 'remarks' => '', 'debit' => $j['debit'], 'credit' => $j['credit'], 'invoice_id' => null, 'invoice_number' => null];
        }

        $events = array_values(array_filter($events, function ($e) use ($from, $to) {
            if ($from && ($e['date'] ?? '') < $from) return false;
            if ($to && ($e['date'] ?? '') > $to) return false;
            return true;
        }));
        usort($events, fn ($a, $b) => ($a['date'] ?? '') <=> ($b['date'] ?? ''));

        $balance     = $opening;
        $debitTotal  = 0.0;
        $creditTotal = 0.0;
        $rows = [[
            'date' => $from ?: ($events[0]['date'] ?? $to), 'source' => '', 'description' => 'Balance b/f', 'remarks' => '',
            'debit' => $opening, 'credit' => 0.0, 'cumulative' => $opening, 'invoice_id' => null, 'invoice_number' => null,
        ]];
        foreach ($events as $e) {
            $balance     += $e['debit'] - $e['credit'];
            $debitTotal  += $e['debit'];
            $creditTotal += $e['credit'];
            $rows[] = array_merge($e, ['cumulative' => round($balance, 2)]);
        }

        return [
            'customer' => [
                'unit_id'       => $unit->id,
                'unit_number'   => $unit->unit_number,
                'customer_code' => $customerCode,
                'customer_name' => $customer?->full_name ?? '—',
            ],
            'rows'   => $rows,
            'totals' => ['debit' => round($debitTotal, 2), 'credit' => round($creditTotal, 2), 'balance' => round($balance, 2)],
        ];
    }

    private function renderStatementPdf(Community $community, Unit $unit, ?string $from, ?string $to): \Symfony\Component\HttpFoundation\Response
    {
        $vars = $this->buildStatementPresentation($community, $unit, $from, $to);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.statement', $vars)->setPaper('a4', 'portrait');

        // Enable inline PHP so the "Page X/Y" page-text script runs.
        $pdf->getDomPDF()->getOptions()->setIsPhpEnabled(true);

        return $pdf->download('CustomerStatement-' . ($vars['customerCode'] ?: $unit->unit_number) . '.pdf');
    }

    /**
     * Assemble every presentation variable the statement blade needs for a unit
     * (header, customer block, transaction ledger, ageing buckets, banking + logo).
     * Extracted so both the single-statement PDF and the combined "View PDF"
     * (Customer Statements) render the exact same layout.
     *
     * @param Community $community
     * @param Unit $unit
     * @param string|null $from
     * @param string|null $to
     * @return array<string, mixed>
     */
    public function buildStatementPresentation(Community $community, Unit $unit, ?string $from, ?string $to): array
    {
        $unit->loadMissing('owner');
        $customer     = $unit->owner;
        $customerCode = $customer?->customer_code ?: $unit->customer_code;

        // Community's primary (current) bank account drives the footer + payment source.
        $bank = \App\Models\BankAccount::where('community_id', $community->id)
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN type = 'current' THEN 0 ELSE 1 END")
            ->first();
        $bankSource = $bank ? strtoupper((string) $bank->bank_name) . ":\n" . $bank->account_number : 'Receipt';

        // ── Transaction ledger (invoices as debits, receipts as credits) ──
        $invoices = Invoice::where('unit_id', $unit->id)->get();
        $payments = CashbookEntry::where('unit_id', $unit->id)->where('type', 'credit')->get();

        $opening = 0.0;
        if ($from) {
            $opening += (float) $invoices->filter(fn ($i) => optional($i->billing_period)->toDateString() < $from)->sum('amount');
            $opening -= (float) $payments->filter(fn ($p) => optional($p->date)->toDateString() < $from)->sum('amount');
        }
        $opening += (new JournalPostingService())->openingBefore($unit->id, $from);

        $events = [];
        foreach ($invoices as $inv) {
            $d = optional($inv->billing_period)->toDateString() ?? optional($inv->created_at)->toDateString();
            $events[] = ['date' => $d, 'source' => 'Invoice', 'description' => $inv->invoice_number, 'debit' => (float) $inv->amount, 'credit' => 0.0, 'invoice_id' => $inv->id];
        }
        foreach ($payments as $p) {
            $d = optional($p->date)->toDateString() ?? optional($p->created_at)->toDateString();
            $events[] = ['date' => $d, 'source' => $bankSource, 'description' => $p->description ?: 'Payment received', 'debit' => 0.0, 'credit' => (float) $p->amount, 'invoice_id' => null];
        }
        foreach ((new JournalPostingService())->eventsForUnit($unit->id) as $j) {
            $events[] = ['date' => $j['date'], 'source' => $j['source'], 'description' => $j['description'], 'debit' => $j['debit'], 'credit' => $j['credit'], 'invoice_id' => null];
        }

        $events = array_values(array_filter($events, function ($e) use ($from, $to) {
            if ($from && ($e['date'] ?? '') < $from) return false;
            if ($to && ($e['date'] ?? '') > $to) return false;
            return true;
        }));
        usort($events, fn ($a, $b) => ($a['date'] ?? '') <=> ($b['date'] ?? ''));

        $balance = $opening;
        $rows = [];
        $rows[] = ['date' => $from ?: ($events[0]['date'] ?? $to), 'source' => '', 'description' => 'Balance b/f', 'debit' => $opening, 'credit' => 0.0, 'cumulative' => $opening, 'invoice_id' => null];
        foreach ($events as $e) {
            $balance += $e['debit'] - $e['credit'];
            $rows[] = ['date' => $e['date'], 'source' => $e['source'], 'description' => $e['description'], 'debit' => $e['debit'], 'credit' => $e['credit'], 'cumulative' => $balance, 'invoice_id' => $e['invoice_id']];
        }

        // ── Ageing buckets (reuse the Age Analysis computation for an exact match) ──
        $ageRow = collect((new AgeAnalysisService())->getAgeAnalysis($community, [])['rows'])
            ->firstWhere('unit_id', $unit->id);
        $ageing = [
            '120_plus' => (float) ($ageRow['120_plus'] ?? 0),
            '90_days'  => (float) ($ageRow['90_days'] ?? 0),
            '60_days'  => (float) ($ageRow['60_days'] ?? 0),
            '30_days'  => (float) ($ageRow['30_days'] ?? 0),
            'current'  => (float) ($ageRow['current'] ?? 0),
        ];

        // ── Header + customer presentation ──
        $et          = $community->entity_type instanceof \BackedEnum ? $community->entity_type->value : $community->entity_type;
        $entityLabel = ($community->suppress_entity_type || ! $et) ? '' : ucwords(str_replace('_', ' ', (string) $et));

        if ($customer && (trim((string) $customer->suburb) !== '' || trim((string) $customer->town) !== '' || trim((string) $customer->postal_code) !== '' || trim((string) $customer->address_line_2) !== '')) {
            $addressLines = collect([$customer->address, $customer->address_line_2, $customer->suburb, $customer->town, $customer->postal_code])
                ->filter(fn ($l) => trim((string) $l) !== '')->values();
        } else {
            $addressLines = collect(preg_split('/,\s*/', (string) ($customer?->address ?? '')))
                ->filter(fn ($l) => trim($l) !== '')->values();
        }

        $statementDate = $to ?: now()->toDateString();
        $totalDue      = $balance;
        $frontendUrl   = rtrim((string) config('app.frontend_url'), '/');
        $accountType   = strtoupper($bank ? ($bank->type instanceof \BackedEnum ? $bank->type->value : (string) $bank->type) : 'CURRENT');

        $organization    = $community->organization;
        $companyLogoPath = $organization?->logoFilePath();

        return compact(
            'community', 'unit', 'customer', 'customerCode', 'addressLines', 'entityLabel',
            'statementDate', 'rows', 'ageing', 'totalDue', 'bank', 'accountType', 'frontendUrl',
            'organization', 'companyLogoPath'
        );
    }

    /**
     * E-mail a unit's customer statement to the primary owner (WeConnectU confirm).
     *
     * @param Community $community
     * @param Unit   $unit
     * @param array  $data
     * @return array
     */
    public function emailStatement(Community $community, Unit $unit, array $data): array
    {
        $unit->loadMissing('owner');
        $to = $unit->owner?->email;
        if (! $to) {
            return ['message' => 'No owner e-mail on file for this unit.'];
        }

        $from  = $data['from'] ?? null;
        $toDate = $data['to'] ?? null;
        $st    = $this->buildStatementData($unit, $from, $toDate);

        $subject = 'Statement — ' . ($unit->customer_code ?: $unit->unit_number);
        $rowsHtml = collect($st['rows'])->map(fn ($r) => '<tr>' . collect($r)->map(fn ($c) => '<td style="padding:4px 8px;border:1px solid #ddd">' . e($c) . '</td>')->implode('') . '</tr>')->implode('');
        $html = '<h3>' . e($subject) . '</h3><table style="border-collapse:collapse;font-family:sans-serif;font-size:13px"><thead><tr>'
            . collect($st['headings'])->map(fn ($h) => '<th style="padding:4px 8px;border:1px solid #ddd;text-align:left">' . e($h) . '</th>')->implode('')
            . '</tr></thead><tbody>' . $rowsHtml . '</tbody></table>';

        $fromAddr = config('mail.from.name') . ' <' . config('mail.from.address') . '>';
        if (app()->isLocal() || app()->runningUnitTests()) {
            Log::info("[local] Statement email suppressed — would send to {$to}", ['unit' => $unit->unit_number, 'subject' => $subject]);
        } else {
            Resend::emails()->send(['from' => $fromAddr, 'to' => [$to], 'subject' => $subject, 'html' => $html]);
        }

        // Record it on the collection-notes log, like WeConnectU.
        $user = Auth::user();
        UnitCollectionNote::create([
            'unit_id'         => $unit->id,
            'organization_id' => $unit->organization_id,
            'note'            => 'Statement e-mailed to ' . $to,
            'created_by_name' => $user?->name ?? $user?->full_name ?? 'System',
            'user_id'         => $user?->id,
        ]);

        return ['message' => 'Statement e-mailed to ' . $to];
    }

    /**
     * Human-readable field labels for activity display.
     */
    private const UNIT_FIELD_LABELS = [
        'unit_number'    => 'Unit Number',
        'block_number'   => 'Block Number',
        'section'        => 'Section',
        'door_number'    => 'Door Number',
        'customer_code'  => 'Customer Code',
        'occupancy_type' => 'Occupancy Type',
        'pq'             => 'PQ',
        'levy_override'  => 'Levy Override',
        'rent_amount'    => 'Rent Amount',
        'address'        => 'Address',
        'status'         => 'Status',
    ];

    private const OWNER_FIELD_LABELS = [
        'full_name'         => 'Full Name',
        'email'             => 'Email',
        'phone'             => 'Phone',
        'landline'          => 'Landline',
        'id_number'         => 'ID / Reg Number',
        'entity_type'       => 'Entity Type',
        'contact2_name'     => 'Contact 2 Name',
        'contact2_email'    => 'Contact 2 Email',
        'contact2_phone'    => 'Contact 2 Cellphone',
        'contact2_landline' => 'Contact 2 Landline',
        'address'           => 'Address',
    ];

    private const OCCUPANT_FIELD_LABELS = [
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
     * One entry per changed category (unit, owner, occupant).
     */
    private function recordUnitActivities(
        Unit  $unit,
        mixed $user,
        array $beforeUnit,
        ?array $beforeOwner,
        ?array $beforeOccupant,
        ?array $submittedOwner,
        ?array $submittedOccupant,
        bool  $newOccupantCreated,
    ): void {
        $batchId = (string) Str::uuid();

        $commonAttrs = [
            'unit_id'          => $unit->id,
            'organization_id'        => $unit->organization_id,
            'batch_id'         => $batchId,
            'user_id'          => $user?->id,
            'changed_by_name'  => $user?->name ?? $user?->full_name ?? 'System',
        ];

        // ── Unit fields diff ─────────────────────────────────────────────
        $afterUnit = [
            'unit_number'    => $unit->unit_number,
            'block_number'   => $unit->block_number,
            'section'        => $unit->section,
            'door_number'    => $unit->door_number,
            'customer_code'  => $unit->customer_code,
            'occupancy_type' => $unit->occupancy_type?->value ?? (string) $unit->occupancy_type,
            'pq'             => $unit->pq,
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
                'full_name'         => $unit->owner->full_name,
                'email'             => $unit->owner->email,
                'phone'             => $unit->owner->phone,
                'landline'          => $unit->owner->landline,
                'id_number'         => $unit->owner->id_number,
                'entity_type'       => $unit->owner->entity_type?->value ?? (string) $unit->owner->entity_type,
                'contact2_name'     => $unit->owner->contact2_name,
                'contact2_email'    => $unit->owner->contact2_email,
                'contact2_phone'    => $unit->owner->contact2_phone,
                'contact2_landline' => $unit->owner->contact2_landline,
                'address'           => $unit->owner->address,
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

        // ── Organization fields diff ───────────────────────────────────────────
        if ($submittedOccupant) {
            if ($newOccupantCreated) {
                // Brand-new occupant moved in — log as a dedicated "Moved in occupant" event
                UnitActivity::create(array_merge($commonAttrs, [
                    'event'    => 'Moved in occupant',
                    'category' => 'occupant',
                    'changes'  => array_filter([
                        !empty($unit->currentOccupant?->full_name) ? ['field' => 'Full Name', 'old' => null, 'new' => $unit->currentOccupant->full_name] : null,
                        !empty($unit->currentOccupant?->email)     ? ['field' => 'Email',     'old' => null, 'new' => $unit->currentOccupant->email]     : null,
                        !empty($unit->currentOccupant?->phone)     ? ['field' => 'Phone',     'old' => null, 'new' => $unit->currentOccupant->phone]     : null,
                        !empty($unit->currentOccupant?->lease_start) ? ['field' => 'Lease Start', 'old' => null, 'new' => $unit->currentOccupant->lease_start] : null,
                        !empty($unit->currentOccupant?->lease_end)   ? ['field' => 'Lease End',   'old' => null, 'new' => $unit->currentOccupant->lease_end]   : null,
                    ]),
                ]));
            } elseif ($unit->currentOccupant) {
                $afterOccupant = [
                    'full_name'   => $unit->currentOccupant->full_name,
                    'email'       => $unit->currentOccupant->email,
                    'phone'       => $unit->currentOccupant->phone,
                    'lease_start' => $unit->currentOccupant->lease_start,
                    'lease_end'   => $unit->currentOccupant->lease_end,
                ];

                $occupantChanges = $this->buildDiff(
                    $beforeOccupant ?? array_fill_keys(array_keys(self::OCCUPANT_FIELD_LABELS), null),
                    $afterOccupant,
                    self::OCCUPANT_FIELD_LABELS,
                    $submittedOccupant,
                );

                if (!empty($occupantChanges)) {
                    UnitActivity::create(array_merge($commonAttrs, [
                        'event'    => 'Updated occupant details',
                        'category' => 'occupant',
                        'changes'  => $occupantChanges,
                    ]));
                }
            }
        }
    }

    /**
     * Bulk delete units by an array of IDs.
     *
     * @param Community $community
     * @param array  $unitIds
     * @return array
     * @throws Exception
     */
    public function deleteUnits(Community $community, array $unitIds): array
    {
        $total = Unit::whereIn('id', $unitIds)
            ->where('community_id', $community->id)
            ->delete();

        if ($total === 0) {
            throw new Exception('No Units deleted');
        }

        $label = $total === 1 ? 'Unit' : 'Units';

        return ['message' => "{$total} {$label} deleted"];
    }

    /**
     * Delete a single unit.
     *
     * @param Community $community
     * @param Unit   $unit
     * @return array
     */
    public function deleteUnit(Community $community, Unit $unit): array
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
     * @param Community $community
     * @param string $format  'csv' | 'xlsx'
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\Response
     */
    public function downloadImportTemplate(Community $community, string $format)
    {
        $headers = [
            'unit_number',
            'section',
            'address',
            'occupancy_type',
            'levy_override',
            'rent_amount',
            'owner_full_name',
            'owner_id_number',
            'owner_email',
            'owner_phone',
            'owner_address',
            'occupant_full_name',
            'occupant_email',
            'occupant_phone',
            'occupant_lease_start',
            'occupant_lease_end',
        ];

        $example = [
            'A01',
            'A',
            '123 Main Street, Gaborone',
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
     * @param Community $community
     * @param mixed  $file   Uploaded file instance
     * @return array { columns: string[], rows: array[], total_rows: int }
     * @throws Exception
     */
    public function parseImportFile(Community $community, mixed $file): array
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
                    $rawCols         = array_map('trim', $row);
                    $validColIndexes = array_keys(array_filter($rawCols, fn($c) => $c !== ''));
                    $columns         = array_values(array_intersect_key($rawCols, array_flip($validColIndexes)));
                    continue;
                }

                // Map columns to associative array (only named columns)
                $assoc = [];
                foreach ($validColIndexes as $pos => $srcIdx) {
                    $assoc[$columns[$pos]] = trim($row[$srcIdx] ?? '');
                }

                $rows[] = $assoc;
            }

            fclose($handle);
        } else {
            // XLSX / XLS
            $spreadsheet = IOFactory::load($path);

            // Numbers (Mac) exports with an optional summary worksheet that becomes the
            // active sheet. Pick the first sheet whose name doesn't look like a summary,
            // falling back to sheet 0 if every sheet is named "Summary".
            $sheetCount = $spreadsheet->getSheetCount();
            $sheet      = null;

            for ($i = 0; $i < $sheetCount; $i++) {
                $candidate = $spreadsheet->getSheet($i);
                if (!preg_match('/summary/i', $candidate->getTitle())) {
                    $sheet = $candidate;
                    break;
                }
            }

            $sheet     = $sheet ?? $spreadsheet->getSheet(0);
            $sheetData = $sheet->toArray(null, true, true, false);

            if (empty($sheetData)) {
                throw new Exception('The uploaded file is empty.');
            }

            $rawColumns = array_map('trim', array_map('strval', $sheetData[0]));

            // Build an index of only the columns that have a non-empty header.
            // Numbers (Mac) exports often append blank trailing columns; dropping
            // them here prevents spurious entries in the column-mapping step.
            $validColIndexes = array_keys(array_filter($rawColumns, fn($c) => $c !== ''));
            $columns         = array_values(array_intersect_key($rawColumns, array_flip($validColIndexes)));

            foreach (array_slice($sheetData, 1) as $row) {
                // Skip entirely empty rows
                $values = array_map(fn($v) => trim((string) $v), $row);
                if (empty(array_filter($values))) {
                    continue;
                }

                $assoc = [];
                foreach ($validColIndexes as $pos => $srcIdx) {
                    $assoc[$columns[$pos]] = $values[$srcIdx] ?? '';
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
     * @param Community $community
     * @param array  $rows
     * @return array
     */
    /**
     * Map a raw import row's keys onto the system field names, accepting both the
     * import-template headers (already system keys) and the WeConnectU export headers
     * ("Unit No", "Owner / Contact Name", …). Unknown keys are preserved.
     *
     * @param array $row
     * @return array
     */
    private function normalizeUnitImportRow(array $row): array
    {
        static $aliases = [
            'block'                    => 'block_number',
            'section / erf no'         => 'section',
            'section'                  => 'section',
            'unit no'                  => 'unit_number',
            'unit number'             => 'unit_number',
            'door no'                  => 'door_number',
            'pq'                       => 'pq',
            'owner / contact name'     => 'owner_full_name',
            'id / passport'            => 'owner_id_number',
            'email address'            => 'owner_email',
            'cell number'              => 'owner_phone',
            'landline number'          => 'owner_landline',
            'contact 2 name'           => 'owner_contact2_name',
            'contact 2 email address'  => 'owner_contact2_email',
            'contact 2 cell number'    => 'owner_contact2_phone',
            'contact 2 landline number'=> 'owner_contact2_landline',
            'postal address'           => 'owner_address',
            'customer code'            => 'customer_code',
            'unit id'                  => 'unit_id',
            'owner id'                 => 'owner_id',
        ];

        $out = [];
        foreach ($row as $key => $value) {
            $normalized = $aliases[strtolower(trim((string) $key))] ?? $key;
            // Don't let a blank aliased column clobber an already-set system value.
            if (! array_key_exists($normalized, $out) || $out[$normalized] === '' || $out[$normalized] === null) {
                $out[$normalized] = $value;
            }
        }

        return $out;
    }

    public function bulkImportUnits(Community $community, array $rows): array
    {
        $user      = Auth::user();
        $organizationId  = $user->organization_id;
        $imported  = 0;
        $duplicates = 0;
        $errors    = [];

        // Pre-fetch existing unit numbers for this community to detect duplicates
        $existingUnitNumbers = Unit::where('community_id', $community->id)
            ->pluck('unit_number')
            ->map(fn($n) => strtolower(trim($n)))
            ->flip()
            ->toArray();

        foreach ($rows as $index => $row) {
            $rowNum = $index + 1;

            // Accept both the import-template headers and the export headers
            // ("Unit No", "Owner / Contact Name", …) so an exported file can be
            // edited and re-uploaded (WeConnectU-style round-trip bulk edit).
            $row = $this->normalizeUnitImportRow($row);

            // --- Server-side validation ---
            $rowErrors = [];

            $unitNumber    = trim($row['unit_number'] ?? '');
            $customerCode  = trim($row['customer_code'] ?? '');
            $unitId        = trim($row['unit_id'] ?? '');
            $occupancyType = trim($row['occupancy_type'] ?? '') ?: 'owner_occupied';
            $ownerName     = trim($row['owner_full_name'] ?? '');
            $ownerEmailRaw = trim($row['owner_email'] ?? '');

            // Support multiple semicolon-separated emails: first = primary, rest = secondary
            $ownerEmailParts    = array_values(array_filter(array_map('trim', explode(';', $ownerEmailRaw))));
            $ownerPrimaryEmail  = $ownerEmailParts[0] ?? '';
            $ownerSecondaryEmails = array_slice($ownerEmailParts, 1);

            if (!$unitNumber) {
                $rowErrors[] = 'Unit number is required.';
            }

            if (!in_array($occupancyType, ['owner_occupied', 'occupant_occupied', 'vacant'])) {
                $rowErrors[] = 'Occupancy type must be owner_occupied, occupant_occupied, or vacant.';
            }

            if (!$ownerName) {
                $rowErrors[] = 'Owner full name is required.';
            }

            foreach ($ownerEmailParts as $e) {
                if (!filter_var($e, FILTER_VALIDATE_EMAIL)) {
                    $rowErrors[] = "Owner email '{$e}' is not a valid email address.";
                }
            }

            $occupantEmailRaw = trim($row['occupant_email'] ?? '');
            $occupantEmailParts    = array_values(array_filter(array_map('trim', explode(';', $occupantEmailRaw))));
            $occupantPrimaryEmail  = $occupantEmailParts[0] ?? '';
            $occupantSecondaryEmails = array_slice($occupantEmailParts, 1);

            foreach ($occupantEmailParts as $e) {
                if (!filter_var($e, FILTER_VALIDATE_EMAIL)) {
                    $rowErrors[] = "Occupant email '{$e}' is invalid.";
                }
            }

            if (!empty($rowErrors)) {
                $errors[] = [
                    'row'     => $rowNum,
                    'errors'  => $rowErrors,
                    'data'    => $row,
                ];
                continue;
            }

            // --- Find an existing unit to update (round-trip bulk edit) ---
            // Match precedence: internal UNIT ID → Customer Code → Unit Number.
            $existing = null;
            if ($unitId !== '') {
                $existing = Unit::where('community_id', $community->id)->where('id', $unitId)->first();
            }
            if (! $existing && $customerCode !== '') {
                $existing = Unit::where('community_id', $community->id)->where('customer_code', $customerCode)->first();
            }
            if (! $existing && $unitNumber !== '') {
                $existing = Unit::where('community_id', $community->id)
                    ->whereRaw('LOWER(unit_number) = ?', [strtolower($unitNumber)])
                    ->first();
            }

            $unitAttrs = array_filter([
                'unit_number'    => $unitNumber ?: null,
                'block_number'   => trim($row['block_number'] ?? '') ?: null,
                'section'        => trim($row['section'] ?? '') ?: null,
                'door_number'    => trim($row['door_number'] ?? '') ?: null,
                'address'        => trim($row['address'] ?? '') ?: null,
                'occupancy_type' => $occupancyType,
                'levy_override'  => ($row['levy_override'] ?? '') !== '' ? (float) $row['levy_override'] : null,
                'rent_amount'    => ($row['rent_amount'] ?? '') !== '' ? (float) $row['rent_amount'] : null,
                'pq'             => ($row['pq'] ?? '') !== '' ? (float) $row['pq'] : null,
            ], fn ($v) => $v !== null);

            $ownerAttrs = [
                'full_name'         => $ownerName,
                'email'             => $ownerPrimaryEmail,
                'secondary_emails'  => !empty($ownerSecondaryEmails) ? $ownerSecondaryEmails : null,
                'phone'             => trim($row['owner_phone'] ?? '') ?: null,
                'landline'          => trim($row['owner_landline'] ?? '') ?: null,
                'id_number'         => trim($row['owner_id_number'] ?? '') ?: null,
                'contact2_name'     => trim($row['owner_contact2_name'] ?? '') ?: null,
                'contact2_email'    => trim($row['owner_contact2_email'] ?? '') ?: null,
                'contact2_phone'    => trim($row['owner_contact2_phone'] ?? '') ?: null,
                'contact2_landline' => trim($row['owner_contact2_landline'] ?? '') ?: null,
                'address'           => trim($row['owner_address'] ?? '') ?: null,
            ];

            if ($existing) {
                // --- Update the existing unit + owner ---
                $existing->update($unitAttrs);
                if ($existing->owner) {
                    $existing->owner->update(array_filter($ownerAttrs, fn ($v) => $v !== null));
                } else {
                    Owner::create(array_merge($ownerAttrs, ['unit_id' => $existing->id, 'organization_id' => $organizationId]));
                }
                $imported++;
                continue;
            }

            // --- Create a brand-new unit + owner ---
            $unit = Unit::create(array_merge($unitAttrs, [
                'community_id'   => $community->id,
                'organization_id' => $organizationId,
                'unit_number'    => $unitNumber,
                'occupancy_type' => $occupancyType,
                'status'         => 'active',
            ]));

            $existingUnitNumbers[strtolower($unitNumber)] = true;

            $owner = Owner::create(array_merge($ownerAttrs, [
                'unit_id'         => $unit->id,
                'organization_id' => $organizationId,
            ]));

            // Stamp a stable customer code derived from the owner surname (or honour an imported one).
            $unit->customer_code = $customerCode !== '' ? $customerCode : $this->generateCustomerCode($unit, $owner);
            $unit->save();

            if ($occupancyType === OccupancyType::OCCUPANT_OCCUPIED->value) {
                $occupantName = trim($row['occupant_full_name'] ?? '');
                if ($occupantName || $occupantPrimaryEmail) {
                    Occupant::create([
                        'unit_id'          => $unit->id,
                        'organization_id'  => $organizationId,
                        'full_name'        => $occupantName ?: 'Unknown Organization',
                        'email'            => $occupantPrimaryEmail ?: null,
                        'secondary_emails' => !empty($occupantSecondaryEmails) ? $occupantSecondaryEmails : null,
                        'phone'            => trim($row['occupant_phone'] ?? '') ?: null,
                        'lease_start'      => $this->parseDate($row['occupant_lease_start'] ?? ''),
                        'lease_end'        => $this->parseDate($row['occupant_lease_end'] ?? ''),
                        'is_active'        => true,
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
     * Generate and stream an occupants-import template file (CSV or XLSX).
     *
     * The template matches occupants to existing units by unit_number (or customer_code).
     *
     * @param Community $community
     * @param string $format  'csv' | 'xlsx'
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\Response
     */
    public function downloadOccupantsTemplate(Community $community, string $format)
    {
        $headers = [
            'unit_number',
            'customer_code',
            'occupant_full_name',
            'occupant_email',
            'occupant_phone',
            'occupant_id_number',
            'occupant_lease_start',
            'occupant_lease_end',
        ];

        $example = [
            'A01',
            '',
            'Jane Doe',
            'jane@example.com',
            '+27821234567',
            '9001015009087',
            '2026-01-01',
            '2026-12-31',
        ];

        $filename = 'occupants-import-template';

        if ($format === 'xlsx') {
            $spreadsheet = new Spreadsheet();
            $sheet       = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Occupants Import');

            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col . '1', $header);
                $sheet->getColumnDimension($col)->setAutoSize(true);
                $sheet->getStyle($col . '1')->getFont()->setBold(true);
                $col++;
            }

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

        $csvContent  = implode(',', $headers) . "\n";
        $csvContent .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $example)) . "\n";

        return response($csvContent, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.csv"',
        ]);
    }

    /**
     * Parse an uploaded occupants CSV/XLSX file. Delegates to the shared parser.
     *
     * @param Community $community
     * @param mixed  $file
     * @return array { columns: string[], rows: array[], total_rows: int }
     * @throws Exception
     */
    public function parseOccupantsImportFile(Community $community, mixed $file): array
    {
        return $this->parseImportFile($community, $file);
    }

    /**
     * Import pre-mapped occupant rows, matching each to an existing unit by
     * unit_number (or customer_code) and upserting the unit's active occupant.
     *
     * @param Community $community
     * @param array  $rows
     * @return array
     */
    /**
     * Map a raw occupant-import row's keys onto system field names, accepting both
     * the occupants-template headers and the occupants-export headers.
     *
     * @param array $row
     * @return array
     */
    private function normalizeOccupantImportRow(array $row): array
    {
        static $aliases = [
            'unit no'                => 'unit_number',
            'unit number'            => 'unit_number',
            'customer code'          => 'customer_code',
            'occupant name'          => 'occupant_full_name',
            'occupant email'         => 'occupant_email',
            'occupant cell number'   => 'occupant_phone',
            'occupant id / passport' => 'occupant_id_number',
            'lease start'            => 'occupant_lease_start',
            'lease end'              => 'occupant_lease_end',
        ];

        $out = [];
        foreach ($row as $key => $value) {
            $normalized = $aliases[strtolower(trim((string) $key))] ?? $key;
            if (! array_key_exists($normalized, $out) || $out[$normalized] === '' || $out[$normalized] === null) {
                $out[$normalized] = $value;
            }
        }

        return $out;
    }

    public function importOccupants(Community $community, array $rows): array
    {
        $user           = Auth::user();
        $organizationId = $user->organization_id;
        $imported       = 0;
        $skipped        = 0;
        $errors         = [];

        foreach ($rows as $index => $row) {
            $rowNum = $index + 1;

            // Accept both the occupants-template headers and the occupants-export
            // headers ("Unit No", "Occupant Name", "Occupant Cell Number", …).
            $row = $this->normalizeOccupantImportRow($row);

            $unitNumber   = trim($row['unit_number'] ?? '');
            $customerCode = trim($row['customer_code'] ?? '');
            $occupantName = trim($row['occupant_full_name'] ?? '');

            $occupantEmailRaw     = trim($row['occupant_email'] ?? '');
            $occupantEmailParts   = array_values(array_filter(array_map('trim', explode(';', $occupantEmailRaw))));
            $occupantPrimaryEmail = $occupantEmailParts[0] ?? '';
            $occupantSecondaryEmails = array_slice($occupantEmailParts, 1);

            $rowErrors = [];

            if (! $unitNumber && ! $customerCode) {
                $rowErrors[] = 'A unit number or customer code is required.';
            }

            if (! $occupantName && ! $occupantPrimaryEmail) {
                $rowErrors[] = 'An occupant name or email is required.';
            }

            foreach ($occupantEmailParts as $e) {
                if (! filter_var($e, FILTER_VALIDATE_EMAIL)) {
                    $rowErrors[] = "Occupant email '{$e}' is not a valid email address.";
                }
            }

            if (! empty($rowErrors)) {
                $errors[] = ['row' => $rowNum, 'errors' => $rowErrors, 'data' => $row];
                continue;
            }

            // Match the unit within this community by unit number, then customer code.
            $unit = Unit::where('community_id', $community->id)
                ->when($unitNumber !== '', fn($q) => $q->where('unit_number', $unitNumber))
                ->when($unitNumber === '' && $customerCode !== '', fn($q) => $q->where('customer_code', $customerCode))
                ->first();

            if (! $unit) {
                $skipped++;
                $errors[] = [
                    'row'    => $rowNum,
                    'errors' => ['No matching unit found for "' . ($unitNumber ?: $customerCode) . '".'],
                    'data'   => $row,
                ];
                continue;
            }

            $occupantData = [
                'full_name'        => $occupantName ?: 'Unknown Occupant',
                'email'            => $occupantPrimaryEmail ?: null,
                'secondary_emails' => ! empty($occupantSecondaryEmails) ? $occupantSecondaryEmails : null,
                'phone'            => trim($row['occupant_phone'] ?? '') ?: null,
                'id_number'        => trim($row['occupant_id_number'] ?? '') ?: null,
                'lease_start'      => $this->parseDate($row['occupant_lease_start'] ?? ''),
                'lease_end'        => $this->parseDate($row['occupant_lease_end'] ?? ''),
            ];

            // Upsert: update the active occupant if one exists, else create one.
            $unit->loadMissing('currentOccupant');

            if ($unit->currentOccupant) {
                $unit->currentOccupant->update($occupantData);
            } else {
                Occupant::create(array_merge($occupantData, [
                    'unit_id'         => $unit->id,
                    'organization_id' => $organizationId,
                    'is_active'       => true,
                ]));

                if ($unit->occupancy_type === OccupancyType::VACANT) {
                    $unit->update(['occupancy_type' => OccupancyType::OCCUPANT_OCCUPIED->value]);
                }
            }

            $imported++;
        }

        $total = count($rows);
        $label = $imported === 1 ? 'occupant' : 'occupants';

        return [
            'imported'    => $imported,
            'skipped'     => $skipped,
            'error_count' => count($errors),
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

    /**
     * Natural-sort ORDER BY expression for unit_number, compatible with MySQL and SQLite.
     * MySQL uses REGEXP_REPLACE; SQLite falls back to plain alphabetical order.
     */
    private function unitNumberOrderRaw(string $column = 'units.unit_number', string $direction = 'asc'): string
    {
        if (DB::getDriverName() === 'sqlite') {
            return "{$column} {$direction}";
        }

        $dir = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        return "CASE WHEN REGEXP_REPLACE({$column}, '[^0-9]', '') = '' THEN 1 ELSE 0 END,"
            . " CAST(NULLIF(REGEXP_REPLACE({$column}, '[^0-9]', ''), '') AS UNSIGNED) {$dir},"
            . " {$column} {$dir}";
    }
}
