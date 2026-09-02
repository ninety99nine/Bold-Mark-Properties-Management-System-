<?php

namespace App\Models;

use App\Enums\JournalLineType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AllocationRule extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'description_starts_with' => 'string',
        'description_contains'    => 'string',
        'ledger_type'             => JournalLineType::class,
        'vat_type'                => 'string',
        'remarks'                 => 'string',
        'apply_to_positive'       => 'boolean',
        'apply_to_negative'       => 'boolean',
        'sort_order'              => 'integer',
        'bank_account_ids'        => 'array',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'description_starts_with',
        'description_contains',
        'ledger_type',
        'vat_type',
        'remarks',
        'apply_to_positive',
        'apply_to_negative',
        'sort_order',
        'bank_account_ids',
        'ledger_id',
        'unit_id',
        'supplier_id',
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
            $query->whereLike('description_starts_with', $searchTerm)
                  ->orWhereLike('description_contains', $searchTerm)
                  ->orWhereLike('remarks', $searchTerm);
        });
    }

    /**
     * Get the ledger (general / reserve fund account) this rule allocates to.
     *
     * @return BelongsTo
     */
    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    /**
     * Get the unit (customer account) this rule allocates to.
     *
     * @return BelongsTo
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the supplier account this rule allocates to.
     *
     * @return BelongsTo
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the community this rule belongs to.
     *
     * @return BelongsTo
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * Get the occupant (organisation) this rule belongs to.
     *
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
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
