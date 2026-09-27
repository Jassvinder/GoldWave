<?php

use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * T-140 — notifications are per User now (the Super Admin has no Member row, and the bell reads one inbox per
     * login). Notifications written before this change were attached to the Member; re-attach them to the member's user
     * and give the old `ProfileChangeRequestReviewed` rows the title/body/category/link the new inbox displays.
     * Data-only; safe to re-run (already-moved rows are no longer Member-typed).
     */
    public function up(): void
    {
        $labels = [
            'pan_card' => 'PAN card',
            'aadhaar_card' => 'Aadhaar card',
            'address' => 'address',
            'profile_photo_path' => 'profile photo',
            'bank_details' => 'bank details',
        ];

        DB::table('notifications')->where('notifiable_type', Member::class)->orderBy('created_at')->get()->each(function ($row) use ($labels): void {
            $userId = DB::table('members')->where('id', $row->notifiable_id)->value('user_id');

            if ($userId === null) {
                return;
            }

            $data = (array) json_decode((string) $row->data, true);

            if (! isset($data['title']) && str_ends_with((string) $row->type, 'ProfileChangeRequestReviewed')) {
                $field = $labels[$data['field_name'] ?? ''] ?? str_replace('_', ' ', (string) ($data['field_name'] ?? 'profile'));
                $approved = ($data['status'] ?? '') === 'approved';
                $reason = $data['rejection_reason'] ?? null;

                $data = array_merge($data, [
                    'key' => 'profile_change_request_reviewed',
                    'category' => 'request',
                    'title' => $approved ? 'Change request approved' : 'Change request rejected',
                    'body' => $approved
                        ? "Your request to change your {$field} was approved."
                        : "Your request to change your {$field} was rejected".($reason ? ": {$reason}" : '.'),
                    'url' => '/member/change-requests',
                ]);
            }

            DB::table('notifications')->where('id', $row->id)->update([
                'notifiable_type' => User::class,
                'notifiable_id' => $userId,
                'data' => json_encode($data),
            ]);
        });
    }

    public function down(): void
    {
        // Data-only move; nothing to undo (a Member-attached inbox no longer exists in the application).
    }
};
