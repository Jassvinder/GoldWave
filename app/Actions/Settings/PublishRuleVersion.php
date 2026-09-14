<?php

namespace App\Actions\Settings;

use App\Models\RuleValue;
use App\Models\RuleVersion;
use App\Models\User;
use App\Support\Dates;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * DOMAIN_LOGIC.md §0's "Centralized, versioned configuration" + T-017
 * pre-coding pass (`DOMAIN_LOGIC.md` §21) — the one shared writer behind
 * every Super Admin settings page (S03/S04/S06/S08, Admin Compensation
 * Management's config page). A settings change of any kind always publishes
 * a whole new `RuleVersion`, never mutates an existing published version's
 * `RuleValue` rows — each version is a complete, self-sufficient snapshot
 * (every key carried forward from the version being replaced, then
 * overlaid with the caller's changes), matching "every finalized
 * calculation stores the rule_version_id it used, never a live re-lookup."
 */
class PublishRuleVersion
{
    /** @param array<string, mixed> $overrideValues */
    public function __invoke(
        array $overrideValues,
        User $operator,
        ?string $notes = null,
        ?string $effectiveFrom = null,
    ): RuleVersion {
        return DB::transaction(function () use ($overrideValues, $operator, $notes, $effectiveFrom) {
            $effectiveFrom ??= Dates::date(now());

            $previous = RuleVersion::where('is_active', true)->first();
            $carriedForward = [];

            if ($previous) {
                $carriedForward = $previous->values()->pluck('value', 'key')->all();
                $previous->update(['is_active' => false, 'effective_to' => $effectiveFrom]);
            }

            $nextVersionNo = (int) (RuleVersion::max('version_no') ?? 0) + 1;

            $version = RuleVersion::create([
                'version_no' => $nextVersionNo,
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'is_active' => true,
                'published_by' => $operator->id,
                'published_at' => now(),
                'notes' => $notes,
            ]);

            $merged = array_merge($carriedForward, $overrideValues);

            foreach ($merged as $key => $value) {
                RuleValue::create([
                    'rule_version_id' => $version->id,
                    'key' => $key,
                    'value' => $value,
                ]);
            }

            Cache::forget('rule_versions.active_id');

            return $version;
        });
    }
}
