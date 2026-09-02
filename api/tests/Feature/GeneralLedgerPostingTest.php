<?php

use App\Enums\FinancialCategory;
use App\Enums\JournalEntryType;
use App\Enums\JournalLineType;
use App\Enums\JournalSource;
use App\Models\Community;
use App\Models\JournalBatch;
use App\Models\Ledger;
use App\Models\Unit;
use App\Services\GeneralLedgerPostingService;
use Carbon\Carbon;
use Database\Seeders\ChartOfAccountsSeeder;

/**
 * Build a community (financial year ending 30 June) with the full chart of
 * accounts seeded for the org.
 */
function glCommunity(\App\Models\User $user): Community
{
    (new ChartOfAccountsSeeder())->seedForOrganization($user->organization_id);

    return Community::factory()->create([
        'organization_id'          => $user->organization_id,
        'financial_year_end_month' => 6,
    ]);
}

function glService(): GeneralLedgerPostingService
{
    return app(GeneralLedgerPostingService::class);
}

it('resolves control accounts by financial category', function () {
    $user = adminUser();
    glCommunity($user);

    $ar = glService()->controlAccount($user->organization_id, FinancialCategory::ACCOUNTS_RECEIVABLE);
    expect($ar->code)->toBe('7000/001');

    $vat = glService()->controlAccount($user->organization_id, FinancialCategory::VAT_CONTROL);
    expect($vat->code)->toBe('6000/001');
});

it('posts a balanced source-tagged batch with a null batch number', function () {
    $user      = adminUser();
    $community = glCommunity($user);
    $unit      = Unit::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id]);

    $income = Ledger::where('organization_id', $user->organization_id)->where('code', '1000/001')->first();

    // Use the unit as the polymorphic source so no factory invoice contaminates
    // the balance (UnitBalanceService still blends documents until Phase 1).
    $batch = glService()->postBatch(
        $community,
        Carbon::parse('2026-07-15'),
        JournalSource::INVOICE,
        $unit,
        'Levy',
        [
            ['line_type' => JournalLineType::CUSTOMER, 'entry_type' => JournalEntryType::DEBIT,  'amount' => 1000, 'unit_id' => $unit->id, 'due_date' => '2026-07-25', 'description' => 'Levies'],
            ['line_type' => JournalLineType::GENERAL,  'entry_type' => JournalEntryType::CREDIT, 'amount' => 1000, 'ledger_id' => $income->id, 'description' => 'Levies'],
        ],
    );

    expect($batch->batch_number)->toBeNull();
    expect($batch->source)->toBe(JournalSource::INVOICE);
    expect($batch->source_type)->toBe($unit->getMorphClass());
    expect($batch->source_id)->toBe($unit->id);
    expect($batch->financial_year)->toBe(2027); // FY ends 30 June → 2026-07-15 falls in FY2027
    expect($batch->lines()->count())->toBe(2);
    expect((float) $unit->fresh()->balance)->toBe(-1000.0); // customer debit increases what they owe
});

it('rejects an unbalanced batch', function () {
    $user      = adminUser();
    $community = glCommunity($user);
    $income    = Ledger::where('organization_id', $user->organization_id)->where('code', '1000/001')->first();

    glService()->postBatch(
        $community,
        Carbon::parse('2026-07-15'),
        JournalSource::MANUAL,
        null,
        null,
        [
            ['line_type' => JournalLineType::GENERAL, 'entry_type' => JournalEntryType::DEBIT,  'amount' => 1000, 'ledger_id' => $income->id],
            ['line_type' => JournalLineType::GENERAL, 'entry_type' => JournalEntryType::CREDIT, 'amount' => 900,  'ledger_id' => $income->id],
        ],
    );
})->throws(Exception::class, 'does not balance');

it('deletes batches for a source document and recalculates balances', function () {
    $user      = adminUser();
    $community = glCommunity($user);
    $unit      = Unit::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id]);
    $income    = Ledger::where('organization_id', $user->organization_id)->where('code', '1000/001')->first();

    glService()->postBatch($community, Carbon::parse('2026-07-15'), JournalSource::INVOICE, $unit, 'Levy', [
        ['line_type' => JournalLineType::CUSTOMER, 'entry_type' => JournalEntryType::DEBIT,  'amount' => 500, 'unit_id' => $unit->id],
        ['line_type' => JournalLineType::GENERAL,  'entry_type' => JournalEntryType::CREDIT, 'amount' => 500, 'ledger_id' => $income->id],
    ]);
    expect((float) $unit->fresh()->balance)->toBe(-500.0);

    glService()->deleteBatchesFor($unit);

    expect(JournalBatch::where('source_id', $unit->id)->count())->toBe(0);
    expect((float) $unit->fresh()->balance)->toBe(0.0);
});

it('keeps auto-posted batches out of the manual journals scope', function () {
    $user      = adminUser();
    $community = glCommunity($user);
    $income    = Ledger::where('organization_id', $user->organization_id)->where('code', '1000/001')->first();
    $expense   = Ledger::where('organization_id', $user->organization_id)->where('code', '2000/001')->first();

    glService()->postBatch($community, Carbon::parse('2026-07-15'), JournalSource::CASHBOOK, null, 'Transfer', [
        ['line_type' => JournalLineType::GENERAL, 'entry_type' => JournalEntryType::DEBIT,  'amount' => 200, 'ledger_id' => $expense->id],
        ['line_type' => JournalLineType::GENERAL, 'entry_type' => JournalEntryType::CREDIT, 'amount' => 200, 'ledger_id' => $income->id],
    ]);

    expect(JournalBatch::query()->count())->toBe(1);
    expect(JournalBatch::manual()->count())->toBe(0);
});
