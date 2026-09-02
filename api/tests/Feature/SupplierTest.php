<?php

use App\Models\Community;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Models\SupplierGroup;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * A user with a plain role (no super/company-admin) and no supplier permissions.
 * The supplier permissions are registered (guard: api) so the permission check
 * resolves to "not granted" rather than throwing for an unknown permission.
 */
function supplierRestrictedUser(): \App\Models\User
{
    foreach (['supplier.create', 'supplier.update', 'supplier.delete'] as $permission) {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
    }

    return createUser(createOrganization(), 'community-manager');
}

function validSupplierPayload(array $overrides = []): array
{
    return array_merge([
        'name'  => 'Acme Cleaning Services',
        'email' => 'billing@acme.test',
        'phone' => '011 555 0000',
    ], $overrides);
}

// ──────────────────────────────────────────────────────────────────────────────
// Unauthenticated access
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 on every supplier route when unauthenticated', function (string $method, string $route, array $params = []) {
    $this->{$method . 'Json'}(route($route, $params))->assertUnauthorized();
})->with([
    ['get',    'api.v1.show.suppliers'],
    ['post',   'api.v1.create.supplier'],
    ['delete', 'api.v1.delete.suppliers'],
    ['get',    'api.v1.show.supplier',   ['supplier' => '00000000-0000-0000-0000-000000000000']],
    ['put',    'api.v1.update.supplier', ['supplier' => '00000000-0000-0000-0000-000000000000']],
    ['delete', 'api.v1.delete.supplier', ['supplier' => '00000000-0000-0000-0000-000000000000']],
]);

// ──────────────────────────────────────────────────────────────────────────────
// GET /v1/suppliers — index
// ──────────────────────────────────────────────────────────────────────────────

// SupplierPolicy::viewAny() → Tier 1 (return true) — no 403 test needed.

it('returns the supplier list structure scoped to the organization', function () {
    $user = adminUser();
    Supplier::factory()->count(3)->create(['organization_id' => $user->organization_id]);

    // Cross-organization suppliers must not appear.
    Supplier::factory()->count(2)->create(['organization_id' => createOrganization()->id]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.suppliers'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'supplier_code', 'name', 'email', 'phone', 'bank_name', 'account_number', 'branch_code', 'vat_number', 'registration_number', 'address', 'is_active', 'balance', 'community_id', 'label', 'created_at', 'updated_at']],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);

    expect($resp->json('meta.total'))->toBe(3);
});

it('exposes a label combining supplier_code and name', function () {
    $user = adminUser();
    $supplier = Supplier::factory()->create([
        'organization_id' => $user->organization_id,
        'supplier_code'   => 'ACM001',
        'name'            => 'Acme',
    ]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.suppliers'))
        ->assertOk()
        ->assertJsonPath('data.0.label', 'ACM001 - Acme');
});

it('defaults to active suppliers only', function () {
    $user = adminUser();
    Supplier::factory()->count(2)->create(['organization_id' => $user->organization_id, 'is_active' => true]);
    Supplier::factory()->create(['organization_id' => $user->organization_id, 'is_active' => false]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.suppliers'))
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(2);
});

it('includes inactive suppliers when is_active=false is requested', function () {
    $user = adminUser();
    Supplier::factory()->count(2)->create(['organization_id' => $user->organization_id, 'is_active' => true]);
    Supplier::factory()->create(['organization_id' => $user->organization_id, 'is_active' => false]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.suppliers') . '?is_active=0')
        ->assertOk();

    expect($resp->json('meta.total'))->toBe(1);
});

it('filters suppliers by community (community-specific + org-shared)', function () {
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);
    $other     = Community::factory()->create(['organization_id' => $user->organization_id]);

    Supplier::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $community->id]);
    Supplier::factory()->create(['organization_id' => $user->organization_id, 'community_id' => null]); // org-shared
    Supplier::factory()->create(['organization_id' => $user->organization_id, 'community_id' => $other->id]);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.suppliers') . '?community_id=' . $community->id)
        ->assertOk();

    // The community-specific one + the org-shared one, but not the other community's.
    expect($resp->json('meta.total'))->toBe(2);
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /v1/suppliers — create
// ──────────────────────────────────────────────────────────────────────────────

it('blocks users without permission from creating a supplier', function () {
    $user = supplierRestrictedUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.supplier'), validSupplierPayload())
        ->assertForbidden();
});

it('requires a name', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.supplier'), validSupplierPayload(['name' => null]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('requires a valid email', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.supplier'), validSupplierPayload(['email' => 'not-an-email']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('creates a supplier and auto-generates a supplier_code', function () {
    $user = adminUser();

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.supplier'), validSupplierPayload(['name' => 'Acme Cleaning']))
        ->assertOk()
        ->assertJsonPath('message', 'Created successfully')
        ->assertJsonPath('data.name', 'Acme Cleaning');

    expect($resp->json('data.supplier_code'))->toBe('ACM001');

    $this->assertDatabaseHas('suppliers', [
        'name'            => 'Acme Cleaning',
        'supplier_code'   => 'ACM001',
        'organization_id' => $user->organization_id,
    ]);
});

it('increments the auto-generated supplier_code sequence per prefix', function () {
    $user = adminUser();
    Supplier::factory()->create(['organization_id' => $user->organization_id, 'supplier_code' => 'ACM001', 'name' => 'Acme One']);

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.supplier'), validSupplierPayload(['name' => 'Acme Two']))
        ->assertOk();

    expect($resp->json('data.supplier_code'))->toBe('ACM002');
});

it('honours a supplied supplier_code', function () {
    $user = adminUser();

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.supplier'), validSupplierPayload(['supplier_code' => 'CUSTOM99']))
        ->assertOk();

    expect($resp->json('data.supplier_code'))->toBe('CUSTOM99');
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /v1/suppliers/{supplier} — show
// ──────────────────────────────────────────────────────────────────────────────

// SupplierPolicy::view() → Tier 1 (return true) — no 403 test needed.

it('returns a single supplier', function () {
    $user     = adminUser();
    $supplier = Supplier::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Widget Co']);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.supplier', $supplier))
        ->assertOk()
        ->assertJsonPath('data.id', $supplier->id)
        ->assertJsonPath('data.name', 'Widget Co');
});

it('returns 404 for a cross-organization supplier', function () {
    $user     = adminUser();
    $supplier = Supplier::factory()->create(['organization_id' => createOrganization()->id]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.supplier', $supplier))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// PUT /v1/suppliers/{supplier} — update
// ──────────────────────────────────────────────────────────────────────────────

it('blocks users without permission from updating a supplier', function () {
    $user     = supplierRestrictedUser();
    $supplier = Supplier::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.supplier', $supplier), ['name' => 'New'])
        ->assertForbidden();
});

it('rejects update with an invalid email', function () {
    $user     = adminUser();
    $supplier = Supplier::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.supplier', $supplier), ['email' => 'bad'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('updates a supplier', function () {
    $user     = adminUser();
    $supplier = Supplier::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Old Name']);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.supplier', $supplier), ['name' => 'New Name', 'is_active' => false])
        ->assertOk()
        ->assertJsonPath('message', 'Updated successfully')
        ->assertJsonPath('data.name', 'New Name');

    $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'name' => 'New Name', 'is_active' => false]);
});

// ──────────────────────────────────────────────────────────────────────────────
// DELETE /v1/suppliers/{supplier} — single
// ──────────────────────────────────────────────────────────────────────────────

it('blocks users without permission from deleting a supplier', function () {
    $user     = supplierRestrictedUser();
    $supplier = Supplier::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.supplier', $supplier))
        ->assertForbidden();
});

it('deletes a single supplier', function () {
    $user     = adminUser();
    $supplier = Supplier::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.supplier', $supplier))
        ->assertOk()
        ->assertJson(['deleted' => true, 'message' => 'Supplier deleted']);

    $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
});

// ──────────────────────────────────────────────────────────────────────────────
// DELETE /v1/suppliers — bulk
// ──────────────────────────────────────────────────────────────────────────────

it('blocks users without permission from bulk-deleting suppliers', function () {
    $user      = supplierRestrictedUser();
    $suppliers = Supplier::factory()->count(2)->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.suppliers'), ['supplier_ids' => $suppliers->pluck('id')->all()])
        ->assertForbidden();
});

it('bulk deletes suppliers and pluralises the message', function () {
    $user      = adminUser();
    $suppliers = Supplier::factory()->count(3)->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.suppliers'), ['supplier_ids' => $suppliers->pluck('id')->all()])
        ->assertOk()
        ->assertJson(['message' => '3 Suppliers deleted']);

    foreach ($suppliers as $s) {
        $this->assertDatabaseMissing('suppliers', ['id' => $s->id]);
    }
});

it('rejects bulk delete when supplier_ids is not an array', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.suppliers'), ['supplier_ids' => 'a-single-id'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['supplier_ids']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Manage-parity create/update fields
// ──────────────────────────────────────────────────────────────────────────────

it('creates a supplier with all manage fields', function () {
    $user  = adminUser();
    $group = SupplierGroup::factory()->create(['organization_id' => $user->organization_id]);

    $payload = validSupplierPayload([
        'name'              => 'Full Fields Co',
        'reference'         => 'REF-999',
        'supplier_type'     => 'trust',
        'status'            => 'pending',
        'payment_type'      => 'eft',
        'account_type'      => 'savings',
        'bank_name'         => 'Standard Bank',
        'account_number'    => '1234567890',
        'branch_code'       => '051001',
        'branch_name'       => 'Sandton',
        'alt_email'         => 'alt@fullfields.test',
        'alt_phone'         => '011 555 9999',
        'address_line_1'    => '1 Main Rd',
        'address_line_2'    => 'Suite 2',
        'suburb'            => 'Northcliff',
        'town'              => 'Johannesburg',
        'postal_code'       => '2195',
        'supplier_group_id' => $group->id,
    ]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.supplier'), $payload)
        ->assertOk()
        ->assertJsonPath('data.supplier_type', 'trust')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.payment_type', 'eft')
        ->assertJsonPath('data.account_type', 'savings')
        ->assertJsonPath('data.supplier_group_id', $group->id);

    $this->assertDatabaseHas('suppliers', [
        'name'              => 'Full Fields Co',
        'reference'         => 'REF-999',
        'supplier_type'     => 'trust',
        'supplier_group_id' => $group->id,
        'organization_id'   => $user->organization_id,
    ]);
});

it('requires an email on create', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.supplier'), validSupplierPayload(['email' => null]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('rejects an invalid supplier_type', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.supplier'), validSupplierPayload(['supplier_type' => 'nope']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['supplier_type']);
});

it('updates manage fields on a supplier', function () {
    $user     = adminUser();
    $supplier = Supplier::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.supplier', $supplier), ['status' => 'unverified', 'town' => 'Cape Town'])
        ->assertOk()
        ->assertJsonPath('data.status', 'unverified')
        ->assertJsonPath('data.town', 'Cape Town');

    $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'status' => 'unverified', 'town' => 'Cape Town']);
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /v1/suppliers/options
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 for the supplier options endpoint when unauthenticated', function () {
    $this->getJson(route('api.v1.show.supplier.options'))->assertUnauthorized();
});

it('returns the supplier options shape', function () {
    $user  = adminUser();
    $group = SupplierGroup::factory()->create(['organization_id' => $user->organization_id, 'name' => 'Plumbers']);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.supplier.options'))
        ->assertOk()
        ->assertJsonStructure([
            'supplier_types' => [['value', 'label']],
            'payment_types'  => [['value', 'label']],
            'account_types'  => [['value', 'label']],
            'banks'          => [['value', 'label']],
            'statuses'       => [['value', 'label']],
            'groups'         => [['id', 'name']],
        ]);

    expect($resp->json('groups.0.name'))->toBe('Plumbers');
    expect(collect($resp->json('banks'))->pluck('label'))->toContain('ABSA', 'Standard Bank', 'Tyme Bank');
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /v1/suppliers/export
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 for the supplier export endpoint when unauthenticated', function () {
    $this->getJson(route('api.v1.export.suppliers'))->assertUnauthorized();
});

it('exports suppliers with the exact headers', function () {
    Excel::fake();
    $user = adminUser();
    Supplier::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->get(route('api.v1.export.suppliers'))
        ->assertOk();

    Excel::assertDownloaded('supplier export-.xlsx', function ($export) {
        return $export->headings() === ['Code', 'Supplier Name', 'Account Number', 'Branch Code', 'Email Address', 'Contact Number'];
    });
});

// ──────────────────────────────────────────────────────────────────────────────
// GET /v1/suppliers/upload-template
// ──────────────────────────────────────────────────────────────────────────────

it('downloads the upload template with the exact headers', function () {
    Excel::fake();
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->get(route('api.v1.download.supplier.template'))
        ->assertOk();

    Excel::assertDownloaded('supplier upload template-.xlsx', function ($export) {
        return $export->headings() === [
            'Code', 'Supplier Name', 'Account Number', 'Branch Code', 'Bank Name',
            'Account Type', 'Email Address', 'Telephone Number', 'VAT Registration Number',
        ];
    });
});

// ──────────────────────────────────────────────────────────────────────────────
// POST /v1/suppliers/import
// ──────────────────────────────────────────────────────────────────────────────

it('blocks users without permission from importing suppliers', function () {
    $user = supplierRestrictedUser();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.import.suppliers'), [])
        ->assertForbidden();
});

it('imports suppliers, upserting by code and returning counts', function () {
    $user = adminUser();

    // Pre-existing supplier that will be updated by the import.
    Supplier::factory()->create([
        'organization_id' => $user->organization_id,
        'supplier_code'   => 'EXIST01',
        'name'            => 'Old Name',
    ]);

    $rows = [
        ['Code', 'Supplier Name', 'Account Number', 'Branch Code', 'Bank Name', 'Account Type', 'Email Address', 'Telephone Number', 'VAT Registration Number'],
        ['EXIST01', 'Updated Name', '111', '222', 'ABSA', 'Savings', 'u@test.com', '0111111111', '4111111111'],
        ['', 'Brand New Supplier', '333', '444', 'Nedbank', 'Current', 'n@test.com', '0222222222', '4222222222'],
        ['', '', '', '', '', '', '', '', ''], // blank row → error
    ];

    Excel::fake();
    Excel::shouldReceive('toArray')->andReturn([$rows]);

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.import.suppliers'), [
            'file' => UploadedFile::fake()->create('suppliers.xlsx', 10),
        ])
        ->assertOk();

    expect($resp->json('created'))->toBe(1);
    expect($resp->json('updated'))->toBe(1);

    $this->assertDatabaseHas('suppliers', ['supplier_code' => 'EXIST01', 'name' => 'Updated Name']);
    $this->assertDatabaseHas('suppliers', ['name' => 'Brand New Supplier', 'bank_name' => 'Nedbank', 'account_type' => 'current']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Supplier documents
// ──────────────────────────────────────────────────────────────────────────────

it('returns 401 for the supplier documents endpoint when unauthenticated', function () {
    $supplier = Supplier::factory()->create(['organization_id' => createOrganization()->id]);

    $this->getJson(route('api.v1.show.supplier.documents', $supplier))->assertUnauthorized();
});

it('lists supplier documents', function () {
    $user     = adminUser();
    $supplier = Supplier::factory()->create(['organization_id' => $user->organization_id]);
    SupplierDocument::factory()->count(2)->create([
        'organization_id' => $user->organization_id,
        'supplier_id'     => $supplier->id,
    ]);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.supplier.documents', $supplier))
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'url', 'created_at']]])
        ->assertJsonCount(2, 'data');
});

it('blocks users without permission from uploading a supplier document', function () {
    $user     = supplierRestrictedUser();
    $supplier = Supplier::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.upload.supplier.document', $supplier), [
            'file' => UploadedFile::fake()->create('doc.pdf', 10),
        ])
        ->assertForbidden();
});

it('uploads a supplier document', function () {
    Storage::fake('public');
    $user     = adminUser();
    $supplier = Supplier::factory()->create(['organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.upload.supplier.document', $supplier), [
            'name' => 'Contract',
            'file' => UploadedFile::fake()->create('contract.pdf', 20),
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Document uploaded')
        ->assertJsonPath('data.name', 'Contract');

    $this->assertDatabaseHas('supplier_documents', [
        'supplier_id' => $supplier->id,
        'name'        => 'Contract',
    ]);
});

it('deletes a supplier document', function () {
    Storage::fake('public');
    $user     = adminUser();
    $supplier = Supplier::factory()->create(['organization_id' => $user->organization_id]);
    $document = SupplierDocument::factory()->create([
        'organization_id' => $user->organization_id,
        'supplier_id'     => $supplier->id,
    ]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.supplier.document', ['supplier' => $supplier, 'supplierDocument' => $document]))
        ->assertOk()
        ->assertJson(['deleted' => true, 'message' => 'Document deleted']);

    $this->assertDatabaseMissing('supplier_documents', ['id' => $document->id]);
});
