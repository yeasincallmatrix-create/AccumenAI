<?php

/**
 * Phase 2 — tenant-aware terminology + rule helpers.
 *
 * Resolves the current institute via TenantContext (the codebase pattern —
 * the User model has no direct institute relation).
 */

if (! function_exists('term')) {
    /**
     * Get a terminology term for the current tenant.
     * Priority: tenant override > country-specific > global > default.
     */
    function term(string $key, ?string $default = null): string
    {
        $institute = null;
        $tenantId = function_exists('tenant_id') ? tenant_id() : null;
        if ($tenantId) {
            $institute = \App\Models\Institute::find($tenantId);
        }

        return app(\App\Services\TerminologyService::class)->get($key, $institute, $default);
    }
}

if (! function_exists('rule')) {
    /**
     * Get a module rule for the current tenant.
     * Priority: tenant override > country-specific > global > default.
     */
    function rule(string $moduleKey, string $ruleKey, $default = null)
    {
        $institute = null;
        $tenantId = function_exists('tenant_id') ? tenant_id() : null;
        if ($tenantId) {
            $institute = \App\Models\Institute::find($tenantId);
        }

        return app(\App\Services\RuleEngineService::class)->get($moduleKey, $ruleKey, $institute, $default);
    }
}
