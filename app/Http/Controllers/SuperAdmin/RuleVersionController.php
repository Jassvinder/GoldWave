<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\Settings\PublishRuleVersion;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\PublishCompensationRulesRequest;
use App\Models\RuleVersion;
use App\Services\RuleVersionService;
use App\Support\Dates;
use App\Support\RuleVersionDiff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * INSTRUCTIONS.md S03 (Compensation Rule Versions) — also serves Admin
 * Compensation Management's config page (`CompensationManagementController::
 * config()` redirects here, DOMAIN_LOGIC.md §21 T-017 pre-coding pass: one
 * shared page/Action, not two divergent implementations).
 */
class RuleVersionController extends Controller
{
    private const COMPENSATION_KEYS = [
        'level_income_rates',
        'pair_value_per_entry',
        'pair_milestones',
        'pair_qualification_emis',
        'booster_levels',
        'purchase_repurchase_income_rates',
        'store_profit_distribution_rates',
        'item_buyback_percent',
        'store_gst_percent',
    ];

    public function index(Request $request, RuleVersionService $rules): Response
    {
        $ordered = RuleVersion::with(['publishedBy', 'values'])
            ->orderBy('version_no')
            ->get();

        $previousValues = null;

        $versions = $ordered
            ->map(function (RuleVersion $version) use (&$previousValues): array {
                $currentValues = $version->values->pluck('value', 'key')->all();

                $changes = $previousValues === null
                    ? null
                    : RuleVersionDiff::summarize($previousValues, $currentValues);

                $previousValues = $currentValues;

                return [
                    'version_no' => $version->version_no,
                    'effective_from' => Dates::date($version->effective_from),
                    'effective_to' => Dates::date($version->effective_to),
                    'is_active' => $version->is_active,
                    'published_by' => $version->publishedBy?->name,
                    'published_at' => Dates::date($version->published_at),
                    'notes' => $version->notes,
                    'changes' => $changes,
                ];
            })
            ->reverse()
            ->values();

        $current = [];

        foreach (self::COMPENSATION_KEYS as $key) {
            $current[$key] = $rules->value($key);
        }

        return Inertia::render('super-admin/rule-versions', [
            'versions' => $versions,
            'current' => $current,
        ]);
    }

    public function store(PublishCompensationRulesRequest $request, PublishRuleVersion $action): RedirectResponse
    {
        $overrides = $request->only(self::COMPENSATION_KEYS);
        $overrides = array_filter($overrides, fn ($value) => $value !== null);

        $action($overrides, $request->user(), $request->string('notes')->toString() ?: null);

        return redirect()->route('super-admin.rule-versions.index')->with('status', 'New compensation rule version published.');
    }
}
