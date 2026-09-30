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
        'level_income_rates_gold',
        'level_income_min_directs',
        'pair_value_per_entry',
        'pair_value_per_entry_gold',
        'pair_milestones',
        'pair_qualification_emis',
        'booster_levels',
        'purchase_repurchase_income_rates',
        'purchase_repurchase_income_rates_gold',
        'store_profit_distribution_rates',
        'store_profit_distribution_rates_gold',
        'item_buyback_percent',
        'item_buyback_percent_gold',
        'store_gst_percent',
        'store_income_min_directs',
        'store_emi_break_overdue_count',
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

        // T-179 — a version published before this key existed has no directs condition: show 0 for every level.
        $current['level_income_min_directs'] ??= array_fill_keys(array_map('strval', range(1, 12)), 0);
        // T-183 — likewise, no store-income directs condition before this key existed.
        $current['store_income_min_directs'] ??= 0;
        // T-185b — the break default applies until a version carries its own value.
        $current['store_emi_break_overdue_count'] ??= 3;

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
