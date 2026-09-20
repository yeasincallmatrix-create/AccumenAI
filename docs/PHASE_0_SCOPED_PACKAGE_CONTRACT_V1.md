# Phase 0 — Scoped Package Contract V1

> **Status:** normative contract for package scoping, module/feature
> entitlement, denial, expiry and super-admin grants.
>
> **Provenance (B90, Phase 7c):** this file was missing from the repo and has
> no ancestor in git history (`git log --all --full-history` for this path is
> empty), so restoration-from-history (C2b) was impossible. It is
> reconstructed (C2a) on 2026-09-20 from the shipped implementation. Every
> normative rule below cites the code that enforces it as
> `path:line`. Where the implementation is the only source of truth, the
> citation IS the specification. Anything not cited is informative context,
> not contract.
>
> **Scope fence:** UI-only platform-admin surfaces plus the entitlement
> engine. No new tables beyond the ones listed in §8. Billing is
> preparation-only (see §9, 63G).

---

## 0. Terminology

| Term | Meaning |
|---|---|
| Package | A `subscription_packages` row (tier), e.g. `free`, `basic`, `advanced`, `premium`. |
| Scope | A `package_scopes` row: one package localised to a (country, industry, sub-industry) triple. |
| Scope hash | `{package_id}-{country_id\|G}-{industry_id\|G}-{sub_industry_id\|G}`, `G` = global (null). |
| Module | A `module_registry` key (e.g. `finance`, `crm`, `medical`). |
| Feature | A `feature_registry` key of form `<module>.<capability>` (e.g. `medical.pharmacy`). |
| Entitlement | An `institute_module_entitlements` row: an individual grant (`is_grant=true`) or denial (`is_grant=false`) for one institute + module. |
| Grant | A `tenant_access_grants` row (super-admin, additive). |
| Denial | A `tenant_access_denials` row (super-admin, subtractive, wins over everything). |
| Effective package | The package actually evaluated: the institute's own package while its subscription is active, else `free`. |
| Fail-closed | Any missing row, unknown key, inactive status or expired window evaluates to `false`/denied. |

---

## 1. Scope model

### 1.1 `package_scopes`

Model: `app/Models/PackageScope.php`.

Fillable columns (`app/Models/PackageScope.php:17-27`):

- `package_id`, `country_id` (nullable), `industry_id` (nullable),
  `sub_industry_id` (nullable)
- `inherit_from_parent` (bool cast, `:31`)
- `price_monthly`, `price_yearly` (decimal:2 casts, `:32-33`), `currency`
- `status` (`active` rows participate; every resolver filters `status = active`)

Rules:

1. **Scope hash is derived, never input.** On creating, `scope_hash` is set to
   `implode('-', [package_id, country_id ?? 'G', industry_id ?? 'G',
   sub_industry_id ?? 'G'])` (`app/Models/PackageScope.php:37-44`).
2. **Global scope** = all three locale columns null
   (`isGlobal()`, `app/Models/PackageScope.php:81-86`).
3. **Price/currency inheritance.** `effectiveMonthlyPrice()`,
   `effectiveYearlyPrice()` and `effectiveCurrency()` return the scope's own
   value when set, otherwise walk `ModuleAccessService::resolveParentScope()`
   up the chain, otherwise fall back to the package base price, otherwise
   `null` (prices) / `'BDT'` (currency)
   (`app/Models/PackageScope.php:88-153`).
4. **Cache invalidation.** On update, the scope flushes its feature cache via
   `ModuleAccessService::flushFeatureCacheForScope()`
   (`app/Models/PackageScope.php:46-48`).
5. **Active scope query helper.** `scopeActive()` filters `status = active`
   (`app/Models/PackageScope.php:71-74`).

### 1.2 `package_scoped_features`

Model: `app/Models/PackageScopedFeature.php`. Table `package_scoped_features`.
Columns: `package_scope_id`, `feature_key`, `enabled` (bool cast)
(`app/Models/PackageScopedFeature.php:14-22`). Belongs to a scope and to the
`FeatureRegistry` by `feature_key`
(`app/Models/PackageScopedFeature.php:24-32`).

### 1.3 `package_scoped_modules`

Model: `app/Models/PackageScopedModule.php`. Table `package_scoped_modules`.
Columns: `package_scope_id`, `module_key`, `enabled` (bool cast), `scope_hash`
(`app/Models/PackageScopedModule.php:12-17`). On creating, an empty
`scope_hash` is derived as `{package_scope_id}|{module_key}`
(`app/Models/PackageScopedModule.php:23-30`).

### 1.4 Parent resolution (`resolveParentScope`)

`app/Services/ModuleAccessService.php:1242-1274`. One level up the scope
hierarchy, first `active` match wins, same `package_id` only:

- scope has sub-industry → try `(country, industry, null)`,
  `(country, null, null)`, `(null, industry, null)`, `(null, null, null)`;
- else scope has industry → try `(country, null, null)`, `(null, null, null)`;
- else scope has country → try `(null, null, null)`;
- global scope → no parent (`null`).

### 1.5 Inheritance reads

`getScopedFeatureKeys()` (`app/Services/ModuleAccessService.php:1133-1160`)
and `getScopedModuleKeys()` (`:1166-1193`):

- collect direct rows with `enabled = true`;
- if `inherit_from_parent` is falsy, return direct rows only;
- else return `(parent ∪ direct) − explicit-disabled`
  (rows with `enabled = false` on the child subtract inherited keys).
- Recursion terminates at a scope with no parent.

### 1.6 Scope provisioning

`ensureScopeExistsForInstitute()` (`app/Services/ModuleAccessService.php:1282-...`):
idempotent; resolves package (institute package else `free`); returns the
existing row for the exact (package, country, industry, sub-industry) tuple or
creates one inside a DB transaction with `inherit_from_parent = true`.
`PackagesGenerateScopes` (`app/Console/Commands/PackagesGenerateScopes.php`)
bulk-generates scopes the same way (skips existing `scope_hash`).

---

## 2. Fallback

### 2.1 Scope fallback chain (`resolveScopedPackage`)

`app/Services/ModuleAccessService.php:999-1048`. Most-specific `active`
`PackageScope` for the institute, else `null` (fail-closed):

1. Package: institute `package_id`; null → `free` package; still null → `null`.
2. Candidates, in order (deduped):
   `(country, industry, sub)`, `(country, industry, null)`,
   `(country, null, sub)`, `(country, null, null)`,
   `(null, industry, sub)`, `(null, industry, null)`,
   `(null, null, null)` = GLOBAL.
3. First row with `status = active` wins.

`resolveScopedPackageForPackage()` (`:1061-1101`) is the same chain keyed on
an explicit tier package (B75 tier grants), using the institute's own
locale. Null → caller falls back to legacy `package_features`.

### 2.2 Effective package (subscription enforcement)

`isSubscriptionActive()` (`app/Services/ModuleAccessService.php:700-719`):
latest `institute_subscriptions` row must exist, have `status = active`, and
`end_date` null-or-future. Any exception → `false` (fail-closed).

`resolvePackageModules()` (`:1207-1237`): when the subscription is inactive
**or** `package_id` is null, the effective package becomes `free`
(SaaS enforcement, Step 60 P0; legacy institutes without a package are FREE
tier — `resolveEnabled()` comment, `:243-244`). Scope resolution probes the
EFFECTIVE package (a cloned institute with the effective `package_id`), not
necessarily the institute's own lapsed tier (`:1218-1222`).

### 2.3 Module base: scoped dual-read with legacy fallback

`resolvePackageModules()` (`:1207-1237`):

- scope resolves for the effective package AND holds a non-empty scoped
  module set → scoped modules win;
- otherwise legacy `package_modules` (`enabled = true`) for the effective
  package is the source of truth.

### 2.4 Feature base: scoped ∩ legacy, then legacy fallback

`computeFeatureAccessMap()` (`:822-865`):

- when a scope holds scoped features, the package base is
  `scoped ∩ legacy-enabled` (a scope cannot invent features the package
  never had);
- when the scoped set is empty, legacy `package_features`
  (`enabled = true`) for the effective package is the source of truth.
- Known limitation B77: an empty scoped set ALWAYS falls back — scope is
  additive; there is no "explicitly empty" scope (`:816-818`).

### 2.5 Tier-grant fallback (B75)

`getTierFeatureKeys()` (`:1113-1128`): tier slug → tier package → scoped
features when a scope resolves and is non-empty (with parent inheritance),
else legacy `package_features`. Unknown tier slugs log a warning and have no
effect (`:932-940`).

### 2.6 Education-industry default veto

`EDUCATION_DISABLED_MODULES = ['sales', 'purchase', 'hr', 'crm']`
(`app/Services/ModuleAccessService.php:42`). For `industry = education` the
package base is filtered BEFORE overrides/entitlements
(`resolveEnabled()` step 1b, `:250-252`), but an override or entitlement can
still re-enable. Entitlements can never bypass industry compatibility
(step 4, `:282-284`; `isIndustryCompatible()`, `:389-406`).

---

## 3. Access-decision layers A1 / A2 / A3 / A4

Evaluation is layered. Each layer is fail-closed; a denial at any layer is
final for that layer's question.

### A1 — Actor layer (who is asking)

1. Guards, in order: `platform_admin`, `institute_user`, `web`, `guardian`,
   `platform_staff`; console → `system`
   (`resolveActorType()`, `app/Services/ModuleAccessService.php:439-448`).
2. Institute owner is a super-user inside their institute:
   `InstituteUser::isOwner()` = role slug `institute-owner`
   (`app/Models/InstituteUser.php:177-180`); `hasPermission()` returns `true`
   for owners, else checks the role's `role_permissions` matrix
   (`:149-161`).
3. AI/assistant callers snapshot permissions once per request:
   owner (or owner membership) → `['*']`, else the role's permission slugs
   (`app/Services/Ai/AiContext.php:63-83`); `hasPermission()` is a strict
   in-array check (`:41-44`).
4. Platform-admin bypass lives in the MIDDLEWARE layer
   (`CheckModuleAccess` / `MedicalModuleAccess`), never inside
   `isFeatureEnabled()` — which is a pure query
   (`app/Services/ModuleAccessService.php:758-760`).
5. Exactly one immutable platform super-admin exists; replacing its identity
   raises `SingleSuperAdminViolationException`
   (`tests/Feature/SingleImmutableSuperAdminTest.php:150-156`); platform
   staff permissions are static per staff `role`
   (`app/Models/PlatformStaff.php:60`, `:131-136`).

### A2 — Subscription / tier layer (what was bought)

1. `isSubscriptionActive()` (§2.2). Inactive/expired/cancelled → effective
   package `free`; legacy packageless institutes → `free`.
2. `resolveScopedPackage()` (§2.1) localises the effective package to the
   institute's (country, industry, sub-industry). No row → fail-closed null;
   callers apply legacy fallback.
3. Commercial fields (`monthly_price`, `yearly_price`, `billing_cycle`,
   `auto_renew`, `discount_percent`, `purchased_by`) are nullable
   informational metadata; `isEnabled()` does NOT require payment status
   (63G preparation-only header,
   `app/Services/ModuleAccessService.php:24-30`).

### A3 — Module layer (is the module on)

`resolveEnabled()` (`app/Services/ModuleAccessService.php:239-304`), per
module, in order:

1. **Package base** — membership in `resolvePackageModules()` (§2.3),
   education-filtered (§2.6).
2. **Legacy permanent override** — `institute_module_overrides` row wins over
   base (`enableModule()` / `disableModule()`, `:86-...`; audit via
   `logAccess()`, `:413-437`; cache flush, `:450-452`).
3. **Active individual entitlement** — grant/deny wins over the override.
   Map built by `getActiveEntitlementMap()` (`:310-337`): only statuses
   `active`/`trialing` whose window is open (`isEntitlementActive()`,
   `:342-380`); **latest `updated_at` wins; deny wins on tie**
   (`:327-332`). This is the "latest wins; deny on tie" rule.
4. **Industry compatibility** — entitlements cannot bypass it (`:282-284`).
5. **Dependency closure** — `checkDependencies()`; missing deps force off.
6. **Parent dependency** — a child with `parent_key` requires the parent on.

Result is cached 1h as `module_access:{institute_id}`
(`getEnabledModules()`, `:63-72`); `flushCache()` on every mutation
(`:450-452`).

Entitlement writes go through the service, never direct model updates:

- `grantModule()` (`:510-573`): module must exist in `module_registry`
  (else validation error); defaults `status = active`, `is_grant = true`,
  open windows; audit `entitlement_granted` / `entitlement_denied` /
  `trial_started`; flushes module + feature caches.
- `revokeModule()` (`:575-602`): sets matching `active`/`trialing`/`pending`
  rows to `revoked` AND soft-deletes them; audit `entitlement_revoked`.
- `extendEntitlement()` (`:604-...`): extend expiry through the service so
  industry checks, cache flush and audit apply.

### A4 — Feature layer (is the capability on)

`isFeatureEnabled()` (`:766-775`) answers from `computeFeatureAccessMap()`
(`:822-979`). `featureKey` must be non-empty `<module>.<capability>`, else
`false` (`:768-770`). Per feature:

- **Gate 1 — parent module enabled.** `isEnabled()` for the feature's module
  (industry veto included); off → `false` (`:877-881`).
- **Gate 2 — registry active.** Only `feature_registry` rows with
  `status = active` are evaluated; unknown keys are skipped, never invented
  (`:824-827`, `:810-814`).
- **Gate 3 — package base.** Scoped ∩ legacy, else legacy (§2.4).
- **Gate 3.5 — institute override wins.**
  `institute_feature_overrides` short-circuits the map entry (`:887-890`).
- **Gate 4 — super-admin grants, additive** (§6). Applied after package +
  overrides. B76: every grant still requires its parent module enabled;
  unknown tier slugs are logged and skipped.
- **Gate 5 — super-admin denials, subtractive, LAST** (§5). Wins over
  everything, including grants and overrides.

Resolution formula: `(Base ∪ Overrides ∪ Grants) − Denials`. Only rows with
`status = active` and `expires_at` null/future apply (`:896-901`, `:956-961`).

Caching: `getFeatureAccessMap()` caches 1h under
`feature_access:{institute_id}:{scope_hash}`
(`:785-794`); `flushFeatureCache()` / `flushFeatureCacheForScope()` /
`flushModuleCacheForScope()` (`:985-...`, `:1369-...`, `:1391-...`) run on
scope, feature and module mutations.

---

## 4. Denial

Two denial instruments, both fail-closed and audit-logged:

1. **Module-level individual denial.** An entitlement with
   `is_grant = false` forces the module off at A3-step 3, over legacy
   overrides. Collisions resolve latest-wins; **deny wins on exact tie**
   (`getActiveEntitlementMap()`, `:310-337`). Audit action
   `entitlement_denied` (`grantModule()`, `:557-567`).
2. **Feature/module super-admin denial (Gate 5).** `tenant_access_denials`
   with `deny_type = feature|module`, applied LAST in A4 (`:955-977`):
   feature denial clears that key; module denial clears every registered
   feature of the module. Only `status = active` and unexpired rows apply.
   Denials beat grants, overrides and package base unconditionally.

Denial rows with unknown keys are ignored (never invented — Gate 2
preserved, `:810-814`). Lifting a denial is a privileged action
(`admin.institutes.denials.lift`, §6).

---

## 5. Expiry

### 5.1 Entitlement windows

`isEntitlementActive()` (`app/Services/ModuleAccessService.php:342-380`):

- `revoked`, `expired`, `pending` → inactive (pending activates only via the
  expiry command, §5.2).
- `trialing` → active only inside `[trial_starts_at, trial_ends_at]`
  (open ends count as unbounded).
- `active` → active unless `now < starts_at` or `now > ends_at`.
- Soft-deleted rows are excluded by Eloquent before evaluation.

### 5.2 The expiry sweeper (`entitlements:expire`)

Command: `app/Console/Commands/EntitlementsExpire.php`, signature
`entitlements:expire {--dry-run}` (`:12-14`).

Transitions (all compare against `now`, `:22`):

| # | From → To | Condition |
|---|---|---|
| 1 | `pending → active` | `starts_at <= now` (audit `entitlement_granted … Activated via entitlements:expire`, `:35-53`); `starts_at` null → activate immediately (`:60-78`). Future pending NEVER activates early. |
| 2 | `active → expired` | `ends_at < now` (audit `entitlement_expired`, `:81-105`). |
| 3 | `trialing → expired` | `trial_ends_at < now` (audits `trial_expired` + `entitlement_expired`, `:107-143`). |

Never transitions: `trialing → active` auto-promotion is FORBIDDEN
(trialing stays trialing until window logic, `:145`); `revoked`/`expired`
are never reactivated (`:146`). After live changes, per-institute module
caches flush (`:148-153`). `--dry-run` previews counts without writes
(`:156-165`).

### 5.3 Grant / denial expiry

`tenant_access_grants` / `tenant_access_denials` carry nullable `expires_at`
(`app/Models/TenantAccessGrant.php:15-26`, `app/Models/TenantAccessDenial.php:15-26`;
casts `:28-32`). Scopes `scopeActive()` + `scopeNotExpired()`
(null-or-future, `:49-59`) define applicability; A4 queries inline the same
predicate (`:896-901`, `:956-961`). `isExpired()` (`:62-69` both models):
null → never expires. Lifecycle columns: grants —
`granted_by/at`, `revoked_at/by`, `status`; denials —
`denied_by/at`, `lifted_at/by`, `status`.

---

## 6. Super-admin grants

### 6.1 Grant instruments (Gate 4, additive)

`computeFeatureAccessMap()` Gates 4 (`app/Services/ModuleAccessService.php:895-953`).
Only `status = active`, unexpired rows (`:896-901`):

- `grant_type = feature` → set that feature key `true` (B76: parent module
  must be enabled, `:905-912`).
- `grant_type = module` → set ALL registered features of the module `true`
  (B76: the module itself must be enabled, `:913-925`).
- `grant_type = tier` → tier slug → tier package (case-insensitive) →
  `getTierFeatureKeys()` (§2.5); each feature still needs its parent module
  enabled (B76, `:942-951`). Unknown tier → warning log, no effect
  (`:932-940`).
- Unknown keys are skipped (Gate 2 preserved).

### 6.2 Privileged surfaces (platform_admin only)

`routes/web.php:467-486` ("Admin: Scoped Packages (Phase 4b-6, UI-only —
no new tables)"):

- Scopes: `admin.packages.scopes.{index,create,store}`,
  `admin.scopes.{show,edit,update}`,
  `admin.scopes.features.update`, `admin.scopes.modules.update`,
  `admin.scopes.destroy` — `PackageScopeAdminController`
  (`app/Http/Controllers/Admin/PackageScopeAdminController.php`):
  paginated index with feature counts (`:34-...`); show merges parent-scope
  rows for diff display (`:78-...`); store clones parent features on create
  (`:154-...`); `updateFeatures`/`updateModules` via `updateOrCreate`
  (`:258-...`, `:289-...`); destroy removes child feature rows (`:320-336`).
- Tenant access: `admin.institutes.access` (show),
  `admin.institutes.grants.{store,revoke}`,
  `admin.institutes.denials.{store,lift}` — `TenantAccessController`.
- Module entitlements per institute:
  `InstituteModuleEntitlementController`
  (`app/Http/Controllers/Admin/InstituteModuleEntitlementController.php`):
  index shows package modules + `resolveEnabled()` + full entitlement
  history + last-50 `module_access_logs` (`:17-56`); `destroy` (revoke) and
  `extend` (via service, `:122-...`, `:133-...`).
- Package↔feature matrix + institute overrides:
  `FeatureAdminController` (`admin.features.*`, `routes/web.php:460-465`).

All grant/deny/extend paths record `granted_by`/`denied_by`
(→ `PlatformAdmin`) and write `module_access_logs` via `logAccess()`
(`:413-437`, fields: institute, module, action, actor + actor_type,
previous/new state, package, notes).

---

## 7. Tables

| Table | Model | Key columns / notes |
|---|---|---|
| `package_scopes` | `PackageScope` | `package_id`, `country_id`, `industry_id`, `sub_industry_id`, derived `scope_hash`, `inherit_from_parent`, `price_monthly/yearly`, `currency`, `status` |
| `package_scoped_features` | `PackageScopedFeature` | `package_scope_id`, `feature_key`, `enabled` |
| `package_scoped_modules` | `PackageScopedModule` | `package_scope_id`, `module_key`, `enabled`, derived `scope_hash` (`{scope}\|{module}`) |
| `institute_module_entitlements` | `InstituteModuleEntitlement` (SoftDeletes) | `institute_id`, `module_key`, `status` (`pending/active/trialing/expired/revoked`), `is_grant`, `starts_at/ends_at`, `trial_starts_at/ends_at`, commercial metadata (nullable), `purchased_by` (→ users), `granted_by` (→ platform_admins), `notes` |
| `institute_module_overrides` | `InstituteModuleOverride` | `institute_id`, `module_key`, `enabled`, `overridden_by`, `reason` |
| `institute_feature_overrides` | `InstituteFeatureOverride` | `institute_id`, `feature_key`, `enabled` |
| `tenant_access_grants` | `TenantAccessGrant` | `institute_id`, `grant_type` (`feature/module/tier`), `grant_key`, `granted_by/at`, `expires_at`, `reason`, `status`, `revoked_at/by` |
| `tenant_access_denials` | `TenantAccessDenial` | `institute_id`, `deny_type` (`feature/module`), `deny_key`, `denied_by/at`, `expires_at`, `reason`, `status`, `lifted_at/by` |
| `module_access_logs` | `ModuleAccessLog` | `institute_id`, `module_key`, `action`, `actor_id/type`, `previous/new_state`, `package_id`, `notes` |
| `module_registry` | `ModuleRegistry` | `key`, `parent_key`, `status`, `sort_order` |
| `feature_registry` | `FeatureRegistry` | `feature_key`, `module_key`, `status`, `sort_order` |
| `package_modules` | `PackageModule` | `package_id`, `module_key`, `enabled` (legacy module base) |
| `package_features` | `PackageFeature` | `package_id`, `feature_key`, `enabled` (legacy feature base) |
| `institute_subscriptions` | (query-builder) | latest row per institute: `status`, `end_date` (A2 input) |
| `subscription_packages` | `SubscriptionPackage` | tiers incl. `free`; base prices |

Invariants:

- I1. No reader invents keys: unknown module/feature/tier keys are ignored
  or logged, never enabled (A3 `grantModule` validation excepted — it
  rejects unknown modules outright).
- I2. Denial precedence: module denial (A3) beats override; feature denial
  (A4 Gate 5) beats everything; exact-tie beats grant.
- I3. Time is a gate: pending/inactive windows and expired grant/denial rows
  never apply; the sweeper is the only writer of lifecycle transitions.
- I4. Service-only writes: entitlements change via `grantModule()`,
  `revokeModule()`, `extendEntitlement()` or `entitlements:expire` so audit
  + cache flush always happen.
- I5. Cache keys embed scope: `module_access:{id}`,
  `feature_access:{id}:{scope_hash}`; every mutation path flushes.

---

## 8. Phasing (what shipped when)

| Phase / tag | Content | Anchor |
|---|---|---|
| Step 60 P0 | SaaS enforcement: expired/cancelled → `free` fallback; packageless → FREE | `ModuleAccessService.php:243-244`, `:1210-1212` |
| 63A / 63B | Individual entitlements: active grants/denials, latest-wins, deny-on-tie, window logic (§2 active rule) | `getActiveEntitlementMap()`, `isEntitlementActive()` |
| 63G | Future billing compatibility, preparation only: commercial metadata nullable; payment NOT required | `ModuleAccessService.php:24-30` |
| Phase 4b–6 | Scoped packages, UI-only, no new tables (beyond §7): scope CRUD, feature/module matrices, tenant grants + denials | `routes/web.php:467-486` |
| Phase 5a | Feature entitlement as pure query (`isFeatureEnabled` enforces nothing by itself) | `ModuleAccessService.php:746-760` |
| Phase 5b | Middleware enforcement (platform-admin bypass lives there) | `CheckModuleAccess` / `MedicalModuleAccess` |
| Phase 6 | Module configuration scope: scoped modules dual-read with legacy fallback; cache flush hooks | `resolvePackageModules()`, `getScopedModuleKeys()` |
| B75 | Tier grants (scope-aware, legacy fallback) | `getTierFeatureKeys()`, Gate 4 tier branch |
| B76 | Grants respect Gate 1 (parent module must be enabled) | Gate 4 branches |
| B77 | Empty scoped set falls back (no "explicitly empty" scope) | `computeFeatureAccessMap()` docblock |
| Roadmap follow-up | `entitlements:expire` update for feature-level expiry | `docs/ROADMAP_CONSOLIDATED_V1.md:602` |
| Gate contract tests | `isFeatureEnabled` chain: package, parent, registry, status, malformed key, null package fallback | `docs/ROADMAP_CONSOLIDATED_V1.md:522`; `tests/Feature/ModuleAccessFeatureGateTest.php` |
| Entitlement rule tests | grants/denials latest-wins; deny on tie | `docs/ROADMAP_CONSOLIDATED_V1.md:229` |

---

## 9. Verification

1. **Scope fallback:** `resolveScopedPackage()` returns most-specific active
   scope; GLOBAL `(null,null,null)` last; null package with no `free` row →
   null (fail-closed). Covered by `ModuleAccessFeatureGateTest`
   (`tests/Feature/ModuleAccessFeatureGateTest.php:51-...`: package with
   feature → true; without → false).
2. **Module chain:** `resolveEnabled()` order §3-A3; education veto
   re-enableable via override/entitlement.
3. **Feature chain:** `(Base ∪ Overrides ∪ Grants) − Denials`; malformed key
   (no dot) → false; unknown keys skipped.
4. **Denial precedence:** Gate 5 denial clears grant+override outcomes;
   module denial clears all module features.
5. **Expiry:** dry-run first —
   `php artisan entitlements:expire --dry-run` prints pending→active,
   active→expired, trialing→expired counts without writes
   (`EntitlementsExpire.php:156-165`).
6. **Admin surfaces:** `php artisan route:list --name=admin` shows
   `packages.scopes.*`, `scopes.*`, `institutes.access/grants/denials`
   (`routes/web.php:467-486`); controller test
   `tests/Feature/PackageScopeAdminControllerTest.php:94` asserts the
   "Scoped Packages" index renders.
7. **Seed prerequisites:** `tests/Feature/TestHarnessBaselineTest.php`
   guards currencies, system roles, tax permissions, industries and clean
   seeder runs — run it before the gate suites.
8. **Audit:** every grant/deny/extend/expire transition writes
   `module_access_logs` with actor + previous/new state; the entitlement
   index shows the last 50 entries per institute
   (`InstituteModuleEntitlementController.php:47-50`).

---

## 10. Open limitations (not contract changes)

- B77: scope is additive — an "explicitly empty" scope cannot be expressed;
  empty scoped sets fall back to legacy rows.
- Trialing never auto-promotes to active; promotion is a product decision
  outside this contract.
- `isFeatureEnabled()` is a pure query; enforcement belongs to middleware
  (Phase 5b). Calling it without enforcement grants nothing.
- Commercial fields are metadata until 63G billing lands; no payment state
  participates in any decision.

---

*End of contract V1. Amendments require a version bump and must keep every
citation accurate against the implementation.*
