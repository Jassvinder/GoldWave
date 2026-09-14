<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Support\Dates;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** INSTRUCTIONS.md M02 — read-only profile view; pending fields (M03) and change requests (M04) are separate pages. */
class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $member = $request->user()->member;

        abort_if($member === null, 404);

        $bankDetail = $member->bankDetails()->latest('id')->first();

        return Inertia::render('member/profile', [
            'member' => [
                'customer_id' => $member->customer_id,
                'name' => $member->user?->name,
                'email' => $member->user?->email,
                'mobile' => $member->user?->mobile,
                'status' => $member->status,
                'activated_at' => Dates::date($member->activated_at),
                'pan_card' => $member->pan_card,
                'aadhaar_card' => $member->aadhaar_card,
                'profile_photo_path' => $member->profile_photo_path,
                'address' => $member->address,
                'pending_fields_submitted_at' => Dates::date($member->pending_fields_submitted_at),
            ],
            'bank_detail' => $bankDetail ? [
                'account_holder_name' => $bankDetail->account_holder_name,
                'account_number' => $bankDetail->account_number,
                'ifsc_code' => $bankDetail->ifsc_code,
                'bank_name' => $bankDetail->bank_name,
                'verified_at' => Dates::date($bankDetail->verified_at),
            ] : null,
        ]);
    }
}
