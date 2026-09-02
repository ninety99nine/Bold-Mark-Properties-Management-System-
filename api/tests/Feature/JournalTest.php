<?php

use App\Models\Community;
use App\Models\JournalBatch;
use App\Models\Ledger;
use App\Models\Owner;
use App\Models\Unit;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function journalScaffold(): array
{
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $unit      = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    Owner::factory()->create(['unit_id' => $unit->id, 'organization_id' => $user->organization_id]);
    $ledger    = Ledger::factory()->create(['organization_id' => $user->organization_id, 'code' => '1000/001', 'name' => 'Levies']);

    return [$user, $community, $unit, $ledger];
}

/**
 * A balanced two-line batch: customer CREDIT + general DEBIT of the same amount.
 */
function balancedPayload(Community $community, Unit $unit, Ledger $ledger, float $amount = 100.0, string $date = '2026-07-15'): array
{
    return [
        'community_id'   => $community->id,
        'financial_year' => 2026,
        'date'           => $date,
        'journal_group'  => 'Transfer',
        'lines'          => [
            ['line_type' => 'customer', 'unit_id' => $unit->id, 'description' => 'Refund', 'amount' => $amount, 'entry_type' => 'credit'],
            ['line_type' => 'general',  'ledger_id' => $ledger->id, 'description' => 'Refund', 'amount' => $amount, 'entry_type' => 'debit'],
        ],
    ];
}

// ──────────────────────────────────────────────────────────────────────────────
// Auth
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on journal routes when unauthenticated', function (string $method, string $route) {
    $this->{$method . 'Json'}(route($route))->assertUnauthorized();
})->with([
    ['get',  'api.v1.show.journal.batches'],
    ['post', 'api.v1.create.journal.batch'],
    ['post', 'api.v1.upload.journal.batch'],
]);

// ──────────────────────────────────────────────────────────────────────────────
// Create
// ──────────────────────────────────────────────────────────────────────────────

it('creates a balanced manual journal batch', function () {
    [$user, $community, $unit, $ledger] = journalScaffold();

    $response = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.journal.batch'), balancedPayload($community, $unit, $ledger))
        ->assertCreated();

    $batch = JournalBatch::findOrFail($response->json('data.id'));

    expect($batch->batch_number)->toBe(1)
        ->and($batch->batch_name)->toBe('Journal Batch 1')
        ->and($batch->journal_group)->toBe('Transfer')
        ->and($batch->community_id)->toBe($community->id)
        ->and($batch->lines()->count())->toBe(2);
});

it('increments the per-community batch number', function () {
    [$user, $community, $unit, $ledger] = journalScaffold();

    $this->actingAs($user, 'api')->postJson(route('api.v1.create.journal.batch'), balancedPayload($community, $unit, $ledger))->assertCreated();
    $second = $this->actingAs($user, 'api')->postJson(route('api.v1.create.journal.batch'), balancedPayload($community, $unit, $ledger))->assertCreated();

    expect($second->json('data.batch_number'))->toBe(2);
});

it('rejects an unbalanced batch', function () {
    [$user, $community, $unit, $ledger] = journalScaffold();

    $payload = balancedPayload($community, $unit, $ledger);
    $payload['lines'][1]['amount'] = 50; // debit no longer equals the 100 credit

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.journal.batch'), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['lines']);
});

it('requires at least two lines', function () {
    [$user, $community, $unit, $ledger] = journalScaffold();

    $payload = balancedPayload($community, $unit, $ledger);
    $payload['lines'] = [$payload['lines'][0]];

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.journal.batch'), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['lines']);
});

it('requires a customer for a customer line and an account for a general line', function () {
    [$user, $community, $unit, $ledger] = journalScaffold();

    $payload = balancedPayload($community, $unit, $ledger);
    unset($payload['lines'][0]['unit_id']);   // customer line without a customer
    unset($payload['lines'][1]['ledger_id']); // general line without an account

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.journal.batch'), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['lines.0.unit_id', 'lines.1.ledger_id']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Customer posting (reflects everywhere)
// ──────────────────────────────────────────────────────────────────────────────

it('posts a customer credit line to the unit balance', function () {
    [$user, $community, $unit, $ledger] = journalScaffold();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.journal.batch'), balancedPayload($community, $unit, $ledger, 250.0))
        ->assertCreated();

    // A customer credit is a credit-on-account → positive stored balance.
    expect((float) $unit->fresh()->balance)->toBe(250.0);
});

it('shows the journal line on the customer detailed ledger', function () {
    [$user, $community, $unit, $ledger] = journalScaffold();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.journal.batch'), balancedPayload($community, $unit, $ledger, 300.0, '2026-07-27'))
        ->assertCreated();

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.detailed.ledger', [
            'community'  => $community->id,
            'date_from'  => '2026-07-01',
            'date_to'    => '2026-09-30',
        ]))
        ->assertOk()
        ->json();

    $ledgerBlock = collect($data['ledgers'])->firstWhere('unit_id', $unit->id);
    expect($ledgerBlock)->not->toBeNull();

    $journalRow = collect($ledgerBlock['rows'])->firstWhere('source', 'Journal Batch 1');
    expect($journalRow)->not->toBeNull()
        ->and(round((float) $journalRow['credit'], 2))->toBe(300.00)
        ->and($journalRow['description'])->toBe('Refund')
        ->and(round((float) $ledgerBlock['totals']['credit'], 2))->toBe(300.00);
});

// ──────────────────────────────────────────────────────────────────────────────
// Show / Update / Delete
// ──────────────────────────────────────────────────────────────────────────────

it('shows a single batch with account labels', function () {
    [$user, $community, $unit, $ledger] = journalScaffold();

    $id = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.journal.batch'), balancedPayload($community, $unit, $ledger))
        ->json('data.id');

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.journal.batch', ['journalBatch' => $id]))
        ->assertOk()
        ->json('data');

    expect($data['lines'])->toHaveCount(2);
    $generalLine = collect($data['lines'])->firstWhere('line_type', 'general');
    expect($generalLine['account'])->toBe('1000/001 - Levies');
});

it('updates a batch and re-posts the balance', function () {
    [$user, $community, $unit, $ledger] = journalScaffold();

    $id = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.journal.batch'), balancedPayload($community, $unit, $ledger, 100.0))
        ->json('data.id');

    expect((float) $unit->fresh()->balance)->toBe(100.0);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.journal.batch', ['journalBatch' => $id]), [
            'date'          => '2026-07-20',
            'journal_group' => 'Legal Fees',
            'lines'         => [
                ['line_type' => 'customer', 'unit_id' => $unit->id, 'description' => 'Refund', 'amount' => 400, 'entry_type' => 'credit'],
                ['line_type' => 'general',  'ledger_id' => $ledger->id, 'description' => 'Refund', 'amount' => 400, 'entry_type' => 'debit'],
            ],
        ])
        ->assertOk();

    expect((float) $unit->fresh()->balance)->toBe(400.0)
        ->and(JournalBatch::find($id)->journal_group)->toBe('Legal Fees');
});

it('deletes a batch and reverses the balance', function () {
    [$user, $community, $unit, $ledger] = journalScaffold();

    $id = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.journal.batch'), balancedPayload($community, $unit, $ledger, 175.0))
        ->json('data.id');

    expect((float) $unit->fresh()->balance)->toBe(175.0);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.journal.batch', ['journalBatch' => $id]))
        ->assertOk();

    expect(JournalBatch::find($id))->toBeNull()
        ->and((float) $unit->fresh()->balance)->toBe(0.0);
});

// ──────────────────────────────────────────────────────────────────────────────
// List + downloads + scoping
// ──────────────────────────────────────────────────────────────────────────────

it('lists batches for a community', function () {
    [$user, $community, $unit, $ledger] = journalScaffold();

    $this->actingAs($user, 'api')->postJson(route('api.v1.create.journal.batch'), balancedPayload($community, $unit, $ledger))->assertCreated();

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.journal.batches', ['community_id' => $community->id]))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['entries_count'])->toBe(2)
        ->and($data[0]['batch_name'])->toBe('Journal Batch 1');
});

it('downloads the batch template', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->get(route('api.v1.download.journal.template'))
        ->assertOk();
});

it('downloads a single batch as excel with the WeConnectU filename', function () {
    [$user, $community, $unit, $ledger] = journalScaffold();

    $id = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.journal.batch'), balancedPayload($community, $unit, $ledger, 100.0, '2026-07-20'))
        ->json('data.id');

    $response = $this->actingAs($user, 'api')
        ->get(route('api.v1.download.journal.batch', ['journalBatch' => $id]))
        ->assertOk();

    expect($response->headers->get('content-disposition'))
        ->toContain('journal batch - 2026-07-20-journal batch 1-.xlsx');
});

it('404s when viewing a batch from another organization', function () {
    [$user, $community, $unit, $ledger] = journalScaffold();
    $id = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.journal.batch'), balancedPayload($community, $unit, $ledger))
        ->json('data.id');

    $other = adminUser();
    $this->actingAs($other, 'api')
        ->getJson(route('api.v1.show.journal.batch', ['journalBatch' => $id]))
        ->assertNotFound();
});
