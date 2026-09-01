<?php

namespace App\Console\Commands;

use App\Models\Community;
use App\Models\ComplianceChecklist;
use App\Models\ComplianceChecklistItem;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Generate WeConnectU-style multi-year compliance checklists so the dashboard
 * Planner shows a row of financial-year tabs (2024/2025 … 2027/2028) instead of
 * a single calendar-year pill. Idempotent — safe to re-run.
 */
class SeedComplianceFinancialYears extends Command
{
    protected $signature = 'demo:compliance-financial-years';

    protected $description = 'Generate FY 2024/2025 → 2027/2028 compliance checklists (WeConnectU-style tabs) for every community.';

    public function handle(): int
    {
        // [label, start, end, dueDateYearShift, forcedStatus|null]
        $windows = [
            ['2024/2025', '2024-07-01', '2025-06-30', -2, 'completed'],
            ['2025/2026', '2025-07-01', '2026-06-30', -1, 'completed'],
            ['2026/2027', '2026-07-01', '2027-06-30',  0, null],       // current FY — keep original statuses
            ['2027/2028', '2027-07-01', '2028-06-30', +1, 'pending'],
        ];

        $communities = Community::all();
        $this->info("Processing {$communities->count()} communities…");
        $created = 0;

        foreach ($communities as $community) {
            $template = ComplianceChecklist::where('community_id', $community->id)
                ->orderBy('financial_year_start')
                ->with('items')
                ->first();

            if (! $template) {
                continue;
            }

            $templateItems = $template->items;

            foreach ($windows as [$label, $start, $end, $yearShift, $forceStatus]) {
                $checklist = ComplianceChecklist::firstOrCreate(
                    ['community_id' => $community->id, 'financial_year_label' => $label],
                    [
                        'organization_id'      => $community->organization_id,
                        'financial_year_start' => $start,
                        'financial_year_end'   => $end,
                        'notes'                => $template->notes,
                        'created_by_id'        => $template->created_by_id,
                    ]
                );

                if ($checklist->items()->exists()) {
                    continue; // already populated
                }

                foreach ($templateItems as $item) {
                    $due = $item->due_date
                        ? Carbon::parse($item->due_date)->addYears($yearShift)->toDateString()
                        : null;

                    ComplianceChecklistItem::create([
                        'compliance_checklist_id' => $checklist->id,
                        'organization_id'         => $community->organization_id,
                        'name'                    => $item->name,
                        'category'                => $item->category,
                        'description'             => $item->description,
                        'status'                  => $forceStatus ?? ($item->status instanceof \BackedEnum ? $item->status->value : $item->status),
                        'priority'                => $item->priority instanceof \BackedEnum ? $item->priority->value : $item->priority,
                        'due_date'                => $due,
                        'sort_order'              => $item->sort_order,
                        'is_recurring'            => $item->is_recurring,
                    ]);
                }
                $created++;
            }

            // Retire the old plain calendar-year "2026" checklist now that ranges exist.
            ComplianceChecklist::where('community_id', $community->id)
                ->where('financial_year_label', '2026')
                ->get()
                ->each(function ($old) {
                    $old->items()->delete();
                    $old->delete();
                });
        }

        $this->info("Done. Created {$created} financial-year checklists across the portfolio.");

        return self::SUCCESS;
    }
}
