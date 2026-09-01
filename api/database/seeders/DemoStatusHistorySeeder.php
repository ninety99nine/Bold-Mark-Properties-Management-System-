<?php

namespace Database\Seeders;

use App\Models\Community;
use App\Models\Organization;
use App\Models\Unit;
use App\Models\UnitStatusHistory;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seeds customer status-change history so the WeConnectU "Status Batches"
 * (manual, grouped) and "Automatic Status Changes" (bulk auto) pages are
 * populated for every demo community.
 *
 * Non-destructive & idempotent: clears each community's history first, then
 * rebuilds a deterministic set — manual batches (payment arrangements, hand-overs,
 * removed-collection-type, plus a couple of mass hand-overs) and two dated bulk
 * automatic runs across most units.
 */
class DemoStatusHistorySeeder extends Seeder
{
    /** Manual batches: [daysAgo, status, note, customerCount]. */
    private const MANUAL_BATCHES = [
        [3,   'payment_arrangement', 'Owner has agreed to pay R1500 on top of their levies', 1],
        [12,  'payment_arrangement', 'Owner has agreed to pay R1000 on top of their levies', 1],
        [21,  'payment_arrangement', 'Owner signed payment arrangement to pay R1000.00 over and above their levies.', 1],
        [34,  'handed_over',         'Account handed over to Lefakane Attorneys', 1],
        [48,  'handed_over',         'Owner has defaulted on their payment arrangement', 1],
        [55,  'none',                'Owner said they will settle account by the end of the week.', 1],
        [63,  'none',                'Owner stated that they have made payment and will send POP.', 1],
        [77,  'payment_arrangement', 'Owner has agreed to pay R2500 on top of their levies', 1],
        [96,  'letter_of_demand',    'Second reminder issued before hand-over.', 1],
        [140, 'handed_over',         'Good day, please take note of the handed over account.', 8],
        [205, 'handed_over',         'Handing over — defaulted for the past 4 months.', 6],
    ];

    /** Bulk automatic runs: [daysAgo, hour, minute]. */
    private const AUTO_RUNS = [
        [21, 14, 29],
        [54, 9,  6],
    ];

    /**
     * Run the seeder.
     *
     * @return void
     */
    public function run(): void
    {
        $organizationId = Organization::where('slug', 'boldmark')->value('id');

        if (!$organizationId) {
            return;
        }

        $today = Carbon::today();

        Community::where('organization_id', $organizationId)->has('units')->each(function (Community $community) use ($organizationId, $today) {
            UnitStatusHistory::where('community_id', $community->id)->delete();

            $units = Unit::where('community_id', $community->id)->orderBy('unit_number')->get();
            if ($units->isEmpty()) {
                return;
            }

            // ── Manual batches (Status Batches page) ─────────────────────────
            foreach (self::MANUAL_BATCHES as $i => [$daysAgo, $status, $note, $count]) {
                $date  = $today->copy()->subDays($daysAgo);
                $batch = $units->slice($i % max($units->count(), 1), $count);
                if ($batch->isEmpty()) {
                    $batch = $units->take($count);
                }

                foreach ($batch as $unit) {
                    UnitStatusHistory::create([
                        'status'          => $status,
                        'note'            => $note,
                        'status_date'     => $date->toDateString(),
                        'is_automatic'    => false,
                        'changed_by_name' => 'Bold Mark Admin',
                        'unit_id'         => $unit->id,
                        'community_id'    => $community->id,
                        'organization_id' => $organizationId,
                        'user_id'         => null,
                    ]);
                }
            }

            // ── Bulk automatic runs (Automatic Status Changes page) ──────────
            foreach (self::AUTO_RUNS as [$daysAgo, $hour, $minute]) {
                $ts = $today->copy()->subDays($daysAgo)->setTime($hour, $minute, 0);

                foreach ($units as $unit) {
                    $h = UnitStatusHistory::create([
                        'status'          => $unit->collection_status?->value ?? 'none',
                        'note'            => null,
                        'status_date'     => $ts->toDateString(),
                        'is_automatic'    => true,
                        'changed_by_name' => 'System',
                        'unit_id'         => $unit->id,
                        'community_id'    => $community->id,
                        'organization_id' => $organizationId,
                        'user_id'         => null,
                    ]);

                    // Stamp the exact run time so the page shows "…09:06:00".
                    $h->timestamps = false;
                    $h->created_at = $ts;
                    $h->updated_at = $ts;
                    $h->save();
                }
            }
        });
    }
}
