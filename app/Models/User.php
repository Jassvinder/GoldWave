<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\MemberIds;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $mobile
 * @property CarbonImmutable|null $contact_changed_at
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string $role
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Member|null $member
 * @property-read Collection<int, Member> $members
 * @property-read Store|null $store
 */
#[Fillable(['name', 'email', 'mobile', 'password', 'role'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'contact_changed_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * GoldWave member record for this login identity — null for a pure Admin/Super Admin user.
     *
     * T-251 (DOMAIN_LOGIC.md §3.4) — a person may hold several IDs. This is the one they work on: for the signed-in
     * user the ID chosen with Switch ID (`MemberIds`), otherwise their first active ID, otherwise their first ID. A
     * person with one ID always gets that ID, exactly as before. Eager loading (`with('member')`) builds the relation
     * on a blank model, so it never sees the selection: read the signed-in user's ID through `$user->member`.
     *
     * @return HasOne<Member, $this>
     */
    public function member(): HasOne
    {
        $relation = $this->hasOne(Member::class);
        $selectedId = MemberIds::selectedIdFor($this);

        if ($selectedId !== null) {
            $relation->orderByRaw('CASE WHEN members.id = ? THEN 0 ELSE 1 END', [$selectedId]);
        }

        return $relation
            ->orderByRaw("CASE WHEN members.status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('members.id');
    }

    /**
     * T-251 — every member ID this person holds, first one first.
     *
     * @return HasMany<Member, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class)->orderBy('members.id');
    }

    /**
     * The store this Admin/Store Owner owns — null for a Member or Super Admin login (T-017).
     *
     * @return HasOne<Store, $this>
     */
    public function store(): HasOne
    {
        return $this->hasOne(Store::class, 'owner_user_id');
    }

    /**
     * DOMAIN_LOGIC.md §2 (29-09-2026) — Super Admin or the company Admin: both run the company-side portal (the Admin
     * without a few Super-Admin-only pages) and receive the company alerts.
     */
    public function isCompanyStaff(): bool
    {
        return in_array($this->role, ['super_admin', 'admin'], true);
    }

    /** The Store Owner role (`store_admin`, shown as "Store Admin"). */
    public function isStoreAdmin(): bool
    {
        return $this->role === 'store_admin';
    }

    /**
     * DOMAIN_LOGIC.md §12.2(d) (T-215) — when the 48-hour wallet-payment hold that follows an email/mobile change
     * ends, or null when there is no hold right now.
     */
    public function walletPaymentHoldUntil(): ?CarbonImmutable
    {
        if ($this->contact_changed_at === null) {
            return null;
        }

        $until = CarbonImmutable::parse($this->contact_changed_at)->addHours((int) config('otp.contact_change_hold_hours'));

        return $until->isFuture() ? $until : null;
    }
}
