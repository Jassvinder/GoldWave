<?php

namespace App\Http\Controllers\Member;

use App\Actions\Profile\SubmitPendingProfileFields;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\SubmitPendingProfileFieldsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** INSTRUCTIONS.md M03 — the one-time Pending Fields form (DOMAIN_LOGIC.md §13). */
class PendingProfileController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        $member = $request->user()->member;

        abort_if($member === null, 404);

        if ($member->pending_fields_submitted_at !== null) {
            return redirect()->route('member.profile.show')
                ->with('status', 'Pending fields have already been submitted.');
        }

        return Inertia::render('member/pending-profile');
    }

    public function store(SubmitPendingProfileFieldsRequest $request, SubmitPendingProfileFields $action): RedirectResponse
    {
        $member = $request->user()->member;

        abort_if($member === null, 404);

        $photoPath = $request->file('profile_photo')->store('profile-photos', 'public');
        $proofPath = $request->file('bank_proof_document')->store('bank-proofs', 'public');

        if ($photoPath === false || $proofPath === false) {
            throw ValidationException::withMessages(['profile_photo' => 'One of the uploaded files could not be stored.']);
        }

        $action(
            $member,
            $request->string('pan_card')->toString(),
            $request->string('aadhaar_card')->toString(),
            $photoPath,
            $request->string('address')->toString(),
            [
                'account_holder_name' => $request->string('bank_account_holder_name')->toString(),
                'account_number' => $request->string('bank_account_number')->toString(),
                'ifsc_code' => $request->string('bank_ifsc_code')->toString(),
                'bank_name' => $request->string('bank_name')->toString(),
                'proof_document_path' => $proofPath,
            ],
        );

        return redirect()->route('member.profile.show')
            ->with('status', 'Pending fields submitted successfully.');
    }
}
