<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceTemplate extends Model
{
    use HasUuids;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_default' => 'boolean',
        'is_system'  => 'boolean',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'country',
        'is_default',
        'is_system',
        'organization_id',
    ];

    /**
     * Scope a query by search term.
     */
    #[Scope]
    protected function search(Builder $query, string $searchTerm): void
    {
        $query->where('name', 'ilike', '%' . $searchTerm . '%');
    }

    /**
     * Get the tenant (organisation) this template belongs to.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get all template items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ComplianceTemplateItem::class)->orderBy('sort_order');
    }
}
