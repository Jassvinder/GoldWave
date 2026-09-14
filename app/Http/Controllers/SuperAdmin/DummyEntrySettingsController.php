<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\DummyEntries\GenerateDailyDummyEntries;
use App\Actions\Settings\PublishRuleVersion;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdateDummyEntrySettingsRequest;
use App\Models\Member;
use App\Services\RuleVersionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** INSTRUCTIONS.md S04 — daily count, enable/disable, generation controls. */
class DummyEntrySettingsController extends Controller
{
    public function index(Request $request, RuleVersionService $rules): Response
    {
        return Inertia::render('super-admin/dummy-entry-settings', [
            'enabled' => (bool) $rules->value('dummy_entry_enabled', false),
            'daily_count' => (int) $rules->value('dummy_entry_daily_count', 0),
            // `is_company_root` (the seeded placement anchor) is also flagged
            // `is_company_dummy=true` but is never a real daily-generated
            // entry — excluded here so these stats reflect actual generation.
            'stats' => [
                'generated' => Member::where('is_company_dummy', true)->where('is_company_root', false)->count(),
                'unassigned' => Member::where('is_company_dummy', true)->where('dummy_status', 'unassigned')->count(),
                'assigned' => Member::where('is_company_dummy', true)->where('dummy_status', 'assigned')->count(),
            ],
        ]);
    }

    public function update(UpdateDummyEntrySettingsRequest $request, PublishRuleVersion $action): RedirectResponse
    {
        $action([
            'dummy_entry_enabled' => $request->boolean('enabled'),
            'dummy_entry_daily_count' => $request->integer('daily_count'),
        ], $request->user());

        return redirect()->route('super-admin.dummy-entry-settings.index')->with('status', 'Dummy entry settings updated.');
    }

    public function generateNow(Request $request, GenerateDailyDummyEntries $action): RedirectResponse
    {
        $created = $action();

        $status = count($created) > 0
            ? count($created).' dummy entr'.(count($created) === 1 ? 'y' : 'ies').' generated.'
            : 'No entries generated — check that generation is enabled with a daily count above 0.';

        return redirect()->route('super-admin.dummy-entry-settings.index')->with('status', $status);
    }
}
