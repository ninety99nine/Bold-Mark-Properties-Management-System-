<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityBillingSetup extends Model
{
    use HasFactory, HasUuids;

    /**
     * The charge → ledger mapping columns (WeConnectU Default Billing Setup).
     *
     * @var array<string>
     */
    public const LEDGER_FIELDS = [
        'levies_ledger_id',
        'reserve_fund_levies_ledger_id',
        'reserve_fund_ledger_id',
        'arrears_interest_ledger_id',
        'water_ledger_id',
        'sewerage_ledger_id',
        'rule_enforcement_income_ledger_id',
        'arrear_admin_ledger_id',
        'debt_collecting_ledger_id',
        'admin_fees_ledger_id',
        'electricity_ledger_id',
        'insurance_ledger_id',
        'csos_ledger_id',
        'ratio_1_ledger_id',
        'ratio_2_ledger_id',
        'ratio_3_ledger_id',
        'ratio_4_ledger_id',
        'ratio_5_ledger_id',
        'bank_charges_ledger_id',
    ];

    /**
     * The boolean toggle columns.
     *
     * @var array<string>
     */
    public const BOOLEAN_FIELDS = [
        'ratio_1_csos_exempt',
        'ratio_2_csos_exempt',
        'ratio_3_csos_exempt',
        'ratio_4_csos_exempt',
        'ratio_5_csos_exempt',
        'apply_csos_levy',
        'occupant_billing',
        'water_recovery',
        'electricity_recovery',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'ratio_1_csos_exempt'  => 'boolean',
        'ratio_2_csos_exempt'  => 'boolean',
        'ratio_3_csos_exempt'  => 'boolean',
        'ratio_4_csos_exempt'  => 'boolean',
        'ratio_5_csos_exempt'  => 'boolean',
        'apply_csos_levy'      => 'boolean',
        'occupant_billing'     => 'boolean',
        'water_recovery'       => 'boolean',
        'electricity_recovery' => 'boolean',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @return array<string>
     */
    protected function fillableFromColumns(): array
    {
        return array_merge(self::LEDGER_FIELDS, self::BOOLEAN_FIELDS, ['community_id', 'organization_id']);
    }

    public function __construct(array $attributes = [])
    {
        $this->fillable = $this->fillableFromColumns();
        parent::__construct($attributes);
    }

    /**
     * The community this billing setup belongs to.
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * The organization (SaaS tenant) this billing setup belongs to.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
