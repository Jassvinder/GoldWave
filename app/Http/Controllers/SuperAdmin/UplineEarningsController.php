<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\UplineEarningsTrace;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T-238 — Super Admin "Upline Earnings": pick a member and see every earning that went up their network because of
 * them (DOMAIN_LOGIC.md §12.3). Read-only. `?member=` opens one member directly (the Member Detail button); `?search=`
 * finds them by Customer ID, name or mobile, opening the member at once when exactly one matches.
 */
class UplineEarningsController extends Controller
{
    private const PER_PAGE = 50;

    public function index(Request $request, UplineEarningsTrace $trace): Response
    {
        $search = trim($request->string('search')->toString());
        $matches = [];
        $member = $request->filled('member') ? Member::with(['user', 'sponsor', 'membershipPlan'])->find($request->integer('member')) : null;

        if ($member === null && $search !== '') {
            // LOWER() on both sides: Postgres `like` is case-sensitive, and "gwl-0012" must find GWL-0012.
            $pattern = '%'.mb_strtolower($search).'%';
            $found = Member::with('user')
                ->realMembers()
                ->where(function ($q) use ($pattern) {
                    $q->whereRaw('LOWER(customer_id) LIKE ?', [$pattern])
                        ->orWhereHas('user', fn ($u) => $u->whereRaw('LOWER(name) LIKE ?', [$pattern])->orWhere('mobile', 'like', $pattern));
                })
                ->orderBy('customer_id')
                ->limit(20)
                ->get();
            $exact = $found->first(fn (Member $m): bool => strcasecmp($m->customer_id, $search) === 0);

            if ($exact !== null || $found->count() === 1) {
                $member = ($exact ?? $found->first())->load(['sponsor', 'membershipPlan']);
            } else {
                $matches = $found->map(fn (Member $m): array => [
                    'id' => $m->id,
                    'customer_id' => $m->customer_id,
                    'name' => $m->user?->name,
                    'mobile' => $m->user?->mobile,
                ])->all();
            }
        }

        $filters = [
            'type' => $request->filled('type') && in_array($request->string('type')->toString(), UplineEarningsTrace::TYPES, true) ? $request->string('type')->toString() : null,
            'status' => $request->filled('status') && in_array($request->string('status')->toString(), UplineEarningsTrace::STATUSES, true) ? $request->string('status')->toString() : null,
            'from' => $request->date('from')?->toDateString(),
            'to' => $request->date('to')?->toDateString(),
        ];

        return Inertia::render('super-admin/upline-earnings', [
            'search' => $search,
            'matches' => $matches,
            'filters' => $filters,
            ...($member === null ? ['member' => null, 'summary' => ['receivers' => [], 'pair_only_count' => 0], 'totals' => null, 'rows' => null] : $this->trace($request, $trace, $member, $filters)),
        ]);
    }

    /**
     * Totals and the per-upline summary cover all of the member's rows; the filters narrow only the detail list.
     *
     * @param  array{type: string|null, status: string|null, from: string|null, to: string|null}  $filters
     * @return array<string, mixed>
     */
    private function trace(Request $request, UplineEarningsTrace $trace, Member $member, array $filters): array
    {
        $all = $trace->rows($member);
        $money = $all->whereIn('type', ['level_income', 'purchase_repurchase', 'store_profit']);
        $filtered = $all
            ->when($filters['type'], fn ($rows, $type) => $rows->where('type', $type))
            ->when($filters['status'], fn ($rows, $status) => $rows->where('status', $status))
            ->when($filters['from'], fn ($rows, $from) => $rows->filter(fn (array $row): bool => $row['date'] >= $from))
            ->when($filters['to'], fn ($rows, $to) => $rows->filter(fn (array $row): bool => $row['date'] <= $to))
            ->values();
        $page = max(1, $request->integer('page', 1));

        return [
            'member' => [
                'id' => $member->id,
                'customer_id' => $member->customer_id,
                'name' => $member->user?->name,
                'plan' => $member->membershipPlan?->name,
                'status' => $member->status,
                'sponsor_customer_id' => $member->sponsor?->customer_id,
            ],
            'summary' => $trace->summary($member, $all),
            'totals' => [
                'paid' => number_format((float) $money->where('status', 'paid')->sum(fn (array $row): float => (float) $row['amount']), 2, '.', ''),
                'held' => number_format((float) $money->where('status', 'held')->sum(fn (array $row): float => (float) $row['amount']), 2, '.', ''),
                'skipped' => $all->where('status', 'skipped')->count(),
                'prizes' => $all->where('type', 'draw_benefit')->count(),
                'pair_entries' => $all->where('type', 'pair_entry')->count(),
            ],
            'rows' => (new LengthAwarePaginator(
                $filtered->forPage($page, self::PER_PAGE)->values(),
                $filtered->count(),
                self::PER_PAGE,
                $page,
                ['path' => $request->url(), 'query' => $request->query()],
            ))->toArray(),
        ];
    }
}
