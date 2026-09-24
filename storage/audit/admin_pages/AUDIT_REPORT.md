# AUDIT REPORT — Admin Pages (Modules/Industries/Packages)
# Date: 2026-09-23
# URLs: /admin/modules, /admin/industries, /admin/packages/4/modules
# Mode: READ-ONLY — no code changes, SELECT-only queries

## 1. Executive Summary
- 3 pages audited (plus nested sub-industries + access-logs routes)
- Controllers found: 2 primary (`ModuleAdminController`, `IndustryAdminController`) + 1 nested (`SubIndustryAdminController`)
- Views found: 3 primary (`admin.modules.index`, `admin.industries.index`, `admin.modules.package-modules`)
- How tenant control works: **layered entitlement engine** — core/config defaults → package_modules (legacy, scope-aware override) → per-tenant overrides → entitlements → industry veto → dependencies/parent gates; results cached 1h per institute under `module_access:{id}`.
- Key gap: toggling a module's global status on `/admin/modules` does **not** flush tenant caches and does **not** remove it from `resolveEnabled()` (status is not consulted there). Package-module save **does** flush caches for all package tenants.

## 2. Routes
| Method | URI | Name | Controller | Middleware |
|--------|-----|------|------------|------------|
| GET | admin/modules | admin.modules.index | Admin\ModuleAdminController@index | auth:platform_admin, verified |
| PUT | admin/modules/{module} | admin.modules.update | Admin\ModuleAdminController@update | auth:platform_admin, verified |
| GET | admin/modules/access-logs | admin.modules.access-logs | Admin\ModuleAdminController@accessLogs | auth:platform_admin, verified |
| GET | admin/packages/{package}/modules | admin.packages.modules | Admin\ModuleAdminController@packageModules | auth:platform_admin, verified |
| PUT | admin/packages/{package}/modules | admin.packages.modules.update | Admin\ModuleAdminController@updatePackageModules | auth:platform_admin, verified |
| GET | admin/packages/{package}/scopes | admin.packages.scopes.index | Admin\PackageScopeAdminController@index | auth:platform_admin, verified |
| POST | admin/packages/{package}/scopes | admin.packages.scopes.store | ... | auth:platform_admin, verified |
| GET | admin/packages/{package}/scopes/create | admin.packages.scopes.create | ... | auth:platform_admin, verified |
| GET | admin/industries | admin.industries.index | Admin\IndustryAdminController@index | auth:platform_admin, verified ($adminMiddleware) |
| POST | admin/industries | admin.industries.store | @store | same |
| GET | admin/industries/create | admin.industries.create | @create | same |
| GET/PUT/DELETE | admin/industries/{industry} | admin.industries.edit/update/destroy | @edit/@update/@destroy | same |
| POST | admin/industries/{industry}/toggle | admin.industries.toggle | @toggle | same |
| GET | admin/industries/{industry}/sub-industries | admin.industries.sub-industries | @subIndustries | same |
| POST/GET/PUT/DELETE/POST(toggle) | .../sub-industries[/{subIndustry}[/toggle]] | admin.industries.sub-industry.* | Admin\SubIndustryAdminController | same |
| GET/PUT/DELETE | admin/institutes/{institute}/modules[/{moduleKey}] | admin.institutes.modules* | ModuleAdminController@instituteModules/update/removeOverride | auth:platform_admin, verified |
| GET | admin/features, admin/features/{feature_key} | admin.features.* | Admin\FeatureAdminController | auth:platform_admin, verified |

`$adminMiddleware = ['auth:platform_admin', 'verified']` (routes/web.php:525).

## 3. Page 1 — /admin/modules

### Controller
- File: `app/Http/Controllers/Admin/ModuleAdminController.php`
- Namespace: `App\Http\Controllers\Admin`
- Methods:
  - `index(Request)` — lists all `module_registry` rows (optional industry filter via `?industry_id=` using PackageScope/PackageScopedModule), active packages, per-package enabled map; view `admin.modules.index`.
  - `update(ModuleRegistry, Request)` — **only** validates `status in:active,inactive` and updates that field. No audit log, no cache flush.
  - `packageModules(SubscriptionPackage)` / `updatePackageModules(...)` — Page 3.
  - `instituteModules` / `updateInstituteModules` / `removeOverride` — per-tenant overrides (enable/disable + reason, flushes cache, logs via service).
  - `accessLogs` — ModuleAccessLog viewer (filter by institute_id, module_key; paginate 50).
- Models: `Industry`, `Institute`, `InstituteModuleOverride`, `ModuleAccessLog`, `ModuleRegistry`, `PackageScope`, `SubscriptionPackage`, `PackageScopedModule`
- Validation: `status required|in:active,inactive` (update); `modules required|array`, `modules.* string|exists:module_registry,key` (package/institute updates)

### View
- File: `resources/views/admin/modules/index.blade.php`
- Layout: `layouts.admin`; sections: `title`, `content`
- UI: Bootstrap tabs — **Module Registry** (table) + **Package Matrix** (module × package grid with ✓/✗ links to package edit); industry filter dropdown; status toggle button per row; Access Logs header button; tab-preserving JS. No Livewire; plain Blade + small vanilla JS.

### Data Displayed
- Columns (registry tab): Key, Name, Type (core/addon/beta badge), Description, Status (toggle form), Dependencies
- Filters: industry_id (all / per active industry); count badge
- Actions: PUT status toggle; links to package module pages; access-logs link
- Pagination: none (full table of 58)
- Matrix tab: read-only ✓/✗ per package; package name links to `/admin/packages/{id}/modules`

### Database (module_registry)
- Total: **58** (Active 58, Inactive 0, Coming soon 0)
- Types: core 25, industry 33
- Structure: ROOT 21 parents; children: education 7, training_center 7, medical 15 (opd, ipd, **pharmacy**, laboratory, billing, emergency, radiology, bloodbank, physiotherapy, dental, vaccination, ambulance, diet, records), sales 7, purchase 6
- Editable from this page: **status only** (active/inactive). Key/name/type/dependencies/coming_soon not editable here.

### How it controls tenant
- Global status toggle writes `module_registry.status` only. `resolveEnabled()` loads `ModuleRegistry::all()` but **never checks status** — so inactive modules still resolve unless gated elsewhere (package module lists, getSubModules, admin lists filter `status=active`).
- **No cache invalidation** on `update()` → tenants keep cached `module_access:{id}` up to 3600s.
- Who sees change: primarily admins (package-modules page filters active); tenant 403 behavior driven by `CheckModuleAccess` → `isEnabled()` → cached resolve.
- Package Matrix is navigation only; real edits happen on Page 3.

## 4. Page 2 — /admin/industries

### Controller
- File: `app/Http/Controllers/Admin/IndustryAdminController.php`
- Methods: `index`, `create`, `store`, `edit`, `update`, `toggle`, `destroy`, `subIndustries`
- Nested: `app/Http/Controllers/Admin/SubIndustryAdminController.php` — `create/store/edit/update/toggle/destroy` (nested invariant: child must belong to parent else 404)
- Models: `Industry`, `SubIndustry`, `PlatformAuditLog`, `Country`
- Validation: name required max:100; slug required max:60 unique; code/description/sort_order nullable; sub-industry: countries[] exists:countries,id
- Delete guards: industry with institutes → error; industry with sub-industries → error; sub-industry with institutes → error

### View
- File: `resources/views/admin/industries/index.blade.php` (+ create/edit, sub-industries, sub-industry-create/edit)
- Layout: `layouts.admin`
- Columns: sort_order, Name, Slug, Sub-Industries count (link), Status (toggle button form), Actions (manage subs, edit, delete with confirm)
- Header: "Add Industry" button. No filters, no pagination (14 rows).

### Data Displayed / DB
- Storage: **DB table `industries`** (14 rows, all active) + **`sub_industries`** (65 rows)
- Config also exists: `config/industry_rules.php` (IndustryRules fallback) and `config/industry-modules.php` (module matrix) — IndustryService docblock: DB replaces config as runtime source of truth (1h cache `taxonomy:industries:*`)
- Industries: Education, Training Center, Healthcare, Information Technology, Finance & Banking, Retail, Manufacturing, Real Estate, Transportation, Service, Restaurant, Hotels, Personal Finance, Other
- Sub-industries: education 8 global + 8 BD; training_center 16 global + 16 BD; healthcare 5 BD (Hospital, Clinic, **Pharmacy**, Diagnostic Center, Nursing Home); IT 3; finance 3; retail 3; manufacturing 3 (incl. Pharmaceutical)
- Default modules per industry: **not on this page** — live in `config/industry-modules.php` (healthcare default: purchase, medical, medical.billing; disabled: education, training_center)

### How it controls tenant
- Industry CRUD/toggle: affects onboarding dropdowns (IndustryRules→IndustryService, cached 1h) and **package scope selection** (industries used for PackageScope industry_id filtering on modules page).
- Does **not** directly change `resolveEnabled()` module lists: that reads `institutes.industry` string + static config matrix. Changing an industry row's name/slug does not re-map existing tenants (their `industry` column is independent unless edited on institute).
- Toggling industry inactive: excludes from active lists; existing tenants keep working (no automatic revoke).
- Admin **can** create new industry (create route); new industry gets no config matrix entry until `config/industry-modules.php` is updated (code change).
- Audit: **YES** — `PlatformAuditLog::record('industry'|'sub_industry', ...)` on create/update/status/delete. **No** `IndustryService::flushCache()` call in these controllers → taxonomy cache may lag up to 1h.

## 5. Page 3 — /admin/packages/4/modules

### Controller
- `ModuleAdminController@packageModules` → view `admin.modules.package-modules`
- `ModuleAdminController@updatePackageModules` → validates modules[] exists in module_registry → `ModuleAccessService::setPackageModules()`
- `setPackageModules()`: DB transaction deletes all `package_modules` for package, re-inserts only checked keys with `enabled=true`; then **flushes `module_access:{id}` + feature cache for every institute on that package**

### View
- File: `resources/views/admin/modules/package-modules.blade.php`
- Layout: `layouts.admin`
- Title: `{package.name} — Package Modules`
- UI: single PUT form; table with form-switch checkboxes (`modules[]` = module key), Key, Name, Description, Dependencies badges (✓/✗); Save Changes button; client-side dependency warning panel (@push scripts). Only **active** modules listed.

### Package 4 Details (DB)
- Name: **PREMIUM**, slug `premium`, status active, price_monthly 9000.00, price_yearly 90000.00, no limits set
- Modules: **56/58 enabled** (all except `crm`? — actually crm absent from list; pos & manufacturing absent). Medical: **15/15 ON** including **medical.pharmacy ON**
- Features (package_features): **42 enabled**, medical features **12** including medical.pharmacy (medical.ipd/opd parent may be module-level only)
- Package scopes: 1 row (package 4, country_id=21 Bangladesh, no industry/sub) with **0 scoped modules** → legacy `package_modules` is source of truth
- Packages total: FREE(1), BASIC(2), ADVANCED(3), PREMIUM(4)

### How it controls tenant
- Toggle + Save → rewrite package_modules → **immediate cache flush** for all package-4 institutes → next `isEnabled()` recomputes → existing tenants affected (not only new).
- Real-time after flush: yes (within same request completion). No ModuleAccessLog row written by setPackageModules (audit gap vs per-tenant enable which does log).
- Tenants on other packages unaffected.

## 6. Cross-Page Data Flow

```
[Page1 /admin/modules] status toggle ──► module_registry.status ──► admin lists only
        │                                   (resolveEnabled ignores status; NO cache flush)
        ▼ Package Matrix link
[Page3 /admin/packages/{id}/modules] checkboxes ──► package_modules (delete+insert)
        │                                            │ setPackageModules()
        │                                            ├─ flush module_access:{id} for all pkg institutes
        │                                            └─ (no ModuleAccessLog)
        ▼
[Page2 /admin/industries] CRUD ──► industries / sub_industries
        │                          ├─ PlatformAuditLog ✓
        │                          ├─ IndustryService taxonomy cache (1h, NOT flushed here)
        │                          └─ PackageScope industry/sub filters (admin UI)
        ▼
ModuleAccessService::resolveEnabled(institute):
  1 core (config) → 2 industry defaults (config) → 3 resolvePackageModules
     (scope-aware: scoped modules if non-empty ELSE package_modules)
  → 4 industry optional+overrides → 5 institute_module_overrides
  → 6 entitlements grant/deny → 7 industry-disabled (config remove)
  → 8 isIndustryCompatible (hardcoded education/medical/training map)
  → 9 dependency closure → 10 parent_key gate
  Cache: Cache::remember('module_access:{id}', 3600)
  Consumers: CheckModuleAccess middleware, isEnabled(), feature map Gate 1
```

Resolution order & admin-page fit:
| Step | Source | Admin page that edits it |
|------|--------|--------------------------|
| Core/industry defaults | config/industry-modules.php | code only (not the 3 audited pages) |
| Package modules | package_modules | **Page 3** |
| Scoped modules | package_scoped_modules via PackageScope | /admin/packages/{id}/scopes + /admin/scopes |
| Registry existence/status | module_registry | **Page 1** (status only) |
| Industry taxonomy | industries/sub_industries | **Page 2** |
| Per-tenant | institute_module_overrides, entitlements, grants | /admin/institutes/{id}/modules, entitlements, tenant access |

## 7. Tenant Impact

| Admin Action | Affected Tenants | Timing |
|--------------|------------------|--------|
| Page1 toggle module status | Admin-facing lists; **not** resolveEnabled; no direct tenant revoke | No cache flush → up to 1h for any status-aware paths |
| Page3 add module to package | All institutes with package_id=4 (3 tenants: 189, 191, 192) | Immediate (cache flushed per institute) |
| Page3 remove module from package | Same 3 tenants lose it unless override/entitlement/core/industry-default re-adds | Immediate after flush |
| Page2 edit industry name/slug | Onboarding/dropdowns (taxonomy cache ≤1h); no instant module re-resolve | Cached 1h; existing institutes' `industry` string unchanged |
| Page2 toggle industry inactive | Active-industry lists only; existing tenants keep access | ≤1h taxonomy cache |
| New package create | New subscribers only (packages managed elsewhere; 4 exist) | On subscribe/changePackage |
| Per-tenant enable/disable | That institute only; override beats package | Immediate (flush + ModuleAccessLog) |
| Per-tenant override remove | Reverts to package default | Immediate |

Can admin override per-tenant? **YES** — `admin/institutes/{institute}/modules` (enable/disable with reason → InstituteModuleOverride + log + flush), entitlements (grantModule/revokeModule), tenant grants/denials (feature layer).

## 8. Package 4 Details
- Name: PREMIUM (id 4), active, 9000/mo, 90000/yr
- Modules: 56 enabled (all active registry except pos, manufacturing)
- Medical modules: 15/15 ON (medical + 14 sub incl. **medical.pharmacy**)
- Pharmacy: module `medical.pharmacy` ✅ + feature `medical.pharmacy` ✅
- Features: 42 ON (12 medical)
- Scopes: 1 country-level (BD) with 0 scoped modules → legacy path
- Tenants on package: 3 (Central Hospital healthcare/hospital; CENTRAL DIAGNOSTIC CENTER healthcare/diagnostic_center; Mawa Supershop retail/supermarket)
- Sample resolution: Institute 189 → 26 modules incl. all medical; Institute 191 → 15 modules incl. 7 medical (fewer → per-tenant overrides/industry gates at work)

## 9. Sub-Industry Support
- In admin UI: **YES** — nested under /admin/industries/{id}/sub-industries (full CRUD + toggle + country filter + country multi-create)
- In DB: **YES** — `sub_industries` (65 rows, `scope_hash` generated column, country_id nullable=global)
- In config: **YES** — `config/industry_rules.php` (fallback labels per country); module matrix has **no** sub-industry keys
- Used for: package scope targeting (`package_scopes.sub_industry_id`), institute filters, taxonomy labels
- Pharmacy-specific packages: **NO package exists for pharmacy**; healthcare sub-industry `pharmacy` exists (BD); no sub-industry → default-modules mapping; would need PackageScope with sub_industry_id + scoped modules/features or a new package row

## 10. Gap Analysis for Pharmacy Package
- ✅ Exists: medical.* modules (incl. pharmacy) in registry; package 4 enables all medical modules+features; healthcare industry + pharmacy sub-industry; package_scopes support sub_industry_id; scoped modules/features tables; per-tenant overrides; access logs viewer; platform audit for industries
- ❌ Missing: dedicated pharmacy package/plan; sub-industry→module default matrix; ModuleAccessLog on package-module saves & registry status updates; cache flush on registry status update; `resolveEnabled` ignores `module_registry.status`; IndustryService flush after industry CRUD; coming_soon still 0/not surfaced on Page 1 UI

## 11. Recommendations
1. In `ModuleAdminController@update`, call flush for affected institutes (or document that status is display-only) and consider honoring `status` inside `resolveEnabled()`.
2. Log package-module changes: extend `setPackageModules()` to `logAccess(..., 'package_modules_updated', ...)` per key diff (matches package_change logging in `changePackage`).
3. Call `IndustryService::flushCache()` in Industry/SubIndustry mutations to drop the 1h taxonomy lag.
4. For a pharmacy package: either create package + package_modules/features, or create PackageScope(country, industry=healthcare, sub=pharmacy) and populate `package_scoped_modules` (scope wins over legacy when non-empty).
5. Surface `coming_soon` column on Page 1 or drop it; currently unused in UI/resolve.
6. Unify industry sources: DB `industries` vs `config/industry-modules.php` keys (e.g. config uses healthcare/education slugs; hardcoded `isIndustryCompatible` map) — document or merge to avoid drift.

## 12. Raw Evidence
Saved under `storage/audit/admin_pages/`:
- `routes_all.txt` — full `php artisan route:list`
- `modules.html` + `modules_headers.txt` — unauthenticated GET → **302 → /admin/login** (auth gate proof; body empty of page HTML)
- `industries.html` + `industries_headers.txt` — same 302
- `package_4_modules.html` + `package_4_modules_headers.txt` — same 302
- `db_modules_industries.txt` — module_registry (58), industries (14), sub_industries (65)
- `db_package4.txt` — packages, package 4 modules/features/scopes
- `db_tenants.txt` — tenant distribution + resolveEnabled samples
- `q_*.php` — read-only tinker query scripts used

Note: full page HTML requires an authenticated platform_admin session; unauthenticated curl correctly returns 302 to `admin/login`, confirming `auth:platform_admin` middleware. Page structure derived from Blade sources (authoritative for rendered output).
