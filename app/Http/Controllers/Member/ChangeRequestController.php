<?php

namespace App\Http\Controllers\Member;

use App\Actions\Profile\SubmitProfileChangeRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\SubmitProfileChangeRequestRequest;
use App\Models\ProfileChangeRequest;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** INSTRUCTIONS.md M04 — submit + track correction requests for locked fields (DOMAIN_LOGIC.md §13 point 3). */
class ChangeRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $member = $request->user()->member;

        abort_if($member === null, 404);

        $requests = $member->profileChangeRequests()
            ->orderByDesc('id')
            ->get()
            ->map($this->mapRequest(...));

        return Inertia::render('member/change-requests', [
            'requests' => $requests,
            'pending_fields_submitted' => $member->pending_fields_submitted_at !== null,
        ]);
    }

    public function store(SubmitProfileChangeRequestRequest $request, SubmitProfileChangeRequest $action): RedirectResponse
    {
        $member = $request->user()->member;

        abort_if($member === null, 404);

        $fieldName = $request->string('field_name')->toString();

        if ($fieldName === 'bank_details') {
            $newValue = [
                'account_holder_name' => $request->string('bank_account_holder_name')->toString(),
                'account_number' => $request->string('bank_account_number')->toString(),
                'ifsc_code' => $request->string('bank_ifsc_code')->toString(),
                'bank_name' => $request->string('bank_name')->toString(),
            ];
        } elseif ($fieldName === 'profile_photo_path') {
            $path = $request->file('new_photo')->store('profile-photos', 'public');

            if ($path === false) {
                throw ValidationException::withMessages(['new_photo' => 'The photo could not be stored.']);
            }

            $newValue = $path;
        } else {
            $newValue = $request->string('new_value')->toString();
        }

        $action($member, $fieldName, $newValue, $request->string('reason')->toString() ?: null);

        return redirect()->route('member.change-requests.index')
            ->with('status', 'Change request submitted.');
    }

    /** @return array<string, mixed> */
    private function mapRequest(ProfileChangeRequest $changeRequest): array
    {
        return [
            'id' => $changeRequest->id,
            'field_name' => $changeRequest->field_name,
            'old_value' => $changeRequest->old_value,
            'new_value' => $changeRequest->new_value,
            'reason' => $changeRequest->reason,
            'status' => $changeRequest->status,
            'rejection_reason' => $changeRequest->rejection_reason,
            'reviewed_at' => Dates::date($changeRequest->reviewed_at),
        ];
    }
}
