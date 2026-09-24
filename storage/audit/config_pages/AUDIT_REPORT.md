# AUDIT REPORT — Configuration Pages
# Project: AccumenAI (Monetix) — Agent: MimoV2Flash — Date: 2026-09-25
# Mode: READ-ONLY (no code changes; SELECT-only queries)

## 1. Executive Summary
- Total pages audited: **6**
- Routes found: **6/6**
- Controllers found: **6/6**
- Views found (page renders): **6/6** (2 expected view folders absent — controller uses shared `admin.modules.*` views)
- Sidebar / nav links: **5/6** (missing: `/admin/industry-subcategories` — orphan, no link anywhere)
- HTTP accessible (unauthenticated): **6/6 → 302 to login, 0 × 5xx, 0 × 404**
- HTTP accessible (authenticated platform_admin): **4/4 admin pages → 200**; settings pages blocked by auth wall (no tenant user exists locally)
- DB tables: **10/10 present**
- Errors: 3 classes found (see §11) — 1 resolved, 2 live (dead route name, stale test)

**Stop-condition deviations (disclosed):**
1. Prescribed Part H query failed: `SQLSTATE[42S22] Unknown column 'is_admin' in 'where clause'` — `users` has no `is_admin`; platform admins live in `platform_admins`. Query re-issued against correct tables.
2. Folders `resources\views\admin\institutes\modules` and `resources\views\admin\packages` do **not** exist — not a defect: controllers render `admin.modules.institute-modules` / `admin.modules.package-modules` (verified 200).
3. `curl` for `/admin/institutes/1/modules` → 404 because institute id 1 does not exist (valid ids: 4, 5, 189, 191, 192, +2). Re-tested with id 4/5 → 200.

## 2. Routes Inventory
| # | Page | URL | Route name(s) | Controller@method | Middleware | Status |
|---|------|-----|---------------|-------------------|------------|--------|
| 1 | Sub-Category CRUD | `/admin/industry-subcategories` (+create/{id}/edit) | `admin.industry-subcategories.*` (7 routes: index,create,store,show,edit,update,destroy) | `Admin\IndustrySubcategoryController@…` | web, `Authenticate:platform_admin`, `EnsureEmailIsVerified` | ✅ |
| 2 | Module Registry | `/admin/modules`, `/admin/modules/{module}`, `/admin/modules/access-logs` | `admin.modules.index/.update/.access-logs` | `Admin\ModuleAdminController@index/update/accessLogs` | web, `Authenticate:platform_admin`, `EnsureEmailIsVerified` | ✅ |
| 3 | Package Module Toggle | `/admin/packages/{package}/modules` (GET, PUT) | `admin.packages.modules`, `admin.packages.modules.update` | `Admin\ModuleAdminController@packageModules/updatePackageModules` | web, `Authenticate:platform_admin`, `EnsureEmailIsVerified` | ✅ |
| 4 | Tenant Module Override | `/admin/institutes/{institute}/modules` (GET, PUT, DELETE `/{moduleKey}`) | `admin.institutes.modules`, `.update`, `.remove` | `Admin\ModuleAdminController@instituteModules/updateInstituteModules/removeOverride` | web, `Authenticate:platform_admin`, `EnsureEmailIsVerified` | ✅ |
| 5 | Terminology Override | `/settings/terminology` (GET, PUT) | `settings.terminology.index/.update` | `Settings\TerminologyController@index/update` | web, `Authenticate:institute_user,web`, `SetTenantContext`, `EnsureEmailIsVerified`, `CheckPermission:settings.manage` | ✅ |
| 6 | Tenant Module Toggle | `/settings/modules` (GET), `/settings/modules/toggle` (POST) | `settings.modules`, `settings.modules.toggle` | `Settings\ModuleManagementController@index/toggle` | web, `Authenticate:institute_user,web`, `SetTenantContext`, `EnsureEmailIsVerified`, `CheckPermission:institute.settings.module.view` (**index only**) | ✅ ⚠️ |

Related (same controller family, verified 200): `/admin/institutes/modules-overview`, `/admin/institutes/{id}/access-log`.
No duplicate registrations for any of the 6 target route names.

## 3. Controllers
| Controller | Exists | Public methods |
|------------|--------|----------------|
| `Admin\IndustrySubcategoryController` | ✅ | index, show, create, store, edit, update, destroy, (private industryKeys) |
| `Admin\ModuleAdminController` | ✅ | index, update, packageModules, updatePackageModules, instituteModules, updateInstituteModules, removeOverride, accessLogs |
| `Admin\InstituteModuleOverrideController` | ✅ | accessLog, overview |
| `SuperAdmin\EmergencyOverrideController` | ✅ | create, store |
| `Settings\TerminologyController` | ✅ | index, update |
| `Settings\ModuleManagementController` | ✅ | index, toggle (+ userCanToggleModule, resolveInstitute) |

## 4. Views
| Folder / view | Exists | Files / notes |
|--------|--------|-------|
| `resources\views\admin\industry-subcategories` | ✅ | index, show, create, edit, _form (5) |
| `resources\views\admin\modules` | ✅ | index, package-modules, institute-modules, access-logs (4) |
| `resources\views\admin\institutes\modules` | ❌ (by design) | controller renders `admin.modules.institute-modules` ✅ |
| `resources\views\admin\packages` | ❌ (by design) | controller renders `admin.modules.package-modules` ✅ |
| `resources\views\super-admin` | ✅ | incl. `institutes\emergency-override\create.blade.php` |
| `resources\views\settings\terminology` | ✅ | index.blade.php (101 lines) |
| `resources\views\settings\modules.blade.php` | ✅ | (130 lines) + `partials\module-toggle-card.blade.php` |

Every `view(...)` call in the 3 audited controllers resolves to an existing blade file.

## 5. Sidebar / Navigation Links
| Page | Admin sidebar (`layouts\admin.blade.php`) | Institute sidebar (`layouts\institute.blade.php`) | Other entry point |
|------|-------------------------------------------|--------------------------------------------------|-------------------|
| Sub-Category | ❌ none | ❌ | ❌ **nowhere in app** (only self-references in its own views) |
| Module Registry | ✅ L357 “Modules & Packages”, L360 “Module Access Logs”, L470 dropdown | ✅ L1137/L1140 (platform-admin block) | — |
| Package Modules | ⚠️ not in sidebar | ⚠️ | ✅ `admin\modules\index.blade.php` L168/L186/L190 |
| Tenant Modules | ⚠️ not in sidebar | ⚠️ | ✅ `admin\institutes\show.blade.php` L49 “Module Access”, access-log, overview, modules access-logs L80 |
| Terminology | — (tenant page) | ❌ no sidebar item | ✅ `settings\index.blade.php` L65 (inside `@if ($canManageSettings && $setting)`) |
| Settings Modules | — (tenant page) | ❌ no sidebar item | ✅ `settings\index.blade.php` L61 (same conditional) |

## 6. Route Files
| Page | File | Lines |
|------|------|-------|
| Sub-Category | `routes/web.php` | 566–572 (`$adminMiddleware = ['auth:platform_admin','verified']` @ L532) |
| Module Registry | `routes/web.php` | 549–551 |
| Package Modules | `routes/web.php` | 552–553 |
| Tenant Modules | `routes/web.php` | 554–556 (related: 575–576, `institute_modules.php` 1789–1790) |
| Terminology | `routes/web.php` | 246–247 |
| Settings Modules | `routes/web.php` | 242–243 |

All 6 pages are defined in **`routes/web.php`** only. Middleware aliases registered in `bootstrap/app.php` L44–49 (`permission` → `CheckPermission`, `tenant` → `SetTenantContext`).

## 7. Middleware (stack order)
| Page | Middleware stack |
|------|------------------|
| Sub-Category | `web` → `Authenticate:platform_admin` → `EnsureEmailIsVerified` |
| Module Registry | `web` → `Authenticate:platform_admin` → `EnsureEmailIsVerified` |
| Package Modules | `web` → `Authenticate:platform_admin` → `EnsureEmailIsVerified` |
| Tenant Modules | `web` → `Authenticate:platform_admin` → `EnsureEmailIsVerified` |
| Terminology (GET/PUT) | `web` → `Authenticate:institute_user,web` → `SetTenantContext` → `EnsureEmailIsVerified` → `CheckPermission:settings.manage` |
| Settings Modules GET | `web` → `Authenticate:institute_user,web` → `SetTenantContext` → `EnsureEmailIsVerified` → `CheckPermission:institute.settings.module.view` |
| Settings Modules POST toggle | `web` → `Authenticate:institute_user,web` → `SetTenantContext` → `EnsureEmailIsVerified` — **no `permission:`** (⚠️ compensated inside controller: `userCanToggleModule()` + package/parent checks return 403/422) |

## 8. Database Tables
| Table | Status | Rows |
|-------|--------|------|
| `industry_subcategories` | ✅ | 23 (education 5, healthcare 7, manufacturing 2, real_estate 2, retail 4, training_center 3) |
| `subcategory_default_modules` | ✅ | 118 |
| `country_tax_modules` | ✅ | 2 |
| `module_terminology` | ✅ | 16 (16 active, 13 global) |
| `module_rules` | ✅ | 5 |
| `super_admin_overrides` | ✅ | 0 |
| `module_registry` | ✅ | 60 (17 root, 0 coming_soon) |
| `package_modules` | ✅ | 124 (pkg1=2, pkg2=14, pkg3=50, pkg4=58) |
| `institute_module_overrides` | ✅ | 77 (6 institutes of 7) |
| `module_access_logs` | ✅ | 58 (53 in last 7 days) |

Also: `institutes.terminology_overrides` column exists; 0 tenants have a non-empty override set yet.

## 9. Admin / Tenant Users (local DB `accumen_ai`)
| Guard | Table | Rows | Sample |
|-------|-------|------|--------|
| `platform_admin` | `platform_admins` | 1 | `1 \| admin@accumen.ai` |
| `web` | `users` | 4 | 1 test@example.com (owner), 2 yeasinsheikh999@gmail.com (owner), 312 hobavog273@liondapt.com (staff), 313 nahid@doctor.com (staff) |
| `institute_user` | `institute_users` | **0** | — ⚠️ no tenant login possible locally |

`users` has **no `is_admin` column** (columns include `account_type`, `status`); prescribed Part H query invalid for this schema.

## 10. HTTP Smoke Test
Unauthenticated (`http://localhost/AccumenAI/public`, Apache:80 up):
| Page | HTTP Status | Location |
|------|-------------|----------|
| `/admin/industry-subcategories` | 302 | `/admin/login` |
| `/admin/modules` | 302 | `/admin/login` |
| `/admin/packages/4/modules` | 302 | `/admin/login` |
| `/admin/institutes/1/modules` | 302 | `/admin/login` |
| `/settings/terminology` | 302 | `/login` |
| `/settings/modules` | 302 | `/login` |

Authenticated as `platform_admin id=1` (in-process kernel):
| Request | Status | Bytes |
|---------|--------|-------|
| `/admin/industry-subcategories` | **200** | 114,475 |
| `/admin/modules` | **200** | 331,667 |
| `/admin/packages/4/modules` | **200** | 162,192 |
| `/admin/institutes/4/modules` | **200** | 184,989 |
| `/admin/institutes/5/modules` | **200** | 187,051 |
| `/admin/institutes/modules-overview` | **200** | 80,093 |
| `/admin/institutes/4/access-log` | **200** | 69,286 |
| `/admin/modules/access-logs` | **200** | 159,960 |
| `/admin/institutes/1/modules` | 404 | id does not exist (verified ids 4,5,189,191,192) |
| `/admin/packages/9999/modules` | 404 | route-model binding ✅ |
| `/admin/industry-subcategories/99999` | 404 | ✅ |
| `/settings/terminology`, `/settings/modules` | 302 → `/login` | requires `institute_user` guard |

Access-control (authenticated `web` user id=1):
- `/admin/modules`, `/admin/industry-subcategories` → **302 → /admin/login** (blocked ✅)
- `/settings/modules`, `/settings/terminology` → **403** (blocked ✅)

Tenant pages (`/settings/*`) could **not** be rendered authenticated: `institute_users` = 0 rows.

## 11. Errors (storage/logs/laravel.log, 125 MB)
| # | Timestamp | Env | Message | Verdict |
|---|-----------|-----|---------|---------|
| 1 | 2026-09-25 00:11:23–24 (×3) | local | `Class "App\Http\Controllers\Admin\InstituteModuleOverrideController" does not exist` | **RESOLVED** — controller file written 00:11:38; `class_exists` OK; `/admin/institutes/modules-overview` → 200 |
| 2 | 2026-09-21 13:12 (×3), 2026-09-25 00:29 | testing | `Route [settings.edit] not defined` (View: `settings\modules.blade.php`, `settings\terminology\index.blade.php`) | **Views fixed** (no `settings.edit` reference remains; modified 09-21 15:54 / 09-25 00:33) **but root cause LIVE**: see §12 |
| 3 | — | — | `tests\Feature\ModuleSettingsModuleAccessTest.php:67` → `route('settings.modules.update')` **does not exist** | **STALE TEST** — will throw `RouteNotFoundException` |
| 4 | 2026-09-25 00:01 (×2) | testing | `Unknown column 'published_at'` (monetix_test) | unrelated to config pages |
| 5 | 2026-09-25 00:26 | local | parse error in `training\batches\show.blade.php` | unrelated |
| 6 | 01:00, 01:22, 02:05, 02:10 | local | Psy `T_NS_SEPARATOR` / `"-e" option does not exist` | **audit-induced** (my failed `tinker --execute` invocations) |

Zero log entries mention `industry-subcategories`, `TerminologyController`, `ModuleManagementController`, `ModuleAdminController`, `settings/modules`, `settings/terminology`, `package-modules`, `institute-modules`.

## 12. Gap Analysis

### ✅ Working
- All 6 pages: route registered, controller exists, view resolves, middleware applied.
- Admin pages render 200 for `platform_admin`; 404 for bad ids; unauthenticated → login redirect.
- Tenant pages correctly reject non-tenant actors (403) and unauthenticated (302).
- All 10 target tables exist with live data (module_registry 60, package_modules 124, overrides 77, terminology 16, subcategories 23).
- Sidebar links for Module Registry + Access Logs (both layouts); settings hub nav links for Modules + Terminology.

### ❌ Missing
1. **`/admin/industry-subcategories` has no navigation entry anywhere** (sidebar, topbar, other views, docs) — orphan page, reachable only by typing the URL.
2. **`institute_users` = 0** → `/settings/terminology` and `/settings/modules` cannot be exercised end-to-end in this environment.
3. `tests/Feature/ModuleSettingsModuleAccessTest.php:67` references non-existent route `settings.modules.update`.
4. No HTTP/feature test hits `admin.industry-subcategories.*` routes (only `IndustrySubcategoryTest` at service level).

### ⚠️ Partial
1. **`settings.edit` route name is dead**: `routes/web.php:232` defines `GET settings → settings.edit`, but identical `GET settings` at L401 and L692 (both `settings.index`) overwrite it in `RouteCollection` (keyed by method+uri, `refreshNameLookups`). Verified: `route('settings.edit')` throws `RouteNotFoundException`; `settings.edit` absent from name list — this is the root cause of log errors in §11.2.
2. `settings.modules.toggle` has no `permission:` middleware (index does); authorization lives in controller (`userCanToggleModule`, package, parent checks).
3. Settings “Modules”/“Terminology” nav items only render when `$canManageSettings && $setting` — users lacking `settings.manage` see no entry point (route still protected).
4. Institute sidebar has no dedicated items for the two tenant config pages (they live inside the Settings hub page).

## 13. Recommendations
1. Add a sidebar/nav link to `admin.industry-subcategories` (e.g. under `CONFIGURATION` next to “Industries”, `layouts/admin.blade.php` ~L363) or link from the Industries index page.
2. Remove the shadowed `settings.edit` definition (`routes/web.php:232`) or rename the duplicate `GET settings` registrations (L401/L692) — one canonical name only.
3. Update `tests/Feature/ModuleSettingsModuleAccessTest.php:67` to `route('settings.modules.toggle')` (and body to `module_key`/`enabled`), or delete if superseded.
4. Add `middleware('permission:institute.settings.module.toggle')` to `settings/modules/toggle` for parity with the GET route (controller checks remain as defense-in-depth).
5. Seed one verified `institute_user` locally so `/settings/*` pages can be smoke-tested over HTTP; use institute ids **4/5** for admin tenant URLs (id 1 does not exist).
6. Add a feature test asserting an admin UI entry point exists for `admin.industry-subcategories.index`.

## 14. Raw Evidence
`storage/audit/config_pages/`
`routes_all.txt`, `routes_all.json`, `routes_filtered.txt`, `routes_target_detail.txt`, `controllers.txt`, `views.txt`, `sidebar.txt`, `nav_context.txt`, `view_refs.txt`, `route_files.txt`, `db_tables.txt`, `data_summary.txt`, `data_summary2.txt`, `admin_users.txt`, `users_schema.txt`, `auth_config.txt`, `entity_ids.txt`, `http_smoke.txt`, `http_auth_admin.txt`, `http_auth_admin2.txt`, `http_404_checks.txt`, `access_control.txt`, `layouts.txt`

## 15. Next Steps
1. Fix orphan link for `/admin/industry-subcategories` (Recommendation 1).
2. Resolve dead `settings.edit` name + duplicate `GET settings` (Recommendation 2).
3. Repair stale test `ModuleSettingsModuleAccessTest` (Recommendation 3).
4. Seed a tenant user, then re-run HTTP smoke for pages 5–6 (Recommendation 5).
5. Re-audit after fixes; optionally run `php artisan route:list` diff to catch further shadowed names.
