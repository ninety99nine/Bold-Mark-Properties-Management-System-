<?php

namespace Database\Seeders;

use App\Models\Community;
use App\Models\NoticeBatch;
use App\Models\NoticeBatchItem;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Demo-only: fabricate two "Legal Notices" batches for a community so the
 * Customer Notices (View last notice batch) page has something to show —
 * grouped into 1st Notices / 2nd Notices / Letters of Demand, with the notice
 * charge, "sent to" contacts, balances and a "View previous notice" link.
 */
class NoticeBatchDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Seed every community so the page has data whichever one is selected.
        $communities = Community::orderBy('name')->get();

        if ($communities->isEmpty()) {
            $this->command?->warn('NoticeBatchDemoSeeder: no community found.');
            return;
        }

        foreach ($communities as $community) {
            $this->seedCommunity($community);
        }
    }

    private function seedCommunity(Community $community): void
    {
        // Idempotent: clear any prior demo batches for this community.
        $prior = NoticeBatch::where('community_id', $community->id)
            ->where('created_by_name', 'Demo Manager')
            ->pluck('id');
        if ($prior->isNotEmpty()) {
            NoticeBatchItem::whereIn('notice_batch_id', $prior)->delete();
            NoticeBatch::whereIn('id', $prior)->delete();
        }

        $units = Unit::where('community_id', $community->id)
            ->whereHas('owner')
            ->with('owner')
            ->orderBy('unit_number')
            ->limit(45)
            ->get()
            ->filter(fn ($u) => $u->owner && $u->owner->email)
            ->values();

        if ($units->isEmpty()) {
            return;
        }

        // Notice charge per level (from the community config, else WeConnectU-style defaults).
        $charges   = (array) ($community->notice_charges ?? []);
        $chargeFor = function (string $key, float $default) use ($charges): float {
            $cfg = (array) ($charges[$key] ?? []);
            $c   = (float) ($cfg['email_charge'] ?? 0) + (float) ($cfg['sms_charge'] ?? 0);
            return $c > 0 ? round($c, 2) : $default;
        };

        // Spread the fetched customers across the three notice levels.
        $assign = [];
        foreach ($units as $i => $u) {
            if ($i < 15) {
                $assign[$u->id] = ['1st Notice', $chargeFor('first', 3.45)];
            } elseif ($i < 24) {
                $assign[$u->id] = ['2nd Notice', $chargeFor('second', 27.60)];
            } else {
                $assign[$u->id] = ['Letter of Demand', $chargeFor('letter_of_demand', 27.60)];
            }
        }

        // Two runs → the latest is "last notice batch", the older is "previous notice".
        $runs = [
            ['ageing' => Carbon::now()->endOfMonth(),                  'ran' => Carbon::now()->subDays(5)],
            ['ageing' => Carbon::now()->subMonthNoOverflow()->endOfMonth(), 'ran' => Carbon::now()->subDays(35)],
        ];

        foreach ($runs as $run) {
            $batch = NoticeBatch::create([
                'ageing_date'     => $run['ageing']->toDateString(),
                'community_id'    => $community->id,
                'organization_id' => $community->organization_id,
                'created_by_name' => 'Demo Manager',
                'total'           => $units->count(),
            ]);
            // Backdate the run date so ordering + "View previous notice" work.
            DB::table('notice_batches')->where('id', $batch->id)->update(['created_at' => $run['ran']]);

            foreach ($units as $u) {
                [$level, $charge] = $assign[$u->id];
                $owner = $u->owner;

                $emails = array_values(array_filter(array_unique(array_merge(
                    [$owner->email],
                    (array) ($owner->secondary_emails ?? []),
                ))));
                $phones = array_values(array_filter([$owner->phone]));
                $sentTo = trim(implode(', ', $emails) . ($phones ? "\n" . implode(', ', $phones) : ''));

                $balance = round((float) $u->balance, 2);
                if ($balance <= 0) {
                    $balance = round(random_int(50000, 1200000) / 100, 2);
                }

                NoticeBatchItem::create([
                    'notice_batch_id' => $batch->id,
                    'unit_id'         => $u->id,
                    'owner_id'        => $owner->id,
                    'customer_code'   => $u->customer_code ?: $owner->customer_code,
                    'customer_name'   => $owner->full_name,
                    'level'           => $level,
                    'balance'         => $balance,
                    'charge'          => $charge,
                    'sent_to'         => $sentTo ?: null,
                    'customer_type'   => 'owner',
                    'emailed'         => true,
                    'email'           => $owner->email,
                    'organization_id' => $community->organization_id,
                ]);
            }
        }

        $this->command?->info("NoticeBatchDemoSeeder: {$community->name} — 2 batches ({$units->count()} customers each).");
    }
}
