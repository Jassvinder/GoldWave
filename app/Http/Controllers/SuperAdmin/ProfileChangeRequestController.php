<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\Profile\ApproveProfileChangeRequest;
use App\Actions\Profile\RejectProfileChangeRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\RejectProfileChangeRequestRequest;
use App\Models\ProfileChangeRequest;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T-109 (17-09-2026), user-reported — `Approve/RejectProfileChangeRequest`
 * have existed since T-012, but a pending request only ever appeared
 * read-only inside a Member's "Recent Activity" feed, with no way to
 * actually act on it. Mirrors `CashPaymentApprovalController`'s
 * pending-only-queue shape.
 */
class ProfileChangeRequestController extends Controller
{
    private const SIMPLE_FIELDS = ['pan_card', 'aadhaar_card', 'profile_photo_path', 'address'];

    public function index(): Response
    {
        $pending = ProfileChangeRequest::with('member.user')
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get()
            ->map(fn (ProfileChangeRequest $changeRequest): array => [
                'id' => $changeRequest->id,
                'field_name' => $changeRequest->field_name,
                'old_value' => $this->displayValue($changeRequest->field_name, $changeRequest->old_value),
                'new_value' => $this->displayValue($changeRequest->field_name, $changeRequest->new_value),
                'reason' => $changeRequest->reason,
                'created_at' => Dates::date($changeRequest->created_at),
                'member' => [
                    'customer_id' => $changeRequest->member->customer_id,
                    'name' => $changeRequest->member->user?->name,
                ],
            ]);

        return Inertia::render('super-admin/profile-change-requests', [
            'pending' => $pending,
        ]);
    }

    public function approve(ProfileChangeRequest $profile_change_request, Request $request, ApproveProfileChangeRequest $action): RedirectResponse
    {
        $action($profile_change_request, $request->user());

        return back()->with('status', 'Change request approved.');
    }

    public function reject(RejectProfileChangeRequestRequest $request, ProfileChangeRequest $profile_change_request, RejectProfileChangeRequest $action): RedirectResponse
    {
        $action($profile_change_request, $request->user(), $request->string('rejection_reason')->toString());

        return back()->with('status', 'Change request rejected.');
    }

    private function displayValue(string $fieldName, ?string $value): ?string
    {
        if ($value === null || in_array($fieldName, self::SIMPLE_FIELDS, true)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return $value;
        }

        return collect($decoded)->map(fn ($v, $k) => "{$k}: {$v}")->implode(', ');
    }
}
