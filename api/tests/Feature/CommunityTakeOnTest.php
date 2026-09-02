<?php

use App\Models\Community;
use App\Models\CommunityBudget;
use App\Models\Owner;
use App\Models\Unit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * The 36 WeConnectU owner-sheet headers, in column order.
 */
function ownerSheetHeaders(): array
{
    return [
        'Block', 'Section / Erf No', 'Unit No', 'Door No', 'Street No (HOA)', 'PQ',
        'Size Unit (Sq m)', 'Size Garage (Sq m)', 'Size Carport (Sq m)', 'Size Parking (Sq m)',
        'Ratio 1', 'Ratio 2', 'Ratio 3', 'Ratio 4', 'Ratio 5',
        'Owner / Contact Name', 'ID / Passport', 'Email Address', 'Cell Number', 'Landline Number',
        'Contact 2 Name', 'Contact 2 Email Address', 'Contact 2 Cell Number', 'Contact 2 Landline Number',
        'Postal Address', 'Trust Name', 'Trust Reg', 'CC Name', 'CC Reg No.', 'PTY Name', 'PTY Reg No.',
        'Body Corporate Name', 'Body Corporate Reg No.', 'Rental Agent Email', 'Customer Code', 'Customer Name',
    ];
}

/**
 * Write a spreadsheet via a fill callback and wrap it as an uploaded .xlsx file.
 */
function takeOnUploadedFile(callable $fill): UploadedFile
{
    $spreadsheet = new Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();
    $fill($sheet);

    $path = tempnam(sys_get_temp_dir(), 'takeon') . '.xlsx';
    (new XlsxWriter($spreadsheet))->save($path);

    return new UploadedFile(
        $path,
        'file.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );
}

/**
 * Build a WeConnectU owner-sheet upload: headers on row 5, data from row 8.
 * Each $rows entry is keyed by header text.
 */
function ownerSheetFile(array $rows): UploadedFile
{
    $headers = ownerSheetHeaders();

    return takeOnUploadedFile(function ($sheet) use ($headers, $rows): void {
        $sheet->setCellValue('A1', 'Set-up Owners / Customers');
        foreach ($headers as $i => $header) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . '5', $header);
        }
        $rowNum = 8;
        foreach ($rows as $row) {
            foreach ($headers as $i => $header) {
                if (array_key_exists($header, $row)) {
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . $rowNum, (string) $row[$header]);
                }
            }
            $rowNum++;
        }
    });
}

/**
 * Build a WeConnectU budget upload for a year. Each $lines entry is
 * ['label' => '1000/001 - Levies', 'months' => [12 floats], 'per_year' => float].
 */
function budgetFile(int $year, array $lines): UploadedFile
{
    return takeOnUploadedFile(function ($sheet) use ($year, $lines): void {
        $sheet->setCellValue('A1', 'Actual Budget');
        $sheet->setCellValue('B1', "{$year}-01-01 to {$year}-12-31");

        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        foreach ($months as $i => $m) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 2) . '4', $m);
        }
        $sheet->setCellValue('N4', 'Per Year');

        $rowNum = 5;
        foreach ($lines as $line) {
            $sheet->setCellValue('A' . $rowNum, $line['label']);
            foreach (($line['months'] ?? []) as $i => $amount) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 2) . $rowNum, $amount);
            }
            $sheet->setCellValue('N' . $rowNum, $line['per_year'] ?? array_sum($line['months'] ?? []));
            $rowNum++;
        }
    });
}

/**
 * A take-on community owned by the given user's organisation.
 */
function takeOnCommunity(\App\Models\User $user): Community
{
    return Community::factory()->create([
        'organization_id' => $user->organization_id,
        'status'          => 'take_on',
    ]);
}

// =============================================================================
// Owner Sheet — template + parse + import
// =============================================================================

it('blocks unauthenticated requests to the owner-sheet endpoints', function (): void {
    $community = Community::factory()->create(['organization_id' => createOrganization()->id]);

    $this->postJson(route('api.v1.community.owner.sheet.parse', $community))->assertUnauthorized();
    $this->postJson(route('api.v1.community.owner.sheet.import', $community))->assertUnauthorized();
});

it('downloads the owner-sheet template as an xlsx', function (): void {
    $user      = adminUser();
    $community = takeOnCommunity($user);

    $this->actingAs($user, 'api')
        ->get(route('api.v1.community.owner.sheet.template', $community))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('round-trips: the owner-sheet template downloads with the exact 36-column WeConnectU layout and parses clean', function (): void {
    $user      = adminUser();
    $community = takeOnCommunity($user);

    $content = $this->actingAs($user, 'api')->get(route('api.v1.community.owner.sheet.template', $community))->streamedContent();
    $path = tempnam(sys_get_temp_dir(), 'ot') . '.xlsx';
    file_put_contents($path, $content);

    $resp = $this->actingAs($user, 'api')
        ->post(route('api.v1.community.owner.sheet.parse', $community), ['file' => new UploadedFile($path, 'owners.xlsx', null, null, true)])
        ->assertOk()
        ->assertJsonPath('summary.units', 0)
        ->assertJsonPath('summary.error_count', 0);

    expect($resp->json('columns'))->toHaveCount(36);
    expect($resp->json('columns.1'))->toBe('Section / Erf No');
});

it('imports units and owners from a WeConnectU owner sheet, grouping repeated erf rows as extra owners', function (): void {
    Storage::fake('public');
    $user      = adminUser();
    $community = takeOnCommunity($user);

    $file = ownerSheetFile([
        ['Section / Erf No' => 'ERF 1', 'Unit No' => 'A01', 'PQ' => '50', 'Owner / Contact Name' => 'John Smith', 'Email Address' => 'john@example.com', 'Customer Code' => 'JS001'],
        ['Section / Erf No' => 'ERF 1', 'PQ' => '50', 'Owner / Contact Name' => 'Jane Smith', 'Email Address' => 'jane@example.com'],
        ['Section / Erf No' => 'ERF 2', 'Unit No' => 'A02', 'PQ' => '50', 'Owner / Contact Name' => 'Bob Jones', 'Email Address' => 'bob@example.com'],
    ]);

    $this->actingAs($user, 'api')
        ->post(route('api.v1.community.owner.sheet.import', $community), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('imported_units', 2)
        ->assertJsonPath('imported_owners', 3);

    $this->assertDatabaseHas('units', ['community_id' => $community->id, 'unit_number' => 'A01', 'customer_code' => 'JS001']);
    $this->assertDatabaseHas('owners', ['email' => 'john@example.com', 'is_primary' => true]);
    $this->assertDatabaseHas('owners', ['email' => 'jane@example.com', 'is_primary' => false]);

    $unit = Unit::where('community_id', $community->id)->where('unit_number', 'A01')->first();
    expect(Owner::where('unit_id', $unit->id)->count())->toBe(2);
});

it('is idempotent — re-importing the same owner sheet does not duplicate units or owners', function (): void {
    Storage::fake('public');
    $user      = adminUser();
    $community = takeOnCommunity($user);

    $rows = [
        ['Section / Erf No' => 'ERF 1', 'Unit No' => 'A01', 'PQ' => '100', 'Owner / Contact Name' => 'John Smith', 'Email Address' => 'john@example.com'],
    ];

    $this->actingAs($user, 'api')->post(route('api.v1.community.owner.sheet.import', $community), ['file' => ownerSheetFile($rows)])->assertOk();
    $this->actingAs($user, 'api')->post(route('api.v1.community.owner.sheet.import', $community), ['file' => ownerSheetFile($rows)])->assertOk();

    expect(Unit::where('community_id', $community->id)->count())->toBe(1);
    expect(Owner::whereHas('unit', fn ($q) => $q->where('community_id', $community->id))->count())->toBe(1);
});

it('reports an error for an owner sheet row missing a compulsory column', function (): void {
    $user      = adminUser();
    $community = takeOnCommunity($user);

    // Missing PQ (compulsory) on an otherwise-valid unit row.
    $file = ownerSheetFile([
        ['Section / Erf No' => 'ERF 9', 'Unit No' => 'A09', 'Owner / Contact Name' => 'No PQ', 'Email Address' => 'nopq@example.com'],
    ]);

    $this->actingAs($user, 'api')
        ->post(route('api.v1.community.owner.sheet.parse', $community), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.error_count', 1);
});

// =============================================================================
// Budget — template + parse + import
// =============================================================================

it('blocks unauthenticated requests to the budget endpoints', function (): void {
    $community = Community::factory()->create(['organization_id' => createOrganization()->id]);

    $this->postJson(route('api.v1.community.budget.parse', $community))->assertUnauthorized();
    $this->postJson(route('api.v1.community.budget.import', $community))->assertUnauthorized();
});

it('downloads the budget template as an xlsx', function (): void {
    $user      = adminUser();
    $community = takeOnCommunity($user);

    $this->actingAs($user, 'api')
        ->get(route('api.v1.community.budget.template', $community))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('round-trips: the budget template downloads pre-filled with the exact 80-line chart and re-imports', function (): void {
    Storage::fake('public');
    $user      = adminUser();
    $community = takeOnCommunity($user);

    $content = $this->actingAs($user, 'api')->get(route('api.v1.community.budget.template', $community))->streamedContent();
    $path = tempnam(sys_get_temp_dir(), 'bt') . '.xlsx';
    file_put_contents($path, $content);

    // The organisation is pre-seeded with the standard chart of accounts, so the
    // budget import upserts those ledgers rather than creating them all anew.
    $this->actingAs($user, 'api')
        ->post(route('api.v1.community.budget.import', $community), ['file' => new UploadedFile($path, 'budget.xlsx', null, null, true)])
        ->assertOk()
        ->assertJsonPath('imported_lines', 80);

    $this->assertDatabaseHas('ledgers', ['organization_id' => $community->organization_id, 'code' => '1000/001', 'name' => 'Levies']);
    $this->assertDatabaseHas('ledgers', ['organization_id' => $community->organization_id, 'code' => '4000/009', 'name' => 'WCA']);
});

it('imports a budget, auto-creating ledgers and writing monthly figures', function (): void {
    Storage::fake('public');
    $user      = adminUser();
    $community = takeOnCommunity($user);

    $file = budgetFile(2026, [
        ['label' => '1000/001 - Levies', 'months' => array_fill(0, 12, 100), 'per_year' => 1200],
        ['label' => '2000/001 - Bank Charges', 'months' => array_fill(0, 12, 10), 'per_year' => 120],
    ]);

    $this->actingAs($user, 'api')
        ->post(route('api.v1.community.budget.import', $community), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('year', 2026)
        ->assertJsonPath('imported_lines', 2);

    $this->assertDatabaseHas('ledgers', ['code' => '1000/001', 'organization_id' => $community->organization_id]);

    $budget = CommunityBudget::where('community_id', $community->id)->whereHas('ledger', fn ($q) => $q->where('code', '1000/001'))->first();
    expect($budget->jan)->toBe(100.0);
    expect($budget->per_year)->toBe(1200.0);
});

it('is idempotent — re-importing a budget upserts rather than duplicating', function (): void {
    Storage::fake('public');
    $user      = adminUser();
    $community = takeOnCommunity($user);

    $lines = [
        ['label' => '1000/001 - Levies', 'months' => array_fill(0, 12, 100), 'per_year' => 1200],
    ];

    $this->actingAs($user, 'api')->post(route('api.v1.community.budget.import', $community), ['file' => budgetFile(2026, $lines)])->assertOk();
    $this->actingAs($user, 'api')->post(route('api.v1.community.budget.import', $community), ['file' => budgetFile(2026, $lines)])->assertOk();

    $this->assertDatabaseCount('community_budgets', 1);
});

// =============================================================================
// Submit Take-on
// =============================================================================

it('blocks unauthenticated requests to submit take-on', function (): void {
    $community = Community::factory()->create(['organization_id' => createOrganization()->id]);

    $this->postJson(route('api.v1.community.submit.takeon', $community))->assertUnauthorized();
});

it('submits take-on, transitioning the community from take_on to active', function (): void {
    $user      = adminUser();
    $community = takeOnCommunity($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.community.submit.takeon', $community))
        ->assertOk()
        ->assertJsonPath('status', 'active');

    $this->assertDatabaseHas('communities', ['id' => $community->id, 'status' => 'active']);
});

// =============================================================================
// Take-on file status (upload / not-applicable / download)
// =============================================================================

it('blocks unauthenticated requests to the take-on status endpoints', function (): void {
    $community = Community::factory()->create(['organization_id' => createOrganization()->id]);

    $this->getJson(route('api.v1.community.takeon.status', $community))->assertUnauthorized();
    $this->postJson(route('api.v1.community.takeon.not.applicable', ['community' => $community, 'key' => 'budget']))->assertUnauthorized();
});

it('reports a done step with one entry after an owner sheet import', function (): void {
    Storage::fake('public');
    $user      = adminUser();
    $community = takeOnCommunity($user);

    $file = ownerSheetFile([
        ['Section / Erf No' => 'ERF 1', 'Unit No' => 'A01', 'PQ' => '100', 'Owner / Contact Name' => 'John', 'Email Address' => 'john@example.com'],
    ]);
    $this->actingAs($user, 'api')->post(route('api.v1.community.owner.sheet.import', $community), ['file' => $file])->assertOk();

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.community.takeon.status', $community))
        ->assertOk()
        ->assertJsonPath('items.owner_sheet.done', true)
        ->assertJsonPath('items.owner_sheet.entries.0.status', 'uploaded')
        ->assertJsonPath('items.budget.done', false);

    expect($resp->json('items.owner_sheet.entries'))->toHaveCount(1);
    $this->assertDatabaseHas('community_takeon_items', ['community_id' => $community->id, 'key' => 'owner_sheet', 'status' => 'uploaded']);
});

it('appends every upload as a history entry (versioned, not replaced)', function (): void {
    Storage::fake('public');
    $user      = adminUser();
    $community = takeOnCommunity($user);

    $rows = [['label' => '1000/001 - Levies', 'months' => array_fill(0, 12, 100), 'per_year' => 1200]];
    $this->actingAs($user, 'api')->post(route('api.v1.community.budget.import', $community), ['file' => budgetFile(2026, $rows)])->assertOk();
    $this->actingAs($user, 'api')->post(route('api.v1.community.budget.import', $community), ['file' => budgetFile(2026, $rows)])->assertOk();

    $resp = $this->actingAs($user, 'api')->getJson(route('api.v1.community.takeon.status', $community))->assertOk();
    expect($resp->json('items.budget.entries'))->toHaveCount(2);
    expect(\App\Models\CommunityTakeonItem::where('community_id', $community->id)->where('key', 'budget')->count())->toBe(2);
});

it('appends a not-applicable entry to the history', function (): void {
    $user      = adminUser();
    $community = takeOnCommunity($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.community.takeon.not.applicable', ['community' => $community, 'key' => 'budget']))
        ->assertOk()
        ->assertJsonPath('status', 'not_applicable');

    $resp = $this->actingAs($user, 'api')->getJson(route('api.v1.community.takeon.status', $community))->assertOk();
    expect($resp->json('items.budget.entries.0.status'))->toBe('not_applicable');
    $this->assertDatabaseHas('community_takeon_items', ['community_id' => $community->id, 'key' => 'budget', 'status' => 'not_applicable']);
});

it('rejects an unknown take-on step key', function (): void {
    $user      = adminUser();
    $community = takeOnCommunity($user);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.community.takeon.not.applicable', ['community' => $community, 'key' => 'nonsense']))
        ->assertStatus(500);
});

it('downloads a specific uploaded take-on history entry by id', function (): void {
    Storage::fake('public');
    $user      = adminUser();
    $community = takeOnCommunity($user);

    $file = budgetFile(2026, [['label' => '1000/001 - Levies', 'months' => array_fill(0, 12, 100), 'per_year' => 1200]]);
    $this->actingAs($user, 'api')->post(route('api.v1.community.budget.import', $community), ['file' => $file])->assertOk();

    $entryId = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.community.takeon.status', $community))
        ->json('items.budget.entries.0.id');

    $this->actingAs($user, 'api')
        ->get(route('api.v1.community.takeon.file', ['community' => $community, 'item' => $entryId]))
        ->assertOk();
});
