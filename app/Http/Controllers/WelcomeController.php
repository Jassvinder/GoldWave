<?php

namespace App\Http\Controllers;

use App\Models\MembershipPlan;
use App\Models\Store;
use App\Services\RuleVersionService;
use Inertia\Inertia;
use Inertia\Response;

/** T-104's public landing page at `/`, made hero-editable by T-115 (19-09-2026); colourful redesign with plans, stores and editable contact details on 02-10-2026. */
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
            'contact' => self::contact($rules),
            // T-217: Silver row then Gold row, each EMI plan followed by its one-time Direct twin of the same total.
            'plans' => MembershipPlan::where('is_active', true)->get([
                'id', 'name', 'amount', 'installment_count', 'product_category', 'fixed_weight_grams',
            ])->sortBy([
                fn (MembershipPlan $a, MembershipPlan $b): int => ($a->product_category === 'gold') <=> ($b->product_category === 'gold'),
                fn (MembershipPlan $a, MembershipPlan $b): int => (float) $a->amount * ($a->installment_count ?? 1) <=> (float) $b->amount * ($b->installment_count ?? 1),
                fn (MembershipPlan $a, MembershipPlan $b): int => ($a->installment_count === null) <=> ($b->installment_count === null),
            ])->values(),
            'stores' => Store::where('status', 'active')->orderBy('name')->get(['id', 'name', 'location']),
        ]);
    }

    /**
     * Public contact details shown on the landing page footer, editable on the Super Admin Landing Page screen.
     * The defaults are drafts — replace them before going live.
     *
     * @return array{phone: string, whatsapp: string, email: string, address: string}
     */
    public static function contact(RuleVersionService $rules): array
    {
        return [
            'phone' => (string) $rules->value('landing_contact_phone', '+91 99999 99999'),
            'whatsapp' => (string) $rules->value('landing_contact_whatsapp', '+91 99999 99999'),
            'email' => (string) $rules->value('landing_contact_email', 'support@goldwave.in'),
            'address' => (string) $rules->value('landing_contact_address', 'GoldWave Head Office, Main Market, Your City, State - 000000'),
        ];
    }
}
