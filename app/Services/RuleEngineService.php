<?php

namespace App\Services;

use App\Models\Institute;
use Illuminate\Support\Facades\DB;

class RuleEngineService
{
    /**
     * Get rule value for a module.
     * Priority: tenant override > country-specific > global > default.
     *
     * Tenant override keys are stored as "{moduleKey}.{ruleKey}".
     */
    public function get(string $moduleKey, string $ruleKey, ?Institute $institute = null, $default = null)
    {
        $fullKey = "{$moduleKey}.{$ruleKey}";

        // 1. Tenant override
        if ($institute && $institute->rule_overrides) {
            $overrides = is_string($institute->rule_overrides)
                ? json_decode($institute->rule_overrides, true)
                : $institute->rule_overrides;

            if (is_array($overrides) && array_key_exists($fullKey, $overrides)) {
                return $overrides[$fullKey];
            }
        }

        // 2. Country-specific
        if ($institute && $institute->country_code) {
            $rule = DB::table('module_rules')
                ->where('country_code', $institute->country_code)
                ->where('module_key', $moduleKey)
                ->where('rule_key', $ruleKey)
                ->where('is_active', true)
                ->first();
            if ($rule) {
                return json_decode($rule->rule_value, true);
            }
        }

        // 3. Global (country_code IS NULL)
        $rule = DB::table('module_rules')
            ->whereNull('country_code')
            ->where('module_key', $moduleKey)
            ->where('rule_key', $ruleKey)
            ->where('is_active', true)
            ->first();

        if ($rule) {
            return json_decode($rule->rule_value, true);
        }

        // 4. Fallback
        return $default;
    }

    /**
     * Set a tenant-level rule override (persists to institutes.rule_overrides JSON).
     * Key format: "{moduleKey}.{ruleKey}".
     */
    public function setOverride(Institute $institute, string $moduleKey, string $ruleKey, $value): void
    {
        $overrides = $institute->rule_overrides;
        $overrides = is_string($overrides) ? (json_decode($overrides, true) ?? []) : ($overrides ?? []);

        $overrides["{$moduleKey}.{$ruleKey}"] = $value;

        // Institute has no array cast for this column yet — persist as JSON string.
        $institute->rule_overrides = json_encode($overrides, JSON_UNESCAPED_UNICODE);
        $institute->save();
    }
}
