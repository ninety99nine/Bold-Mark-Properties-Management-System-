<?php

namespace App\Console\Commands;

use App\Models\Community;
use App\Models\Owner;
use App\Models\Unit;
use Illuminate\Console\Command;

/**
 * Create the "OAKHURST BC" demo community with the exact U001–U050 units from the
 * WeConnectU PQ batch file (matching unit numbers + customer codes), so uploading
 * that file lands cleanly and the PQ page mirrors WeConnectU like-for-like.
 *
 * Reads storage/app/oakhurst-units.json (parsed from the WeConnectU export).
 * Idempotent — re-running rebuilds the community from scratch.
 */
class SeedOakhurstDemo extends Command
{
    protected $signature = 'demo:seed-oakhurst';

    protected $description = 'Seed the OAKHURST BC demo community (U001–U050) for a like-for-like WeConnectU PQ import.';

    public function handle(): int
    {
        $path = storage_path('app/oakhurst-units.json');
        if (! is_file($path)) {
            $this->error("{$path} not found — parse the WeConnectU PQ file first.");

            return self::FAILURE;
        }

        $rows = json_decode((string) file_get_contents($path), true);
        if (! is_array($rows) || $rows === []) {
            $this->error('No unit rows parsed from oakhurst-units.json.');

            return self::FAILURE;
        }

        $orgId = Community::query()->value('organization_id');
        if (! $orgId) {
            $this->error('No organization found to attach the community to.');

            return self::FAILURE;
        }

        // Fresh start — remove any prior OAKHURST BC demo (units + owners).
        Community::where('name', 'OAKHURST BC')->get()->each(function (Community $c) {
            Unit::where('community_id', $c->id)->get()->each(function (Unit $u) {
                Owner::where('unit_id', $u->id)->delete();
                $u->delete();
            });
            $c->delete();
        });

        $community = Community::create([
            'name'              => 'OAKHURST BC',
            'entity_type'       => 'body_corporate',
            'address'           => 'Oakhurst, Cape Town, 8001',
            'admin_fund_amount' => 250000,
            'billing_day'       => 25,
            'country'           => 'ZA',
            'currency'          => 'ZAR',
            'is_active'         => true,
            'organization_id'   => $orgId,
        ]);

        usort($rows, fn ($a, $b) => strcmp($a['unit_number'], $b['unit_number']));

        foreach ($rows as $r) {
            $unit = Unit::create([
                'unit_number'     => $r['unit_number'],
                'section'         => $r['section'] ?? $r['unit_number'],
                'customer_code'   => $r['customer_code'] ?? null,
                'address'         => 'Oakhurst — Unit ' . $r['unit_number'],
                'occupancy_type'  => 'owner_occupied',
                'status'          => 'active',
                'balance'         => 0,
                'community_id'    => $community->id,
                'organization_id' => $orgId,
            ]);

            Owner::create([
                'full_name'       => fake()->name(),
                'email'           => strtolower($r['unit_number']) . '@oakhurst.example',
                'phone'           => '08' . fake()->numerify('########'),
                'entity_type'     => 'individual',
                'unit_id'         => $unit->id,
                'organization_id' => $orgId,
            ]);
        }

        $this->info('Created "OAKHURST BC" with ' . count($rows) . ' units (U001–U050).');
        $this->info('Open it → Units → PQs → Upload new PQs, and drop the WeConnectU PQ file to populate PQ / Ratio 1 / Unit Size.');

        return self::SUCCESS;
    }
}
