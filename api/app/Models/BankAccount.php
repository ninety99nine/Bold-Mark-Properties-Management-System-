<?php

namespace App\Models;

use App\Enums\BankAccountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAccount extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'type'          => BankAccountType::class,
        'balance'       => 'float',
        'balance_as_at' => 'date',
        'is_active'     => 'boolean',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'bank_name',
        'account_number',
        'branch_code',
        'branch_name',
        'integration',
        'type',
        'balance',
        'balance_as_at',
        'is_active',
        'organization_id',
        'community_id',
    ];

    /**
     * The community this bank account belongs to.
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * The organization (SaaS tenant) this bank account belongs to.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
