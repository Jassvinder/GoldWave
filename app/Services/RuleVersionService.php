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
}
