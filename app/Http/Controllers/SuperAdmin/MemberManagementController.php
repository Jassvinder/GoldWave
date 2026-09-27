<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\Admin\ResetMemberPassword;
use App\Actions\Admin\UpdateMemberDetails;
use App\Actions\Emi\RevertCurrentRateBooking;
use App\Actions\Profile\VerifyMemberBankDetail;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\ResetMemberPasswordRequest;
use App\Http\Requests\SuperAdmin\RevertCurrentRateBookingRequest;
use App\Http\Requests\SuperAdmin\UpdateMemberDetailsRequest;
use App\Models\EmiRateBookingEvent;
use App\Models\EmiSchedule;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Services\MemberNetworkSummary;
use App\Support\Dates;
use App\Support\WebpImageStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** INSTRUCTIONS.md's Admin Member Management — company-wide member list + detail. */
class MemberManagementController extends Controller
{
    public function __construct(private readonly MemberNetworkSummary $networkSummary) {}

    public function index(Request $request): Response
    {
        $query = Member::query()
            ->with(['user.store', 'membershipPlan', 'sponsor', 'placementParent'])
            ->withCount('directs')
            ->where('is_company_dummy', false);

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('customer_id', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('mobile', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('plan')) {
            $query->whereHas('membershipPlan', fn ($q) => $q->where('code', $request->string('plan')->toString()));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->boolean('store_owner')) {
            $query->whereHas('user.store');
        }

        $members = $query->orderByDesc('id')->paginate(25)->withQueryString();

        $network = $this->networkSummary->forMany($members->getCollection()->pluck('id')->all());

        $members->through(fn (Member $member): array => array_merge($this->summarize($member), [
            'directs_count' => $member->directs_count,
            'placement_side' => $member->placement_side,
            'placement_parent_customer_id' => $member->placementParent?->customer_id,
            'team_left' => $network[$member->id]['left']['total'],
            'team_right' => $network[$member->id]['right']['total'],
            'team_total' => $network[$member->id]['team_total'],
            'is_store_owner' => $member->user?->store !== null,
        ]));

        return Inertia::render('super-admin/member-management', [
            'members' => $members,
            'filters' => $request->only(['search', 'plan', 'status', 'store_owner']),
            'plan_options' => MembershipPlan::orderBy('code')->pluck('code'),
            'status_options' => ['draft', 'payment_pending', 'payment_confirmed', 'active', 'cancelled'],
            'stats' => [
                'total' => Member::where('is_company_dummy', false)->count(),
                'active' => Member::where('is_company_dummy', false)->where('status', 'active')->count(),
                'pending' => Member::where('is_company_dummy', false)->whereIn('status', ['payment_pending', 'payment_confirmed'])->count(),
                'inactive' => Member::where('is_company_dummy', false)->whereIn('status', ['draft', 'cancelled'])->count(),
            ],
        ]);
    }

    public function export(Request $request): HttpResponse
    {
        $rows = Member::with(['user', 'membershipPlan', 'sponsor'])
            ->where('is_company_dummy', false)
            ->orderBy('id')
            ->get()
            ->map(fn (Member $member) => [
                $member->customer_id,
                $member->user?->name,
                $member->user?->mobile,
                $member->user?->email,
                $member->membershipPlan?->code,
                $member->sponsor?->customer_id,
                $member->placement_side,
                $member->status,
                Dates::date($member->activated_at),
            ]);

        $header = ['Customer ID', 'Name', 'Mobile', 'Email', 'Plan', 'Sponsor', 'Placement Side', 'Status', 'Activated'];
        $csv = implode(',', $header)."\n".$rows->map(fn ($row) => implode(',', $row))->implode("\n");

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="members.csv"',
        ]);
    }

    public function show(Request $request, Member $member): Response
    {
        $member->load(['user', 'membershipPlan', 'sponsor', 'placementParent', 'emiSchedule.installments', 'productBenefits', 'bankDetails']);

        $activity = collect()
            ->merge($member->profileChangeRequests->map(fn ($r) => [
                'type' => 'Profile Change Request',
                'description' => "{$r->field_name}: {$r->status}",
                'sort_at' => $r->created_at,
            ]))
            ->merge($member->payments->map(fn ($p) => [
                'type' => 'Payment',
                'description' => "{$p->type} — \u{20B9}{$p->amount} ({$p->status})",
                'sort_at' => $p->paid_at ?? $p->created_at,
            ]))
            ->merge($member->walletLedgerEntries->map(fn ($w) => [
                'type' => 'Wallet',
                'description' => "{$w->entry_type} \u{20B9}{$w->amount} — {$w->category}",
                'sort_at' => $w->processed_at ?? $w->created_at,
            ]))
            ->merge($member->payoutRequests->map(fn ($p) => [
                'type' => 'Payout Request',
                'description' => "\u{20B9}{$p->requested_amount} — {$p->status}",
                'sort_at' => $p->created_at,
            ]))
            ->sortByDesc('sort_at')
            ->values()
            ->take(50)
            ->map(fn (array $entry): array => [
                'type' => $entry['type'],
                'description' => $entry['description'],
                'occurred_at' => Dates::date($entry['sort_at']),
            ]);

        return Inertia::render('super-admin/member-detail', [
            'member' => array_merge($this->summarize($member), [
                'gender' => $member->gender,
                'pan_card' => $member->pan_card,
                'aadhaar_card' => $member->aadhaar_card,
                'address' => $member->address,
                'pending_fields_submitted_at' => Dates::date($member->pending_fields_submitted_at),
                'placement_parent_customer_id' => $member->placementParent?->customer_id,
                'placement_side' => $member->placement_side,
                'direct_count' => $member->directs()->count(),
                'network' => $this->networkSummary->forMember($member),
                'store_owners_in_downline' => $this->networkSummary->storeOwnersIn($member),
                'is_store_owner' => $member->user?->store !== null,
                'wallet_balance' => (string) $member->wallet_balance,
            ]),
            'bank_details' => (function () use ($member) {
                $bankDetail = $member->bankDetails->sortByDesc('id')->first();

                return $bankDetail ? [
                    'account_holder_name' => $bankDetail->account_holder_name,
                    'account_number' => $bankDetail->account_number,
                    'ifsc_code' => $bankDetail->ifsc_code,
                    'bank_name' => $bankDetail->bank_name,
                    'verified_at' => Dates::date($bankDetail->verified_at),
                ] : null;
            })(),
            'product_benefits' => $member->productBenefits->map(fn ($b) => [
                'metal' => $b->metal,
                'entry_date' => Dates::date($b->entry_date),
                'delivered_at' => Dates::date($b->delivered_at),
            ]),
            'emi' => $member->emiSchedule ? [
                'rate_booking' => $this->rateBookingSummary($member->emiSchedule),
                'total_installments' => $member->emiSchedule->total_installments,
                'installments' => $member->emiSchedule->installments->map(fn ($i) => [
                    'installment_no' => $i->installment_no,
                    'due_date' => Dates::date($i->due_date),
                    'amount' => $i->amount,
                    'status' => $i->status,
                ]),
            ] : null,
            'income' => $member->incomeLedgerCalculations()->orderByDesc('id')->limit(20)->get()->map(fn ($i) => [
                'type' => $i->type,
                'level_no' => $i->level_no,
                'amount' => $i->amount,
                'eligibility_status' => $i->eligibility_status,
                'created_at' => Dates::date($i->created_at),
            ]),
            'wallet_ledger' => $member->walletLedgerEntries()->orderByDesc('id')->limit(20)->get()->map(fn ($w) => [
                'entry_type' => $w->entry_type,
                'category' => $w->category,
                'amount' => $w->amount,
                'status' => $w->status,
                'created_at' => Dates::date($w->created_at),
            ]),
            'payouts' => $member->payoutRequests()->orderByDesc('id')->get()->map(fn ($p) => [
                'requested_amount' => $p->requested_amount,
                'status' => $p->status,
                'created_at' => Dates::date($p->created_at),
            ]),
            'draw_history' => $member->drawGroupMemberships()->with('drawGroup')->get()->map(fn ($m) => [
                'group_no' => $m->drawGroup->group_no,
                'is_winner_removed' => $m->is_winner_removed,
            ]),
            'booster_history' => $member->boosterQualifications()->with('payoutSchedules')->get()->map(fn ($q) => [
                'level_no' => $q->level_no,
                'qualified_at' => Dates::date($q->qualified_at),
                'paid_schedule_count' => $q->payoutSchedules->where('status', 'paid')->count(),
            ]),
            'store_profit_distributions' => $member->storeProfitDistributions()->orderByDesc('id')->get()->map(fn ($d) => [
                'beneficiary_type' => $d->beneficiary_type,
                'rate_percent' => $d->rate_percent,
                'amount' => $d->amount,
            ]),
            'activity' => $activity,
        ]);
    }

    public function update(UpdateMemberDetailsRequest $request, Member $member, UpdateMemberDetails $action): RedirectResponse
    {
        $photoPath = null;

        if ($request->hasFile('profile_photo')) {
            $photoPath = WebpImageStore::store($request->file('profile_photo'), 'profile-photos');

            if ($photoPath === false) {
                throw ValidationException::withMessages(['profile_photo' => 'The uploaded file could not be stored.']);
            }
        }

        $proofPath = null;

        if ($request->hasFile('bank_proof_document')) {
            $proofPath = WebpImageStore::store($request->file('bank_proof_document'), 'bank-proofs');

            if ($proofPath === false) {
                throw ValidationException::withMessages(['bank_proof_document' => 'The uploaded file could not be stored.']);
            }
        }

        $bankFields = array_filter([
            'account_holder_name' => $request->string('bank_account_holder_name')->toString() ?: null,
            'account_number' => $request->string('bank_account_number')->toString() ?: null,
            'ifsc_code' => $request->string('bank_ifsc_code')->toString() ?: null,
            'bank_name' => $request->string('bank_name')->toString() ?: null,
            'proof_document_path' => $proofPath,
        ], fn ($value) => $value !== null);

        $action(
            $member,
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->string('mobile')->toString() ?: null,
            $request->string('pan_card')->toString() ?: null,
            $request->string('aadhaar_card')->toString() ?: null,
            $request->string('address')->toString() ?: null,
            $photoPath,
            $bankFields === [] ? null : $bankFields,
            $request->string('gender')->toString() ?: null,
        );

        return redirect()->route('super-admin.members.show', $member)->with('status', 'Member details updated.');
    }

    public function resetPassword(ResetMemberPasswordRequest $request, Member $member, ResetMemberPassword $action): RedirectResponse
    {
        $action($member, $request->string('password')->toString());

        return redirect()->route('super-admin.members.show', $member)->with('status', "Password reset for {$member->customer_id}.");
    }

    /**
     * The EMI card's rate-booking block: current state, whether a revert is allowed (and if not, why), and the log.
     *
     * @return array<string, mixed>
     */
    private function rateBookingSummary(EmiSchedule $schedule): array
    {
        $isCurrentRate = $schedule->rate_booking_method === 'current_rate';
        $blocker = $isCurrentRate ? app(RevertCurrentRateBooking::class)->blocker($schedule) : null;

        return [
            'method' => $schedule->rate_booking_method,
            'installment_amount' => $schedule->installment_amount,
            'rate_per_gram' => $isCurrentRate ? $schedule->rate_per_gram_at_booking : null,
            'fixed_weight_grams' => $isCurrentRate ? $schedule->fixed_weight_grams : null,
            'booked_at' => Dates::date($schedule->current_rate_booked_at),
            'can_revert' => $isCurrentRate && $blocker === null,
            'revert_blocker' => $isCurrentRate ? $blocker : null,
            'events' => $schedule->rateBookingEvents()->with('performedBy')->orderByDesc('id')->limit(10)->get()
                ->map(fn (EmiRateBookingEvent $event): array => [
                    'event' => $event->event,
                    'by' => $event->performedBy?->name,
                    'reason' => $event->reason,
                    'occurred_at' => Dates::date($event->created_at),
                ])->all(),
        ];
    }

    public function revertCurrentRate(RevertCurrentRateBookingRequest $request, Member $member, RevertCurrentRateBooking $action): RedirectResponse
    {
        $action($member, $request->user(), $request->string('reason')->toString());

        return redirect()->route('super-admin.members.show', $member)
            ->with('status', "{$member->customer_id}'s EMI schedule is back on Future Rate.");
    }

    /** DOMAIN_LOGIC.md §11.2 point 8 — verifies the member's latest bank detail row, so it can be used for a payout request. */
    public function verifyBankDetail(Request $request, Member $member, VerifyMemberBankDetail $action): RedirectResponse
    {
        $bankDetail = $member->bankDetails()->latest('id')->first();

        abort_if($bankDetail === null, 404);

        if ($bankDetail->verified_at === null) {
            $action($bankDetail, $request->user());
        }

        return redirect()->route('super-admin.members.show', $member)
            ->with('status', "{$member->customer_id}'s bank details are verified.");
    }

    /** @return array<string, mixed> */
    private function summarize(Member $member): array
    {
        return [
            'id' => $member->id,
            'customer_id' => $member->customer_id,
            'name' => $member->user?->name,
            'mobile' => $member->user?->mobile,
            'email' => $member->user?->email,
            'plan' => $member->membershipPlan?->code,
            'sponsor_customer_id' => $member->sponsor?->customer_id,
            'status' => $member->status,
            'activated_at' => Dates::date($member->activated_at),
        ];
    }
}
