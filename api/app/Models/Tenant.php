<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tenant extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_active'   => 'boolean',
        'credentials' => 'array',
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
        'logo_url',
        'is_active',
        'contact_email',
        'contact_phone',
        'address',
        'country',
        'currency',
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
        $query->where('name', 'like', '%' . $searchTerm . '%')
              ->orWhere('company_name', 'like', '%' . $searchTerm . '%')
              ->orWhere('contact_email', 'like', '%' . $searchTerm . '%');
    }

    /**
     * Get users belonging to this tenant.
     *
     * @return HasMany
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get charge types belonging to this tenant.
     *
     * @return HasMany
     */
    public function chargeTypes(): HasMany
    {
        return $this->hasMany(ChargeType::class);
    }

    /**
     * Get estates belonging to this tenant.
     *
     * @return HasMany
     */
    public function estates(): HasMany
    {
        return $this->hasMany(Estate::class);
    }

    /**
     * Get units belonging to this tenant.
     *
     * @return HasMany
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /**
     * Get owners belonging to this tenant.
     *
     * @return HasMany
     */
    public function owners(): HasMany
    {
        return $this->hasMany(Owner::class);
    }

    /**
     * Get unit tenants (property occupants) belonging to this tenant.
     *
     * @return HasMany
     */
    public function unitTenants(): HasMany
    {
        return $this->hasMany(UnitTenant::class);
    }

    /**
     * Get invoices belonging to this tenant.
     *
     * @return HasMany
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get cashbook entries belonging to this tenant.
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
     * Get the copyright name: copyright_name if set, otherwise name.
     *
     * @return string
     */
    public function getCopyrightNameAttribute(?string $value): string
    {
        return $value ?? $this->name;
    }
}
