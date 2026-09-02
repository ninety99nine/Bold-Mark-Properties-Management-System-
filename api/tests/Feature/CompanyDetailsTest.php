<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// =============================================================================
// Company Details — scalar fields
// =============================================================================

it('updates the company details fields', function (): void {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.organization'), [
            'company_name'           => 'Bold Mark Properties',
            'company_reg_no'         => '2021/147096/07',
            'transfer_clearance_fee' => 1100.00,
            'contact_phone'          => '010 824 9671',
            'outgoing_email'         => 'noreply@boldmarkprop.co.za',
        ])
        ->assertOk()
        ->assertJsonPath('data.company_reg_no', '2021/147096/07')
        ->assertJsonPath('data.outgoing_email', 'noreply@boldmarkprop.co.za');

    expect($user->organization->fresh()->transfer_clearance_fee)->toEqual('1100.00');
});

it('updates the company bank details', function (): void {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.organization'), [
            'bank_account_holder' => 'Bold Mark Properties',
            'bank_name'           => 'Standard Bank',
            'bank_account_type'   => 'Current',
            'bank_account_number' => '201656302',
            'bank_branch_code'    => '004305',
            'bank_branch_name'    => 'Rosebank',
        ])
        ->assertOk()
        ->assertJsonPath('data.bank_name', 'Standard Bank')
        ->assertJsonPath('data.bank_account_type', 'Current');
});

it('rejects an unsupported bank name', function (): void {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.organization'), [
            'bank_name' => 'Not A Real Bank',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('bank_name');
});

it('rejects an invalid account type', function (): void {
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.organization'), [
            'bank_account_type' => 'Cheque',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('bank_account_type');
});

// =============================================================================
// Company Details — logo, icon and email header/footer uploads
// =============================================================================

it('uploads the header logo and top-left icon', function (): void {
    Storage::fake('public');
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->put(route('api.v1.update.organization'), [
            'logo' => UploadedFile::fake()->image('logo.png', 600, 200),
            'icon' => UploadedFile::fake()->image('icon.png', 55, 55),
        ])
        ->assertOk();

    $org = $user->organization->fresh();
    expect($org->logo_url)->not->toBeNull();
    expect($org->icon_url)->not->toBeNull();
});

it('uploads the default email header', function (): void {
    Storage::fake('public');
    $user = adminUser();

    $this->actingAs($user, 'api')
        ->put(route('api.v1.update.organization'), [
            'email_header' => UploadedFile::fake()->image('header.png', 600, 200),
        ])
        ->assertOk();

    expect($user->organization->fresh()->email_header_url)->not->toBeNull();
});

it('removes the default email header', function (): void {
    Storage::fake('public');
    $user = adminUser();
    $user->organization->update(['email_header_url' => 'http://localhost/storage/x.png']);

    $this->actingAs($user, 'api')
        ->putJson(route('api.v1.update.organization'), [
            'remove_email_header' => true,
        ])
        ->assertOk();

    expect($user->organization->fresh()->email_header_url)->toBeNull();
});
