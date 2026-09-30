<?php

namespace App\Services;

use App\Models\DrawGroup;
use App\Models\DrawGroupMonthConfig;

/**
 * DOMAIN_LOGIC.md §8.3 (30-09-2026, user decision) — which prize a group's draw month carries. A prize set for that
 * exact group and month wins; otherwise the Super Admin's default applies: Silver for months 1–15, Gold for 16–20.
 * A draw therefore never waits for a prize to be configured.
 */
class DrawPrizeResolver
{
    /** Months 1–15 of every group's cycle are Silver, 16–20 Gold (§8.3). */
    public const SILVER_MONTHS = 15;

    /** User-confirmed defaults (30-09-2026), used until Super Admin publishes different ones in Draw Settings. */
    public const DEFAULTS = [
        'draw_prize_silver_name' => 'Silver Jewellery',
        'draw_prize_silver_value' => 20000,
        'draw_prize_gold_name' => 'Gold Jewellery',
        'draw_prize_gold_value' => 25000,
        'draw_winners_per_month' => 1,
    ];

    public function __construct(private readonly RuleVersionService $rules) {}

    /** @return array{prize_name: string, prize_value: float, metal_type: string, winners_count: int} */
    public function defaultFor(int $cycleMonthNo): array
    {
        $metal = $cycleMonthNo <= self::SILVER_MONTHS ? 'silver' : 'gold';

        return [
            'prize_name' => (string) $this->rules->value("draw_prize_{$metal}_name", self::DEFAULTS["draw_prize_{$metal}_name"]),
            'prize_value' => (float) $this->rules->value("draw_prize_{$metal}_value", self::DEFAULTS["draw_prize_{$metal}_value"]),
            'metal_type' => $metal,
            'winners_count' => max(1, (int) $this->rules->value('draw_winners_per_month', self::DEFAULTS['draw_winners_per_month'])),
        ];
    }

    /** @return array{prize_name: string, prize_value: float, metal_type: string, winners_count: int} */
    public function forMonth(DrawGroup $group, int $cycleMonthNo): array
    {
        $config = DrawGroupMonthConfig::where('draw_group_id', $group->id)
            ->where('cycle_month_no', $cycleMonthNo)
            ->first();

        if ($config === null) {
            return $this->defaultFor($cycleMonthNo);
        }

        return [
            'prize_name' => $config->prize_name,
            'prize_value' => (float) $config->prize_value,
            'metal_type' => $config->metal_type,
            'winners_count' => (int) $config->winners_count,
        ];
    }

    /**
     * Freezes the prize a draw is held for, so later changes to the defaults never rewrite a past draw (§8.3's
     * immutable snapshot). Reports and the Financial Summary read this row.
     */
    public function snapshot(DrawGroup $group, int $cycleMonthNo): DrawGroupMonthConfig
    {
        return DrawGroupMonthConfig::firstOrCreate(
            ['draw_group_id' => $group->id, 'cycle_month_no' => $cycleMonthNo],
            $this->defaultFor($cycleMonthNo),
        );
    }
}
