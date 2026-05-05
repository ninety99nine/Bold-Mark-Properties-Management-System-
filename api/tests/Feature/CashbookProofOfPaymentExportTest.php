<?php

use App\Models\CashbookEntry;
use App\Models\Estate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function makeCashbookEntry(array $overrides = []): CashbookEntry
{
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    return CashbookEntry::factory()->create(array_merge([
        'organization_id' => $user->organization_id,
        'estate_id'       => $estate->id,
    ], $overrides));
}

// ──────────────────────────────────────────────────────────────────────────────
// POST /cashbook/{entry}/proof-of-payment  — upload
// ──────────────────────────────────────────────────────────────────────────────

it('upload proof of payment returns 401 without auth', function () {
    $entry = makeCashbookEntry();

    $this->postJson(route('api.v1.upload.proof.of.payment', $entry))
        ->assertUnauthorized();
});

it('upload proof of payment stores the file on the public disk', function () {
    Storage::fake('public');

    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $entry  = CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id,
        'estate_id'       => $estate->id,
    ]);

    $file = UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf');

    $this->actingAs($user, 'api')
        ->post(route('api.v1.upload.proof.of.payment', $entry), ['file' => $file])
        ->assertOk();

    Storage::disk('public')->assertExists('proof-of-payment/' . $file->hashName());
});

it('upload proof of payment updates proof_of_payment_path on the entry', function () {
    Storage::fake('public');

    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $entry  = CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id,
        'estate_id'       => $estate->id,
    ]);

    $file = UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf');

    $this->actingAs($user, 'api')
        ->post(route('api.v1.upload.proof.of.payment', $entry), ['file' => $file]);

    expect($entry->fresh()->proof_of_payment_path)->not->toBeNull();
});

it('upload proof of payment returns a cashbook entry resource', function () {
    Storage::fake('public');

    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $entry  = CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id,
        'estate_id'       => $estate->id,
    ]);

    $file = UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf');

    $this->actingAs($user, 'api')
        ->post(route('api.v1.upload.proof.of.payment', $entry), ['file' => $file])
        ->assertOk()
        ->assertJsonStructure(['data' => ['id', 'proof_of_payment_url']]);
});

it('upload proof of payment replaces an existing file', function () {
    Storage::fake('public');

    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $entry  = CashbookEntry::factory()->create([
        'organization_id'      => $user->organization_id,
        'estate_id'            => $estate->id,
        'proof_of_payment_path' => 'proof-of-payment/old-receipt.pdf',
    ]);

    Storage::disk('public')->put('proof-of-payment/old-receipt.pdf', 'old content');

    $newFile = UploadedFile::fake()->create('new-receipt.pdf', 100, 'application/pdf');

    $this->actingAs($user, 'api')
        ->post(route('api.v1.upload.proof.of.payment', $entry), ['file' => $newFile])
        ->assertOk();

    Storage::disk('public')->assertMissing('proof-of-payment/old-receipt.pdf');
});

it('upload proof of payment requires the file field', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $entry  = CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id,
        'estate_id'       => $estate->id,
    ]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.upload.proof.of.payment', $entry), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});

it('upload proof of payment rejects unsupported mime types', function () {
    Storage::fake('public');

    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $entry  = CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id,
        'estate_id'       => $estate->id,
    ]);

    $file = UploadedFile::fake()->create('spreadsheet.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $this->actingAs($user, 'api')
        ->withHeaders(['Accept' => 'application/json'])
        ->post(route('api.v1.upload.proof.of.payment', $entry), ['file' => $file])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});

it('upload proof of payment accepts jpg files', function () {
    Storage::fake('public');

    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $entry  = CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id,
        'estate_id'       => $estate->id,
    ]);

    $file = UploadedFile::fake()->image('receipt.jpg');

    $this->actingAs($user, 'api')
        ->post(route('api.v1.upload.proof.of.payment', $entry), ['file' => $file])
        ->assertOk();
});

it('upload proof of payment accepts png files', function () {
    Storage::fake('public');

    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $entry  = CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id,
        'estate_id'       => $estate->id,
    ]);

    $file = UploadedFile::fake()->image('receipt.png');

    $this->actingAs($user, 'api')
        ->post(route('api.v1.upload.proof.of.payment', $entry), ['file' => $file])
        ->assertOk();
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /cashbook/{entry}/proof-of-payment/download  — download
// ──────────────────────────────────────────────────────────────────────────────

it('download proof of payment returns 401 without auth', function () {
    $entry = makeCashbookEntry();

    $this->getJson(route('api.v1.download.proof.of.payment', $entry))
        ->assertUnauthorized();
});

it('download proof of payment streams the file with content-disposition header', function () {
    Storage::fake('public');

    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    Storage::disk('public')->put('proof-of-payment/receipt.pdf', 'dummy pdf content');

    $entry = CashbookEntry::factory()->create([
        'organization_id'       => $user->organization_id,
        'estate_id'             => $estate->id,
        'proof_of_payment_path' => 'proof-of-payment/receipt.pdf',
    ]);

    $response = $this->actingAs($user, 'api')
        ->get(route('api.v1.download.proof.of.payment', $entry));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('receipt.pdf');
});

it('download proof of payment returns 500 when no file is attached', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $entry  = CashbookEntry::factory()->create([
        'organization_id'       => $user->organization_id,
        'estate_id'             => $estate->id,
        'proof_of_payment_path' => null,
    ]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.download.proof.of.payment', $entry))
        ->assertStatus(500);
});

// ──────────────────────────────────────────────────────────────────────────────
// DELETE /cashbook/{entry}/proof-of-payment  — delete
// ──────────────────────────────────────────────────────────────────────────────

it('delete proof of payment returns 401 without auth', function () {
    $entry = makeCashbookEntry();

    $this->deleteJson(route('api.v1.delete.proof.of.payment', $entry))
        ->assertUnauthorized();
});

it('delete proof of payment removes the file from the public disk', function () {
    Storage::fake('public');

    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    Storage::disk('public')->put('proof-of-payment/receipt.pdf', 'content');

    $entry = CashbookEntry::factory()->create([
        'organization_id'       => $user->organization_id,
        'estate_id'             => $estate->id,
        'proof_of_payment_path' => 'proof-of-payment/receipt.pdf',
    ]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.proof.of.payment', $entry))
        ->assertOk();

    Storage::disk('public')->assertMissing('proof-of-payment/receipt.pdf');
});

it('delete proof of payment clears proof_of_payment_path on the entry', function () {
    Storage::fake('public');

    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    Storage::disk('public')->put('proof-of-payment/receipt.pdf', 'content');

    $entry = CashbookEntry::factory()->create([
        'organization_id'       => $user->organization_id,
        'estate_id'             => $estate->id,
        'proof_of_payment_path' => 'proof-of-payment/receipt.pdf',
    ]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.proof.of.payment', $entry))
        ->assertOk();

    expect($entry->fresh()->proof_of_payment_path)->toBeNull();
});

it('delete proof of payment returns a success message', function () {
    Storage::fake('public');

    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    Storage::disk('public')->put('proof-of-payment/receipt.pdf', 'content');

    $entry = CashbookEntry::factory()->create([
        'organization_id'       => $user->organization_id,
        'estate_id'             => $estate->id,
        'proof_of_payment_path' => 'proof-of-payment/receipt.pdf',
    ]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.proof.of.payment', $entry))
        ->assertOk()
        ->assertJson(['message' => 'Proof of payment removed']);
});

it('delete proof of payment is idempotent when no file is attached', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $entry  = CashbookEntry::factory()->create([
        'organization_id'       => $user->organization_id,
        'estate_id'             => $estate->id,
        'proof_of_payment_path' => null,
    ]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.proof.of.payment', $entry))
        ->assertOk()
        ->assertJson(['message' => 'Proof of payment removed']);
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /cashbook/export  — export
// ──────────────────────────────────────────────────────────────────────────────

it('cashbook export returns 401 without auth', function () {
    $this->getJson(route('api.v1.export.cashbook.entries'))
        ->assertUnauthorized();
});

it('cashbook export returns a csv response by default', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    CashbookEntry::factory()->create(['organization_id' => $user->organization_id, 'estate_id' => $estate->id]);

    $response = $this->actingAs($user, 'api')
        ->get(route('api.v1.export.cashbook.entries'));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});

it('cashbook export csv contains expected headings', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    CashbookEntry::factory()->create(['organization_id' => $user->organization_id, 'estate_id' => $estate->id]);

    $response = $this->actingAs($user, 'api')
        ->get(route('api.v1.export.cashbook.entries'));

    $csv = $response->streamedContent();

    expect($csv)->toContain('Date');
    expect($csv)->toContain('Description');
    expect($csv)->toContain('Amount');
});

it('cashbook export csv contains entry data', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    CashbookEntry::factory()->create([
        'organization_id' => $user->organization_id,
        'estate_id'       => $estate->id,
        'description'     => 'Rent payment from unit 4',
    ]);

    $csv = $this->actingAs($user, 'api')
        ->get(route('api.v1.export.cashbook.entries'))
        ->streamedContent();

    expect($csv)->toContain('Rent payment from unit 4');
});

it('cashbook export only includes the authenticated tenants entries', function () {
    $otherUser   = adminUser();
    $otherEstate = Estate::factory()->create(['organization_id' => $otherUser->organization_id]);
    CashbookEntry::factory()->create([
        'organization_id' => $otherUser->organization_id,
        'estate_id'       => $otherEstate->id,
        'description'     => 'Other tenant payment',
    ]);

    $myUser   = adminUser();
    $myEstate = Estate::factory()->create(['organization_id' => $myUser->organization_id]);
    CashbookEntry::factory()->create([
        'organization_id' => $myUser->organization_id,
        'estate_id'       => $myEstate->id,
        'description'     => 'My payment',
    ]);

    $csv = $this->actingAs($myUser, 'api')
        ->get(route('api.v1.export.cashbook.entries'))
        ->streamedContent();

    expect($csv)->toContain('My payment');
    expect($csv)->not->toContain('Other tenant payment');
});

it('cashbook export returns csv content-disposition header', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    CashbookEntry::factory()->create(['organization_id' => $user->organization_id, 'estate_id' => $estate->id]);

    $response = $this->actingAs($user, 'api')
        ->get(route('api.v1.export.cashbook.entries'));

    expect($response->headers->get('Content-Disposition'))->toContain('cashbook-');
    expect($response->headers->get('Content-Disposition'))->toContain('.csv');
});

it('cashbook export with _format=csv returns csv', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    CashbookEntry::factory()->create(['organization_id' => $user->organization_id, 'estate_id' => $estate->id]);

    $response = $this->actingAs($user, 'api')
        ->get(route('api.v1.export.cashbook.entries') . '?_format=csv');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});

it('cashbook export returns empty csv with only headings when no entries exist', function () {
    $user = adminUser();

    $csv = $this->actingAs($user, 'api')
        ->get(route('api.v1.export.cashbook.entries'))
        ->streamedContent();

    expect($csv)->toContain('Date');
    expect($csv)->toContain('Description');
});
