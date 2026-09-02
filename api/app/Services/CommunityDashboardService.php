<?php

namespace App\Services;

use App\Enums\BankAccountType;
use App\Enums\InvoiceStatus;
use App\Models\BankAccount;
use App\Models\CashbookEntry;
use App\Models\Community;
use App\Models\ComplianceChecklist;
use App\Models\Invoice;
use App\Models\Unit;

class CommunityDashboardService
{
    /**
     * Assemble the WeConnectU-style community dashboard payload.
     *
     * The financial-year selector scopes ONLY the Planner & Compliance list.
     * The financial summary, counts and panels always reflect live current
     * state (they are NOT year-scoped) — matching WeConnectU exactly.
     *
     * @param  Community    $community
     * @param  string|null  $financialYear  Selected FY label (e.g. "2026-2027").
     * @return array
     */
    public function dashboard(Community $community, ?string $financialYear = null): array
    {
        // ── Financial-year tabs (from the community's compliance checklists) ──
        $checklists = ComplianceChecklist::where('community_id', $community->id)
            ->orderBy('financial_year_start')
            ->get();

        $financialYears = $checklists->pluck('financial_year_label')->unique()->values();

        // Resolve the selected checklist: requested year → FY containing today → latest.
        $selected = $checklists->firstWhere('financial_year_label', $financialYear)
            ?? $checklists->first(fn ($c) => $c->financial_year_start <= now() && $c->financial_year_end >= now())
            ?? $checklists->last();

        return [
            'financial_years'      => $financialYears,
            'selected_year'        => $selected?->financial_year_label,
            'selected_checklist_id' => $selected?->id,
            'planner'              => $this->planner($selected),
            'finance'           => $this->finance($community),
            'counts'            => $this->counts(),          // modules not built yet → zeros
            'pending_transfers' => [],                       // Transfers module (pending)
            'my_tasks'          => [],                        // Tasks module (pending)
        ];
    }

    /**
     * Map a checklist's items into WeConnectU planner cards for the selected FY.
     *
     * @param  ComplianceChecklist|null  $checklist
     * @return array<int, array>
     */
    private function planner(?ComplianceChecklist $checklist): array
    {
        if (! $checklist) {
            return [];
        }

        $items = $checklist->items()
            ->orderByRaw('due_date IS NULL DESC')   // "Confirm Date" (no date) first, like WeConnectU
            ->orderBy('due_date')
            ->orderBy('sort_order')
            ->get();

        return $items->map(function ($item) {
            $status = $item->status instanceof \App\Enums\ComplianceItemStatus
                ? $item->status->value
                : (string) $item->status;

            return [
                'id'             => $item->id,
                'title'          => $item->name,
                'category'       => $item->category,
                'due_date'       => $item->due_date?->toDateString(),
                'pill'           => $this->statusPill($status, $item->due_date !== null),
                'has_attachment' => (bool) ($item->evidence_file_path
                    || $item->attachments()->exists()),
                // Sub-item X/Y progress is a WeConnectU concept not yet modelled
                // (see community-dashboard-plan.md D4); null → card omits the chip.
                'checklist'      => null,
            ];
        })->all();
    }

    /**
     * Translate a compliance item status into a WeConnectU planner pill.
     *
     * WeConnectU pills: Confirm Date · Planned · Confirmed · Done (+ Overdue).
     */
    private function statusPill(string $status, bool $hasDueDate): string
    {
        if (! $hasDueDate) {
            return 'confirm_date';
        }

        return match ($status) {
            'completed'   => 'done',
            'overdue'     => 'overdue',
            'in_progress' => 'confirmed',
            'waived'      => 'planned',
            default       => 'planned', // pending
        };
    }

    /**
     * Financial summary card: Bank Balance, Investments, Outstanding Debt + trend.
     * NOT year-scoped — always live current state.
     *
     * @param  Community  $community
     * @return array
     */
    private function finance(Community $community): array
    {
        $accounts = BankAccount::where('community_id', $community->id)
            ->where('is_active', true)
            ->get();

        $current     = $accounts->where('type', BankAccountType::CURRENT);
        $investments = $accounts->where('type', BankAccountType::INVESTMENT);

        $outstanding = $this->outstandingDebt($community);

        return [
            'bank_balance' => [
                'value' => round((float) $current->sum('balance'), 2),
                'count' => $current->count(),
                'as_at' => optional($current->max('balance_as_at'))?->toDateString()
                    ?? optional($current->first()?->balance_as_at)?->toDateString(),
            ],
            'investments' => [
                'value' => round((float) $investments->sum('balance'), 2),
                'count' => $investments->count(),
                'as_at' => optional($investments->max('balance_as_at'))?->toDateString()
                    ?? optional($investments->first()?->balance_as_at)?->toDateString(),
            ],
            'outstanding_debt' => $outstanding,
            'debt_trend'       => $this->debtTrend($community, $outstanding),
        ];
    }

    /**
     * Net outstanding owed to the community (mirrors CommunityService balance logic).
     *
     * @param  Community  $community
     * @return float
     */
    private function outstandingDebt(Community $community): float
    {
        $communityUnitIds = Unit::where('community_id', $community->id)->select('id');

        $outstandingInvoiceIds = Invoice::whereIn('unit_id', $communityUnitIds)
            ->whereIn('status', ['unpaid', 'overdue', 'partially_paid'])
            ->select('id');

        $gross = (float) Invoice::whereIn('unit_id', $communityUnitIds)
            ->whereIn('status', ['unpaid', 'overdue', 'partially_paid'])
            ->sum('amount');

        $partiallyPaid = (float) CashbookEntry::whereIn('invoice_id', $outstandingInvoiceIds)
            ->sum('amount');

        return round(max(0.0, $gross - $partiallyPaid), 2);
    }

    /**
     * Per-community 6-month cumulative debt trend for the dashboard trend line.
     *
     * Outstanding invoices are bucketed by the month billed and made cumulative,
     * with debt older than the window rolled into the opening baseline so the
     * final point equals the current total outstanding.
     *
     * @param  Community  $community
     * @param  float      $totalOutstanding
     * @return array{total: float, percent_change: float, series: array<int, array{label: string, value: float}>}
     */
    private function debtTrend(Community $community, float $totalOutstanding): array
    {
        $communityUnitIds = Unit::where('community_id', $community->id)->select('id');

        $invoices = Invoice::whereIn('unit_id', $communityUnitIds)
            ->whereIn('status', [
                InvoiceStatus::UNPAID->value,
                InvoiceStatus::OVERDUE->value,
                InvoiceStatus::PARTIALLY_PAID->value,
            ])
            ->get(['billing_period', 'created_at', 'amount']);

        $byMonth = [];
        foreach ($invoices as $invoice) {
            $date = $invoice->billing_period ?? $invoice->created_at;
            $key  = $date ? $date->format('Y-m') : now()->format('Y-m');
            $byMonth[$key] = ($byMonth[$key] ?? 0) + (float) $invoice->amount;
        }

        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $months[] = now()->startOfMonth()->subMonths($i);
        }
        $windowStartKey = $months[0]->format('Y-m');

        $baseline = 0.0;
        foreach ($byMonth as $key => $amount) {
            if ($key < $windowStartKey) {
                $baseline += $amount;
            }
        }

        $series  = [];
        $running = $baseline;
        foreach ($months as $month) {
            $running += $byMonth[$month->format('Y-m')] ?? 0;
            $series[] = [
                'label' => $month->format('M'),
                'value' => round($running, 2),
            ];
        }

        $last = $series[count($series) - 1]['value'] ?? 0;
        $prev = $series[count($series) - 2]['value'] ?? 0;
        $percentChange = $prev > 0
            ? round((($last - $prev) / $prev) * 100, 1)
            : ($last > 0 ? 100.0 : 0.0);

        return [
            'total'          => round($totalOutstanding, 2),
            'percent_change' => $percentChange,
            'series'         => $series,
        ];
    }

    /**
     * Operational counts. Tasks / Transfers / Offences modules are not built yet
     * (see community-dashboard-plan.md §8) → zeros, exactly like a fresh community
     * in WeConnectU. Widgets flip to live data when each module lands.
     *
     * @return array
     */
    private function counts(): array
    {
        return [
            'open_tasks'        => 0,
            'pending_transfers' => 0,
            'warnings'          => 0,
            'penalties'         => 0,
            'fines'             => 0,
        ];
    }
}
