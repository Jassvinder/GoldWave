<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;

/**
 * @property-read User|null $user
 * @property-read Member|null $sponsor
 * @property-read Member|null $placementParent
 * @property-read MembershipPlan|null $membershipPlan
 * @property-read User|null $activatedBy
 * @property-read Collection<int, Member> $directs
 * @property-read Collection<int, Member> $placementChildren
 * @property-read Collection<int, Payment> $payments
 * @property-read EmiSchedule|null $emiSchedule
 * @property-read Collection<int, ProductBenefit> $productBenefits
 * @property-read Collection<int, PairEntry> $pairEntries
 * @property-read Collection<int, MemberBankDetail> $bankDetails
 * @property-read Collection<int, PayoutRequest> $payoutRequests
 * @property-read Collection<int, DrawGroupMember> $drawGroupMemberships
 * @property-read Collection<int, BoosterQualification> $boosterQualifications
 * @property-read Collection<int, ProfileChangeRequest> $profileChangeRequests
 * @property-read Collection<int, IncomeLedgerCalculation> $incomeLedgerCalculations
 * @property-read Collection<int, WalletLedgerEntry> $walletLedgerEntries
 * @property-read Collection<int, PairRewardTransaction> $pairRewardTransactions
 * @property-read Collection<int, StoreProfitDistribution> $storeProfitDistributions
 */
class Member extends Model
{
    use Notifiable;

    protected $fillable = [
        'user_id',
        'customer_id',
        'sponsor_id',
        'placement_parent_id',
        'placement_side',
        'membership_plan_id',
        'status',
        'activated_at',
        'activated_by',
        'registered_via_store_id',
        'is_company_dummy',
        'dummy_status',
        'dummy_generated_at',
        'dummy_assigned_at',
        'dummy_assigned_by',
        'is_company_root',
        'placeholder_name',
        'pan_card',
        'aadhaar_card',
        'profile_photo_path',
        'address',
        'pending_fields_submitted_at',
        'wallet_balance',
        'wallet_hold_amount',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'dummy_generated_at' => 'datetime',
            'dummy_assigned_at' => 'datetime',
            'pending_fields_submitted_at' => 'datetime',
            'is_company_dummy' => 'boolean',
            'is_company_root' => 'boolean',
            'wallet_balance' => 'decimal:2',
            'wallet_hold_amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The Sponsor/Direct relationship — never used interchangeably with placement (DOMAIN_LOGIC.md §0/§4).
     *
     * @return BelongsTo<Member, $this>
     */
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'sponsor_id');
    }

    /** @return HasMany<Member, $this> */
    public function directs(): HasMany
    {
        return $this->hasMany(Member::class, 'sponsor_id');
    }

    /**
     * The Binary Position relationship — never used interchangeably with sponsor (DOMAIN_LOGIC.md §0/§4).
     *
     * @return BelongsTo<Member, $this>
     */
    public function placementParent(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'placement_parent_id');
    }

    /** @return HasMany<Member, $this> */
    public function placementChildren(): HasMany
    {
        return $this->hasMany(Member::class, 'placement_parent_id');
    }

    /** @return BelongsTo<MembershipPlan, $this> */
    public function membershipPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class);
    }

    /** @return BelongsTo<User, $this> */
    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasOne<EmiSchedule, $this> */
    public function emiSchedule(): HasOne
    {
        return $this->hasOne(EmiSchedule::class);
    }

    /** @return HasMany<ProductBenefit, $this> */
    public function productBenefits(): HasMany
    {
        return $this->hasMany(ProductBenefit::class);
    }

    /** @return HasMany<PairEntry, $this> */
    public function pairEntries(): HasMany
    {
        return $this->hasMany(PairEntry::class);
    }

    /** @return HasMany<MemberBankDetail, $this> */
    public function bankDetails(): HasMany
    {
        return $this->hasMany(MemberBankDetail::class);
    }

    /** @return HasMany<PayoutRequest, $this> */
    public function payoutRequests(): HasMany
    {
        return $this->hasMany(PayoutRequest::class);
    }

    /** @return HasMany<DrawGroupMember, $this> */
    public function drawGroupMemberships(): HasMany
    {
        return $this->hasMany(DrawGroupMember::class);
    }

    /** @return HasMany<BoosterQualification, $this> */
    public function boosterQualifications(): HasMany
    {
        return $this->hasMany(BoosterQualification::class);
    }

    /** @return HasMany<ProfileChangeRequest, $this> */
    public function profileChangeRequests(): HasMany
    {
        return $this->hasMany(ProfileChangeRequest::class);
    }

    /** @return HasMany<IncomeLedgerCalculation, $this> */
    public function incomeLedgerCalculations(): HasMany
    {
        return $this->hasMany(IncomeLedgerCalculation::class, 'beneficiary_member_id');
    }

    /** @return HasMany<WalletLedgerEntry, $this> */
    public function walletLedgerEntries(): HasMany
    {
        return $this->hasMany(WalletLedgerEntry::class);
    }

    /** @return HasMany<PairRewardTransaction, $this> */
    public function pairRewardTransactions(): HasMany
    {
        return $this->hasMany(PairRewardTransaction::class);
    }

    /** @return HasMany<StoreProfitDistribution, $this> */
    public function storeProfitDistributions(): HasMany
    {
        return $this->hasMany(StoreProfitDistribution::class, 'beneficiary_member_id');
    }
}
