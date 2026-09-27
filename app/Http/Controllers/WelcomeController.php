<?php

namespace App\Http\Controllers;

use App\Services\RuleVersionService;
use Inertia\Inertia;
use Inertia\Response;

/** T-104's public landing page at `/`, made hero-editable by T-115 (19-09-2026). */
class WelcomeController extends Controller
{
    public function index(RuleVersionService $rules): Response
    {
        return Inertia::render('welcome', [
            'hero' => [
                'headline' => $rules->value(
                    'landing_hero_headline',
                    'Own real gold & silver jewellery, one easy instalment at a time.',
                ),
                'subtext' => $rules->value(
                    'landing_hero_subtext',
                    'GoldWave is a jewellery membership program — pick a plan, pay in convenient monthly instalments, and receive genuine gold or silver jewellery, while your own network builds rewards alongside you.',
                ),
                'cta_primary_label' => $rules->value('landing_hero_cta_primary_label', 'Join Now'),
                'cta_secondary_label' => $rules->value('landing_hero_cta_secondary_label', 'Member Login'),
            ],
        ]);
    }
}
