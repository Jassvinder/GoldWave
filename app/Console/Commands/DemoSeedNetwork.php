<?php

namespace App\Console\Commands;

use App\Actions\Compensation\EvaluatePairMilestones;
use App\Actions\Draw\ExecuteMonthlyDraw;
use App\Actions\Draw\GenerateDrawGroups;
use App\Actions\DummyEntries\GenerateDailyDummyEntries;
use App\Actions\Emi\BookCurrentRate;
use App\Actions\Emi\QuoteCurrentRateBooking;
use App\Actions\Payments\ApproveCashPayment;
use App\Actions\Payments\InitiateEmiInstallmentPayment;
use App\Actions\Registration\RegisterMember;
use App\Actions\Settings\PublishRuleVersion;
use App\Actions\Store\AllocateStoreInventoryItem;
use App\Actions\Store\ConfirmStoreSale;
use App\Actions\Store\CreateStore;
use App\Actions\Store\RecordItemBuyback;
use App\Actions\Store\ResetStorePassword;
use App\Jobs\EvaluateMonthlyPairMilestones;
use App\Models\DrawGroup;
use App\Models\DrawGroupMonthConfig;
use App\Models\EmiInstallment;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\MetalRate;
use App\Models\Store;
use App\Models\User;
use App\Services\RuleVersionService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Dev/demo-only tool (not part of the DOMAIN_LOGIC.md/TASKS.md backlog,
 * which is fully complete) — populates the real dev database with a
 * realistic member network, driven entirely through the real
 * Registration/Payment/Store/Draw Actions (never raw Eloquent::create for
 * anything that should trigger compensation), so every income type
 * (Level Income, Pair/Reward, Booster, Draw + upline benefit,
 * Purchase/Repurchase, Store Profit Distribution) has real data to
 * inspect via the Super Admin Reports/Compensation Audit/Member
 * Management pages. Safe to run multiple times — each run is purely
 * additive, attaching new members onto the existing real network rather
 * than resetting anything. Intentionally kept in the codebase as a
 * reusable tool, not deleted after use.
 */
class DemoSeedNetwork extends Command
{
    protected $signature = 'demo:seed-network {count=500 : how many new members to create}';

    protected $description = 'Populate the dev database with a realistic member network exercising every income type.';

    /** @var array<int, string> */
    private array $sponsorPool = [];

    public function handle(
        RegisterMember $register,
        ApproveCashPayment $approveCash,
        InitiateEmiInstallmentPayment $initiateInstallment,
    ): int {
        // Running two instances at once produced real duplicate-email/
        // deadlock collisions during batch seeding (each instance computed
        // its own starting index independently) — guard against that
        // rather than relying on the caller never doing it again.
        $lock = Cache::lock('demo-seed-network', 3600);

        if (! $lock->get()) {
            $this->error('Another demo:seed-network run is already in progress.');

            return self::FAILURE;
        }

        try {
            return $this->executeSeeding($register, $approveCash, $initiateInstallment);
        } finally {
            $lock->release();
        }
    }

    private function executeSeeding(
        RegisterMember $register,
        ApproveCashPayment $approveCash,
        InitiateEmiInstallmentPayment $initiateInstallment,
    ): int {
        $operator = User::where('role', 'super_admin')->first();

        if (! $operator) {
            $this->error('No super_admin user found — cannot approve payments.');

            return self::FAILURE;
        }

        $count = (int) $this->argument('count');
        $plans = MembershipPlan::where('is_active', true)->get()->keyBy('code');

        $this->sponsorPool = Member::whereNotNull('customer_id')
            ->where('status', 'active')
            ->orderBy('id')
            ->pluck('customer_id')
            ->all();

        if (empty($this->sponsorPool)) {
            $this->error('No existing active member to sponsor off — seed at least one real member first.');

            return self::FAILURE;
        }

        $startIndex = User::where('email', 'like', 'demo%@goldwave.test')->count();
        $created = 0;
        $failed = 0;

        for ($i = 0; $i < $count; $i++) {
            $globalIndex = $startIndex + $i;

            try {
                $member = $this->registerOne($register, $plans, $globalIndex);
                $this->approveRegistration($approveCash, $member, $operator);
                $this->maybeAdvanceEmi($initiateInstallment, $approveCash, $member->fresh(), $operator);

                $this->sponsorPool[] = $member->fresh()->customer_id;
                $created++;
            } catch (Throwable $e) {
                $failed++;
                $this->warn("Member {$globalIndex} failed: {$e->getMessage()}");
            }

            if (($i + 1) % 50 === 0) {
                $this->info(($i + 1)." / {$count} processed ({$created} created, {$failed} failed)");
            }
        }

        $this->info("Registration pass done: {$created} created, {$failed} failed.");

        $this->runDummyEntries($operator);
        $this->runDraws();
        $this->runPairMilestones();
        $this->runStoreDemos($operator, $register, $approveCash);

        $this->info('Demo network seeding complete.');

        return self::SUCCESS;
    }

    /** @param  Collection<string, MembershipPlan>  $plans */
    private function registerOne(RegisterMember $register, Collection $plans, int $globalIndex): Member
    {
        $sponsorCode = $this->pickSponsor();
        $plan = $this->pickPlan($plans);

        return $register([
            'sponsor_code' => $sponsorCode,
            'placement_side' => random_int(0, 1) === 0 ? 'left' : 'right',
            'mobile' => (string) (9800000000 + $globalIndex),
            'email' => "demo{$globalIndex}@goldwave.test",
            'name' => "Demo Member {$globalIndex}",
            'gender' => random_int(0, 1) === 0 ? 'male' : 'female',
            'membership_plan_id' => $plan->id,
            'payment_mode' => 'cash',
        ]);
    }

    private function approveRegistration(ApproveCashPayment $approveCash, Member $member, User $operator): void
    {
        $payment = $member->payments()->where('type', 'registration')->firstOrFail();
        $approveCash($payment, $operator);

        // T-149 follow-up (24-09-2026, user-reported bug) — the name was previously built from
        // $globalIndex (a count of demo% emails), while the real Customer ID comes from a
        // separate, global counter (CustomerIdGenerator) that also advances for dummy entries,
        // store demo activity, etc. — the two drifted ("Demo Member 497" got "GWL498"). Renaming
        // here, from the *actually assigned* Customer ID after activation, makes the digits match
        // every time, regardless of anything else that touched the counter in between.
        $fresh = $member->fresh();
        $digits = substr((string) $fresh->customer_id, 3);
        $fresh->user->update(['name' => "Demo Member {$digits}"]);
    }

    private function maybeAdvanceEmi(
        InitiateEmiInstallmentPayment $initiateInstallment,
        ApproveCashPayment $approveCash,
        Member $member,
        User $operator,
    ): void {
        $schedule = $member->emiSchedule;

        if (! $schedule) {
            return; // One-time plan — nothing more to pay.
        }

        $roll = random_int(1, 100);
        $targetPaid = match (true) {
            $roll <= 55 => 1, // Just-joined — only the registration installment.
            $roll <= 85 => min($schedule->total_installments, random_int(2, 6)),
            default => $schedule->total_installments, // Fully completed.
        };

        // Installments can only be paid strictly in order (DOMAIN_LOGIC.md §5
        // item 8 — "no advance/skip-ahead payment"), and only once their
        // status is due/overdue (normally set by the real day-by-day
        // ProcessEmiDueStatuses scheduled job). For demo seeding only, force
        // each installment to 'due' immediately before paying it, one at a
        // time, rather than fast-forwarding real calendar time.
        $toPay = max(0, $targetPaid - 1);

        for ($n = 0; $n < $toPay; $n++) {
            $next = EmiInstallment::where('emi_schedule_id', $schedule->id)
                ->where('status', '!=', 'paid')
                ->orderBy('installment_no')
                ->first();

            if (! $next) {
                break;
            }

            if (! in_array($next->status, ['due', 'overdue'], true)) {
                $next->update(['status' => 'due']);
            }

            $payment = $initiateInstallment($member, $next, 'cash');
            $approveCash($payment, $operator);
        }

        // Registration is always Future Rate (T-116); about a third of members book at the Current Rate afterwards so the
        // demo data has both kinds (a fully paid schedule has nothing left to book and is skipped by the guard).
        if (random_int(1, 100) <= 35) {
            try {
                $quote = app(QuoteCurrentRateBooking::class)($member->fresh());
                app(BookCurrentRate::class)($member->fresh(), $quote['metal_rate_id'], $quote['paid_installments']);
            } catch (ValidationException) {
                // Not bookable (nothing left to pay, etc.) — stays on Future Rate.
            }
        }
    }

    private function pickSponsor(): string
    {
        $poolSize = count($this->sponsorPool);

        if ($poolSize > 50 && random_int(1, 100) <= 70) {
            $recentStart = $poolSize - 50;

            return $this->sponsorPool[random_int($recentStart, $poolSize - 1)];
        }

        return $this->sponsorPool[random_int(0, $poolSize - 1)];
    }

    /** @param  Collection<string, MembershipPlan>  $plans */
    private function pickPlan(Collection $plans): MembershipPlan
    {
        $weighted = ['A' => 20, 'B' => 20, 'C' => 20, 'D' => 15, 'E' => 15, 'F' => 10];
        $roll = random_int(1, array_sum($weighted));
        $cumulative = 0;

        foreach ($weighted as $code => $weight) {
            $cumulative += $weight;

            if ($roll <= $cumulative && $plans->has($code)) {
                return $plans->get($code);
            }
        }

        return $plans->first();
    }

    private function runDummyEntries(User $operator): void
    {
        // RuleVersionSeeder ships dummy entries switched off (spec-neutral); demo data wants a few so the S04/S05 pages have something to show.
        if (! (bool) app(RuleVersionService::class)->value('dummy_entry_enabled', false)) {
            app(PublishRuleVersion::class)(
                ['dummy_entry_enabled' => true, 'dummy_entry_daily_count' => 2],
                $operator,
                'Demo seeding: enabled daily dummy entries (2/day).',
            );
        }

        $created = app(GenerateDailyDummyEntries::class)();
        $this->info('Dummy entries generated: '.count($created));
    }

    private function runDraws(): void
    {
        $groups = app(GenerateDrawGroups::class)();
        $this->info('Draw groups generated: '.count($groups));

        // ExecuteMonthlyDraw deliberately never invents a prize (T-010) — a
        // real Super Admin must configure DrawGroupMonthConfig first. For
        // demo purposes only, auto-configure the next unconfigured month for
        // every active group so seeded draws actually produce winners.
        DrawGroup::where('status', 'active')->get()->each(function (DrawGroup $group) {
            $nextMonth = $group->executions()->count() + 1;

            if ($nextMonth > 20) {
                return;
            }

            DrawGroupMonthConfig::firstOrCreate(
                ['draw_group_id' => $group->id, 'cycle_month_no' => $nextMonth],
                [
                    'prize_name' => $nextMonth <= 15 ? 'Silver Prize' : 'Gold Prize',
                    'prize_value' => $nextMonth <= 15 ? 5000 : 50000,
                    'metal_type' => $nextMonth <= 15 ? 'silver' : 'gold',
                ],
            );
        });

        $executions = app(ExecuteMonthlyDraw::class)();
        $this->info('Draw executions: '.count($executions));
    }

    private function runPairMilestones(): void
    {
        app(EvaluateMonthlyPairMilestones::class)->handle(app(EvaluatePairMilestones::class));
        $this->info('Pair/Reward milestone evaluation run.');
    }

    /**
     * Demo stores, each owned by a real network Member (raised to `admin`) so Store Profit Distribution fires. Each store
     * gets a Store ID login (password = its own Store ID, same convention as a Member's initial password), gold + silver
     * inventory, a mix of walk-in and member sales (new sale / purchase / repurchase) and one buyback.
     */
    private function runStoreDemos(User $operator, RegisterMember $register, ApproveCashPayment $approveCash): void
    {
        $definitions = [
            ['Demo Jewellers', 'Demo City', '9990000000', '9990000099', 500000, 200000],
            ['Sunrise Gold House', 'Mumbai', '9990000001', '9990000098', 800000, 300000],
            ['Silver Line Jewels', 'Jaipur', '9990000002', '9990000097', 400000, 150000],
            ['Royal Ornaments', 'Delhi', '9990000003', '9990000096', 600000, 250000],
        ];

        foreach ($definitions as $index => [$name, $location, $contact, $ownerMobile, $allocation, $advance]) {
            $store = Store::where('name', $name)->first() ?? $this->createStoreWithOwner(
                $operator, $register, $approveCash, $index + 1, $name, $location, $contact, $ownerMobile, $allocation, $advance,
            );

            $this->runStoreActivity($store, $operator);
        }
    }

    private function createStoreWithOwner(
        User $operator,
        RegisterMember $register,
        ApproveCashPayment $approveCash,
        int $number,
        string $name,
        string $location,
        string $contact,
        string $ownerMobile,
        float $allocation,
        float $advance,
    ): Store {
        // A Store Owner must also be a full network Member (DOMAIN_LOGIC.md
        // §2/§21) — Store::ownerMember() resolving to null makes
        // CalculateStoreProfitDistribution bail out entirely with zero
        // rows, so the owner is registered as a real Member first, same
        // as any other member, then the resulting user's role is raised
        // to admin.
        $plan = MembershipPlan::where('code', 'E')->firstOrFail();
        $ownerMember = $register([
            'sponsor_code' => $this->pickSponsor(),
            'placement_side' => $number % 2 === 0 ? 'right' : 'left',
            'mobile' => $ownerMobile,
            'email' => "storeowner{$number}@goldwave.test",
            'name' => "{$name} Owner",
            'gender' => 'male',
            'membership_plan_id' => $plan->id,
            'payment_mode' => 'cash',
        ]);
        $approveCash($ownerMember->payments()->where('type', 'registration')->firstOrFail(), $operator);
        $admin = $ownerMember->user;
        $admin->update(['role' => 'store_admin']);

        $store = app(CreateStore::class)($name, $admin, $contact, $location, $allocation, $advance, $operator);

        // Store ID login (T-117): initial password = the Store ID itself — reset it from Store Management.
        app(ResetStorePassword::class)($store, (string) $store->store_code, $operator);

        return $store;
    }

    private function runStoreActivity(Store $store, User $operator): void
    {
        $goldChain = app(AllocateStoreInventoryItem::class)($store, 'Gold Chain', 'gold', 10, 50, 65000, $operator);
        $silverAnklet = app(AllocateStoreInventoryItem::class)($store, 'Silver Anklet', 'silver', 50, 40, 17500, $operator);

        $memberIds = Member::whereNotNull('customer_id')->inRandomOrder()->limit(8)->pluck('id');
        $types = ['new_sale', 'purchase', 'repurchase'];

        foreach ($memberIds as $index => $memberId) {
            // Alternate walk-in sales (no member, so no Purchase/Repurchase income) with member sales.
            $member = $index % 2 === 0 ? Member::find((int) $memberId) : null;
            $isGold = $index % 2 === 0;
            $item = $isGold ? $goldChain : $silverAnklet;

            try {
                app(ConfirmStoreSale::class)(
                    $store,
                    $member,
                    $member !== null ? $types[intdiv($index, 2) % 3] : 'new_sale',
                    $item->item_name,
                    $item,
                    (float) $item->weight,
                    1,
                    $isGold ? 6500 : 350,
                    (float) $item->price,
                    0,
                    'cash',
                    $operator,
                );
            } catch (Throwable $e) {
                $this->warn("Store sale skipped ({$store->name}): {$e->getMessage()}");
            }
        }

        $goldRate = MetalRate::where('metal', 'gold')->orderByDesc('effective_from')->first();
        $buybackMember = Member::whereNotNull('customer_id')->inRandomOrder()->first();

        if ($goldRate && $buybackMember) {
            try {
                app(RecordItemBuyback::class)(
                    $store,
                    $buybackMember,
                    'Old Gold Ring',
                    'gold',
                    5,
                    1,
                    'Demo buyback for seeded data',
                    $goldRate,
                    $operator,
                );
            } catch (Throwable $e) {
                $this->warn("Buyback skipped ({$store->name}): {$e->getMessage()}");
            }
        }

        $this->info("Store '{$store->name}' ({$store->store_code}) set up with inventory, sales and a buyback.");
    }
}
