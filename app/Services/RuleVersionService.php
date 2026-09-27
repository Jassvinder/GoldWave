<?php

namespace App\Services;

use App\Models\RuleVersion;
use Illuminate\Support\Facades\Cache;

/**
 * ARCHITECTURE.md: reads the active `rule_versions`/`rule_values` snapshot.
 * Every compensation/pricing Action depends on this, never on a raw query
 * against `rule_values` — this is what makes "store the rule_version_id
 * used" (DOMAIN_LOGIC.md §22) automatic instead of something each Action
 * has to remember.
 */
class RuleVersionService
{
    public function activeVersion(): ?RuleVersion
    {
        // Cache only the scalar id, never the Eloquent model — caching a model
        // instance risks a stale/incompatible serialized shape surviving
        // across a deploy that changes RuleVersion's structure.
        $id = Cache::remember('rule_versions.active_id', 60, function () {
            return RuleVersion::where('is_active', true)->value('id');
        });

        return $id ? RuleVersion::find((int) $id) : null;
    }

    /**
     * Read a configured value from the active rule version, falling back to
     * $default only if the key has never been configured (never silently
     * substitutes a default for a configured value).
     */
    public function value(string $key, mixed $default = null): mixed
    {
        $version = $this->activeVersion();

        if (! $version) {
            return $default;
        }

        $ruleValue = $version->values()->where('key', $key)->first();

        if (! $ruleValue) {
            return $default;
        }

        return $ruleValue->value ?? $default;
    }

    /**
     * T-110 (19-09-2026) — Gold/Silver compensation split: every split rate
     * is stored as two sibling keys, `{$baseKey}` (Silver — the original,
     * unsuffixed key, left as-is per the user's own instruction) and
     * `{$baseKey}_gold`. Callers pass whichever metal the triggering
     * payment/sale/plan actually is; `'silver'` (or anything else) reads the
     * plain key unchanged.
     */
    public function metalValue(string $baseKey, string $metal, mixed $default = null): mixed
    {
        $key = $metal === 'gold' ? "{$baseKey}_gold" : $baseKey;

        return $this->value($key, $default);
    }
}
