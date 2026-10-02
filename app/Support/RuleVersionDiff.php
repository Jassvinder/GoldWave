<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * T-108 (17-09-2026), user-reported — Version History previously showed
 * nothing beyond a bare label/date/publisher unless the Super Admin
 * happened to type an optional free-text note, which they usually didn't.
 * This computes what actually changed between one `rule_values` snapshot
 * and the previous one, field by field, so the timeline always shows real
 * content instead of relying on that optional note. Every `RuleVersion` is
 * a complete key=>value snapshot (`PublishRuleVersion`), so a diff between
 * two consecutive versions' full snapshots is exactly "what this publish
 * changed."
 */
class RuleVersionDiff
{
    private const LABELS = [
        'emi_current_rate_maintenance_cost_percent' => 'EMI Current Rate Maintenance Cost %',
        'level_income_rates' => 'Level Income Rates (Silver)',
        'level_income_rates_gold' => 'Level Income Rates (Gold)',
        'level_income_min_directs' => 'Level Income Directs Needed',
        'pair_qualification_emis' => 'Pair Qualification EMIs',
        'pair_value_per_entry' => 'Pair Value Per Entry (Silver)',
        'pair_value_per_entry_gold' => 'Pair Value Per Entry (Gold)',
        'pair_milestones' => 'Pair Milestones',
        'payout_min_amount' => 'Payout Minimum Amount',
        'payout_tds_percent' => 'Payout TDS %',
        'payout_processing_fee_percent' => 'Payout Processing Fee %',
        'draw_group_size' => 'Draw Group Size',
        'draw_eligibility_emis' => 'Draw Eligibility EMIs',
        'draw_prize_silver_name' => 'Draw Prize — Silver (Months 1–15)',
        'draw_prize_silver_value' => 'Draw Prize Value — Silver (Months 1–15)',
        'draw_prize_gold_name' => 'Draw Prize — Gold (Months 16–20)',
        'draw_prize_gold_value' => 'Draw Prize Value — Gold (Months 16–20)',
        'draw_winners_per_month' => 'Draw Winners Per Month',
        'booster_levels' => 'Booster Levels',
        'dummy_entry_enabled' => 'Dummy Entry Enabled',
        'dummy_entry_daily_count' => 'Dummy Entry Daily Count',
        'purchase_repurchase_income_rates' => 'Purchase/Repurchase Income Rates (Silver)',
        'purchase_repurchase_income_rates_gold' => 'Purchase/Repurchase Income Rates (Gold)',
        'store_profit_distribution_rates' => 'Store Profit Distribution Rates (Silver)',
        'store_profit_distribution_rates_gold' => 'Store Profit Distribution Rates (Gold)',
        'item_buyback_percent' => 'Item Buyback % (Silver)',
        'item_buyback_percent_gold' => 'Item Buyback % (Gold)',
        'store_gst_percent' => 'Store GST %',
        'store_income_min_directs' => 'Store Income Directs to Unlock',
        'store_emi_break_overdue_count' => 'Repurchase EMI: Overdue EMIs to Break',
        'landing_hero_headline' => 'Landing Page Headline',
        'landing_hero_subtext' => 'Landing Page Subtext',
        'landing_hero_cta_primary_label' => 'Landing Page Primary CTA Label',
        'landing_hero_cta_secondary_label' => 'Landing Page Secondary CTA Label',
        'landing_contact_phone' => 'Landing Page Contact Phone',
        'landing_contact_whatsapp' => 'Landing Page Contact WhatsApp',
        'landing_contact_email' => 'Landing Page Contact Email',
        'landing_contact_address' => 'Landing Page Contact Address',
    ];

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return list<string>
     */
    public static function summarize(array $old, array $new): array
    {
        $keys = array_unique([...array_keys($old), ...array_keys($new)]);
        sort($keys);

        $changes = [];

        foreach ($keys as $key) {
            $oldValue = $old[$key] ?? null;
            $newValue = $new[$key] ?? null;

            if (json_encode($oldValue) === json_encode($newValue)) {
                continue;
            }

            $label = self::LABELS[$key] ?? Str::headline($key);

            if (is_array($oldValue) || is_array($newValue)) {
                $changes = [...$changes, ...self::diffArray($label, is_array($oldValue) ? $oldValue : [], is_array($newValue) ? $newValue : [])];
            } else {
                $changes[] = "{$label}: ".self::format($oldValue).' → '.self::format($newValue);
            }
        }

        return $changes;
    }

    /**
     * @param  array<int|string, mixed>  $old
     * @param  array<int|string, mixed>  $new
     * @return list<string>
     */
    private static function diffArray(string $label, array $old, array $new): array
    {
        $oldLeaves = self::flatten($old);
        $newLeaves = self::flatten($new);
        $paths = array_unique([...array_keys($oldLeaves), ...array_keys($newLeaves)]);
        sort($paths);

        $changes = [];

        foreach ($paths as $path) {
            $oldValue = $oldLeaves[$path] ?? null;
            $newValue = $newLeaves[$path] ?? null;

            if ((string) $oldValue === (string) $newValue) {
                continue;
            }

            $changes[] = "{$label} — {$path}: ".self::format($oldValue).' → '.self::format($newValue);
        }

        return $changes;
    }

    /**
     * @param  array<int|string, mixed>  $value
     * @return array<string, mixed>
     */
    private static function flatten(array $value, string $prefix = ''): array
    {
        $isList = array_is_list($value);
        $result = [];

        foreach ($value as $key => $item) {
            if ($isList && is_array($item)) {
                $identifier = $item['milestone_no'] ?? $item['level_no'] ?? ((int) $key + 1);
                $path = $prefix === '' ? "#{$identifier}" : "{$prefix} #{$identifier}";
            } else {
                $segment = is_string($key) ? Str::headline($key) : (string) $key;
                $path = $prefix === '' ? $segment : "{$prefix} {$segment}";
            }

            if (is_array($item)) {
                $result = [...$result, ...self::flatten($item, $path)];
            } else {
                $result[$path] = $item;
            }
        }

        return $result;
    }

    private static function format(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return (string) $value;
    }
}
