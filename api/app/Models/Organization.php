<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Organization extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_active'              => 'boolean',
        'credentials'            => 'array',
        'transfer_clearance_fee' => 'decimal:2',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'slug',
        'company_name',
        'company_slogan',
        'company_reg_no',
        'transfer_clearance_fee',
        'logo_url',
        'icon_url',
        'email_header_url',
        'email_footer_url',
        'is_active',
        'contact_email',
        'outgoing_email',
        'contact_phone',
        'address',
        'country',
        'currency',
        'bank_account_holder',
        'bank_name',
        'bank_account_type',
        'bank_account_number',
        'bank_branch_code',
        'bank_branch_name',
        'primary_color',
        'secondary_color',
        'copyright_name',
        'credentials',
    ];

    /**
     * Scope a query by search term.
     *
     * @param Builder $query
     * @param string $searchTerm
     * @return void
     */
    #[Scope]
    protected function search(Builder $query, string $searchTerm): void
    {
        $query->whereLike('name', $searchTerm)
              ->orWhereLike('company_name', $searchTerm)
              ->orWhereLike('contact_email', $searchTerm);
    }

    /**
     * Get users belonging to this occupant.
     *
     * @return HasMany
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get ledgers belonging to this occupant.
     *
     * @return HasMany
     */
    public function ledgers(): HasMany
    {
        return $this->hasMany(Ledger::class);
    }

    /**
     * Get communities belonging to this occupant.
     *
     * @return HasMany
     */
    public function communities(): HasMany
    {
        return $this->hasMany(Community::class);
    }

    /**
     * Get units belonging to this occupant.
     *
     * @return HasMany
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /**
     * Get owners belonging to this occupant.
     *
     * @return HasMany
     */
    public function owners(): HasMany
    {
        return $this->hasMany(Owner::class);
    }

    /**
     * Get unit organizations (property occupants) belonging to this occupant.
     *
     * @return HasMany
     */
    public function occupants(): HasMany
    {
        return $this->hasMany(Occupant::class);
    }

    /**
     * Get invoices belonging to this occupant.
     *
     * @return HasMany
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get cashbook entries belonging to this occupant.
     *
     * @return HasMany
     */
    public function cashbookEntries(): HasMany
    {
        return $this->hasMany(CashbookEntry::class);
    }

    /**
     * Get the display name: company_name if set, otherwise name.
     *
     * @return string
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->company_name ?? $this->name;
    }

    /**
     * Resolve the header logo to a local filesystem path for PDF rendering
     * (DomPDF reads local files reliably). Returns null when no logo is set or
     * the file is missing — callers should then fall back to the company name.
     *
     * @return string|null
     */
    public function logoFilePath(): ?string
    {
        if (! $this->logo_url) {
            return null;
        }

        $relative = ltrim(str_replace(\Illuminate\Support\Facades\Storage::disk('public')->url(''), '', $this->logo_url), '/');
        $absolute = storage_path('app/public/' . $relative);

        return is_file($absolute) ? $absolute : null;
    }

    /**
     * Get the copyright name: copyright_name if set, otherwise name.
     *
     * @return string
     */
    public function getCopyrightNameAttribute(?string $value): string
    {
        return $value ?? $this->name;
    }
}
