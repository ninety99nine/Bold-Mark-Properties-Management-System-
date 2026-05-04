<?php

use App\Models\Estate;
use App\Models\Organization;

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ Unauthenticated access                                                   ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('returns 401 on every authenticated tenant route when unauthenticated', function (string $method, string $route) {
    $this->{$method . 'Json'}(route($route))->assertUnauthorized();
})->with([
    ['get', 'api.v1.show.organization'],
    ['put', 'api.v1.update.organization'],
]);

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ GET /v1/tenant  —  current tenant                                        ║
// ╚══════════════════════════════════════════════════════════════════════════╝

it('returns the current tenant for the authenticated user', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.organization'))
        ->assertOk()
        ->assertJsonPath('data.id', $user->organization_id);
});

it('returns the full tenant payload structure', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.organization'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id', 'name', 'slug',
                'company_name', 'company_slogan', 'logo_url',
                'contact_email', 'contact_phone', 'address',
                'country', 'currency',
                'primary_color', 'secondary_color', 'copyright_name',
                'is_active', 'created_at', 'updated_at',
                'estates',
            ],
        ]);
});

it('eager-loads the tenant\'s estates relationship on show', function () {
    $user = adminUser();
    Estate::factory()->count(2)->create(['organization_id' => $user->organization_id]);
    Estate::factory()->create(['organization_id' => createTenant()->id]); // foreign — must NOT appear

    $estates = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.organization'))
        ->assertOk()
        ->json('data.estates');

    expect($estates)->toBeArray()->toHaveCount(2);
    foreach ($estates as $e) {
        expect($e['organization_id'])->toBe($user->organization_id);
    }
});

it('does not expose credentials on the show payload (sensitive)', function () {
    $user = adminUser();
    // Stamp credentials on the tenant so we can prove the resource doesn't leak them.
    $user->organization->update(['credentials' => ['stripe_secret' => 'sk_live_should_not_leak']]);

    $row = $this->actingAs($user, 'api')
        ->getJson(route('api.v1.show.organization'))
        ->assertOk()
        ->json('data');

    expect($row)->not->toHaveKey('credentials');
});

it('each user only sees their own tenant', function () {
    $userA = adminUser();
    $userB = adminUser();

    $a = $this->actingAs($userA, 'api')->getJson(route('api.v1.show.organization'))->assertOk();
    $b = $this->actingAs($userB, 'api')->getJson(route('api.v1.show.organization'))->assertOk();

    expect($a->json('data.id'))->toBe($userA->organization_id);
    expect($b->json('data.id'))->toBe($userB->organization_id);
    expect($a->json('data.id'))->not->toBe($b->json('data.id'));
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ PUT /v1/tenant  —  update current tenant                                 ║
// ╚══════════════════════════════════════════════════════════════════════════╝

// ──────────────────────────────────────────────────────────────────────────────
// Validation rules — every field
// ──────────────────────────────────────────────────────────────────────────────

it('rejects update with company_name > 255 chars', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.organization'), ['company_name' => str_repeat('x', 256)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['company_name']);
});

it('rejects update with company_name not a string', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.organization'), ['company_name' => ['nested']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['company_name']);
});

it('rejects update with company_slogan > 255 chars', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.organization'), ['company_slogan' => str_repeat('y', 256)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['company_slogan']);
});

it('rejects update with invalid contact_email format', function (string $email) {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.organization'), ['contact_email' => $email])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['contact_email']);
})->with([
    'plain text'    => ['notanemail'],
    'missing local' => ['@host.com'],
    'with spaces'   => ['has spaces@host.com'],
]);

it('rejects update with contact_email > 255 chars', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.organization'), ['contact_email' => str_repeat('a', 250) . '@x.com'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['contact_email']);
});

it('rejects update with contact_phone > 30 chars', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.organization'), ['contact_phone' => str_repeat('1', 31)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['contact_phone']);
});

it('rejects update with address > 500 chars', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.organization'), ['address' => str_repeat('a', 501)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['address']);
});

it('rejects update with country length other than 2', function (string $country) {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.organization'), ['country' => $country])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['country']);
})->with([
    'one char'   => ['Z'],
    'three char' => ['ZAF'],
    'empty'      => [''],
]);

it('accepts a 2-char country code', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.organization'), ['country' => 'BW'])
        ->assertOk()
        ->assertJsonPath('data.country', 'BW');
});

it('rejects update with currency > 10 chars', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.organization'), ['currency' => str_repeat('A', 11)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['currency']);
});

it('accepts a 3-char currency code (ISO 4217)', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.organization'), ['currency' => 'BWP'])
        ->assertOk()
        ->assertJsonPath('data.currency', 'BWP');
});

// ──────────────────────────────────────────────────────────────────────────────
// Color hex validation — regex /^#[0-9A-Fa-f]{3,6}$/
// ──────────────────────────────────────────────────────────────────────────────

it('rejects primary_color without the # prefix', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.organization'), ['primary_color' => '1F3A5C'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['primary_color']);
});

it('rejects primary_color with non-hex characters', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.organization'), ['primary_color' => '#GGHHII'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['primary_color']);
});

it('rejects primary_color when too short (1 or 2 hex chars)', function (string $color) {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.organization'), ['primary_color' => $color])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['primary_color']);
})->with([
    '1 hex char'  => ['#A'],
    '2 hex chars' => ['#AB'],
]);

it('rejects primary_color when too long (>6 hex chars)', function () {
    $user = adminUser();

    // 7-char body (#ABCDEF1) exceeds the regex {3,6} cap. Note the field's
    // max:10 char rule would actually pass — the regex is the stricter gate.
    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.organization'), ['primary_color' => '#ABCDEF1'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['primary_color']);
});

it('accepts a 3-char hex color', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.organization'), ['primary_color' => '#FFF'])
        ->assertOk()
        ->assertJsonPath('data.primary_color', '#FFF');
});

it('accepts a 6-char hex color (mixed case)', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.organization'), ['primary_color' => '#aB12cD'])
        ->assertOk()
        ->assertJsonPath('data.primary_color', '#aB12cD');
});

it('rejects secondary_color with same regex rules as primary_color', function (string $color) {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.organization'), ['secondary_color' => $color])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['secondary_color']);
})->with([
    'no hash'     => ['D89B4B'],
    'non-hex'     => ['#XYZXYZ'],
    'too long'    => ['#ABCDEF1'],
    'just hash'   => ['#'],
]);

it('accepts secondary_color #D89B4B', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.organization'), ['secondary_color' => '#D89B4B'])
        ->assertOk()
        ->assertJsonPath('data.secondary_color', '#D89B4B');
});

// ──────────────────────────────────────────────────────────────────────────────
// Successful update + side effects
// ──────────────────────────────────────────────────────────────────────────────

it('updates tenant company settings and returns the updated resource + message', function () {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.organization'), [
            'company_name'    => 'Bold Mark Properties Updated',
            'company_slogan'  => 'Moving People Forward',
            'contact_email'   => 'info@boldmark.co.za',
            'primary_color'   => '#1F3A5C',
            'secondary_color' => '#D89B4B',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Updated successfully')
        ->assertJsonPath('data.company_name', 'Bold Mark Properties Updated')
        ->assertJsonPath('data.company_slogan', 'Moving People Forward')
        ->assertJsonPath('data.contact_email', 'info@boldmark.co.za');

    $this->assertDatabaseHas('organizations', [
        'id'           => $user->organization_id,
        'company_name' => 'Bold Mark Properties Updated',
        'primary_color' => '#1F3A5C',
    ]);
});

it('allows partial update — untouched fields remain', function () {
    $user = adminUser();
    $user->organization->update([
        'company_name'   => 'Original Co',
        'contact_email'  => 'original@x.com',
        'primary_color'  => '#111',
    ]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.organization'), ['company_name' => 'New Co'])
        ->assertOk()
        ->assertJsonPath('data.company_name', 'New Co')
        ->assertJsonPath('data.contact_email', 'original@x.com')
        ->assertJsonPath('data.primary_color', '#111');
});

it('preserves existing values when nullable fields are explicitly nulled', function () {
    $user = adminUser();
    $user->organization->update(['contact_phone' => '+27 11 555 0000', 'address' => '12 Acacia']);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.organization'), [
            'contact_phone' => null,
            'address'       => null,
        ])
        ->assertOk();

    // The service filters out nulls before update — originals stay intact.
    $fresh = $user->organization->fresh();
    expect($fresh->contact_phone)->toBe('+27 11 555 0000');
    expect($fresh->address)->toBe('12 Acacia');
});

// ──────────────────────────────────────────────────────────────────────────────
// Spoof safety — non-fillable fields cannot be changed via this endpoint
// ──────────────────────────────────────────────────────────────────────────────

it('does not let the client change `name`, `slug`, `logo_url` or `is_active` via update', function () {
    $user = adminUser();
    $tenant = $user->organization;
    $originals = [
        'name'      => $tenant->name,
        'slug'      => $tenant->slug,
        'logo_url'  => $tenant->logo_url,
        'is_active' => $tenant->is_active,
    ];

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.organization'), [
            'name'      => 'Hijacked',
            'slug'      => 'hijacked-slug',
            'logo_url'  => 'https://evil.example.com/pwn.png',
            'is_active' => false,
            // Plus a real change so the request still does something useful.
            'company_name' => 'New Co',
        ])
        ->assertOk();

    $fresh = $tenant->fresh();
    expect($fresh->name)->toBe($originals['name']);
    expect($fresh->slug)->toBe($originals['slug']);
    expect($fresh->logo_url)->toBe($originals['logo_url']);
    expect((bool) $fresh->is_active)->toBe((bool) $originals['is_active']);
    expect($fresh->company_name)->toBe('New Co');
});

it('does not let the client change `credentials` via update (sensitive)', function () {
    $user = adminUser();
    $user->organization->update(['credentials' => ['stripe_secret' => 'sk_live_original']]);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.organization'), [
            'credentials'  => ['stripe_secret' => 'sk_live_pwned'],
            'company_name' => 'Touched',
        ])
        ->assertOk();

    expect($user->organization->fresh()->credentials)->toBe(['stripe_secret' => 'sk_live_original']);
});

it('CHARACTERIZATION: copyright_name is dropped before reaching the service', function () {
    // The service's allow-list includes `copyright_name`, but UpdateOrganizationRequest::rules()
    // does NOT — so it never makes it past `validated()`. Frontend cannot update
    // copyright_name through this endpoint today.
    $user = adminUser();
    $original = $user->organization->copyright_name;

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.organization'), [
            'copyright_name' => 'Brand New Name',
        ])
        ->assertOk();

    expect($user->organization->fresh()->copyright_name)->toBe($original);
});

// ╔══════════════════════════════════════════════════════════════════════════╗
// ║ GET /v1/branding  —  public, subdomain-resolved                          ║
// ╚══════════════════════════════════════════════════════════════════════════╝

// ──────────────────────────────────────────────────────────────────────────────
// Access — no auth required
// ──────────────────────────────────────────────────────────────────────────────

it('does NOT require authentication', function () {
    $this->getJson(route('api.v1.branding'))
        ->assertOk();
});

// ──────────────────────────────────────────────────────────────────────────────
// Resolution by subdomain
// ──────────────────────────────────────────────────────────────────────────────

it('returns the platform default branding when no subdomain matches an active tenant', function () {
    $body = $this->getJson(route('api.v1.branding'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['name', 'logo_url', 'primary_color', 'accent_color', 'credentials', 'copyright_name'],
        ])
        ->json();

    expect($body['data']['name'])->toBe('Property Management Platform');
    expect($body['data']['primary_color'])->toBe('#0B1F38');
    expect($body['data']['accent_color'])->toBe('#D89B4B');
    expect($body['data']['credentials'])->toBe([]);
});

it('returns the matching tenant\'s branding when the subdomain resolves to an active tenant', function () {
    $tenant = Organization::factory()->create([
        'name'           => 'Bold Mark',
        'slug'           => 'boldmark',
        'is_active'      => true,
        'primary_color'  => '#111111',
        'logo_url'       => 'https://cdn.example.com/logo.png',
        'copyright_name' => 'Bold Mark (Pty) Ltd',
    ]);

    $body = $this->getJson('http://boldmark.example.com/api/v1/branding')
        ->assertOk()
        ->json();

    expect($body['data']['name'])->toBe('Bold Mark');
    expect($body['data']['primary_color'])->toBe('#111111');
    expect($body['data']['logo_url'])->toBe('https://cdn.example.com/logo.png');
    expect($body['data']['copyright_name'])->toBe('Bold Mark (Pty) Ltd');
});

it('falls back to the platform default when the subdomain matches an INACTIVE tenant', function () {
    Organization::factory()->create([
        'slug'      => 'sleepy',
        'is_active' => false,
    ]);

    $body = $this->getJson('http://sleepy.example.com/api/v1/branding')
        ->assertOk()
        ->json();

    expect($body['data']['name'])->toBe('Property Management Platform');
});

it('does not leak the tenant slug, contact_email or other internal fields via /branding', function () {
    Organization::factory()->create([
        'slug'          => 'leaky',
        'is_active'     => true,
        'contact_email' => 'private@x.com',
        'address'       => '1 Secret St',
    ]);

    $row = $this->getJson('http://leaky.example.com/api/v1/branding')
        ->assertOk()
        ->json('data');

    expect($row)->not->toHaveKey('id');
    expect($row)->not->toHaveKey('slug');
    expect($row)->not->toHaveKey('contact_email');
    expect($row)->not->toHaveKey('address');
});

it('returns the tenant\'s credentials array if set (so the frontend can render integration toggles)', function () {
    Organization::factory()->create([
        'slug'        => 'creds',
        'is_active'   => true,
        'credentials' => ['stripe' => true, 'resend' => true],
    ]);

    $row = $this->getJson('http://creds.example.com/api/v1/branding')
        ->assertOk()
        ->json('data');

    expect($row['credentials'])->toBe(['stripe' => true, 'resend' => true]);
});

it('CHARACTERIZATION: organizations table has no `accent_color` column, so /branding returns null for resolved organizations', function () {
    // The fallback hard-codes accent_color = '#D89B4B', but a resolved tenant
    // exposes $tenant->accent_color which doesn't exist on the model — null.
    // Worth either renaming the controller to use secondary_color or adding
    // an accent_color column. For now, characterize current behavior.
    Organization::factory()->create([
        'slug'             => 'accent-test',
        'is_active'        => true,
        'secondary_color'  => '#FF0000',
    ]);

    $row = $this->getJson('http://accent-test.example.com/api/v1/branding')
        ->assertOk()
        ->json('data');

    expect($row['accent_color'])->toBeNull();
});
