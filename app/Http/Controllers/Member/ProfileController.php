<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Support\Dates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/** INSTRUCTIONS.md M02 — the profile view; pending fields (M03) is its own page, and a locked field's change request (M04) can be filed from here (T-143). */
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
                'gender' => $member->gender,
                'status' => $member->status,
                'activated_at' => Dates::date($member->activated_at),
                'pan_card' => $member->pan_card,
                'aadhaar_card' => $member->aadhaar_card,
                'profile_photo_url' => $member->profile_photo_path ? Storage::disk('public')->url($member->profile_photo_path) : null,
                'address' => $member->address,
                'pending_fields_submitted_at' => Dates::date($member->pending_fields_submitted_at),
                'sponsor_customer_id' => $member->sponsor?->customer_id,
                'sponsor_name' => $member->sponsor?->user?->name,
            ],
            'bank_detail' => $bankDetail ? [
                'account_holder_name' => $bankDetail->account_holder_name,
                'account_number' => $bankDetail->account_number,
                'ifsc_code' => $bankDetail->ifsc_code,
                'bank_name' => $bankDetail->bank_name,
                'verified_at' => Dates::date($bankDetail->verified_at),
            ] : null,
            // Fields that already have a request waiting for the Super Admin — shown as "Change requested" instead of a button.
            'pending_change_fields' => $member->profileChangeRequests()->where('status', 'pending')->pluck('field_name')->all(),
        ]);
    }
}
