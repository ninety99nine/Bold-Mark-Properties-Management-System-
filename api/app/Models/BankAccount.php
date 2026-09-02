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
        'type'                   => BankAccountType::class,
        'balance'                => 'float',
        'balance_as_at'          => 'date',
        'is_active'              => 'boolean',
        'is_default'             => 'boolean',
        'tenant_billing_account' => 'boolean',
        'opening_balance'        => 'float',
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
        'is_default',
        'tenant_billing_account',
        'opening_balance',
        'ledger_id',
        'organization_id',
        'community_id',
    ];

    /**
     * The GL ledger (8000/00n Bank account) backing this bank account.
     *
     * @return BelongsTo
     */
    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    /**
     * The community this bank account belongs to.
     *
     * @return BelongsTo
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
