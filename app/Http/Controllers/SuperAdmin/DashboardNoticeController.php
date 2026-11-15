<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\SaveDashboardNoticeRequest;
use App\Models\DashboardNotice;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T-245 (DOMAIN_LOGIC.md §2.2b, INSTRUCTIONS.md S19) — Super Admin or the company Admin manages the offers, events and
 * notices shown at the top of the member dashboard. Today's gold/silver rate is shown there too, from Rate Settings.
 */
class DashboardNoticeController extends Controller
{
    public function index(): Response
    {
        $today = now()->toDateString();

        return Inertia::render('super-admin/dashboard-notices', [
            'notices' => DashboardNotice::with(['createdBy:id,name', 'updatedBy:id,name'])
                ->orderByDesc('id')
                ->get()
                ->map(fn (DashboardNotice $notice): array => [
                    'id' => $notice->id,
                    'kind' => $notice->kind,
                    'title' => $notice->title,
                    'body' => $notice->body,
                    'starts_on' => $notice->starts_on?->toDateString(),
                    'ends_on' => $notice->ends_on?->toDateString(),
                    'is_active' => $notice->is_active,
                    'state' => match (true) {
                        ! $notice->is_active => 'off',
                        $notice->starts_on !== null && $notice->starts_on->toDateString() > $today => 'scheduled',
                        $notice->ends_on !== null && $notice->ends_on->toDateString() < $today => 'ended',
                        default => 'showing',
                    },
                    'updated' => Dates::date($notice->updated_at),
                    'updated_by' => ($notice->updatedBy ?? $notice->createdBy)?->name,
                ])
                ->all(),
        ]);
    }

    public function store(SaveDashboardNoticeRequest $request): RedirectResponse
    {
        DashboardNotice::create([...$request->validated(), 'created_by' => $request->user()->id]);

        return back()->with('status', 'Notice added.');
    }

    public function update(SaveDashboardNoticeRequest $request, DashboardNotice $notice): RedirectResponse
    {
        $notice->update([...$request->validated(), 'updated_by' => $request->user()->id]);

        return back()->with('status', 'Notice saved.');
    }

    public function destroy(DashboardNotice $notice): RedirectResponse
    {
        $notice->delete();

        return back()->with('status', 'Notice deleted.');
    }
}
