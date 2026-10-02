<?php

namespace App\Actions\DummyEntries;

use App\Actions\Registration\CalculateEmiRateBooking;
use App\Models\EmiInstallment;
use App\Models\EmiSchedule;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Services\BinaryPlacementResolver;
use App\Services\CustomerIdGenerator;
use App\Services\RuleVersionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * DOMAIN_LOGIC.md §14.1/§14.2 — reads Super Admin's enable toggle/daily
 * count; if disabled or zero, creates nothing. Otherwise generates that many
 * dummy `members` rows, each placed on the Right side of the seeded company
 * root Member (DOMAIN_LOGIC.md §21 T-013 pre-coding pass, user-confirmed
 * placement anchor) via the same occupied-side-traversal
 * `BinaryPlacementResolver` real registration uses — this naturally
 * continues down whichever node currently sits at the bottom of the chain,
 * whether that's a still-unassigned dummy or an already-assigned real
 * leader, with no separate "most recently assigned leader" tracking needed
 * (§14.3's "continues on the Right side of that assigned leader" falls out
 * automatically). Each entry also gets the root as its `sponsor_id` — the
 * Sponsor/Direct sense of "the company's Direct" — and a real sequential
 * Customer ID (§14.3: "their Customer IDs are genuine and valid").
 *
 * T-149 (22-09-2026, user decision) — every dummy entry is also created on
 * the configured EMI plan (`dummy_entry_plan_code`, default Plan A), with
 * installment #1 recorded as an already-paid cash payment: it exists purely
 * so the entry's EMI schedule has a real starting point, is never queued for
 * Super Admin's manual Cash Payments approval, and deliberately never fires
 * `PaymentConfirmed` — a placeholder/company-seeded entry must never trigger
 * real compensation for real upline members (the same principle already
 * enforced for these entries as *beneficiaries* in `CreatePairEntries`/
 * `EvaluateBoosterQualification`, extended here to the *payer* side). No
 * further installment exists yet — DOMAIN_LOGIC.md §14.3 "no next EMI is
 * paid until a leader is assigned" falls out naturally from installments 2+
 * simply not being generated until `AssignDummyEntryToLeader` does so.
 */
class GenerateDailyDummyEntries
{
    public function __construct(
        private readonly BinaryPlacementResolver $placementChain,
        private readonly RuleVersionService $rules,
        private readonly CustomerIdGenerator $customerIds,
        private readonly CalculateEmiRateBooking $calculateEmiRateBooking,
    ) {}

    /** @return array<int, Member> */
    public function __invoke(): array
    {
        if (! $this->rules->value('dummy_entry_enabled', false)) {
            return [];
        }

        $count = (int) $this->rules->value('dummy_entry_daily_count', 0);

        if ($count <= 0) {
            return [];
        }

        $root = Member::where('is_company_root', true)->firstOrFail();
        $planCode = (string) $this->rules->value('dummy_entry_plan_code', 'A');
        $plan = MembershipPlan::where('code', $planCode)->where('is_active', true)->firstOrFail();

        return DB::transaction(function () use ($root, $count, $plan) {
            $created = [];

            for ($i = 0; $i < $count; $i++) {
                $placement = $this->placementChain->resolve($root, 'right');

                $created[] = $this->createEntry($root, $plan, $placement['parent_id'], $placement['side']);
            }

            return $created;
        });
    }

    /**
     * Creates one dummy entry (sponsor = the company root) with its silent paid installment #1, ending `unassigned`.
     * Also used by `InsertEntryUnderRoot` (T-174), which creates it unplaced first and then slots it under the root.
     * Callers wrap it in their own transaction.
     */
    public function createEntry(Member $root, MembershipPlan $plan, ?int $parentId, ?string $side, bool $benefitsLimited = false): Member
    {
        $customerId = $this->customerIds->next();

        $dummy = Member::create([
            'customer_id' => $customerId,
            'sponsor_id' => $root->id,
            'placement_parent_id' => $parentId,
            'placement_side' => $side,
            'membership_plan_id' => $plan->id,
            'status' => 'active',
            'is_company_dummy' => true,
            'dummy_status' => 'generated',
            'dummy_generated_at' => now(),
            'placeholder_name' => "Company Direct — {$customerId}",
            'benefits_limited' => $benefitsLimited,
        ]);

        $this->seedFirstInstallment($dummy, $plan);

        $dummy->update(['dummy_status' => 'unassigned']);

        return $dummy->fresh() ?? $dummy;
    }

    private function seedFirstInstallment(Member $dummy, MembershipPlan $plan): void
    {
        $rateBooking = ($this->calculateEmiRateBooking)($plan, 'future_rate');

        $schedule = EmiSchedule::create([
            'member_id' => $dummy->id,
            'membership_plan_id' => $plan->id,
            'total_installments' => $plan->installment_count,
            'rate_booking_method' => 'future_rate',
            'installment_amount' => $rateBooking['installment_amount'],
            'future_commitment_amount' => $rateBooking['future_commitment_amount'],
        ]);

        // Deliberately created directly as `paid`, never through InitiateEmiInstallmentPayment/ApproveCashPayment, so
        // it never enters the Cash Payments queue and never dispatches PaymentConfirmed (see class docblock).
        $payment = Payment::create([
            'member_id' => $dummy->id,
            'type' => 'registration',
            'amount' => $rateBooking['installment_amount'],
            'mode' => 'cash',
            'status' => 'paid',
            'cash_status' => 'approved',
            'idempotency_key' => (string) Str::uuid(),
            'paid_at' => now(),
        ]);

        EmiInstallment::create([
            'emi_schedule_id' => $schedule->id,
            'installment_no' => 1,
            'due_date' => now()->toDateString(),
            'amount' => $rateBooking['installment_amount'],
            'status' => 'paid',
            'payment_id' => $payment->id,
        ]);
    }
}
