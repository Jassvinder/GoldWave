<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\ProductBenefit;
use App\Support\Dates;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** INSTRUCTIONS.md M05 — current plan + product benefit (DOMAIN_LOGIC.md §3). */
class MembershipController extends Controller
{
    public function show(Request $request): Response
    {
        $member = $request->user()->member;

        abort_if($member === null, 404);

        $member->load(['membershipPlan', 'productBenefits.metalRate', 'productBenefits.store']);

        return Inertia::render('member/membership', [
            'plan' => $member->membershipPlan ? [
                'code' => $member->membershipPlan->code,
                'name' => $member->membershipPlan->name,
                'amount' => $member->membershipPlan->amount,
                'installment_count' => $member->membershipPlan->installment_count,
                'product_category' => $member->membershipPlan->product_category,
                'fixed_weight_grams' => $member->membershipPlan->fixed_weight_grams,
            ] : null,
            'product_benefits' => $member->productBenefits->map($this->mapBenefit(...))->all(),
        ]);
    }

    /** @return array<string, mixed> */
    private function mapBenefit(ProductBenefit $benefit): array
    {
        return [
            'metal' => $benefit->metal,
            'rate_per_gram_at_entry' => $benefit->rate_per_gram_at_entry,
            'entry_date' => Dates::date($benefit->entry_date),
            'delivered_at' => Dates::date($benefit->delivered_at),
            'store_name' => $benefit->store?->name,
        ];
    }
}
