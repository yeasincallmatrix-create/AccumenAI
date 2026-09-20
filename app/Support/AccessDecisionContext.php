<?php

namespace App\Support;

/**
 * Phase 10 — Structured reason taxonomy for access decisions.
 *
 * Every decision site in ModuleAccessService / EffectiveEntitlementResolver
 * maps to exactly one of these constants. The constant value is stored in
 * the `reason` column of module_access_logs (varchar 60).
 *
 * Rules:
 *  - Constants are string-valued for human-readable logs.
 *  - Add new constants at the end; never reorder or rename existing ones.
 *  - The value MUST fit in 60 characters (varchar 60 on the column).
 */
final class AccessDecisionContext
{
    // --- Denial reasons (negative decisions) ---

    /** Package does not include this feature. */
    public const REASON_PACKAGE_FEATURE_NOT_ENTITLED = 'PACKAGE_FEATURE_NOT_ENTITLED';

    /** Module's required dependency is disabled. */
    public const REASON_DEPENDENCY_DISABLED = 'DEPENDENCY_DISABLED';

    /** Parent module is disabled. */
    public const REASON_PARENT_DISABLED = 'PARENT_DISABLED';

    /** Module incompatible with institute industry (industry veto). */
    public const REASON_INDUSTRY_VETO = 'INDUSTRY_VETO';

    /** Subscription has expired or is not active. */
    public const REASON_SUBSCRIPTION_EXPIRED = 'SUBSCRIPTION_EXPIRED';

    /** Scope resolution returned null. */
    public const REASON_SCOPE_NOT_FOUND = 'SCOPE_NOT_FOUND';

    /** Super admin denial was applied. */
    public const REASON_DENIAL_APPLIED = 'DENIAL_APPLIED';

    /** Institute override set feature to disabled. */
    public const REASON_OVERRIDE_DISABLED = 'OVERRIDE_DISABLED';

    /** Feature exists in registry but is not active. */
    public const REASON_FEATURE_REGISTRY_INACTIVE = 'FEATURE_REGISTRY_INACTIVE';

    /** Module not enabled for institute (resolved disabled). */
    public const REASON_MODULE_NOT_ENABLED = 'MODULE_NOT_ENABLED';

    // --- Allow reasons (positive decisions) ---

    /** Package includes the feature. */
    public const REASON_PACKAGE_INCLUDED = 'PACKAGE_INCLUDED';

    /** Super admin grant was applied. */
    public const REASON_GRANT_APPLIED = 'GRANT_APPLIED';

    /** Institute override set feature to enabled. */
    public const REASON_OVERRIDE_ENABLED = 'OVERRIDE_ENABLED';

    /** Feature resolved via tier grant. */
    public const REASON_TIER_GRANT_APPLIED = 'TIER_GRANT_APPLIED';

    /**
     * All valid reason codes (for validation / test stability assertions).
     *
     * @return array<int, string>
     */
    public static function allReasons(): array
    {
        return [
            self::REASON_PACKAGE_FEATURE_NOT_ENTITLED,
            self::REASON_DEPENDENCY_DISABLED,
            self::REASON_PARENT_DISABLED,
            self::REASON_INDUSTRY_VETO,
            self::REASON_SUBSCRIPTION_EXPIRED,
            self::REASON_SCOPE_NOT_FOUND,
            self::REASON_DENIAL_APPLIED,
            self::REASON_OVERRIDE_DISABLED,
            self::REASON_FEATURE_REGISTRY_INACTIVE,
            self::REASON_MODULE_NOT_ENABLED,
            self::REASON_PACKAGE_INCLUDED,
            self::REASON_GRANT_APPLIED,
            self::REASON_OVERRIDE_ENABLED,
            self::REASON_TIER_GRANT_APPLIED,
        ];
    }

    /**
     * Build a decision-context array suitable for logAccess() extras.
     *
     * @return array{reason: string|null, feature_key: string|null, decision: string|null, request_id: string|null}
     */
    public static function forLog(?string $reason = null, ?string $featureKey = null, ?string $decision = null, ?string $requestId = null): array
    {
        return [
            'reason' => $reason,
            'feature_key' => $featureKey,
            'decision' => $decision,
            'request_id' => $requestId,
        ];
    }

    private function __construct() {}
}
