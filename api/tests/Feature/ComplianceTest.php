<?php

use App\Models\ComplianceChecklist;
use App\Models\ComplianceChecklistItem;
use App\Models\ComplianceItemAttachment;
use App\Models\ComplianceTemplate;
use App\Models\ComplianceTemplateItem;
use App\Models\Estate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function makeChecklist(array $overrides = []): array
{
    $user    = adminUser();
    $estate  = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $checklist = ComplianceChecklist::factory()->create(array_merge([
        'organization_id' => $user->organization_id,
        'estate_id'       => $estate->id,
        'created_by_id'   => $user->id,
    ], $overrides));

    return compact('user', 'estate', 'checklist');
}

function makeChecklistWithItem(array $itemOverrides = []): array
{
    ['user' => $user, 'estate' => $estate, 'checklist' => $checklist] = makeChecklist();

    $item = ComplianceChecklistItem::factory()->create(array_merge([
        'compliance_checklist_id' => $checklist->id,
        'organization_id'         => $user->organization_id,
    ], $itemOverrides));

    return compact('user', 'estate', 'checklist', 'item');
}

function makeChecklistWithAttachment(): array
{
    ['user' => $user, 'estate' => $estate, 'checklist' => $checklist, 'item' => $item] = makeChecklistWithItem();

    $attachment = ComplianceItemAttachment::factory()->create([
        'compliance_checklist_item_id' => $item->id,
        'uploaded_by_id'               => $user->id,
        'file_path'                    => 'compliance-evidence/' . $checklist->id . '/test.pdf',
        'file_name'                    => 'test.pdf',
    ]);

    return compact('user', 'estate', 'checklist', 'item', 'attachment');
}

function makeTemplate(array $overrides = []): array
{
    $user     = adminUser();
    $template = ComplianceTemplate::factory()->create(array_merge([
        'organization_id' => $user->organization_id,
    ], $overrides));

    return compact('user', 'template');
}

// ──────────────────────────────────────────────────────────────────────────────
// showChecklists — GET /compliance/checklists
// ──────────────────────────────────────────────────────────────────────────────

it('show checklists returns 401 without auth', function () {
    $this->getJson(route('api.v1.show.compliance-checklists'))
        ->assertUnauthorized();
});

it('show checklists returns paginated data', function () {
    ['user' => $user] = makeChecklist();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.compliance-checklists'))
        ->assertOk()
        ->assertJsonStructure(['data', 'meta', 'links']);
});

it('show checklists returns tenant checklists', function () {
    ['user' => $user, 'checklist' => $checklist] = makeChecklist();

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.compliance-checklists'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($checklist->id);
});

it('show checklists does not return other org checklists', function () {
    ['checklist' => $otherChecklist] = makeChecklist();
    $myUser = adminUser();

    $ids = $this->actingAs($myUser, 'api')
        ->getJson(route('api.v1.show.compliance-checklists'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->not->toContain($otherChecklist->id);
});

it('show checklists filters by estate_id', function () {
    ['user' => $user, 'checklist' => $checklist] = makeChecklist();
    $otherEstate = Estate::factory()->create(['organization_id' => $user->organization_id]);
    ComplianceChecklist::factory()->create([
        'organization_id' => $user->organization_id,
        'estate_id'       => $otherEstate->id,
    ]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.compliance-checklists') . '?estate_id=' . $checklist->estate_id)
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($checklist->id);
    expect($ids)->not->toContain(
        ComplianceChecklist::where('estate_id', $otherEstate->id)->first()->id
    );
});

it('show checklists filters by financial_year_label', function () {
    $user    = adminUser();
    $estate1 = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $estate2 = Estate::factory()->create(['organization_id' => $user->organization_id]);

    $fy2025 = ComplianceChecklist::factory()->create([
        'organization_id'      => $user->organization_id,
        'estate_id'            => $estate1->id,
        'financial_year_label' => '2025/2026',
        'financial_year_start' => '2025-03-01',
        'financial_year_end'   => '2026-02-28',
    ]);
    ComplianceChecklist::factory()->create([
        'organization_id'      => $user->organization_id,
        'estate_id'            => $estate2->id,
        'financial_year_label' => '2024/2025',
        'financial_year_start' => '2024-03-01',
        'financial_year_end'   => '2025-02-28',
    ]);

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.compliance-checklists') . '?financial_year_label=2025%2F2026')
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($fy2025->id);
    expect(count($ids))->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// showChecklist — GET /compliance/checklists/{complianceChecklist}
// ──────────────────────────────────────────────────────────────────────────────

it('show checklist returns 401 without auth', function () {
    ['checklist' => $checklist] = makeChecklist();

    $this->getJson(route('api.v1.show.compliance-checklist', $checklist))
        ->assertUnauthorized();
});

it('show checklist returns the checklist', function () {
    ['user' => $user, 'checklist' => $checklist] = makeChecklist();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.compliance-checklist', $checklist))
        ->assertOk()
        ->assertJsonFragment(['id' => $checklist->id]);
});

it('show checklist response has expected fields', function () {
    ['user' => $user, 'checklist' => $checklist] = makeChecklist();

    $data = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.compliance-checklist', $checklist))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveKeys([
        'id', 'estate_id', 'financial_year_label', 'financial_year_start',
        'financial_year_end', 'items_count', 'completed_items_count',
        'overdue_items_count', 'progress_percentage', 'items',
    ]);
});

it('show checklist has progress_percentage of zero when no items', function () {
    ['user' => $user, 'checklist' => $checklist] = makeChecklist();

    $progress = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.compliance-checklist', $checklist))
        ->assertOk()
        ->json('data.progress_percentage');

    expect($progress)->toBe(0);
});

it('show checklist progress_percentage is 100 when all items completed', function () {
    ['user' => $user, 'checklist' => $checklist, 'item' => $item] = makeChecklistWithItem(
        ['status' => 'completed']
    );

    $progress = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.compliance-checklist', $checklist))
        ->assertOk()
        ->json('data.progress_percentage');

    expect($progress)->toBe(100);
});

it('show checklist returns 404 for a non-existent checklist', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.compliance-checklist', Str::uuid()))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// createChecklist — POST /compliance/checklists
// ──────────────────────────────────────────────────────────────────────────────

it('create checklist returns 401 without auth', function () {
    $this->postJson(route('api.v1.create.compliance-checklist'), [])
        ->assertUnauthorized();
});

it('create checklist persists and returns the checklist', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    $response = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.compliance-checklist'), [
            'estate_id'            => $estate->id,
            'financial_year_label' => '2025/2026',
            'financial_year_start' => '2025-03-01',
            'financial_year_end'   => '2026-02-28',
        ])
        ->assertOk();

    expect(ComplianceChecklist::where('estate_id', $estate->id)->exists())->toBeTrue();
    expect($response->json('data.financial_year_label'))->toBe('2025/2026');
});

it('create checklist requires estate_id', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.compliance-checklist'), [
            'financial_year_label' => '2025/2026',
            'financial_year_start' => '2025-03-01',
            'financial_year_end'   => '2026-02-28',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('estate_id');
});

it('create checklist requires financial_year_start before financial_year_end', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.compliance-checklist'), [
            'estate_id'            => $estate->id,
            'financial_year_label' => '2025/2026',
            'financial_year_start' => '2026-03-01',
            'financial_year_end'   => '2025-02-28',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('financial_year_end');
});

it('create checklist returns 409 when estate and financial year already exist', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    $payload = [
        'estate_id'            => $estate->id,
        'financial_year_label' => '2025/2026',
        'financial_year_start' => '2025-03-01',
        'financial_year_end'   => '2026-02-28',
    ];

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.compliance-checklist'), $payload)
        ->assertOk();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.compliance-checklist'), $payload)
        ->assertStatus(409);
});

it('create checklist with template_id generates items from template', function () {
    $user     = adminUser();
    $estate   = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $template = ComplianceTemplate::factory()->create(['organization_id' => $user->organization_id]);
    ComplianceTemplateItem::create([
        'name'                   => 'Annual Audit',
        'category'               => 'financial',
        'priority'               => 'high',
        'sort_order'             => 0,
        'is_recurring'           => true,
        'compliance_template_id' => $template->id,
    ]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.compliance-checklist'), [
            'estate_id'            => $estate->id,
            'financial_year_label' => '2025/2026',
            'financial_year_start' => '2025-03-01',
            'financial_year_end'   => '2026-02-28',
            'template_id'          => $template->id,
        ])
        ->assertOk();

    $checklist = ComplianceChecklist::where('estate_id', $estate->id)->first();
    expect($checklist->items()->count())->toBe(1);
    expect($checklist->items()->first()->name)->toBe('Annual Audit');
});

it('create checklist stores notes', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.compliance-checklist'), [
            'estate_id'            => $estate->id,
            'financial_year_label' => '2025/2026',
            'financial_year_start' => '2025-03-01',
            'financial_year_end'   => '2026-02-28',
            'notes'                => 'Reviewed by legal team.',
        ])
        ->assertOk();

    expect(ComplianceChecklist::where('estate_id', $estate->id)->first()->notes)
        ->toBe('Reviewed by legal team.');
});

it('create checklist assigns organization_id from authenticated user', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.compliance-checklist'), [
            'estate_id'            => $estate->id,
            'financial_year_label' => '2025/2026',
            'financial_year_start' => '2025-03-01',
            'financial_year_end'   => '2026-02-28',
        ])
        ->assertOk();

    $checklist = ComplianceChecklist::where('estate_id', $estate->id)->first();
    expect($checklist->organization_id)->toBe($user->organization_id);
    expect($checklist->created_by_id)->toBe($user->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// updateChecklist — PUT /compliance/checklists/{complianceChecklist}
// ──────────────────────────────────────────────────────────────────────────────

it('update checklist returns 401 without auth', function () {
    ['checklist' => $checklist] = makeChecklist();

    $this->putJson(route('api.v1.update.compliance-checklist', $checklist), [])
        ->assertUnauthorized();
});

it('update checklist updates financial_year_label', function () {
    ['user' => $user, 'checklist' => $checklist] = makeChecklist();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.compliance-checklist', $checklist), [
            'financial_year_label' => '2026/2027',
        ])
        ->assertOk();

    expect($checklist->fresh()->financial_year_label)->toBe('2026/2027');
});

it('update checklist updates notes', function () {
    ['user' => $user, 'checklist' => $checklist] = makeChecklist();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.compliance-checklist', $checklist), [
            'notes' => 'Updated notes.',
        ])
        ->assertOk();

    expect($checklist->fresh()->notes)->toBe('Updated notes.');
});

it('update checklist returns success message', function () {
    ['user' => $user, 'checklist' => $checklist] = makeChecklist();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.compliance-checklist', $checklist), [
            'notes' => 'x',
        ])
        ->assertOk()
        ->assertJsonFragment(['message' => 'Updated successfully']);
});

it('update checklist returns 404 for a non-existent checklist', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.compliance-checklist', Str::uuid()), ['notes' => 'x'])
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// deleteChecklist — DELETE /compliance/checklists/{complianceChecklist}
// ──────────────────────────────────────────────────────────────────────────────

it('delete checklist returns 401 without auth', function () {
    ['checklist' => $checklist] = makeChecklist();

    $this->deleteJson(route('api.v1.delete.compliance-checklist', $checklist))
        ->assertUnauthorized();
});

it('delete checklist removes the checklist from the database', function () {
    ['user' => $user, 'checklist' => $checklist] = makeChecklist();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.compliance-checklist', $checklist))
        ->assertOk();

    expect(ComplianceChecklist::find($checklist->id))->toBeNull();
});

it('delete checklist returns a success message', function () {
    ['user' => $user, 'checklist' => $checklist] = makeChecklist();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.compliance-checklist', $checklist))
        ->assertOk()
        ->assertJsonFragment(['message' => 'Compliance checklist deleted successfully.']);
});

it('delete checklist returns 404 for a non-existent checklist', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.compliance-checklist', Str::uuid()))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// createChecklistItem — POST /compliance/checklists/{complianceChecklist}/items
// ──────────────────────────────────────────────────────────────────────────────

it('create checklist item returns 401 without auth', function () {
    ['checklist' => $checklist] = makeChecklist();

    $this->postJson(route('api.v1.create.compliance-checklist-item', $checklist), [])
        ->assertUnauthorized();
});

it('create checklist item persists and returns the item', function () {
    ['user' => $user, 'checklist' => $checklist] = makeChecklist();

    $response = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.compliance-checklist-item', $checklist), [
            'name'     => 'Fire Safety Inspection',
            'category' => 'safety',
            'priority' => 'high',
        ])
        ->assertOk();

    expect($checklist->items()->count())->toBe(1);
    expect($response->json('data.name'))->toBe('Fire Safety Inspection');
});

it('create checklist item requires name, category, and priority', function () {
    ['user' => $user, 'checklist' => $checklist] = makeChecklist();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.compliance-checklist-item', $checklist), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'category', 'priority']);
});

it('create checklist item rejects invalid priority', function () {
    ['user' => $user, 'checklist' => $checklist] = makeChecklist();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.compliance-checklist-item', $checklist), [
            'name'     => 'Item',
            'category' => 'legal',
            'priority' => 'ultra',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('priority');
});

it('create checklist item defaults is_recurring to true', function () {
    ['user' => $user, 'checklist' => $checklist] = makeChecklist();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.compliance-checklist-item', $checklist), [
            'name'     => 'Item',
            'category' => 'legal',
            'priority' => 'low',
        ])
        ->assertOk();

    expect($checklist->items()->first()->is_recurring)->toBeTrue();
});

it('create checklist item auto-assigns sort_order within category', function () {
    ['user' => $user, 'checklist' => $checklist] = makeChecklist();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.compliance-checklist-item', $checklist), [
            'name'     => 'Item A',
            'category' => 'legal',
            'priority' => 'medium',
        ])
        ->assertOk();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.compliance-checklist-item', $checklist), [
            'name'     => 'Item B',
            'category' => 'legal',
            'priority' => 'medium',
        ])
        ->assertOk();

    $orders = $checklist->items()->orderBy('sort_order')->pluck('sort_order')->toArray();
    expect($orders[0])->toBeLessThan($orders[1]);
});

it('create checklist item returns success message', function () {
    ['user' => $user, 'checklist' => $checklist] = makeChecklist();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.compliance-checklist-item', $checklist), [
            'name'     => 'Item',
            'category' => 'legal',
            'priority' => 'low',
        ])
        ->assertOk()
        ->assertJsonFragment(['message' => 'Compliance item added successfully.']);
});

it('create checklist item stores description', function () {
    ['user' => $user, 'checklist' => $checklist] = makeChecklist();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.compliance-checklist-item', $checklist), [
            'name'        => 'Item',
            'category'    => 'legal',
            'priority'    => 'low',
            'description' => 'Detailed instructions here.',
        ])
        ->assertOk();

    expect($checklist->items()->first()->description)->toBe('Detailed instructions here.');
});

// ──────────────────────────────────────────────────────────────────────────────
// updateChecklistItem — PUT /compliance/checklists/{checklist}/items/{item}
// ──────────────────────────────────────────────────────────────────────────────

it('update checklist item returns 401 without auth', function () {
    ['checklist' => $checklist, 'item' => $item] = makeChecklistWithItem();

    $this->putJson(route('api.v1.update.compliance-checklist-item', [$checklist, $item]), [])
        ->assertUnauthorized();
});

it('update checklist item updates the name', function () {
    ['user' => $user, 'checklist' => $checklist, 'item' => $item] = makeChecklistWithItem();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.compliance-checklist-item', [$checklist, $item]), [
            'name' => 'Renamed Item',
        ])
        ->assertOk();

    expect($item->fresh()->name)->toBe('Renamed Item');
});

it('update checklist item status to completed sets completed_at and completed_by_id', function () {
    ['user' => $user, 'checklist' => $checklist, 'item' => $item] = makeChecklistWithItem(
        ['status' => 'pending']
    );

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.compliance-checklist-item', [$checklist, $item]), [
            'status' => 'completed',
        ])
        ->assertOk();

    $fresh = $item->fresh();
    expect($fresh->completed_at)->not->toBeNull();
    expect($fresh->completed_by_id)->toBe($user->id);
});

it('update checklist item status away from completed clears completed_at', function () {
    ['user' => $user, 'checklist' => $checklist, 'item' => $item] = makeChecklistWithItem(
        ['status' => 'completed', 'completed_at' => now(), 'completed_by_id' => 1]
    );

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.compliance-checklist-item', [$checklist, $item]), [
            'status' => 'pending',
        ])
        ->assertOk();

    $fresh = $item->fresh();
    expect($fresh->completed_at)->toBeNull();
    expect($fresh->completed_by_id)->toBeNull();
});

it('update checklist item rejects invalid status', function () {
    ['user' => $user, 'checklist' => $checklist, 'item' => $item] = makeChecklistWithItem();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.compliance-checklist-item', [$checklist, $item]), [
            'status' => 'done',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

it('update checklist item returns 404 for a non-existent item', function () {
    ['user' => $user, 'checklist' => $checklist] = makeChecklist();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.compliance-checklist-item', [$checklist, Str::uuid()]), [
            'name' => 'x',
        ])
        ->assertNotFound();
});

it('update checklist item returns success message', function () {
    ['user' => $user, 'checklist' => $checklist, 'item' => $item] = makeChecklistWithItem();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.compliance-checklist-item', [$checklist, $item]), [
            'name' => 'Updated',
        ])
        ->assertOk()
        ->assertJsonFragment(['message' => 'Compliance item updated successfully.']);
});

// ──────────────────────────────────────────────────────────────────────────────
// deleteChecklistItem — DELETE /compliance/checklists/{checklist}/items/{item}
// ──────────────────────────────────────────────────────────────────────────────

it('delete checklist item returns 401 without auth', function () {
    ['checklist' => $checklist, 'item' => $item] = makeChecklistWithItem();

    $this->deleteJson(route('api.v1.delete.compliance-checklist-item', [$checklist, $item]))
        ->assertUnauthorized();
});

it('delete checklist item removes the item from the database', function () {
    ['user' => $user, 'checklist' => $checklist, 'item' => $item] = makeChecklistWithItem();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.compliance-checklist-item', [$checklist, $item]))
        ->assertOk();

    expect(ComplianceChecklistItem::find($item->id))->toBeNull();
});

it('delete checklist item returns a success message', function () {
    ['user' => $user, 'checklist' => $checklist, 'item' => $item] = makeChecklistWithItem();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.compliance-checklist-item', [$checklist, $item]))
        ->assertOk()
        ->assertJsonFragment(['message' => 'Compliance item deleted successfully.']);
});

it('delete checklist item returns 404 for a non-existent item', function () {
    ['user' => $user, 'checklist' => $checklist] = makeChecklist();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.compliance-checklist-item', [$checklist, Str::uuid()]))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// uploadAttachments — POST /compliance/checklists/{checklist}/items/{item}/attachments
// ──────────────────────────────────────────────────────────────────────────────

it('upload attachments returns 401 without auth', function () {
    ['checklist' => $checklist, 'item' => $item] = makeChecklistWithItem();

    $this->postJson(route('api.v1.upload.compliance-attachments', [$checklist, $item]))
        ->assertUnauthorized();
});

it('upload attachments stores the file and creates an attachment record', function () {
    Storage::fake('local');
    ['user' => $user, 'checklist' => $checklist, 'item' => $item] = makeChecklistWithItem();

    $file = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');

    $response = $this->actingAs($user, 'api')
        ->post(route('api.v1.upload.compliance-attachments', [$checklist, $item]), [
            'attachments' => [$file],
        ])
        ->assertOk();

    expect(ComplianceItemAttachment::where('compliance_checklist_item_id', $item->id)->count())->toBe(1);
    expect($response->json('message'))->toBe('Attachment uploaded successfully.');
});

it('upload attachments accepts the legacy evidence key', function () {
    Storage::fake('local');
    ['user' => $user, 'checklist' => $checklist, 'item' => $item] = makeChecklistWithItem();

    $file = UploadedFile::fake()->create('evidence.pdf', 100, 'application/pdf');

    $this->actingAs($user, 'api')
        ->post(route('api.v1.upload.compliance-attachments', [$checklist, $item]), [
            'evidence' => $file,
        ])
        ->assertOk();

    expect(ComplianceItemAttachment::where('compliance_checklist_item_id', $item->id)->count())->toBe(1);
});

it('upload attachments returns 422 when no files are provided', function () {
    Storage::fake('local');
    ['user' => $user, 'checklist' => $checklist, 'item' => $item] = makeChecklistWithItem();

    $this->actingAs($user, 'api')
        ->post(route('api.v1.upload.compliance-attachments', [$checklist, $item]), [])
        ->assertStatus(422);
});

it('upload attachments message counts multiple files', function () {
    Storage::fake('local');
    ['user' => $user, 'checklist' => $checklist, 'item' => $item] = makeChecklistWithItem();

    $files = [
        UploadedFile::fake()->create('a.pdf', 50, 'application/pdf'),
        UploadedFile::fake()->create('b.pdf', 50, 'application/pdf'),
    ];

    $response = $this->actingAs($user, 'api')
        ->post(route('api.v1.upload.compliance-attachments', [$checklist, $item]), [
            'attachments' => $files,
        ])
        ->assertOk();

    expect($response->json('message'))->toBe('2 attachments uploaded successfully.');
    expect(ComplianceItemAttachment::where('compliance_checklist_item_id', $item->id)->count())->toBe(2);
});

it('upload attachments stores file_name and mime_type on the attachment record', function () {
    Storage::fake('local');
    ['user' => $user, 'checklist' => $checklist, 'item' => $item] = makeChecklistWithItem();

    $file = UploadedFile::fake()->create('my-report.pdf', 100, 'application/pdf');

    $this->actingAs($user, 'api')
        ->post(route('api.v1.upload.compliance-attachments', [$checklist, $item]), [
            'attachments' => [$file],
        ])
        ->assertOk();

    $attachment = ComplianceItemAttachment::where('compliance_checklist_item_id', $item->id)->first();
    expect($attachment->file_name)->toBe('my-report.pdf');
    expect($attachment->uploaded_by_id)->toBe($user->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// downloadAttachment — GET /compliance/checklists/{cl}/items/{item}/attachments/{att}/download
// ──────────────────────────────────────────────────────────────────────────────

it('download attachment returns 401 without auth', function () {
    ['checklist' => $checklist, 'item' => $item, 'attachment' => $attachment] = makeChecklistWithAttachment();

    $this->getJson(route('api.v1.download.compliance-attachment', [$checklist, $item, $attachment]))
        ->assertUnauthorized();
});

it('download attachment streams the file', function () {
    Storage::fake('local');
    ['user' => $user, 'checklist' => $checklist, 'item' => $item] = makeChecklistWithItem();

    $file       = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');
    $path       = $file->store('compliance-evidence/' . $checklist->id, 'local');
    $attachment = ComplianceItemAttachment::factory()->create([
        'compliance_checklist_item_id' => $item->id,
        'uploaded_by_id'               => $user->id,
        'file_path'                    => $path,
        'file_name'                    => 'report.pdf',
    ]);

    $response = $this->actingAs($user, 'api')
        ->get(route('api.v1.download.compliance-attachment', [$checklist, $item, $attachment]))
        ->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('report.pdf');
});

it('download attachment returns 404 when file does not exist on disk', function () {
    Storage::fake('local');
    ['user' => $user, 'checklist' => $checklist, 'item' => $item] = makeChecklistWithItem();

    $attachment = ComplianceItemAttachment::factory()->create([
        'compliance_checklist_item_id' => $item->id,
        'uploaded_by_id'               => $user->id,
        'file_path'                    => 'compliance-evidence/nonexistent/missing.pdf',
        'file_name'                    => 'missing.pdf',
    ]);

    $this->actingAs($user, 'api')
        ->get(route('api.v1.download.compliance-attachment', [$checklist, $item, $attachment]))
        ->assertNotFound();
});

it('download attachment returns 404 for a non-existent attachment', function () {
    ['user' => $user, 'checklist' => $checklist, 'item' => $item] = makeChecklistWithItem();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.download.compliance-attachment', [$checklist, $item, Str::uuid()]))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// deleteAttachment — DELETE /compliance/checklists/{cl}/items/{item}/attachments/{att}
// ──────────────────────────────────────────────────────────────────────────────

it('delete attachment returns 401 without auth', function () {
    ['checklist' => $checklist, 'item' => $item, 'attachment' => $attachment] = makeChecklistWithAttachment();

    $this->deleteJson(route('api.v1.delete.compliance-attachment', [$checklist, $item, $attachment]))
        ->assertUnauthorized();
});

it('delete attachment removes the attachment record', function () {
    Storage::fake('local');
    ['user' => $user, 'checklist' => $checklist, 'item' => $item] = makeChecklistWithItem();

    $file = UploadedFile::fake()->create('doc.pdf', 50, 'application/pdf');
    $path = $file->store('compliance-evidence/' . $checklist->id, 'local');

    $attachment = ComplianceItemAttachment::factory()->create([
        'compliance_checklist_item_id' => $item->id,
        'uploaded_by_id'               => $user->id,
        'file_path'                    => $path,
        'file_name'                    => 'doc.pdf',
    ]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.compliance-attachment', [$checklist, $item, $attachment]))
        ->assertOk();

    expect(ComplianceItemAttachment::find($attachment->id))->toBeNull();
    Storage::disk('local')->assertMissing($path);
});

it('delete attachment returns a success message', function () {
    Storage::fake('local');
    ['user' => $user, 'checklist' => $checklist, 'item' => $item] = makeChecklistWithItem();

    $attachment = ComplianceItemAttachment::factory()->create([
        'compliance_checklist_item_id' => $item->id,
        'uploaded_by_id'               => $user->id,
        'file_path'                    => 'compliance-evidence/' . $checklist->id . '/x.pdf',
        'file_name'                    => 'x.pdf',
    ]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.compliance-attachment', [$checklist, $item, $attachment]))
        ->assertOk()
        ->assertJsonFragment(['message' => 'Attachment removed successfully.']);
});

it('delete attachment returns 404 for a non-existent attachment', function () {
    ['user' => $user, 'checklist' => $checklist, 'item' => $item] = makeChecklistWithItem();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.compliance-attachment', [$checklist, $item, Str::uuid()]))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// showTemplates — GET /compliance/templates
// ──────────────────────────────────────────────────────────────────────────────

it('show templates returns 401 without auth', function () {
    $this->getJson(route('api.v1.show.compliance-templates'))
        ->assertUnauthorized();
});

it('show templates returns tenant templates', function () {
    ['user' => $user, 'template' => $template] = makeTemplate();

    $ids = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.compliance-templates'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toContain($template->id);
});

it('show templates does not return other org templates', function () {
    ['template' => $otherTemplate] = makeTemplate();
    $myUser = adminUser();

    $ids = $this->actingAs($myUser, 'api')
        ->getJson(route('api.v1.show.compliance-templates'))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->not->toContain($otherTemplate->id);
});

it('show templates orders defaults first', function () {
    $user = adminUser();
    ComplianceTemplate::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Z Template', 'is_default' => false]);
    ComplianceTemplate::factory()->create(['organization_id' => $user->organization_id, 'name' => 'A Template', 'is_default' => true]);

    $names = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.compliance-templates'))
        ->assertOk()
        ->json('data.*.name');

    expect($names[0])->toBe('A Template');
});

// ──────────────────────────────────────────────────────────────────────────────
// showTemplate — GET /compliance/templates/{complianceTemplate}
// ──────────────────────────────────────────────────────────────────────────────

it('show template returns 401 without auth', function () {
    ['template' => $template] = makeTemplate();

    $this->getJson(route('api.v1.show.compliance-template', $template))
        ->assertUnauthorized();
});

it('show template returns the template with items', function () {
    ['user' => $user, 'template' => $template] = makeTemplate();
    ComplianceTemplateItem::create([
        'name'                   => 'Annual Report',
        'category'               => 'financial',
        'priority'               => 'high',
        'sort_order'             => 0,
        'is_recurring'           => true,
        'compliance_template_id' => $template->id,
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.compliance-template', $template))
        ->assertOk();

    expect($response->json('data.id'))->toBe($template->id);
    expect($response->json('data.items'))->toHaveCount(1);
    expect($response->json('data.items.0.name'))->toBe('Annual Report');
});

it('show template returns 404 for a non-existent template', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.compliance-template', Str::uuid()))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// createTemplate — POST /compliance/templates
// ──────────────────────────────────────────────────────────────────────────────

it('create template returns 401 without auth', function () {
    $this->postJson(route('api.v1.create.compliance-template'), [])
        ->assertUnauthorized();
});

it('create template persists and returns the template', function () {
    $user = adminUser();

    $response = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.compliance-template'), [
            'name' => 'FICA Compliance Template',
        ])
        ->assertOk();

    expect(ComplianceTemplate::where('organization_id', $user->organization_id)->exists())->toBeTrue();
    expect($response->json('data.name'))->toBe('FICA Compliance Template');
    expect($response->json('message'))->toBe('Compliance template created successfully.');
});

it('create template requires name', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.compliance-template'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

it('create template creates items when provided', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.compliance-template'), [
            'name'  => 'Full Template',
            'items' => [
                ['name' => 'Fire Inspection', 'category' => 'safety', 'priority' => 'high'],
                ['name' => 'Tax Return',      'category' => 'financial', 'priority' => 'critical'],
            ],
        ])
        ->assertOk();

    $template = ComplianceTemplate::where('organization_id', $user->organization_id)->first();
    expect($template->items()->count())->toBe(2);
});

it('create template items validation requires name, category, priority when items present', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.compliance-template'), [
            'name'  => 'Template',
            'items' => [
                ['description' => 'Missing required fields'],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.name', 'items.0.category', 'items.0.priority']);
});

it('create template stores country and is_default', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.compliance-template'), [
            'name'       => 'ZA Template',
            'country'    => 'ZA',
            'is_default' => true,
        ])
        ->assertOk();

    $template = ComplianceTemplate::where('organization_id', $user->organization_id)->first();
    expect($template->country)->toBe('ZA');
    expect($template->is_default)->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────────────────
// updateTemplate — PUT /compliance/templates/{complianceTemplate}
// ──────────────────────────────────────────────────────────────────────────────

it('update template returns 401 without auth', function () {
    ['template' => $template] = makeTemplate();

    $this->putJson(route('api.v1.update.compliance-template', $template), [])
        ->assertUnauthorized();
});

it('update template updates the name', function () {
    ['user' => $user, 'template' => $template] = makeTemplate();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.compliance-template', $template), [
            'name' => 'Renamed Template',
        ])
        ->assertOk();

    expect($template->fresh()->name)->toBe('Renamed Template');
});

it('update template replaces items when items array is provided', function () {
    ['user' => $user, 'template' => $template] = makeTemplate();

    ComplianceTemplateItem::create([
        'name'                   => 'Old Item',
        'category'               => 'legal',
        'priority'               => 'low',
        'sort_order'             => 0,
        'is_recurring'           => true,
        'compliance_template_id' => $template->id,
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.compliance-template', $template), [
            'items' => [
                ['name' => 'New Item A', 'category' => 'safety',    'priority' => 'high'],
                ['name' => 'New Item B', 'category' => 'financial',  'priority' => 'medium'],
            ],
        ])
        ->assertOk();

    $items = $template->fresh()->items()->get();
    expect($items->count())->toBe(2);
    expect($items->pluck('name')->toArray())->not->toContain('Old Item');
});

it('update template returns success message', function () {
    ['user' => $user, 'template' => $template] = makeTemplate();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.compliance-template', $template), [
            'name' => 'Updated',
        ])
        ->assertOk()
        ->assertJsonFragment(['message' => 'Compliance template updated successfully.']);
});

it('update template returns 404 for a non-existent template', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.compliance-template', Str::uuid()), ['name' => 'x'])
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// deleteTemplate — DELETE /compliance/templates/{complianceTemplate}
// ──────────────────────────────────────────────────────────────────────────────

it('delete template returns 401 without auth', function () {
    ['template' => $template] = makeTemplate();

    $this->deleteJson(route('api.v1.delete.compliance-template', $template))
        ->assertUnauthorized();
});

it('delete template removes the template from the database', function () {
    ['user' => $user, 'template' => $template] = makeTemplate();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.compliance-template', $template))
        ->assertOk();

    expect(ComplianceTemplate::find($template->id))->toBeNull();
});

it('delete template returns a success message', function () {
    ['user' => $user, 'template' => $template] = makeTemplate();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.compliance-template', $template))
        ->assertOk()
        ->assertJsonFragment(['message' => 'Compliance template deleted successfully.']);
});

it('delete template returns 404 for a non-existent template', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.compliance-template', Str::uuid()))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// portfolioSummary — GET /compliance/portfolio-summary
// ──────────────────────────────────────────────────────────────────────────────

it('portfolio summary returns 401 without auth', function () {
    $this->getJson(route('api.v1.compliance.portfolio-summary'))
        ->assertUnauthorized();
});

it('portfolio summary returns summary, estates, and financial_years keys', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.compliance.portfolio-summary'))
        ->assertOk()
        ->assertJsonStructure(['summary', 'estates', 'financial_years']);
});

it('portfolio summary summary has expected metric keys', function () {
    $user = adminUser();

    $summary = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.compliance.portfolio-summary'))
        ->assertOk()
        ->json('summary');

    expect($summary)->toHaveKeys([
        'total_estates', 'fully_compliant', 'partially_compliant',
        'not_started', 'total_items', 'total_completed',
        'total_overdue', 'portfolio_progress',
    ]);
});

it('portfolio summary counts estates correctly', function () {
    $user    = adminUser();
    $estate1 = Estate::factory()->create(['organization_id' => $user->organization_id]);
    $estate2 = Estate::factory()->create(['organization_id' => $user->organization_id]);

    ComplianceChecklist::factory()->create([
        'organization_id'      => $user->organization_id,
        'estate_id'            => $estate1->id,
        'financial_year_start' => '2025-03-01',
        'financial_year_end'   => '2026-02-28',
    ]);
    ComplianceChecklist::factory()->create([
        'organization_id'      => $user->organization_id,
        'estate_id'            => $estate2->id,
        'financial_year_start' => '2025-03-01',
        'financial_year_end'   => '2026-02-28',
    ]);

    $summary = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.compliance.portfolio-summary'))
        ->assertOk()
        ->json('summary');

    expect($summary['total_estates'])->toBe(2);
});

it('portfolio summary reports fully_compliant when all items completed', function () {
    ['user' => $user, 'checklist' => $checklist] = makeChecklist();

    ComplianceChecklistItem::factory()->create([
        'compliance_checklist_id' => $checklist->id,
        'organization_id'         => $user->organization_id,
        'status'                  => 'completed',
    ]);

    $summary = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.compliance.portfolio-summary'))
        ->assertOk()
        ->json('summary');

    expect($summary['fully_compliant'])->toBe(1);
    expect($summary['portfolio_progress'])->toBe(100);
});

it('portfolio summary financial_years lists available financial year labels', function () {
    $user   = adminUser();
    $estate = Estate::factory()->create(['organization_id' => $user->organization_id]);

    ComplianceChecklist::factory()->create([
        'organization_id'      => $user->organization_id,
        'estate_id'            => $estate->id,
        'financial_year_label' => '2025/2026',
        'financial_year_start' => '2025-03-01',
        'financial_year_end'   => '2026-02-28',
    ]);

    $years = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.compliance.portfolio-summary'))
        ->assertOk()
        ->json('financial_years');

    expect($years)->toContain('2025/2026');
});

it('portfolio summary does not include other org checklists', function () {
    makeChecklist(); // creates for another org

    $myUser = adminUser();

    $summary = $this->actingAs($myUser, 'api')
        ->getJson(route('api.v1.compliance.portfolio-summary'))
        ->assertOk()
        ->json('summary');

    expect($summary['total_estates'])->toBe(0);
});
