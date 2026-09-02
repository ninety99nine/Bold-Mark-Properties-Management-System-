<?php

namespace App\Models;

use App\Enums\ComplianceItemPriority;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceTemplateItem extends Model
{
    use HasUuids;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'priority'          => ComplianceItemPriority::class,
        'default_month_due' => 'integer',
        'sort_order'        => 'integer',
        'is_recurring'      => 'boolean',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'category',
        'description',
        'priority',
        'default_month_due',
        'sort_order',
        'is_recurring',
        'compliance_template_id',
    ];

    /**
     * Get the template this item belongs to.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ComplianceTemplate::class, 'compliance_template_id');
    }
}
