<?php

namespace App\Support;

use App\Models\Member;
use Illuminate\Support\Facades\Storage;

/**
 * T-119 (19-09-2026) — the card-shaped data both Tree View and Directs View
 * render identically (name, Customer ID, "Sponsored by", avatar source,
 * inactive flag). One place builds it so the two controllers cannot drift.
 * Avatar rule: the member's own uploaded photo when present, else a
 * gender-based placeholder chosen by the frontend from `gender`.
 */
class NetworkNodeCard
{
    /**
     * @return array{id: int, name: string|null, customer_id: string|null, status: string, gender: string|null, photo_url: string|null, sponsor_name: string|null}
     */
    public static function from(Member $member): array
    {
        $member->loadMissing(['user', 'sponsor.user']);

        return [
            'id' => $member->id,
            'name' => $member->user?->name,
            'customer_id' => $member->customer_id,
            'status' => $member->status,
            'gender' => $member->gender,
            'photo_url' => $member->profile_photo_path ? Storage::disk('public')->url($member->profile_photo_path) : null,
            'sponsor_name' => $member->sponsor?->user?->name,
        ];
    }
}
