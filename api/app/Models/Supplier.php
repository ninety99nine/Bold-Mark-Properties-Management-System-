<?php

namespace App\Models;

use App\Enums\SupplierAccountType;
use App\Enums\SupplierPaymentType;
use App\Enums\SupplierStatus;
use App\Enums\SupplierType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Supplier extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'supplier_code'       => 'string',
        'reference'           => 'string',
        'name'                => 'string',
        'supplier_type'       => SupplierType::class,
        'status'              => SupplierStatus::class,
        'payment_type'        => SupplierPaymentType::class,
        'email'               => 'string',
        'alt_email'           => 'string',
        'phone'               => 'string',
        'alt_phone'           => 'string',
        'bank_name'           => 'string',
        'account_type'        => SupplierAccountType::class,
        'account_number'      => 'string',
        'branch_code'         => 'string',
        'branch_name'         => 'string',
        'vat_number'          => 'string',
        'registration_number' => 'string',
        'address'             => 'string',
        'address_line_1'      => 'string',
        'address_line_2'      => 'string',
        'suburb'              => 'string',
        'town'                => 'string',
        'postal_code'         => 'string',
        'is_active'           => 'boolean',
        'balance'             => 'float',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'supplier_code',
        'reference',
        'name',
        'supplier_type',
        'status',
        'payment_type',
        'email',
        'alt_email',
        'phone',
        'alt_phone',
        'bank_name',
        'account_type',
        'account_number',
        'branch_code',
        'branch_name',
        'vat_number',
        'registration_number',
        'address',
        'address_line_1',
        'address_line_2',
        'suburb',
        'town',
        'postal_code',
        'is_active',
        'balance',
        'supplier_group_id',
        'community_id',
        'organization_id',
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
        $query->where(function (Builder $query) use ($searchTerm) {
            $query->whereLike('name', $searchTerm)
                  ->orWhereLike('supplier_code', $searchTerm)
                  ->orWhereLike('reference', $searchTerm)
                  ->orWhereLike('account_number', $searchTerm)
                  ->orWhereLike('email', $searchTerm);
        });
    }

    /**
     * Scope suppliers visible to a community (community-specific or org-shared).
     *
     * @param Builder $query
     * @param string $communityId
     * @return void
     */
    #[Scope]
    protected function forCommunity(Builder $query, string $communityId): void
    {
        $query->where(function (Builder $query) use ($communityId) {
            $query->where('community_id', $communityId)
                  ->orWhereNull('community_id');
        });
    }

    /**
     * Scope to active suppliers only.
     *
     * @param Builder $query
     * @return void
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Get the community this supplier belongs to (null = organisation-wide).
     *
     * @return BelongsTo
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * Get the supplier group this supplier belongs to.
     *
     * @return BelongsTo
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(SupplierGroup::class, 'supplier_group_id');
    }

    /**
     * Get the occupant (organisation) this supplier belongs to.
     *
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the documents attached to this supplier.
     *
     * @return HasMany
     */
    public function documents(): HasMany
    {
        return $this->hasMany(SupplierDocument::class);
    }

    /**
     * Resolve route binding scoped to the authenticated user's organisation.
     *
     * @param mixed $value
     * @param string|null $field
     * @return self|null
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }
        return $this->where($field ?? $this->getRouteKeyName(), $value)
                    ->where('organization_id', $user->organization_id)
                    ->first();
    }
}
