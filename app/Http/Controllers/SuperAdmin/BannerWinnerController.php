<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\SaveBannerWinnerRequest;
use App\Models\BannerWinner;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T-248 (DOMAIN_LOGIC.md §8.8, INSTRUCTIONS.md S20) — Super Admin or the company Admin lists the draw winners shown on
 * the member login banner. Display only, kept apart from Draw Management until the client's draw is finalised.
 */
class BannerWinnerController extends Controller
{
    public function index(): Response
    {
        $latestMonth = BannerWinner::max('draw_month');

        return Inertia::render('super-admin/banner-winners', [
            'winners' => BannerWinner::with(['member.user:id,name', 'createdBy:id,name', 'updatedBy:id,name'])
                ->orderByDesc('draw_month')
                ->orderBy('group_no')
                ->orderBy('id')
                ->get()
                ->map(fn (BannerWinner $winner): array => [
                    'id' => $winner->id,
                    'group_no' => $winner->group_no,
                    'draw_month' => $winner->draw_month->format('Y-m'),
                    'month_label' => $winner->draw_month->format('F Y'),
                    'customer_id' => $winner->member->customer_id,
                    'name' => $winner->member->user?->name,
                    'prize' => $winner->prize,
                    'on_banner' => $latestMonth !== null && $winner->draw_month->toDateString() === substr((string) $latestMonth, 0, 10),
                    'updated' => Dates::date($winner->updated_at),
                    'updated_by' => ($winner->updatedBy ?? $winner->createdBy)?->name,
                ])
                ->all(),
        ]);
    }

    public function store(SaveBannerWinnerRequest $request): RedirectResponse
    {
        BannerWinner::create([...$request->winnerData(), 'created_by' => $request->user()->id]);

        return back()->with('status', 'Winner added.');
    }

    public function update(SaveBannerWinnerRequest $request, BannerWinner $winner): RedirectResponse
    {
        $winner->update([...$request->winnerData(), 'updated_by' => $request->user()->id]);

        return back()->with('status', 'Winner saved.');
    }

    public function destroy(BannerWinner $winner): RedirectResponse
    {
        $winner->delete();

        return back()->with('status', 'Winner removed.');
    }
}
