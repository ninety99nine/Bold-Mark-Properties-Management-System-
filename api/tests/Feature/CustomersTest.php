<?php

use App\Models\Community;
use App\Models\CustomerGroup;
use App\Models\Owner;
use App\Models\Unit;
use Illuminate\Http\UploadedFile;

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function customerSetup(): array
{
    $user      = adminUser();
    $community = Community::factory()->create(['organization_id' => $user->organization_id]);

    return [$user, $community];
}

function makeCustomer(\App\Models\User $user, Community $community, array $overrides = []): Owner
{
    return Owner::factory()->create(array_merge([
        'organization_id' => $user->organization_id,
        'community_id'    => $community->id,
        'unit_id'         => null,
        'customer_code'   => 'CUS' . fake()->unique()->numerify('###'),
        'customer_type'   => 'individual',
    ], $overrides));
}

function validCustomerPayload(array $overrides = []): array
{
    return array_merge([
        'customer_type' => 'individual',
        'full_name'     => 'John Doe',
    ], $overrides);
}

function makeCustomerUpload(string $name, string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'cust') . '_' . $name;
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, null, null, true);
}

// =============================================================================
// Unauthenticated access
// =============================================================================

it('blocks unauthenticated requests on every customer route', function (string $method, string $route, array $params): void {
    $this->{$method . 'Json'}(route($route, $params))->assertUnauthorized();
})->with([
    ['get',    'api.v1.show.community.customers', ['community' => '00000000-0000-0000-0000-000000000000']],
    ['post',   'api.v1.create.community.customer', ['community' => '00000000-0000-0000-0000-000000000000']],
    ['get',    'api.v1.show.customer.groups', ['community' => '00000000-0000-0000-0000-000000000000']],
    ['post',   'api.v1.create.customer.group', ['community' => '00000000-0000-0000-0000-000000000000']],
]);

// =============================================================================
// Show Customers
// =============================================================================

// CommunityPolicy::viewCustomers() → Tier 1 (return true) — no 403 test needed.

it('lists customers scoped to the community including standalone customers', function (): void {
    [$user, $community] = customerSetup();
    makeCustomer($user, $community, ['full_name' => 'Standalone Cust', 'customer_code' => 'STA001']);

    $unit = Unit::factory()->create(['community_id' => $community->id, 'organization_id' => $user->organization_id]);
    makeCustomer($user, $community, ['unit_id' => $unit->id, 'full_name' => 'Unit Cust', 'customer_code' => 'UNI001']);

    // A customer in a different community must not appear.
    $other = Community::factory()->create(['organization_id' => $user->organization_id]);
    makeCustomer($user, $other, ['full_name' => 'Other Cust']);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.community.customers', $community))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'code', 'customer_code', 'full_name', 'reference', 'payment_type', 'is_disabled', 'customer_groups']],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);

    expect($resp->json('meta.total'))->toBe(2);
});

// =============================================================================
// Create Customer
// =============================================================================

it('blocks unauthenticated requests to create a customer', function (): void {
    $this->postJson(route('api.v1.create.community.customer', ['community' => '00000000-0000-0000-0000-000000000000']), validCustomerPayload())
        ->assertUnauthorized();
});

it('requires a customer type', function (): void {
    [$user, $community] = customerSetup();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community.customer', $community), validCustomerPayload(['customer_type' => null]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['customer_type']);
});

it('requires a name', function (): void {
    [$user, $community] = customerSetup();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community.customer', $community), validCustomerPayload(['full_name' => null]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['full_name']);
});

it('allows a customer without an email', function (): void {
    [$user, $community] = customerSetup();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community.customer', $community), validCustomerPayload(['full_name' => 'No Email']))
        ->assertOk();

    $this->assertDatabaseHas('owners', ['full_name' => 'No Email', 'community_id' => $community->id, 'email' => null]);
});

it('auto-generates a customer code when left blank', function (): void {
    [$user, $community] = customerSetup();

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community.customer', $community), validCustomerPayload(['full_name' => 'Alfonso CA']))
        ->assertOk();

    expect($resp->json('data.customer_code'))->toStartWith('ALF');
});

it('keeps an explicit customer code when provided', function (): void {
    [$user, $community] = customerSetup();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community.customer', $community), validCustomerPayload(['customer_code' => 'MYCODE01']))
        ->assertOk()
        ->assertJsonPath('data.customer_code', 'MYCODE01');
});

it('syncs the selected customer groups on create', function (): void {
    [$user, $community] = customerSetup();
    $group = CustomerGroup::create(['name' => 'VIP', 'community_id' => $community->id, 'organization_id' => $user->organization_id]);

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.community.customer', $community), validCustomerPayload(['customer_group_ids' => [$group->id]]))
        ->assertOk();

    $this->assertDatabaseHas('customer_group_owner', ['customer_group_id' => $group->id, 'owner_id' => $resp->json('data.id')]);
});

// =============================================================================
// Update / Disable / Enable / Delete Customer
// =============================================================================

it('updates a customer and re-syncs its groups', function (): void {
    [$user, $community] = customerSetup();
    $customer = makeCustomer($user, $community);
    $group    = CustomerGroup::create(['name' => 'Trustees', 'community_id' => $community->id, 'organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.customer', [$community, $customer]), [
            'full_name'          => 'Updated Name',
            'customer_group_ids' => [$group->id],
        ])
        ->assertOk();

    $this->assertDatabaseHas('owners', ['id' => $customer->id, 'full_name' => 'Updated Name']);
    $this->assertDatabaseHas('customer_group_owner', ['customer_group_id' => $group->id, 'owner_id' => $customer->id]);
});

it('disables and re-enables a customer', function (): void {
    [$user, $community] = customerSetup();
    $customer = makeCustomer($user, $community);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.disable.customer', [$community, $customer]))
        ->assertOk();
    $this->assertDatabaseHas('owners', ['id' => $customer->id, 'is_disabled' => true]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.enable.customer', [$community, $customer]))
        ->assertOk();
    $this->assertDatabaseHas('owners', ['id' => $customer->id, 'is_disabled' => false]);
});

it('deletes a customer', function (): void {
    [$user, $community] = customerSetup();
    $customer = makeCustomer($user, $community);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.customer', [$community, $customer]))
        ->assertOk();

    $this->assertDatabaseMissing('owners', ['id' => $customer->id]);
});

it('returns 404 for a customer in another organization', function (): void {
    [$user, $community] = customerSetup();
    $otherUser      = otherOrganizationUser();
    $otherCommunity = Community::factory()->create(['organization_id' => $otherUser->organization_id]);
    $otherCustomer  = makeCustomer($otherUser, $otherCommunity);

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customer', [$community, $otherCustomer]))
        ->assertNotFound();
});

// =============================================================================
// Customer Groups
// =============================================================================

// CustomerGroupPolicy::viewAny() → Tier 1 (return true) — no 403 test needed.

it('lists customer groups with a customers count', function (): void {
    [$user, $community] = customerSetup();
    $group    = CustomerGroup::create(['name' => 'Owners', 'community_id' => $community->id, 'organization_id' => $user->organization_id]);
    $customer = makeCustomer($user, $community);
    $customer->customerGroups()->attach($group->id);

    $resp = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.customer.groups', $community))
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'customers_count']]]);

    expect($resp->json('data.0.customers_count'))->toBe(1);
});

it('creates a customer group', function (): void {
    [$user, $community] = customerSetup();

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.customer.group', $community), ['name' => 'Directors'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Directors');

    $this->assertDatabaseHas('customer_groups', ['name' => 'Directors', 'community_id' => $community->id]);
});

it('rejects a duplicate customer group name in the same community', function (): void {
    [$user, $community] = customerSetup();
    CustomerGroup::create(['name' => 'Directors', 'community_id' => $community->id, 'organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->postJson(route('api.v1.create.customer.group', $community), ['name' => 'Directors'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('deletes a customer group', function (): void {
    [$user, $community] = customerSetup();
    $group = CustomerGroup::create(['name' => 'Temp', 'community_id' => $community->id, 'organization_id' => $user->organization_id]);

    $this->actingAs($user, 'api')
        ->deleteJson(route('api.v1.delete.customer.group', [$community, $group]))
        ->assertOk();

    $this->assertDatabaseMissing('customer_groups', ['id' => $group->id]);
});

// =============================================================================
// Bulk tools — Notes + Debit Order Mandates
// =============================================================================

it('downloads the customer notes template as an xlsx', function (): void {
    [$user, $community] = customerSetup();

    $this->actingAs($user, 'api')
        ->get(route('api.v1.download.customer.notes.template', $community))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('imports customer notes matching by customer code', function (): void {
    [$user, $community] = customerSetup();
    $customer = makeCustomer($user, $community, ['customer_code' => 'ABC001']);

    $csv  = "Customer,Notes\nABC001,Follow up on arrears\nZZZ999,Unknown code\n";
    $file = makeCustomerUpload('notes.csv', $csv);

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.import.customer.notes', $community), ['file' => $file])
        ->assertOk();

    expect($resp->json('imported'))->toBe(1);
    expect($resp->json('skipped'))->toBe(1);
    $this->assertDatabaseHas('owners', ['id' => $customer->id, 'notes' => 'Follow up on arrears']);
});

it('imports debit-order mandates and flags the customer for debit order', function (): void {
    [$user, $community] = customerSetup();
    $customer = makeCustomer($user, $community, ['customer_code' => 'DEB001']);

    $header = 'BANK NAME,BRANCH CODE,ACCOUNT NAME,ACCOUNT NUMBER,CUSTOMER CODE,ACCOUNT TYPE (CURRENT / SAVINGS),MANDATE TYPE (Balance / Monthly / Monthly Plus),Monthly Plus Amount (if any),Collection Date group (1 / 15 /28)';
    $row    = 'Capitec Bank,470010,John Doe,123456789,DEB001,CURRENT,Monthly,,15';
    $file   = makeCustomerUpload('mandates.csv', $header . "\n" . $row . "\n");

    $resp = $this->actingAs($user, 'api')
        ->postJson(route('api.v1.import.debit.order.mandates', $community), ['file' => $file])
        ->assertOk();

    expect($resp->json('imported'))->toBe(1);
    $this->assertDatabaseHas('owners', [
        'id'             => $customer->id,
        'bank_name'      => 'Capitec Bank',
        'account_type'   => 'Current',
        'mandate_type'   => 'monthly',
        'collection_day' => 15,
        'debit_order'    => true,
        'payment_type'   => 'debit_order',
    ]);
});
