<?php

namespace App\Http\Middleware;

use App\Models\Member;
use App\Services\NotificationFeed;
use App\Support\Portal;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            // T-140 — the header bell: unread count + latest items for the logged-in user (null when logged out).
            'notifications' => fn () => $request->user() ? app(NotificationFeed::class)->bell($request->user(), $request) : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
            // T-251 — the header "Switch ID" list; null unless a member-side person holds 2+ active IDs.
            'memberIdSwitcher' => fn () => $this->memberIdSwitcher($request),
        ];
    }

    /** @return list<array{id: int, customer_id: string|null, plan: string|null, selected: bool}>|null */
    private function memberIdSwitcher(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null || ! ($user->role === 'member' || Portal::current($request) === Portal::MEMBER)) {
            return null;
        }

        $ids = $user->members()->where('status', 'active')->with('membershipPlan:id,name')->get();

        if ($ids->count() < 2) {
            return null;
        }

        $selectedId = $user->member?->id;

        return array_values($ids->map(fn (Member $member): array => [
            'id' => $member->id,
            'customer_id' => $member->customer_id,
            'plan' => $member->membershipPlan?->name,
            'selected' => $member->id === $selectedId,
        ])->all());
    }
}
