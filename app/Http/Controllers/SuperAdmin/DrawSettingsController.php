<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\Settings\PublishRuleVersion;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdateDrawSettingsRequest;
use App\Services\RuleVersionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** INSTRUCTIONS.md S06 (settings half) — group size; monthly prize name/value lives per-group on `DrawGroupMonthConfig`, configured when a group is generated. */
class DrawSettingsController extends Controller
{
    public function index(Request $request, RuleVersionService $rules): Response
    {
        return Inertia::render('super-admin/draw-settings', [
            'draw_group_size' => (int) $rules->value('draw_group_size', 200),
        ]);
    }

    public function update(UpdateDrawSettingsRequest $request, PublishRuleVersion $action): RedirectResponse
    {
        $action(['draw_group_size' => $request->integer('draw_group_size')], $request->user());

        return redirect()->route('super-admin.draw-settings.index')->with('status', 'Draw settings updated.');
    }
}
