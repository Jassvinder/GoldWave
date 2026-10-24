<?php

namespace App\Http\Controllers;

use App\Actions\Draw\ExecuteMonthlyDraw;
use App\Models\DrawExecution;
use App\Models\MembershipPlan;
use App\Models\Store;
use App\Services\RuleVersionService;
use App\Support\Dates;
use Illuminate\Support\Carbon;
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
            'plans' => MembershipPlan::where('is_active', true)->orderBy('id')->get([
                'id', 'name', 'amount', 'installment_count', 'product_category', 'fixed_weight_grams',
            ]),
            'stores' => Store::where('status', 'active')->orderBy('name')->get(['id', 'name', 'location']),
            'latest_draw' => self::latestDraw(),
        ]);
    }

    /**
     * T-239 — every winner of the latest calendar month that had a draw (automatic or manual, DOMAIN_LOGIC.md §8.4),
     * with the Sponsor named only when they also received the prize (§8.5). Older months stay behind member login.
     *
     * @return array{month: string, winners: array<int, array<string, mixed>>}|null
     */
    private static function latestDraw(): ?array
    {
        $drawn = DrawExecution::whereIn('status', ['executed', 'reconciled']);
        $latest = (clone $drawn)->max('executed_at');

        if ($latest === null) {
            return null;
        }

        $month = Carbon::parse($latest);

        $winners = $drawn
            ->whereBetween('executed_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->with(['drawGroup.monthConfigs', 'winner.user', 'uplineBenefitMember.user'])
            ->get()
            ->sortBy([
                fn (DrawExecution $a, DrawExecution $b): int => $a->drawGroup->group_no <=> $b->drawGroup->group_no,
                ['cycle_month_no', 'asc'],
                ['winner_no', 'asc'],
            ])
            ->values()
            ->map(function (DrawExecution $execution): array {
                $prize = $execution->drawGroup->monthConfigs->firstWhere('cycle_month_no', $execution->cycle_month_no);

                return [
                    'id' => $execution->id,
                    'group_no' => $execution->drawGroup->group_no,
                    'cycle_month_no' => $execution->cycle_month_no,
                    'cycle_months' => ExecuteMonthlyDraw::CYCLE_MONTHS,
                    'executed_at' => Dates::date($execution->executed_at),
                    'prize_name' => $prize?->prize_name,
                    'prize_value' => $prize?->prize_value,
                    'metal' => $prize?->metal_type,
                    'winner_name' => $execution->winner->user?->name,
                    'winner_customer_id' => $execution->winner->customer_id,
                    'upline_name' => $execution->uplineBenefitMember?->user?->name,
                    'upline_customer_id' => $execution->uplineBenefitMember?->customer_id,
                ];
            })
            ->all();

        return ['month' => $month->format('F Y'), 'winners' => $winners];
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
