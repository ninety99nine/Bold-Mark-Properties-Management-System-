<?php

namespace Database\Seeders;

use App\Enums\ComplianceItemStatus;
use App\Models\CashbookEntry;
use App\Models\Community;
use App\Models\ComplianceChecklist;
use App\Models\ComplianceChecklistItem;
use App\Models\ComplianceTemplate;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Unit;
use App\Models\User;
use App\Services\UnitBalanceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Supplemental demo seed — fills the dashboard gaps the base DemoSeeder leaves:
 *
 *   A. Compliance checklists + items per community (base seeder only makes
 *      templates, so the Compliance doughnut and tracker were empty).
 *   B. Accumulating monthly arrears for existing debtor units so the Debt
 *      trend rises month-over-month and the age-analysis buckets fill.
 *   C. Current-month payment entries so "Collected This Month" is non-zero.
 *
 * Idempotent: each part checks for its own marker before writing, so it can be
 * run repeatedly without duplicating data.
 *
 * Usage: php artisan db:seed --class=DemoDashboardBackfillSeeder
 */
class DemoDashboardBackfillSeeder extends Seeder
{
    private string $organizationId;

    private int $augustCounter = 0;

    public function run(): void
    {
        $organization = Organization::where('name', 'Bold Mark Properties')->firstOrFail();
        $this->organizationId = $organization->id;
        $admin = User::where('organization_id', $organization->id)->first();

        $this->seedCompliance($admin);
        $this->seedAccumulatingArrears();
        $this->seedCurrentMonthCollections();

        $this->command->info('Recalculating unit balances...');
        app(UnitBalanceService::class);
        \Artisan::call('units:recalculate-balances');

        // Re-derive collection statuses now that arrears have accumulated, so the
        // Age Analysis markers reflect each debtor's final arrears depth.
        $this->command->info('Re-aligning collection statuses with accumulated arrears...');
        $this->call(DemoCollectionStatusSeeder::class);

        $this->command->info('Dashboard backfill complete.');
    }

    /* ------------------------------------------------------------------ */
    /*  A. COMPLIANCE CHECKLISTS + ITEMS                                    */
    /* ------------------------------------------------------------------ */

    private function seedCompliance(?User $admin): void
    {
        if (ComplianceChecklist::where('organization_id', $this->organizationId)->exists()) {
            $this->command->info('Compliance checklists already present — skipping.');
            return;
        }

        $this->command->info('Seeding compliance checklists...');

        $templates = ComplianceTemplate::where('organization_id', $this->organizationId)
            ->with('items')->get();
        $byCountry = $templates->keyBy('country'); // 'BW', 'ZA', and '' (generic)
        $generic   = $templates->firstWhere('country', null) ?? $templates->first();

        foreach (Community::where('organization_id', $this->organizationId)->get() as $community) {
            $template = $byCountry->get($community->country) ?? $generic;
            if (! $template) continue;

            $checklist = ComplianceChecklist::create([
                'financial_year_label' => '2026',
                'financial_year_start' => '2026-01-01',
                'financial_year_end'   => '2026-12-31',
                'notes'                => 'FY2026 statutory compliance checklist.',
                'organization_id'      => $this->organizationId,
                'community_id'         => $community->id,
                'created_by_id'        => $admin?->id,
            ]);

            $i = 0;
            foreach ($template->items as $item) {
                $i++;
                $month = $item->default_month_due ?: (($i % 12) + 1);
                $dueDate = Carbon::create(2026, $month, 15);

                // Realistic status mix — most past-due items done, a few slipped,
                // upcoming items still pending. ~70% compliant overall.
                $status     = $this->pickStatus($month, $i);
                $completed  = $status === ComplianceItemStatus::COMPLETED->value;

                ComplianceChecklistItem::create([
                    'name'                    => $item->name,
                    'category'                => $item->category,
                    'description'             => $item->description,
                    'status'                  => $status,
                    'priority'                => $item->priority,
                    'due_date'                => $dueDate,
                    'sort_order'              => $item->sort_order,
                    'is_recurring'            => $item->is_recurring,
                    'completion_notes'        => $completed ? 'Completed and filed.' : null,
                    'completed_at'            => $completed ? $dueDate->copy()->subDays(rand(1, 10)) : null,
                    'compliance_checklist_id' => $checklist->id,
                    'organization_id'         => $this->organizationId,
                    'assigned_to_id'          => $admin?->id,
                    'completed_by_id'         => $completed ? $admin?->id : null,
                ]);
            }
        }
    }

    /**
     * Choose a status for a checklist item so the portfolio lands around ~70%
     * compliant with a believable spread of overdue / pending work.
     */
    private function pickStatus(int $month, int $index): string
    {
        $currentMonth = 8; // demo "now" — August 2026

        if ($month >= $currentMonth) {
            // Upcoming items: mostly pending, a couple already in progress.
            return $index % 5 === 0
                ? ComplianceItemStatus::IN_PROGRESS->value
                : ComplianceItemStatus::PENDING->value;
        }

        // Past-due items: overwhelmingly completed, a few overdue / in progress.
        $roll = $index % 10;
        if ($roll === 0) return ComplianceItemStatus::OVERDUE->value;
        if ($roll === 1) return ComplianceItemStatus::IN_PROGRESS->value;
        return ComplianceItemStatus::COMPLETED->value;
    }

    /* ------------------------------------------------------------------ */
    /*  B. ACCUMULATING ARREARS (rising debt trend)                        */
    /* ------------------------------------------------------------------ */

    private function seedAccumulatingArrears(): void
    {
        $marker = Invoice::where('organization_id', $this->organizationId)
            ->where('invoice_number', 'like', 'INV-ARR-%')->exists();
        if ($marker) {
            $this->command->info('Accumulating arrears already present — skipping.');
            return;
        }

        $this->command->info('Seeding accumulating monthly arrears...');

        $counter = 0;
        $endMonth = Carbon::create(2026, 8, 1); // most-recent arrears month

        // Anchor on each existing debtor's overdue invoices. A unit in arrears
        // extends its unpaid run forward into the recent months (so the age
        // buckets fill), but by a VARIED number of months so the portfolio shows
        // the full notice ladder — a few just one notice behind, some handed over
        // — rather than every debtor collapsing to the deepest status.
        $anchors = Invoice::where('organization_id', $this->organizationId)
            ->where('status', 'overdue')
            ->with('unit')
            ->get()
            ->groupBy('unit_id')
            // Shallowest existing arrears first, so the extra months we add keep
            // the debtors monotonically spread across the notice stages.
            ->sortBy(fn ($invoices) => $invoices->count())
            ->values();

        foreach ($anchors as $index => $invoices) {
            $anchor = $invoices->sortBy('billing_period')->first();
            if (! $anchor || ! $anchor->unit) continue;

            // Give each debtor a target total-overdue that fans evenly across the
            // six notice stages (1 → 1st-notice … 6 → handed-over), then top up
            // from their existing depth. Debtors are sorted shallowest-first so
            // the target rises with (and never falls below) what they already owe.
            $total       = max(1, $anchors->count());
            $target      = 1 + intdiv($index * 6, $total);           // 1..6
            $extraMonths = max(0, $target - $invoices->count());

            // Walk the most-recent months backward from Aug 2026, skipping any
            // period the unit already has, until we've added $extraMonths.
            $added  = 0;
            $cursor = $endMonth->copy();
            $existingMonths = $invoices
                ->map(fn ($inv) => Carbon::parse($inv->billing_period)->format('Y-m'))
                ->flip();

            while ($added < $extraMonths && $cursor->gte(Carbon::create(2026, 1, 1))) {
                $key = $cursor->format('Y-m');
                if (! $existingMonths->has($key)) {
                    $counter++;
                    Invoice::create([
                        'invoice_number'    => 'INV-ARR-' . str_pad((string) $counter, 5, '0', STR_PAD_LEFT),
                        'status'            => 'overdue',
                        'billed_to_type'    => $anchor->billed_to_type,
                        'billed_to_id'      => $anchor->billed_to_id,
                        'amount'            => $anchor->amount,
                        'billing_period'    => $cursor->copy()->startOfMonth()->toDateString(),
                        'due_date'          => $cursor->copy()->startOfMonth()->addMonth()->toDateString(),
                        'sent_at'           => $cursor->copy()->startOfMonth()->addDays(2),
                        'issued_by_type'    => 'system',
                        'issued_by_user_id' => null,
                        'unit_id'           => $anchor->unit_id,
                        'ledger_id'         => $anchor->ledger_id,
                        'organization_id'   => $this->organizationId,
                    ]);
                    $added++;
                }
                $cursor->subMonth();
            }
        }

        $this->command->info("  created {$counter} arrears invoices.");
    }

    /* ------------------------------------------------------------------ */
    /*  C. CURRENT-MONTH COLLECTIONS                                        */
    /* ------------------------------------------------------------------ */

    private function seedCurrentMonthCollections(): void
    {
        $monthStart = Carbon::create(2026, 8, 1);
        $exists = CashbookEntry::where('organization_id', $this->organizationId)
            ->where('type', 'credit')
            ->where('date', '>=', $monthStart->toDateString())
            ->exists();
        if ($exists) {
            $this->command->info('Current-month collections already present — skipping.');
            return;
        }

        $this->command->info('Seeding current-month collections...');

        // Record this month's levy receipts against a spread of units that are
        // NOT in arrears (paying members), so Customer Management stays coherent.
        $overdueUnitIds = Invoice::where('organization_id', $this->organizationId)
            ->where('status', 'overdue')->pluck('unit_id')->unique();

        $units = Unit::where('organization_id', $this->organizationId)
            ->whereNotIn('id', $overdueUnitIds)
            ->with('community')
            ->inRandomOrder()
            ->limit(60)
            ->get();

        $count = 0;
        foreach ($units as $unit) {
            $amount = (float) ($unit->levy_override
                ?? $unit->community->admin_fund_amount
                ?? 2500);
            if ($amount <= 0) $amount = 2500;

            // Copy billing recipient + ledger from an existing invoice on this unit
            // so the August levy is billed exactly like the historical months.
            $template = Invoice::where('unit_id', $unit->id)
                ->where('organization_id', $this->organizationId)
                ->latest('billing_period')
                ->first();
            if (! $template) continue;

            $day = rand(1, 18); // demo "now" is Aug 18
            $paidOn = $monthStart->copy()->addDays($day - 1);

            // Back the receipt with a matching PAID August invoice and allocate the
            // payment to it, so the unit balance nets to zero (no phantom credit)
            // while "Collected This Month" still reflects the receipt.
            $this->augustCounter++;
            $invoice = Invoice::create([
                'invoice_number'    => 'INV-AUG-' . str_pad((string) $this->augustCounter, 5, '0', STR_PAD_LEFT),
                'status'            => 'paid',
                'billed_to_type'    => $template->billed_to_type,
                'billed_to_id'      => $template->billed_to_id,
                'amount'            => $amount,
                'billing_period'    => $monthStart->toDateString(),
                'due_date'          => $monthStart->copy()->addMonth()->toDateString(),
                'sent_at'           => $monthStart->copy()->addDays(2),
                'issued_by_type'    => 'system',
                'issued_by_user_id' => null,
                'unit_id'           => $unit->id,
                'ledger_id'         => $template->ledger_id,
                'organization_id'   => $this->organizationId,
            ]);

            CashbookEntry::create([
                'description'      => 'Levy payment received — ' . $unit->unit_number,
                'amount'           => $amount,
                'type'             => 'credit',
                'date'             => $paidOn->toDateString(),
                'notes'            => 'August 2026 levy',
                'community_id'     => $unit->community_id,
                'organization_id'  => $this->organizationId,
                'ledger_id'        => $template->ledger_id,
                'unit_id'          => $unit->id,
                'invoice_id'       => $invoice->id,
            ]);
            $count++;
        }

        $this->command->info("  created {$count} current-month payment entries.");
    }
}
