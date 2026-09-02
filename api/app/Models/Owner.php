<?php

namespace App\Models;

use App\Enums\OwnerEntityType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Owner extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'full_name'           => 'string',
        'email'               => 'string',
        'phone'               => 'string',
        'landline'            => 'string',
        'id_number'           => 'string',
        'entity_type'         => OwnerEntityType::class,
        'secondary_emails'    => 'array',
        'is_primary'          => 'boolean',
        'user_verified'       => 'boolean',
        'is_disabled'         => 'boolean',
        'disabled_at'         => 'datetime',
        'debit_order'         => 'boolean',
        'monthly_plus_amount' => 'float',
        'collection_day'      => 'integer',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'full_name',
        'is_primary',
        'is_disabled',
        'disabled_at',
        'user_verified',
        'user_display_name',
        'email',
        'secondary_emails',
        'phone',
        'landline',
        'id_number',
        'entity_type',
        'trust_name',
        'trust_reg',
        'cc_name',
        'cc_reg_no',
        'pty_name',
        'pty_reg_no',
        'body_corporate_name',
        'body_corporate_reg_no',
        'contact2_name',
        'contact2_email',
        'contact2_phone',
        'contact2_landline',
        'customer_type',
        'vat_no',
        'alt_email',
        'alt_phone',
        'payment_type',
        'pdf_password',
        'customer_group',
        'reference',
        'country',
        'old_customer_code',
        'customer_code',
        'address',
        'address_line_2',
        'suburb',
        'town',
        'postal_code',
        'account_holder',
        'bank_name',
        'account_type',
        'account_number',
        'branch_code',
        'branch_name',
        'debit_order',
        'mandate_type',
        'monthly_plus_amount',
        'collection_day',
        'notes',
        'unit_id',
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
            $query->whereLike('full_name', $searchTerm)
                  ->orWhereLike('email', $searchTerm)
                  ->orWhereLike('phone', $searchTerm)
                  ->orWhereLike('id_number', $searchTerm)
                  ->orWhereLike('customer_code', $searchTerm)
                  ->orWhereLike('reference', $searchTerm);
        });
    }

    /**
     * Scope customers belonging to a community (directly or via their unit).
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
                  ->orWhereHas('unit', fn (Builder $unit) => $unit->where('community_id', $communityId));
        });
    }

    /**
     * Scope to active (non-disabled) customers.
     *
     * @param Builder $query
     * @return void
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_disabled', false);
    }

    /**
     * Scope to disabled customers.
     *
     * @param Builder $query
     * @return void
     */
    #[Scope]
    protected function disabled(Builder $query): void
    {
        $query->where('is_disabled', true);
    }

    /**
     * Get the unit this owner owns.
     *
     * @return BelongsTo
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the community this customer belongs to (for standalone customers).
     *
     * @return BelongsTo
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * Get the occupant (organisation) this owner belongs to.
     *
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get invoices billed to this owner.
     *
     * @return HasMany
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'billed_to_id')
                    ->where('billed_to_type', 'owner');
    }

    /**
     * Get the customer groups this customer belongs to.
     *
     * @return BelongsToMany
     */
    public function customerGroups(): BelongsToMany
    {
        return $this->belongsToMany(CustomerGroup::class, 'customer_group_owner')
                    ->withTimestamps();
    }

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
