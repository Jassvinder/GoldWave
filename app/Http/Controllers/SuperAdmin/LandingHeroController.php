<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\Settings\PublishRuleVersion;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdateLandingHeroRequest;
use App\Services\RuleVersionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** T-115 (19-09-2026) — Super-Admin-editable hero copy for the public landing page (`/`), built on T-104's static version. */
class LandingHeroController extends Controller
{
    public function index(Request $request, RuleVersionService $rules): Response
    {
        return Inertia::render('super-admin/landing-hero', [
            'headline' => $rules->value('landing_hero_headline', ''),
            'subtext' => $rules->value('landing_hero_subtext', ''),
            'cta_primary_label' => $rules->value('landing_hero_cta_primary_label', ''),
            'cta_secondary_label' => $rules->value('landing_hero_cta_secondary_label', ''),
        ]);
    }

    public function update(UpdateLandingHeroRequest $request, PublishRuleVersion $action): RedirectResponse
    {
        $action([
            'landing_hero_headline' => $request->string('headline')->toString(),
            'landing_hero_subtext' => $request->string('subtext')->toString(),
            'landing_hero_cta_primary_label' => $request->string('cta_primary_label')->toString(),
            'landing_hero_cta_secondary_label' => $request->string('cta_secondary_label')->toString(),
        ], $request->user());

        return redirect()->route('super-admin.landing-hero.index')->with('status', 'Landing page hero updated.');
    }
}
