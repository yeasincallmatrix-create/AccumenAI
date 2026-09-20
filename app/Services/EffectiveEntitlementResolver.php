<?php

namespace App\Services;

use App\Models\Institute;
use App\Models\PackageScope;
use App\Support\AccessDecisionContext;

/**
 * Phase 8 — Effective Resolution Engine (facade).
 *
 * Unified entry point for ALL access decisions. This class contains NO
 * resolution logic of its own: every read delegates to the corresponding
 * public method on ModuleAccessService, so behavior is byte-identical
 * to the pre-Phase-8 call paths.
 *
 * Contract: docs/PHASE_0_SCOPED_PACKAGE_CONTRACT_V1.md §3 (A3/A4),
 * §9. Resolution formula: (Base ∪ Overrides ∪ Grants) − Denials.
 *
 * What the facade adds (and only this):
 *   - a single public interface (resolveForInstitute / resolveModules /
 *     resolveFeatures / isModuleEnabled / isFeatureEnabled / flushCache);
 *   - a unified return shape with meta information (resolution_source,
 *     grants_applied, denials_applied).
 *
 * B100: meta counts come from getFeatureAccessMapWithMeta() — the SAME
 * single computation that builds the map — so the counts can never
 * drift from the rows Gate 4 / Gate 5 evaluated. The resolver issues
 * NO direct DB queries at all.
 *
 * Stateless: no instance state is kept between calls. All caching stays
 * inside ModuleAccessService (module_access:{id},
 * feature_access:{id}:{scope_hash}); cache keys are unchanged.
 */
class EffectiveEntitlementResolver
{
    public const SOURCE_SCOPE = 'scope';

    public const SOURCE_GLOBAL = 'global';

    public const SOURCE_FALLBACK = 'fallback';

    public function __construct(
        protected ModuleAccessService $access
    ) {}

    /**
     * Resolve complete access state for an institute.
     *
     * Phase 10: includes per-feature denial reasons for decision tracing.
     *
     * @return array{
     *   modules: array<string, bool>,
     *   features: array<string, bool>,
     *   scope: ?PackageScope,
     *   meta: array{resolution_source: string, grants_applied: int, denials_applied: int},
     *   reasons: array<string, string>
     * }
     */
    public function resolveForInstitute(Institute $institute): array
    {
        $scope = $this->access->resolveScopedPackage($institute);

        // B100: one computation yields both the map and the meta counts —
        // grants/denials are loaded exactly once, by the same code path
        // that evaluates Gate 4 / Gate 5.
        $featuresWithMeta = $this->access->getFeatureAccessMapWithMeta($institute);

        return [
            'modules' => $this->resolveModules($institute),
            'features' => $featuresWithMeta['map'],
            'scope' => $scope,
            'meta' => [
                'resolution_source' => $this->resolutionSource($scope),
                'grants_applied' => $featuresWithMeta['grants_applied'],
                'denials_applied' => $featuresWithMeta['denials_applied'],
            ],
            'reasons' => $featuresWithMeta['reasons'] ?? [],
        ];
    }

    /**
     * Module map (module_key => bool). Delegates to resolveEnabled()
     * (uncached) — identical output to the pre-Phase-8 call path.
     *
     * @return array<string, bool>
     */
    public function resolveModules(Institute $institute): array
    {
        return $this->access->resolveEnabled($institute);
    }

    /**
     * Feature map (feature_key => bool). Delegates to
     * getFeatureAccessMap() — the same cached map controllers consume.
     *
     * @return array<string, bool>
     */
    public function resolveFeatures(Institute $institute): array
    {
        return $this->access->getFeatureAccessMap($institute);
    }

    /**
     * Delegates to ModuleAccessService::isEnabled().
     */
    public function isModuleEnabled(Institute $institute, string $moduleKey): bool
    {
        return $this->access->isEnabled($institute, $moduleKey);
    }

    /**
     * Delegates to ModuleAccessService::isFeatureEnabled() — preserves
     * the uncached compute path and the malformed-key fail-closed rule.
     */
    public function isFeatureEnabled(Institute $institute, string $featureKey): bool
    {
        return $this->access->isFeatureEnabled($institute, $featureKey);
    }

    /**
     * Flush module + feature caches (mirrors every mutation path in
     * ModuleAccessService, which always flushes both).
     */
    public function flushCache(int $instituteId): void
    {
        $this->access->flushCache($instituteId);
        $this->access->flushFeatureCache($instituteId);
    }

    /**
     * Classify where the effective package came from.
     */
    public function resolutionSource(?PackageScope $scope): string
    {
        if ($scope === null) {
            return self::SOURCE_FALLBACK;
        }

        if ($scope->country_id === null && $scope->industry_id === null && $scope->sub_industry_id === null) {
            return self::SOURCE_GLOBAL;
        }

        return self::SOURCE_SCOPE;
    }
}
