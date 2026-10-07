<?php

namespace App\Support;

use App\Models\Member;
use App\Services\MemberNetworkSummary;
use Illuminate\Support\Facades\Storage;

/**
 * T-119 (19-09-2026) — the card-shaped data both Tree View and Directs View
 * render identically (name, Customer ID, "Sponsored by", avatar source,
 * inactive flag). One place builds it so the two controllers cannot drift.
 * Avatar rule: the member's own uploaded photo when present, else a
 * gender-based placeholder chosen by the frontend from `gender`.
 *
 * T-208 (02-10-2026) — each card also shows the member's Left/Right Binary team size. `forMany()` gets them for every
 * card on the page from one `MemberNetworkSummary::forMany()` query, never one query per card; the numbers are the same
 * leg totals the Team strip and Dashboard show.
 *
 * @phpstan-type Card array{id: int, name: string|null, customer_id: string|null, status: string, gender: string|null, photo_url: string|null, sponsor_name: string|null, left_count: int, right_count: int}
 */
class NetworkNodeCard
{
    /**
     * @param  array{left: array{total: int}, right: array{total: int}}|null  $summary  this member's entry from MemberNetworkSummary::forMany()
     * @return Card
     */
    public static function from(Member $member, ?array $summary = null): array
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
            'left_count' => $summary['left']['total'] ?? 0,
            'right_count' => $summary['right']['total'] ?? 0,
        ];
    }

    /**
     * Cards for a batch of members with their Left/Right counts from a single query, keyed by member id.
     *
     * @param  iterable<Member>  $members
     * @return array<int, Card>
     */
    public static function forMany(iterable $members, MemberNetworkSummary $network): array
    {
        $byId = [];

        foreach ($members as $member) {
            $byId[$member->id] = $member;
        }

        $summaries = $network->forMany(array_keys($byId));

        $cards = [];

        foreach ($byId as $id => $member) {
            $cards[$id] = self::from($member, $summaries[$id]);
        }

        return $cards;
    }
}
