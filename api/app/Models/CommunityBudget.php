<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CommunityBudget extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'year'     => 'integer',
        'jan'      => 'float',
        'feb'      => 'float',
        'mar'      => 'float',
        'apr'      => 'float',
        'may'      => 'float',
        'jun'      => 'float',
        'jul'      => 'float',
        'aug'      => 'float',
        'sep'      => 'float',
        'oct'      => 'float',
        'nov'      => 'float',
        'dec'      => 'float',
        'per_year' => 'float',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'year',
        'jan',
        'feb',
        'mar',
        'apr',
        'may',
        'jun',
        'jul',
        'aug',
        'sep',
        'oct',
        'nov',
        'dec',
        'per_year',
        'community_id',
        'ledger_id',
        'organization_id',
    ];

    /**
     * Get the community this budget line belongs to.
     *
     * @return BelongsTo
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * Get the ledger (chart-of-accounts line) this budget is for.
     *
     * @return BelongsTo
     */
    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    /**
     * Get the occupant (organisation) this budget belongs to.
     *
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
