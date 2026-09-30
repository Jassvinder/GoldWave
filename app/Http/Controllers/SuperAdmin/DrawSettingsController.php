<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\Settings\PublishRuleVersion;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdateDrawSettingsRequest;
use App\Services\DrawPrizeResolver;
use App\Services\RuleVersionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * INSTRUCTIONS.md S06 (settings half) — group size and the default monthly prizes (§8.3, 30-09-2026): Silver for
 * months 1–15 and Gold for 16–20 of every group's cycle, used whenever a group-month has no prize of its own.
 */
class DrawSettingsController extends Controller
{
    public function index(Request $request, RuleVersionService $rules): Response
    {
        $prizes = [];

        foreach (DrawPrizeResolver::DEFAULTS as $key => $default) {
            $prizes[$key] = $rules->value($key, $default);
        }

        return Inertia::render('super-admin/draw-settings', [
            'draw_group_size' => (int) $rules->value('draw_group_size', 200),
            'draw_prize_silver_name' => (string) $prizes['draw_prize_silver_name'],
            'draw_prize_silver_value' => (float) $prizes['draw_prize_silver_value'],
            'draw_prize_gold_name' => (string) $prizes['draw_prize_gold_name'],
            'draw_prize_gold_value' => (float) $prizes['draw_prize_gold_value'],
            'draw_winners_per_month' => (int) $prizes['draw_winners_per_month'],
            'silver_months' => DrawPrizeResolver::SILVER_MONTHS,
        ]);
    }

    public function update(UpdateDrawSettingsRequest $request, PublishRuleVersion $action): RedirectResponse
    {
        $action([
            'draw_group_size' => $request->integer('draw_group_size'),
            'draw_prize_silver_name' => $request->string('draw_prize_silver_name')->trim()->toString(),
            'draw_prize_silver_value' => (float) $request->input('draw_prize_silver_value'),
            'draw_prize_gold_name' => $request->string('draw_prize_gold_name')->trim()->toString(),
            'draw_prize_gold_value' => (float) $request->input('draw_prize_gold_value'),
            'draw_winners_per_month' => $request->integer('draw_winners_per_month'),
        ], $request->user());

        return redirect()->route('super-admin.draw-settings.index')->with('status', 'Draw settings updated.');
    }
}
