<?php

namespace App\Services;

use Exception;
use App\Enums\CommunityStatus;
use App\Models\Community;
use App\Models\CashbookEntry;
use App\Models\Invoice;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\CommunityResource;
use App\Http\Resources\CommunityResources;

class CommunityService extends BaseService
{
    /**
     * Return a paginated, filtered list of communities for the authenticated occupant.
     *
     * @param array $data
     * @return CommunityResources
     */
    public function showCommunities(array $data): CommunityResources
    {
        $user  = Auth::user();
        $query = Community::where('organization_id', $user->organization_id)
            ->with('communityManager:id,name,email')
            ->withCount([
                'units',
                'units as occupied_units_count' => fn ($q) => $q->whereIn('occupancy_type', ['owner_occupied', 'occupant_occupied']),
                'units as vacant_units_count'   => fn ($q) => $q->where('occupancy_type', 'vacant'),
            ])
            ->withSum('units', 'rent_amount');

        if (!empty($data['country'])) {
            $query->where('country', $data['country']);
        }

        if (!empty($data['entity_type'])) {
            $query->where('entity_type', $data['entity_type']);
        }

        if (!empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        if (isset($data['is_active'])) {
            $isActive = $data['is_active'] === 'true' || $data['is_active'] === true || $data['is_active'] === 1;
            $query->where('is_active', $isActive);
        }

        if (!request()->has('_sort')) {
            $query = $query->latest();
        }

        return $this->setQuery($query)->getOutput();
    }

    /**
     * Return summary statistics across all communities for the authenticated occupant.
     *
     * @param array $data
     * @return array
     */
    public function showCommunitiesSummary(array $data): array
    {
        $user     = Auth::user();
        $organizationId = $user->organization_id;
        $country  = $data['country'] ?? null;

        $communityQuery = Community::where('organization_id', $organizationId);
        if ($country) {
            $communityQuery->where('country', $country);
        }
        $totalCommunities = $communityQuery->count();

        // Per-status counts drive the Active / Take-on / Suspended tabs.
        $statusCounts = (clone $communityQuery)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $statusBreakdown = [
            'active'    => (int) ($statusCounts[CommunityStatus::ACTIVE->value] ?? 0),
            'take_on'   => (int) ($statusCounts[CommunityStatus::TAKE_ON->value] ?? 0),
            'suspended' => (int) ($statusCounts[CommunityStatus::SUSPENDED->value] ?? 0),
        ];

        $unitQuery = Unit::where('organization_id', $organizationId);
        if ($country) {
            $unitQuery->whereHas('community', fn($q) => $q->where('country', $country));
        }
        $totalUnits = (clone $unitQuery)->count();

        $occupied = (clone $unitQuery)
            ->whereIn('occupancy_type', ['owner_occupied', 'occupant_occupied'])
            ->count();

        // Monthly revenue: sum of levy_override (where set) + default levy amounts for levy communities,
        // and rent_amount for rental communities. Simplified as sum of rent_amount across all units.
        $monthlyRevenue = (clone $unitQuery)
            ->whereNotNull('rent_amount')
            ->sum('rent_amount');

        $vacant = (clone $unitQuery)
            ->where('occupancy_type', 'vacant')
            ->count();

        return [
            'total_communities'   => $totalCommunities,
            'total_units'     => $totalUnits,
            'occupied'        => $occupied,
            'vacant'          => $vacant,
            'monthly_revenue' => (float) $monthlyRevenue,
            'status_counts'   => $statusBreakdown,
        ];
    }

    /**
     * Create a new community and auto-configure its ledgers.
     *
     * @param array $data
     * @return array
     * @throws Exception
     */
    public function createCommunity(array $data): array
    {
        $user = Auth::user();

        $communityData = collect($data)
            ->only(['name', 'code', 'address', 'entity_type', 'financial_year_end_month', 'merchant_number', 'pdf_passwords', 'previous_managing_agent', 'opening_balance_date', 'admin_fund_amount', 'reserve_fund_amount', 'csos_levy_amount', 'default_rent_amount', 'billing_day', 'payment_terms_days', 'country', 'currency'])
            ->toArray();

        // Community Manager must be a same-organization user (or none).
        $communityData['community_manager_id'] = $this->scopedManagerId($data['community_manager_id'] ?? null, $user->organization_id);

        // New communities start life in "Take-on" until they are fully onboarded.
        $community = Community::create(array_merge($communityData, [
            'organization_id' => $user->organization_id,
            'is_active' => true,
            'status'    => CommunityStatus::TAKE_ON->value,
        ]));

        // Assign staff users to the community. The creator is always included so
        // they retain access; the rest are scoped to same-organization users.
        $requestedIds = collect($data['user_ids'] ?? [])->push($user->id)->unique();
        $assignedIds  = User::where('organization_id', $user->organization_id)
            ->whereIn('id', $requestedIds->all())
            ->pluck('id')
            ->all();
        $community->assignedUsers()->sync($assignedIds);

        // Auto-configure default ledgers based on community type
        (new CommunityLedgerService())->setupDefaultLedgers($community);

        return $this->showCreatedResource($community);
    }

    /**
     * Bulk delete communities by an array of IDs.
     *
     * @param array $communityIds
     * @return array
     * @throws Exception
     */
    public function deleteCommunities(array $communityIds): array
    {
        $user   = Auth::user();
        $communities = Community::whereIn('id', $communityIds)
            ->where('organization_id', $user->organization_id)
            ->get();

        $total = $communities->count();

        if ($total === 0) {
            throw new Exception('No Communities deleted');
        }

        foreach ($communities as $community) {
            $community->delete();
        }

        $label = $total === 1 ? 'Community' : 'Communities';

        return ['message' => "{$total} {$label} deleted"];
    }

    /**
     * Return a single community resource with computed stats for the detail page.
     *
     * @param Community $community
     * @return JsonResponse
     */
    public function showCommunity(Community $community): JsonResponse
    {
        $community->loadCount([
            'units as owner_occupied_count'  => fn($q) => $q->where('occupancy_type', 'owner_occupied'),
            'units as occupant_occupied_count' => fn($q) => $q->where('occupancy_type', 'occupant_occupied'),
            'units as vacant_count'          => fn($q) => $q->where('occupancy_type', 'vacant'),
            'units as total_units_count',
        ]);

        // Assigned staff users (WeConnectU "Select Users") + the community manager
        // — used to prefill the Edit Community modal's pickers. Bank accounts and
        // members back the community-info modal's Bank Details / Trustees tabs.
        $community->load([
            'assignedUsers:id',
            'communityManager:id,name,email',
            'bankAccounts',
            'members',
            'billingSetup',
        ]);

        $invoiceStatusCounts = Invoice::whereHas('unit', fn($q) => $q->where('community_id', $community->id))
            ->selectRaw("
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_count,
                SUM(CASE WHEN status = 'partially_paid' THEN 1 ELSE 0 END) as partial_count
            ")
            ->first();

        // --- Net community balance (mirrors UnitResource balance formula) ---
        //
        // outstanding  = gross invoice amounts - partial payments already allocated to those invoices.
        //                GREATEST(0,...) prevents a mis-statused invoice from going negative.
        // credits      = unallocated cashbook credit entries for units in this community.
        // total_balance = credits - outstanding  (negative = community has net arrears)

        $communityUnitIds = Unit::where('community_id', $community->id)->select('id');

        // Subquery: IDs of all outstanding (not yet fully paid) invoices for this community.
        $outstandingInvoiceIds = Invoice::whereIn('unit_id', $communityUnitIds)
            ->whereIn('status', ['unpaid', 'overdue', 'partially_paid'])
            ->select('id');

        // Gross sum of those invoices.
        $totalGrossOutstanding = (float) Invoice::whereIn('unit_id', $communityUnitIds)
            ->whereIn('status', ['unpaid', 'overdue', 'partially_paid'])
            ->sum('amount');

        // Cashbook amounts already allocated to those invoices (partial payments).
        $totalPartiallyPaid = (float) CashbookEntry::whereIn('invoice_id', $outstandingInvoiceIds)
            ->sum('amount');

        $totalNetOutstanding = max(0.0, $totalGrossOutstanding - $totalPartiallyPaid);

        // Unallocated credit entries sitting on units in this community.
        $totalUnallocatedCredits = (float) CashbookEntry::whereIn('unit_id', $communityUnitIds)
            ->whereNull('invoice_id')
            ->where('type', 'credit')
            ->sum('amount');

        // Monthly revenue: mirrors billing logic exactly.
        // admin_fund_amount is the total community levy budget, distributed per unit via PQ or equal-share.
        // levy_override replaces that unit's share; rent is added for occupant_occupied units.
        $units          = Unit::where('community_id', $community->id)->where('status', 'active')
            ->select(['occupancy_type', 'levy_override', 'rent_amount', 'pq'])
            ->get();
        $totalPq        = $units->whereNotNull('pq')->sum('pq');
        $totalUnitCount = $units->count();
        $adminBudget    = (float) ($community->admin_fund_amount ?? 0);
        $monthlyRevenue = $units->reduce(function (float $carry, Unit $unit) use ($community, $totalPq, $totalUnitCount, $adminBudget): float {
            $occ = $unit->occupancy_type instanceof \App\Enums\OccupancyType
                ? $unit->occupancy_type->value
                : (string) $unit->occupancy_type;
            if ($occ !== 'vacant') {
                if ($unit->levy_override !== null) {
                    $carry += (float) $unit->levy_override;
                } elseif ($adminBudget > 0) {
                    $carry += ($totalPq > 0 && $unit->pq !== null)
                        ? round(($unit->pq / $totalPq) * $adminBudget, 2)
                        : ($totalUnitCount > 0 ? round($adminBudget / $totalUnitCount, 2) : 0);
                }
            }
            if ($occ === 'occupant_occupied') {
                $carry += (float) ($unit->rent_amount ?? 0);
            }
            return $carry;
        }, 0.0);

        return (new CommunityResource($community))
            ->additional([
                'stats' => [
                    'total_units'           => (int) $community->total_units_count,
                    'owner_occupied_count'  => (int) $community->owner_occupied_count,
                    'occupant_occupied_count' => (int) $community->occupant_occupied_count,
                    'vacant_count'          => (int) $community->vacant_count,
                    'monthly_revenue'       => (float) $monthlyRevenue,
                    'total_balance'         => $totalUnallocatedCredits - $totalNetOutstanding,
                    'invoice_status'        => [
                        'paid'    => (int) ($invoiceStatusCounts?->paid_count ?? 0),
                        'overdue' => (int) ($invoiceStatusCounts?->overdue_count ?? 0),
                        'partial' => (int) ($invoiceStatusCounts?->partial_count ?? 0),
                    ],
                ],
            ])
            ->response();
    }

    /**
     * Update an community's attributes.
     *
     * @param Community $community
     * @param array  $data
     * @return array
     */
    public function updateCommunity(Community $community, array $data): array
    {
        $fillable = collect($data)
            ->only([
                'name', 'code', 'address', 'entity_type',
                'admin_fund_amount', 'reserve_fund_amount', 'csos_levy_amount', 'default_rent_amount',
                'billing_day', 'payment_terms_days', 'payment_reminder_days', 'billing_paused', 'is_active',
                'country', 'currency',
                'registration_number', 'csos_registration_number', 'income_tax_number',
                'merchant_number', 'pdf_passwords',
                'previous_managing_agent', 'opening_balance_date',
                'financial_year_end_month', 'is_vat_registered', 'vat_number',
                'interest_rate', 'interest_exempt_threshold', 'ageing_type',
                // Settings → Charges
                'penalty_admin_fee', 'warning_admin_fee', 'transfer_clearance_fee', 'phonecall_fee',
                'handed_over_fee', 'notice_threshold_amount', 'apply_debt_collection_fee',
                'notice_charges', 'notices_exemption',
            ])
            ->filter(fn($v) => !is_null($v))
            ->toArray();

        $community->update($fillable);

        // Community Manager is handled separately so it can be cleared (null) —
        // the null-filter above would otherwise drop it. Scoped to the org.
        if (array_key_exists('community_manager_id', $data)) {
            $community->update([
                'community_manager_id' => $this->scopedManagerId($data['community_manager_id'], $community->organization_id),
            ]);
        }

        // Re-sync assigned users (WeConnectU "Select Users") when provided. Scoped
        // to same-organization users so a stray id can't grant cross-org access.
        if (array_key_exists('user_ids', $data)) {
            $assignedIds = User::where('organization_id', $community->organization_id)
                ->whereIn('id', collect($data['user_ids'] ?? [])->unique()->all())
                ->pluck('id')
                ->all();
            $community->assignedUsers()->sync($assignedIds);
        }

        return $this->showUpdatedResource($community);
    }

    /**
     * Submit a community's take-on, transitioning it from "Take-on" to "Active".
     * Mirrors WeConnectU's "Submit Take-on" action.
     *
     * @param Community $community
     * @return array
     */
    public function submitTakeOn(Community $community): array
    {
        $alreadyActive = $community->status === CommunityStatus::ACTIVE;

        if (!$alreadyActive) {
            $community->update(['status' => CommunityStatus::ACTIVE->value]);
        }

        return [
            'status'  => CommunityStatus::ACTIVE->value,
            'message' => $alreadyActive ? 'Community is already active.' : 'Take-on submitted. Community is now active.',
        ];
    }

    /**
     * Resolve a requested community-manager id to a valid same-organization user
     * id, or null. Prevents assigning a manager from another organization.
     *
     * @param mixed $managerId
     * @param string $organizationId
     * @return int|null
     */
    private function scopedManagerId($managerId, string $organizationId): ?int
    {
        if (empty($managerId)) {
            return null;
        }

        return User::where('organization_id', $organizationId)
            ->whereKey($managerId)
            ->value('id');
    }

    /**
     * Delete a single community.
     *
     * @param Community $community
     * @return array
     */
    public function deleteCommunity(Community $community): array
    {
        $deleted = $community->delete();

        return [
            'deleted' => $deleted,
            'message' => $deleted ? 'Community deleted' : 'Community delete unsuccessful',
        ];
    }
}
