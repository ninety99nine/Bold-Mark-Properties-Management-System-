<?php

namespace App\Services;

use App\Models\Community;
use App\Models\CommunityBudget;
use Carbon\Carbon;

/**
 * WeConnectU-style Financial Year / Budget Period list.
 *
 * Drives the "Financial Year / Budget Period" selector on the Customer Age
 * Analysis page. For a community it derives a window of financial years —
 * the past 3, the current year-end, and the next 2 future years — each labelled
 * like "01/01/2026 - 31/12/2026 (Setup)". A period is "Setup" when the community
 * has budget lines captured for that year; future years without a budget are
 * flagged "(Not Setup)" (rendered red in the UI).
 *
 * The financial year window is anchored on the community's
 * financial_year_end_month (defaults to December → a calendar year).
 */
class FinancialYearService extends BaseService
{
    /** Number of past financial years to list. */
    private const PAST = 3;

    /** Number of future financial years to list. */
    private const FUTURE = 2;

    /**
     * Build the financial-year / budget-period list for a community.
     *
     * @param Community $community
     * @return array{periods: array, current_year: int}
     */
    public function getFinancialYears(Community $community): array
    {
        $endMonth = (int) ($community->financial_year_end_month ?: 12);
        $today    = Carbon::today();

        // The current financial year is the one whose year-end is the first
        // year-end on/after today.
        $currentEnd = Carbon::create($today->year, $endMonth, 1)->endOfMonth();
        if ($currentEnd->lt($today)) {
            $currentEnd->addYear();
        }

        // Years that already have a captured budget → "Setup".
        $setupYears = CommunityBudget::where('community_id', $community->id)
            ->distinct()
            ->pluck('year')
            ->map(fn ($y) => (int) $y)
            ->all();

        $periods = [];
        for ($offset = -self::PAST; $offset <= self::FUTURE; $offset++) {
            $end   = $currentEnd->copy()->addYears($offset);
            $start = $end->copy()->subYear()->addDay();

            $year    = $end->year;
            $isSetup = in_array($year, $setupYears, true);

            $periods[] = [
                'start'      => $start->toDateString(),
                'end'        => $end->toDateString(),
                'year'       => $year,
                'is_setup'   => $isSetup,
                'is_current' => $offset === 0,
                'is_past'    => $offset < 0,
                'is_future'  => $offset > 0,
                'label'      => sprintf(
                    '%s - %s (%s)',
                    $start->format('d/m/Y'),
                    $end->format('d/m/Y'),
                    $isSetup ? 'Setup' : 'Not Setup'
                ),
            ];
        }

        return [
            'periods'      => $periods,
            'current_year' => $currentEnd->year,
        ];
    }
}
