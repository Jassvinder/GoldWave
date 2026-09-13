<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DOMAIN_LOGIC.md §6 (Level Income) and §15 (Purchase/Repurchase Upline
 * Income) share this one shape, distinguished by `type` — see the migration
 * docblock for why.
 *
 * @property-read Member|null $beneficiary
 * @property-read Payment|null $sourcePayment
 * @property-read RuleVersion $ruleVersion
 */
class IncomeLedgerCalculation extends Model
{
    protected $fillable = [
        'type',
        'source_payment_id',
        'source_store_sale_id',
        'beneficiary_member_id',
        'level_no',
        'rate_percent',
        'amount',
        'rule_version_id',
        'eligibility_status',
        'skip_reason',
    ];

    protected function casts(): array
    {
        return [
            'rate_percent' => 'decimal:3',
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'beneficiary_member_id');
    }

    /** @return BelongsTo<Payment, $this> */
    public function sourcePayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'source_payment_id');
    }

    /** @return BelongsTo<RuleVersion, $this> */
    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(RuleVersion::class);
    }
}
