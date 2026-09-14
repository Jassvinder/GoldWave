<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DOMAIN_LOGIC.md §16.4 — one row per beneficiary (store_owner,
 * sponsor_level_1..3) per confirmed store sale.
 *
 * @property-read StoreSale|null $storeSale
 * @property-read Member|null $beneficiary
 * @property-read User|null $beneficiaryUser
 * @property-read RuleVersion|null $ruleVersion
 */
class StoreProfitDistribution extends Model
{
    protected $fillable = [
        'store_sale_id',
        'beneficiary_type',
        'beneficiary_member_id',
        'beneficiary_user_id',
        'rate_percent',
        'amount',
        'rule_version_id',
    ];

    protected function casts(): array
    {
        return [
            'rate_percent' => 'decimal:3',
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<StoreSale, $this> */
    public function storeSale(): BelongsTo
    {
        return $this->belongsTo(StoreSale::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'beneficiary_member_id');
    }

    /** @return BelongsTo<User, $this> */
    public function beneficiaryUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beneficiary_user_id');
    }

    /** @return BelongsTo<RuleVersion, $this> */
    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(RuleVersion::class);
    }
}
