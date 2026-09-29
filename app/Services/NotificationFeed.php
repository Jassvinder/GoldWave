<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * T-140 — everything the header bell and the Notifications pages read: the unread count, the latest items, the paginated
 * list with a category filter, and the per-category counts behind the tabs. Always scoped to one user's own
 * notifications. The category lives inside the JSON `data` column, so the filter uses a per-driver JSON expression.
 */
class NotificationFeed
{
    public const PER_PAGE = 15;

    public const BELL_ITEMS = 6;

    /** @return array{unread_count: int, latest: list<array<string, mixed>>, index_url: string} */
    public function bell(User $user, Request $request): array
    {
        return [
            'unread_count' => $user->unreadNotifications()->count(),
            'latest' => array_values($user->notifications()->latest()->limit(self::BELL_ITEMS)->get()
                ->map(fn (DatabaseNotification $n): array => $this->item($n))->all()),
            'index_url' => self::indexUrl($user, $request),
        ];
    }

    /**
     * @return array{
     *     list: LengthAwarePaginator<int, array<string, mixed>>,
     *     counts: array{total: int, unread: int, categories: array<string, array{total: int, unread: int}>},
     *     filters: array{category: string|null, unread: bool},
     * }
     */
    public function page(User $user, ?string $category, bool $unreadOnly): array
    {
        /** @var MorphMany<DatabaseNotification, User> $query */
        $query = $user->notifications();
        $query->latest();

        if ($category !== null && $category !== '') {
            $this->whereCategory($query, $category);
        }

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        $paginator = $query->paginate(self::PER_PAGE)->withQueryString();
        $paginator->through(fn (DatabaseNotification $n): array => $this->item($n));

        return [
            // Deliberately not called "notifications" — that key is already the shared bell payload
            // (HandleInertiaRequests::share()) injected on every page, and a same-named page prop would
            // silently override it wherever this page's own props are merged in, crashing the header bell.
            'list' => $paginator,
            'counts' => $this->counts($user),
            'filters' => ['category' => $category ?: null, 'unread' => $unreadOnly],
        ];
    }

    /** @return array{total: int, unread: int, categories: array<string, array{total: int, unread: int}>} */
    public function counts(User $user): array
    {
        $expression = $this->categoryExpression();

        // `reorder()`: the `notifications()` relation orders by `created_at`, which Postgres rejects next to a GROUP BY.
        $rows = $user->notifications()
            ->getQuery()
            ->reorder()
            ->select(
                DB::raw("{$expression} as category"),
                DB::raw('count(*) as total'),
                DB::raw('sum(case when read_at is null then 1 else 0 end) as unread'),
            )
            ->groupBy(DB::raw($expression))
            ->get();

        $categories = [];
        $total = 0;
        $unread = 0;

        foreach ($rows as $row) {
            $key = (string) ($row->getAttribute('category') ?? 'other');
            $categories[$key] = ['total' => (int) $row->getAttribute('total'), 'unread' => (int) $row->getAttribute('unread')];
            $total += (int) $row->getAttribute('total');
            $unread += (int) $row->getAttribute('unread');
        }

        return ['total' => $total, 'unread' => $unread, 'categories' => $categories];
    }

    /** @return array<string, mixed> */
    public function item(DatabaseNotification $notification): array
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'category' => (string) ($data['category'] ?? 'other'),
            'title' => (string) ($data['title'] ?? class_basename($notification->type)),
            'body' => (string) ($data['body'] ?? ''),
            // Clicking an item goes through `notifications/{id}/open`, which marks it read and then redirects to its target.
            'open_url' => route('notifications.open', $notification->id, false),
            'has_target' => filled($data['url'] ?? null),
            'read' => $notification->read_at !== null,
            'created_at' => Carbon::parse($notification->created_at)->toIso8601String(),
        ];
    }

    /** The Notifications page of the portal the request is in (a Store Owner using their Member login stays in the Member portal). */
    public static function indexUrl(User $user, Request $request): string
    {
        $first = $request->segment(1);

        $portal = match (true) {
            in_array($first, ['member', 'admin', 'super-admin'], true) => $first,
            $user->isCompanyStaff() => 'super-admin',
            $user->isStoreAdmin() => 'admin',
            default => 'member',
        };

        return '/'.$portal.'/notifications';
    }

    /**
     * The SQL that reads `data.category` — Postgres (production) and SQLite (tests) spell it differently. Both branches
     * are fixed strings, never built from request input.
     *
     * @return literal-string
     */
    private function categoryExpression(): string
    {
        return DB::connection()->getDriverName() === 'pgsql'
            ? "((data::jsonb) ->> 'category')"
            : "json_extract(data, '\$.category')";
    }

    /** @param  MorphMany<DatabaseNotification, User>  $query */
    private function whereCategory(MorphMany $query, string $category): void
    {
        $query->whereRaw($this->categoryExpression().' = ?', [$category]);
    }
}
