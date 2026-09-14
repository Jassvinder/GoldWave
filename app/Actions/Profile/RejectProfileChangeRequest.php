<?php

namespace App\Actions\Profile;

use App\Models\ProfileChangeRequest;
use App\Models\User;
use App\Notifications\ProfileChangeRequestReviewed;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DOMAIN_LOGIC.md §13 point 3 — Super Admin rejects a pending Change
 * Request: the underlying field is left completely unchanged, a rejection
 * reason is recorded, and the member is notified.
 */
class RejectProfileChangeRequest
{
    public function __invoke(ProfileChangeRequest $changeRequest, User $operator, string $rejectionReason): ProfileChangeRequest
    {
        $reviewed = DB::transaction(function () use ($changeRequest, $operator, $rejectionReason) {
            $locked = ProfileChangeRequest::whereKey($changeRequest->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'change_request' => 'Only a pending change request can be rejected.',
                ]);
            }

            $locked->update([
                'status' => 'rejected',
                'reviewed_by' => $operator->id,
                'reviewed_at' => now(),
                'rejection_reason' => $rejectionReason,
            ]);

            return $locked->fresh();
        });

        $reviewed->member()->firstOrFail()->notify(new ProfileChangeRequestReviewed($reviewed));

        return $reviewed;
    }
}
