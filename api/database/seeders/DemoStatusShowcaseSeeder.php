<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Models\CashbookEntry;
use App\Models\Community;
use App\Models\Invoice;
use App\Models\Ledger;
use App\Models\NoteAttachment;
use App\Models\Unit;
use App\Models\UnitCollectionNote;
use App\Services\UnitBalanceService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Turns the commercial owners-register community (units UURO-##) into a full
 * showcase of the Age Analysis status markers: every collection status plus the
 * debit-order clock, transfer arrows and their combinations — each unit given a
 * real spread of aged arrears across all buckets so the grid looks complete.
 *
 * Idempotent: wipes the showcase community's invoices/receipts first, then
 * rebuilds them deterministically. Run via:
 *   php artisan db:seed --class=DemoStatusShowcaseSeeder
 */
class DemoStatusShowcaseSeeder extends Seeder
{
    /**
     * The marker cases to cycle through the community's units.
     * [collection_status, debit_order, transfer_active]
     *
     * @var array<int, array{0:string,1:bool,2:bool}>
     */
    private const CASES = [
        ['none',                false, false], // plain (no marker, arrears only)
        ['first_notice',        false, false], // peach dot "1st Notice"
        ['second_notice',       false, false], // red dot "2nd Notice"
        ['final_notice',        false, false], // black dot "Letter of demand"
        ['letter_of_demand',    false, false], // document "Letter of Demand sent"
        ['payment_arrangement', false, false], // gold flag
        ['handed_over',         false, false], // red flag
        ['none',                true,  false], // debit-order clock only
        ['none',                false, true],  // transfer arrows only
        ['handed_over',         false, true],  // transfer arrows + red flag
        ['first_notice',        true,  false], // clock + peach dot
        ['payment_arrangement', false, true],  // transfer arrows + gold flag
        ['letter_of_demand',    true,  false], // clock + document
        ['second_notice',       false, true],  // transfer arrows + red dot
        ['final_notice',        true,  true],  // clock + arrows + black dot
        ['first_notice',        false, true],  // arrows + peach dot
        ['letter_of_demand',    false, false], // document
        ['handed_over',         true,  false], // clock + red flag
        ['second_notice',       true,  false], // clock + red dot
        ['final_notice',        false, true],  // arrows + black dot
    ];

    /** Aged-arrears template: [days overdue relative to today, base amount] — one per bucket. */
    private const AGED_INVOICES = [
        [130, 1800.00], // 120+ days
        [ 80, 1450.00], // 90 days
        [ 50, 1200.00], // 60 days
        [ 20,  950.00], // 30 days
        [ -5, 2000.00], // current (not yet due)
    ];

    /**
     * Run the seeder.
     *
     * @return void
     */
    public function run(): void
    {
        $community = Community::whereHas('units', fn ($q) => $q->where('unit_number', 'like', 'URO-%'))->first();

        if (!$community) {
            $this->command?->warn('Showcase community (UURO-##) not found — skipping.');
            return;
        }

        $organizationId = $community->organization_id;
        $ledger         = Ledger::where('organization_id', $organizationId)->first();
        $balanceService = app(UnitBalanceService::class);

        $units   = Unit::where('community_id', $community->id)->with('owner')->orderBy('unit_number')->get();
        $unitIds = $units->pluck('id');

        // Clean slate for the showcase community so the spread is exact.
        // Invoice soft-deletes, so force-delete or the unique (unit, ledger,
        // period) index would still collide on re-run.
        CashbookEntry::whereIn('unit_id', $unitIds)->delete();
        if (DB::getSchemaBuilder()->hasTable('invoice_items')) {
            DB::table('invoice_items')
                ->whereIn('invoice_id', Invoice::withTrashed()->whereIn('unit_id', $unitIds)->pluck('id'))
                ->delete();
        }
        Invoice::withTrashed()->whereIn('unit_id', $unitIds)->forceDelete();

        // Reset collection notes (each attachment note gets its own file below,
        // so removing one attachment never affects another).
        UnitCollectionNote::whereIn('unit_id', $unitIds)->delete();

        $today = Carbon::today();

        foreach ($units as $i => $unit) {
            [$status, $debitOrder, $transferActive] = self::CASES[$i % count(self::CASES)];

            $unit->collection_status = $status;
            $unit->debit_order       = $debitOrder;
            $unit->transfer_active   = $transferActive;
            $unit->save();

            $ownerId = $unit->owner?->id;

            // Per-unit multiplier (0.55–1.50) so balances vary across the grid.
            $factor = round(0.55 + (($i * 37) % 96) / 100, 2);

            foreach (self::AGED_INVOICES as $j => [$daysOverdue, $baseAmount]) {
                $dueDate = $today->copy()->subDays($daysOverdue);
                $amount  = round($baseAmount * $factor, 2);

                Invoice::create([
                    'invoice_number'    => 'INV-SHOW-' . $unit->unit_number . '-' . $j,
                    'status'            => $daysOverdue > 0 ? InvoiceStatus::OVERDUE->value : InvoiceStatus::UNPAID->value,
                    'billed_to_type'    => 'owner',
                    'billed_to_id'      => $ownerId,
                    'amount'            => $amount,
                    'billing_period'    => $dueDate->copy()->startOfMonth()->toDateString(),
                    'due_date'          => $dueDate->toDateString(),
                    'invoice_date'      => $dueDate->copy()->subDays(7)->toDateString(),
                    'issued_by_type'    => 'system',
                    'issued_by_user_id' => null,
                    'unit_id'           => $unit->id,
                    'ledger_id'         => $ledger?->id,
                    'organization_id'   => $organizationId,
                ]);
            }

            $balanceService->recalculate($unit);

            // Vary the note profile per unit so every combination is on show:
            //   0 → none (faded icon)   1 → manual only (one with attachment)
            //   2 → system only         3 → mixed (manual w/ + w/o attachment + system)
            // [daysAgo, note, is_system, hasAttachment]
            $manual = [
                [4,  'Spoke to client, they will settle the balance by Friday.', false, true],
                [11, '0763175932 - call not going through, left a voicemail.',   false, false],
                [23, 'Owner sent AOD, agreed to pay R1000 over and above levies.', false, true],
            ];
            $system = [
                [26, 'Handed Over: Handing over, defaulted for the past 4 months.', true, false],
                [40, '2nd Notice @ R 2 066.07',        true, false],
                [58, 'Letter of Demand @ R 8 187.98',  true, false],
                [72, 'Balance Paid - removed debt status', true, false],
                [95, 'Balance Paid',                   true, false],
            ];

            $profile = $i % 4;
            $notes   = match ($profile) {
                0 => [],
                1 => [$manual[0], $manual[1]],
                2 => [$system[1], $system[3], $system[4]],
                default => [$manual[0], $manual[2], $system[0], $system[2], $system[4]],
            };

            foreach ($notes as $k => [$daysAgo, $text, $isSystem, $hasAttachment]) {
                $ts = $today->copy()->subDays($daysAgo)->setTime(9, 30, 0);

                $note = new UnitCollectionNote([
                    'unit_id'         => $unit->id,
                    'organization_id' => $organizationId,
                    'note'            => $text,
                    'is_system'       => $isSystem,
                    'created_by_name' => $isSystem ? 'System' : 'Bold Mark Admin',
                    'user_id'         => null,
                ]);
                $note->save();
                $note->timestamps = false;
                $note->created_at = $ts;
                $note->updated_at = $ts;
                $note->save();

                if (!$hasAttachment) {
                    continue;
                }

                // Showcase multiple attachments: the first manual note on the
                // "mixed" profile (3) gets two files; every other attachment
                // note gets one.
                $attachmentNames = ($profile === 3 && $k === 0)
                    ? ['Proof of payment', 'Acknowledgement of debt']
                    : ['Proof of payment'];

                foreach ($attachmentNames as $slug => $name) {
                    $docPath = "collection_notes/{$organizationId}/demo-{$unit->unit_number}-{$daysAgo}-{$slug}.pdf";
                    Storage::disk('public')->put($docPath, "%PDF-1.4\nDemo attachment — {$name} — Bold Mark Properties\n");

                    NoteAttachment::create([
                        'unit_collection_note_id' => $note->id,
                        'name'                    => $name,
                        'path'                    => $docPath,
                        'organization_id'         => $organizationId,
                    ]);
                }
            }
        }

        $this->command?->info("Showcase seeded on '{$community->name}' — {$units->count()} units across all status markers.");
    }
}
