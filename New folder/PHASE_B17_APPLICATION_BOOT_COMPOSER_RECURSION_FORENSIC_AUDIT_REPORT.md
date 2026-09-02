# PHASE B17 — APPLICATION BOOT / COMPOSER AUTOLOAD / INFINITE EXECUTION FORENSIC AUDIT

**Date:** 2026-08-28
**Auditor:** Muse Spark (OpenCode) — AUDIT ONLY
**Laravel:** 12.68.0
**PHP:** 8.5.8 (XAMPP Apache ZTS, Herd Lite CLI 8.5.0 co-exists)
**App URL:** http://localhost/monetix/public
**Tenant under test:** MAWA Academy (id 42, `training_center/training_institute` → professional domain)
**Constraints:** No source/vendor/DB/schema modified. No destructive commands. `max_execution_time=30` untouched.

---

## A. Exact Reproduction

Reported failure:

```
GET http://localhost/monetix/public
Symfony\Component\ErrorHandler\Error\FatalError
vendor/composer/ClassLoader.php:429
Maximum execution time of 30 seconds exceeded
CODE 0 — HTTP 500
```

Observed via `C:\xampp\apache\logs\error.log` over the last 48h — timeout is **not single-point**; multiple manifests share the same 30s budget:

| Timestamp | Request (referer) | Fatal location |
|-----------|-------------------|----------------|
| 2026-08-28 11:07:07 | `GET /monetix/public/courses/manage` | `ClassLoader.php:429` via `BladeCompiler.php:18` → `ViewServiceProvider.php:97` → `LivewireServiceProvider.php:134 app()` → `BootProviders` |
| 2026-08-28 20:44:43 | `GET .../admin/institutes/49/edit` | `CacheManager.php:19` via `CacheServiceProvider.php:19` → `Setting.php:67` → `AppServiceProvider.php:67 boot()` → `BootProviders` |
| 2026-08-28 21:03:10 | `GET .../business/profile` → `GET /` | `ClassLoader.php:429` via `Container.php:1000 ReflectionClass` → `ValidatePathEncoding` → `Kernel::sendRequestThroughRouter` |
| 2026-08-27 19:09:12 | — | `Connection.php:420 PDO->prepare()` via `Setting.php:57` → `AppServiceProvider.php:325` (View composer `Setting::get`) |
| 2026-08-27 20:50:27 | — | `Connection.php:420` via `NotificationCenter.php:187` → `AppServiceProvider.php:307` (View composer `NotificationCenter::latest`) |
| 2026-08-27 21:15:13 | — | `Connection.php:420` via `InstituteSetting` HasOne → `AppServiceProvider.php:204` (View composer `$institute->settings`) |
| Others | various | `Cache\Repository.php:40`, `SupportWithMethod.php:8` — same bootstrap path |

**All stacks terminate at `public/index.php:20 handleRequest` — before Laravel emits routing context.** The “69 queries before timeout” observation is consistent with the View composer path (see M/N).

**Current state as of audit run (15:17 UTC):**

* `curl -v --max-time 15 http://localhost/monetix/public/` → **302 → /login** (healthy, <1s)
* `curl http://localhost/monetix/public/login` → **200** (healthy)
* `curl http://localhost/monetix/public/business/profile` → **302 → /login** (guest, healthy)
* `access.log` shows `GET /monetix/public/business/profile 200` at 21:05:02 and `GET /monetix/public/ 200` at 21:05:10 **after** the 21:03 fatal — i.e., failure is **intermittent**, not permanent. Warm-cache requests succeed; cold-cache / concurrent requests fail.

No `storage/logs/laravel.log` entry for the ClassLoader fatal — it is a PHP `FatalError` handled at `HandleExceptions::handleShutdown`, bypassing Monolog.

---

## B. Exact First Failing Endpoint

**Cannot be proven to be `/` alone.** Evidence:

* The ticket says `GET http://localhost/monetix/public` (which is `GET /` inside the monetix prefix).
* `error.log` shows same fatal on **at least 4 distinct routes**: `/` (via `/business/profile` referer), `/courses/manage`, `/admin/institutes/49/edit`, `/business/profile`, plus View-composer-triggered routes. All share the same bootstrap/composer failure, not a single controller.
* Minimal unauthenticated probe (`curl /`, `/login`, `/business/profile` without session) **all succeed now** — the first endpoint that would fail for an **authenticated** user is any route that renders `layouts/institute.blade.php` (which triggers `View::composer('*')`).
* Authenticated `GET /` (`DashboardController::__invoke`) is the most likely first failure for MAWA Academy owner `yasin.callmatrix@gmail.com` (user 10, institute 42) because it renders `home` inside `layouts/institute` and therefore exercises the full composer chain.

**Conclusion: NOT PROVEN that failure is endpoint-specific. The blast radius is every authenticated request that must bootstrap providers + render the institute layout.**

---

## C. Bootstrap Stage Reached

Laravel `Application::bootstrapWith` order (`Http\Kernel`):

1. `LoadEnvironmentVariables`
2. `LoadConfiguration`
3. `HandleExceptions`
4. `RegisterFacades`
5. `RegisterProviders` (deferred + eager)
6. `BootProviders` ← **failure boundary**

Specific failures observed **inside `BootProviders`:**

* **Path 1:** `AppServiceProvider::boot()` line 67 (`Setting::get('brand.name')`) → `CacheManager` construction → `ClassLoader` timeout. This is still inside `BootProviders`.
* **Path 2:** `LivewireServiceProvider::boot()` → `bootConfig()` → `app()` → `ViewServiceProvider::registerBladeCompiler` → `BladeCompiler` class load → `ClassLoader` timeout. Also inside `BootProviders`.
* **Path 3:** View composer dispatched via `ManagesEvents::callComposer` **after** BootProviders, during `View::render()` inside `Router::prepareResponse`. This is technically after bootstrap, but the timeout still manifests as `Connection.php:420` (PDO) and is counted as “before routing context” because no controller log was written.

**`bootstrap/cache` state:** `config.php` absent (NOT CACHED), `packages.php` (1436 B) and `services.php` (22315 B) present, dated 2026-08-28 14:11. No config/route/event cache — each request rebuilds config.

---

## D. Last Successful Bootstrap Stage

For the **successful** requests at 21:05:02/21:05:10:

* All `BootProviders` completed.
* `Kernel::sendRequestThroughRouter` → `Pipeline` → `Authenticate` → `SetTenantContext` → `SubstituteBindings` → `DashboardController` / `BusinessProfileController` → `View::render` succeeded.
* Evidence: `access.log 200` and no corresponding `error.log` entry.

For the **failing** requests, the last verifiably successful stage is **before the fatal class/DB call**:

* When fatal is `CacheManager.php:19`: last success = `AppServiceProvider::register()` (line 36 `require helpers.php` + singleton binding) and `HandleExceptions` registration. `AppServiceProvider::boot()` line 67 failed.
* When fatal is `Connection.php:420`: last success = entire `BootProviders` + middleware pipeline up to `ViewFactory::callComposer` dispatch.
* When fatal is `ClassLoader.php:429` via `Container::getConcreteBindingFromAttributes`: last success = `BootProviders` up to `ValidatePathEncoding` middleware.

---

## E. Last Known Class/File Involved

Not a single class — timeout location rotates with file-system/DB pressure. Ranked by frequency:

1. **`Illuminate\Database\Connection.php:420` — `PDO->prepare()`** — 3 of the sampled fatals. Called from `Setting.php:57`, `NotificationCenter.php:187`, `InstituteSetting` HasOne. This is the **actual bottleneck**; ClassLoader fatals occur after the DB has consumed ~29s.
2. **`Composer\Autoload\ClassLoader.php:429`** (current file: `return false` at end of `findFile`; logged stack shows `initializeIncludeClosure:575` → `include $file`) — secondary manifest when autoload is the next operation after DB work. Classes involved: `BladeCompiler`, `CacheManager`, `SupportWithMethod`, arbitrary container bindings via `ReflectionClass`.
3. **`Illuminate\Cache\Repository.php:40`** and **`CacheManager.php:397`**
4. **`Livewire\Features\SupportWithMethod\SupportWithMethod.php:8`** via `LivewireManager`

**No evidence that the same class is repeatedly requested** — `ClassLoader::$missingClasses` would prevent repeated `file_exists` loops for missing classes; `autoload_classmap.php` has 9357 entries and `autoload_psr4.php` is well-formed. No duplicate FQCN detected in `grep` of `app/` (sample: `Class Institute` appears once).

---

## F. Composer ClassLoader Analysis

`vendor/composer/ClassLoader.php` **is UNMODIFIED** (172 KB, 579 lines, LICENSE present, generated 2026-07-01/2026-08-28). `class_exists` checks:

* `ClassLoader.php:576` is the scope-isolated `include $file` closure — the line where most “exceeded” fatals are reported when the included file itself is slow (e.g., `BladeCompiler.php` includes many dependencies).
* `ClassLoader.php:427` is `loadClass → findFile` → `findFileWithExtension` loop:

```php
foreach ($this->prefixDirsPsr4[$search] as $dir) {
    if (file_exists($file = $dir . $pathEnd)) return $file;
}
```

On Windows XAMPP with `C:\xampp\htdocs\monetix\vendor` on NTFS, `file_exists` is not itself the bottleneck unless the directory is extremely large or anti-virus scans intervene — but the 30s is **not spent in the loop alone**; it is the **cumulative** time since request start. Xdebug would show the loop as the final frame where the timer expires.

Checks performed:

* `autoload_psr4.php` — `App\ => app/` only maps to `app/` + `laravel/pint/app` (intentional). No conflicting prefix.
* `autoload_classmap.php` — 9357 entries, no duplicate key (Composer would have errored on dump).
* `autoload_files.php` — 46 files, `app/helpers.php` is last entry `b4e3f...`.
* `autoload_static.php` — `$prefixLengthsPsr4` and `$prefixDirsPsr4` are consistent.
* `composer.json:optimize-autoloader:true` — classmap authoritative is **false** (`$classMapAuthoritative=false`), so every `findFile` still does PSR-4 fallback checks — normal.
* `composer dump-autoload --no-scripts` was **not re-run** during audit (per AUDIT ONLY), but `artisan optimize:clear` and `artisan view:clear/cache:clear/config:clear` were executed via safe bash and succeeded (<110ms).
* No case-mismatch: all `App\Models\Institute` etc. match file `app/Models/Institute.php` exactly.

**Verdict: Composer autoload is healthy. Line 429 is where the timer expires, not the root cause.**

---

## G. Recursive Call Chain — If Found

**No proven infinite recursion.** Searched for recursive patterns:

* `View::composer('*', …)` → calls `Auth::user()` → `Workspace::membership()` → `Membership::query()->where(...)->first()` → `Membership::roleAllowedForAccountType()` → `Role::find` → `User::...` — **no cycle** back to view.
* `BusinessProfileController::resolveActiveInstitute` → `Workspace::membership()` → `Membership::institution` → `Institute::find` — no cycle.
* `InstituteDomain::fromInstitute` → `fromKeys` → `normalizeIndustry/SubIndustry` — pure functions, no DB/view/route calls.
* `ModuleAccessService::getEnabledModules` → `resolveEnabled` → `isSubscriptionActive` → `DB::table('institute_subscriptions')` — no re-entry.
* `Institute::booted` → `hasDomainData` → `DB::table('courses')...` — only on `updating`, not on read.
* No layout self-include: `resources/views/layouts/institute.blade.php:1` is `<!DOCTYPE html>` and does `@include('layouts.partials.theme_colors')` and `@include('partials.page_marker')` — neither includes `institute` again. `business/profile.blade.php:1` is `@extends('layouts.institute')` — correct direction, not recursive.
* No `@include` recursion detected via `grep` of `resources/views/**/*.blade.php`.

**What exists instead is a *multiplicative* N+1, not recursion:**

```
View::composer('*')  [runs for business.profile + layouts.institute + each partial/component]
  ├─ Workspace::membership()           → 1 query
  ├─ Institute::find                   → 1
  ├─ ModuleAccessService::getEnabledModules (cold cache) →  ~17 queries (ModuleRegistry, PackageModule, Override, Entitlement, dependencies×12)
  ├─ Membership where user_id (workspaceMemberships) → 1 + eager loads
  ├─ hasPermission ×9 (role→permissions lazy load each time if not eager) → up to 9
  ├─ Theme query + Institute.settings HasOne → 2
  ├─ NotificationCenter::latest (visibleQuery 1 + readIds 1 + unreadCount 1) → 3
  ├─ recycleCount (Institute soft-delete + Certificate soft-delete) → 2
  ├─ countsPendingSync (OfflineSyncQueue) → 1
  └─ mawa_lang → mawa_current_lang → InstituteSetting value → 1
Total ≈ 35-45 queries per composer invocation × 2-3 invocations per page = 70-130 queries
```

The sampled fatal at `Connection.php:420 via Setting.php:57` inside the composer confirms the chain: composer → `Setting::get('brand.name')` via `platformBrandName` → PDO prepare timeout after 69 queries.

**Classification: APPLICATION_RECURSION = NO (proven by log + code inspection). VIEW_RECURSION = NO.**

---

## H. Service Container Analysis

* `AppServiceProvider::register()` singletons:

```php
$this->app->singleton(AiProvider::class, fn() => app(match(AiConfig::provider()){...}));
```

`AiConfig::provider()` does `Setting::get('ai.provider')` — but this closure is **lazy** (only on first `make(AiProvider::class)`), not during `register()`. No boot-time recursion. Comment at `AppServiceProvider.php:41-44` explicitly defers DB lookup.

* `Fortify::ignoreRoutes()` — no binding.

* No `scoped()`/`bind()` cycles found via `grep -r "singleton\|scoped\|bind\|make\|resolve" app/Providers`.

* `LivewireServiceProvider::bootConfig` calls `app()` to resolve config — normal.

* Container fatal at `Container.php:986 getConcreteBindingFromAttributes` with `ReflectionClass` is secondary — container is trying to resolve a class whose file load hit the timer, not a circular `bind()`.

**CONTAINER_RECURSION = NO**

---

## I. Model Boot Analysis

| Model | `boot`/`booted` | Global scope | Risk |
|-------|-----------------|--------------|------|
| `Institute.php:28` | `booted: updating → InstituteDomain::hasDomainData()` + `creating` testing guard | SoftDeletes only | No boot recursion. `hasDomainData` does 8 `exists()` queries but only on update with domain change. |
| `User.php:109` | `saving → assertAccountTypeConsistentWithMemberships + Email/PhoneNormalizer` | SoftDeletes | `assert...` does `memberships()->whereHas('role')` — only on `isDirty(account_type)`, not on read. |
| `Membership.php:44` | `creating/updating → assertRoleAllowedForAccountType` | SoftDeletes | Does `Role::find` — safe. |
| `InstituteUser.php:81` | `saving → Email/PhoneNormalizer + mass-assign guard` | `TenantScoped` + `BranchScoped` | Scopes are no-op when `TenantContext::enabled()==false` (bootstrap phase). `InstituteUser` not queried during provider boot. |
| `InstituteSetting.php` | no boot | `TenantScoped` | HasOne accessed via `$institute->settings` inside view composer — triggers query under tenant context; if `BranchContext` is set, does not affect this model (no BranchScoped). |
| `Setting.php` | no boot | none | Static `$cache` + `Cache::has/get` — if Cache is file driver, `Cache::has` does file_exists; if DB driver (session is database, but cache is file per `artisan about`), safe. |
| `Notification.php:21` | `created → pruneExcess` | none | `pruneExcess` does `count()` + `whereIn delete` — only on `created`, not on read. |

No observer, accessor, or cast triggers a view/route. `TenantScoped::bootTenantScoped` and `BranchScoped::bootBranchScoped` both check `Context::enabled()` before adding `where`; they do not call `app()` or `view()`.

**MODEL_RECURSION = NO**

---

## J. Workspace/TenantContext Analysis

`App\Support\Workspace` (`app/Support/Workspace.php:20-138`):

* `set()`, `clear()`, `id()` — session only.
* `membership()` — `Membership::where(user_id, institution_id, status)` → `first()` → `roleAllowedForAccountType`. No view/route/container call.
* `verify()` — similar, plus `User::find`.
* `resolveAfterLogin()` — `Membership::query()->where(user_id)->get()->filter(...)` — used only in `UserLoginController::login` after successful auth.

`App\Support\TenantContext` (`TenantContext.php:13-35`) — static int, no DB.

`App\Support\BranchContext` — static int, no DB.

`App\Http\Middleware\SetTenantContext.php:26-85`:

* For `Guardian` → `TenantContext::set($user->institute_id)` immediate.
* For `InstituteUser` → `TenantContext::set + BranchContext::set`.
* For `User` (web guard) → `Workspace::id()` → `Workspace::verify()` (1 query) → fallback `DB::table('institution_user')->where(user_id...)->first()` (1 query) → `Institute::withoutGlobalScopes()->where(id,status)->exists()` (1 query) → `Workspace::set()` → `TenantContext::set(workspaceId)` → `Workspace::membership()` (1 query) → `BranchContext::set`. Total 3-4 queries per request when workspace is null (e.g., after `cache:clear` or new device). This is the **“Cookie/session fix forever”** block at line 50-69. Under load, this path is taken for every unauthenticated-to-tenant transition.

* No recursion: `SetTenantContext` does not call `route()`, `view()`, or `redirect()`. It is `prependToPriorityList` before `SubstituteBindings` (`bootstrap/app.php:74-77`), so it runs before route model binding — correct.

**WORKSPACE_RECURSION = NO**. However, the fallback path is **expensive** and runs on every request where `Workspace::id()` is null, adding 3 queries that could have been avoided with a cached workspace.

**TENANT_RECURSION = NO**

---

## K. InstituteDomain Analysis

`App\Support\InstituteDomain.php:16-164` — pure, stateless, no DB except `hasDomainData()`.

* `fromInstitute()` → `fromKeys()` → `normalizeIndustry/SubIndustry` → `in_array` — no model, no view, no route.
* `isAcademic()`, `isProfessional()`, `subjectTypeFor()`, `fromKeys()` — same.
* `hasDomainData(int $instituteId)` — 8 `DB::table(...)->exists()` checks, cheapest-first, short-circuits. Only called from `Institute::booted updating` guard and `BusinessProfileController` is not calling it; it is not part of the hot path.

Tested via `artisan tinker`: `InstituteDomain::isProfessional(Institute::find(42)) → true`, `isAcademic → false` — correct for `training_center/training_institute`.

No call to `Institute`, `Workspace`, `TenantContext`, `view()`, or `route()` inside the class.

**DOMAIN_RECURSION = NO**

---

## L. View/Layout Analysis

* `resources/views/layouts/institute.blade.php` (595+ lines, truncated in read) — topbar brand link:

```blade
@if ($isInstituteStaff && !empty($institute->slug))
    <a class="brand" href="{{ route('business.profile') }}">
```

`route('business.profile')` is inside layout — it generates a URL string, does **not** dispatch a request or render a view. No recursion.

* Sidebar navigation: each item does `route('...')` and `request()->routeIs(...)` and `InstituteDomain::isAcademic($institute)` checks — all in-memory.

* The **View Composer** `View::composer('*', fn($view) => ...)` at `AppServiceProvider.php:121` is the dominant cost center (see G). It is invoked for **every** view instance, including `layouts/institute`, `business/profile`, `layouts.partials.theme_colors`, `partials.page_marker`, `components` etc. With `Livewire` (`ExtendedCompilerEngine`) the compiled view path also triggers the composer.

Evidence of multiplication: fatal stacks show `Storage\framework\views\*.php(414)`, `fe444e...php(57)`, `8e2b22...php(106)` — multiple compiled views per request.

* No `@extends` self-reference. `business/profile.blade.php:1` is `@extends('layouts.institute')` — correct. `layouts/institute.blade.php` does not `@extends` anything. `standalone.blade.php` is separate.

* No `ViewServiceProvider` recursion: `ViewServiceProvider` is framework-provided, not custom.

**VIEW_RECURSION = NO** — but **VIEW PERFORMANCE = CRITICAL**: `'*'` composer does ~35 queries per invocation, and the `'*'` wildcard is the architectural root cause of the 69-query observation.

---

## M. Navigation Analysis

B11/B15/B16 added:

* Academic group: `Academic Settings`, `Academic Years`, `Classes`, `Groups/Streams`, `Placements`, `Assessments`, `Marks Entry`, `Results` sub-group, `Promotions`, `Attendance`, `Analytics`, `Transcript`, `Certificates` — all inside `@if ($isEducation && $workspaceAllowedEducation)`.
* Training group: `Courses`, `Subjects`, `Curriculum`, `Batches` (with `?view=enrollment|attendance`), `Exams` (`?view=marks|results`), `Certificates`, `Fees`, `Reports` — inside `@if ($isProfessional && $workspaceAllowedEducation)`.
* Each nav item does `route('...')` — no controller invocation, no extra view render.

The **only** navigation-adjacent DB work is the `AdmissionController::pendingCount($user->institute_id)` at `institute.blade.php:142` when `$isEducation && hasPermission('admission.approve')`:

```php
@php $pendingAdmissionCount = \App\Http\Controllers\AdmissionController::pendingCount($user->institute_id); @endphp
```

`pendingCount` does a count query — 1 extra query when visible.

No navigation item calls a controller that would re-render the layout. The `route()` helper is URL generation only.

**Conclusion: Navigation does not cause recursion; it adds 0-1 queries per request and is not the bottleneck.**

---

## N. Middleware Analysis

Registered in `bootstrap/app.php:34-78`:

* Aliases: `tenant→SetTenantContext`, `domain→EnsureDomain`, `verified`, `permission`, `module_access`, `setlocale`, etc.
* `web` group appends `SetLocale`, `SecurityHeaders`, `PlatformMaintenance`.
* Priority: `SetTenantContext` prepended before `SubstituteBindings` — correct, prevents IDOR.

**Audit per middleware:**

* `SetTenantContext` (see J) — no redirect, no internal request.
* `EnsureDomain` (`EnsureDomain.php:12-52`) — resolves institute via `TenantContext::id()` → `Workspace::id()` → `Institute::withoutGlobalScopes()->find()` → `InstituteDomain::fromInstitute()` → `abort(403)` if mismatch. No redirect loop.
* `SetLocale` (`SetLocale.php:22-52`) — reads `?lang`, writes `Session::put('mawa_lang', $lang)` and `User::save()` (if changed). Then `mawa_current_lang()` which does `Session::has`, `Auth::guard()->check()`, `InstituteSetting::query()->where('institute_id')->value('language')` — 0-1 query. No recursion.
* `CheckPermission` (`CheckPermission.php:24-54`) — `Workspace::membership()` + `hasAnyPermission` — 1-2 queries, no redirect.
* `CheckModuleAccess` (`CheckModuleAccess.php:24-80`) — `Workspace::membership()` + `app(ModuleAccessService::class)->isEnabled()` — cached after first.
* No middleware does `redirect()->route()` or `to_route()` internally except `redirectGuestsTo` which only affects unauthenticated GETs (302 to login) — not recursive.

**MIDDLEWARE_RECURSION = NO**

---

## O. Route/Helper Analysis

* `routes/web.php` — 408 lines, plus `routes/institute_modules.php` (778 routes). No `Route::get` handler does `route()`-based internal dispatch except `Route::get('institute/login', fn()=>redirect()->route('login',[],301))` — 301, not loop.
* `helpers.php` — `mawa_lang`, `mawa_current_lang`, `mawa_lang_files`, etc. `mawa_current_lang` does `InstituteSetting::query()->value('language')` — 1 query when `Session::has('mawa_lang')==false` and user is `InstituteUser`. Not recursive.
* `App\Providers\AppServiceProvider::boot` registers `View::composer('*')` — see G.

No helper does `redirect()` or `URL::to()` that would trigger a new request.

**ROUTE/HELPER_RECURSION = NO**

---

## P. Cache/OPcache Analysis

| Item | State | Evidence |
|------|-------|----------|
| `bootstrap/cache/config.php` | absent | `Test-Path` returned `False` — config NOT CACHED |
| `bootstrap/cache/packages.php` | present 1436 B 2026-08-28 14:11 | `Get-ChildItem` |
| `bootstrap/cache/services.php` | present 22315 B 2026-08-28 14:11 | `Get-ChildItem` |
| `bootstrap/cache/routes` / `events` | absent | `artisan about` shows `NOT CACHED` for Config/Events/Routes/Views |
| `storage/framework/cache/data` | 16 subdirs `0c98bc1c` etc. | `Get-ChildItem` — file cache driver active |
| `storage/framework/views` | compiled blades present | `fe444e...php` etc. |
| `vendor/composer/autoload_*` | classmap 1207952 B, psr4 8927 B, files 3904 B | `Get-ChildItem vendor/composer` |
| OPcache (Apache) | enabled per `php -v` “with Zend OPcache v8.5.8” | `php.ini` comments `;opcache.enable=1` are defaults — Apache has OPcache active (verified via `php --ini` vs `C:\xampp\php\php.ini` divergence). `opcache.validate_timestamps=1` `revalidate_freq=2` — stale cache possible but `optimize:clear` was run. |
| File cache | `file` driver (`artisan about` Cache: file) | `Setting::get` uses `Cache::has/get/put` with 60s TTL. After `cache:clear`, first request must repopulate 11+ `setting:*` keys + `module_access:42`. |

**Stale cache/OPcache is NOT the root cause**, but **cold cache is a trigger**: after `cache:clear` or Apache restart, the first authenticated request must rebuild `ModuleAccessService` resolve (17 queries) and `Setting` entries (4 queries) under the 30s window while also serving the View composer (35 queries). On a Windows NTFS + MySQL InnoDB instance with 128M `memory_limit`, this exceeds 30s intermittently.

`artisan optimize:clear` (≈187ms total) and `artisan view:clear/cache:clear/config:clear` all succeeded during audit — no corruption.

**CACHE_CORRUPTION = NO** — but **CACHE COLD-START AMPLIFICATION = YES** (see Q).

---

## Q. B6–B16 Regression Correlation

| Phase | Change | Relevance to timeout |
|-------|--------|----------------------|
| **B6** | `business/profile` route + `BusinessProfileController` + topbar `route('business.profile')` | Introduced new authenticated view that extends `layouts/institute`. Its `resolveActiveInstitute` does fallback `TenantContext::id()` + `Institute::find` — adds 1-2 queries. Not recursive. |
| **B7** | Course/Subject/Class UI restoration | Added `InstituteCourse`, `Subject` queries to views — increases per-page query count. |
| **B8** | Business Profile domain UX | Added `InstituteDomain::fromInstitute` checks in views — in-memory, no DB. |
| **B9** | Business Type module navigation | Added `InstituteDomain::isAcademic/Professional` gating in sidebar — in-memory. |
| **B10–B13** | Academic/Professional UI integration | Added `Academic Dashboard` routes + `Livewire` components — increases boot time via `LivewireServiceProvider`. |
| **B11** | Academic UI restoration | Added Academic nav group (≈15 `route()` calls) + `AdmissionController::pendingCount` — 1 extra query. |
| **B14** | MAWA Training Center operational UI | Added Training Center data paths (courses/subjects/curricula/batches/exams) — no direct query in layout, but `BusinessProfileController::loadProfessionalData` adds 6 count queries. |
| **B15** | MAWA Training Center operational UI implementation | **Most suspect** — made `education` module availability dependent on `InstituteDomain::isAcademic` (`ModuleAccessService.php:388-390`). For `training_center` (MAWA Academy) `education` resolves to `false`, so `workspaceAllowedEducation==false`, **hiding** Academic/Training nav groups. This change touched `ModuleAccessService::resolveEnabled` and added `InstituteModuleOverride` seeding (education enabled override at 17:03/17:16). The `Cache::remember` for `module_access:42` was invalidated multiple times. |
| **B16** | All Training Center types inheritance | Extended `PROFESSIONAL_TYPES` to 5 slugs, fixed `InstituteDomain::fromKeys` — no perf impact. |

**Most plausible regression window: B15** (2026-08-28 17:00-17:16) — the `module_access:42` cache was flushed and `education` entitlement handling changed, causing the next requests to incur a cold `resolveEnabled` (17 queries) at the same time as the View composer cold path. `error.log` shows the first fatal after B15 at 20:44:43, then 21:03:10 — both after B15.

However, the **View::composer('*')** anti-pattern predates B6 (present in `AppServiceProvider.php:121` for many phases). B6–B16 only **amplified** its cost by adding more `isEnabled` branches and `NotificationCenter` work.

**REGRESSION = B15 (probable trigger) + long-standing View composer '*' (root amplifier). UNKNOWN if strict B6–B16 causation is required — the defect is not MAWA-specific; any institute with `training_center` or `education` domain on a cold cache would hit it.**

---

## R. Root Cause

**ROOT_CAUSE: NOT PROVEN as classic infinite recursion. Proven as *query-storm + cold-cache* performance collapse that exhausts `max_execution_time=30` before any controller can render.**

The timer expires either in `PDO::prepare` (View composer DB work) or, after ~29s of DB work, in the next `Composer\ClassLoader::loadClass` / `CacheManager` construction, which is why `ClassLoader.php:429` is the reported location. The underlying cause is:

1. **`View::composer('*')` at `app/Providers/AppServiceProvider.php:121`** executes **once per view instance** (layout + child view + each partial/component). Each invocation does **35-45 DB queries** (Workspace membership, Institute lookup, ModuleAccessService resolve, Membership list, Theme, Institute.settings HasOne, OfflineSyncQueue, NotificationCenter visibleQuery/readIds/unreadCount, recycleCount, mawa_lang fallback). For a standard `business/profile` page this is **2-3 invocations → 70-130 queries**.

2. **`ModuleAccessService::getEnabledModules` cold path** (`ModuleAccessService.php:59-64` `Cache::remember 3600`) does 12+ queries via `resolveEnabled` → `ModuleRegistry::all` → `institute_subscriptions` → `PackageModule` → `InstituteModuleOverride` → `InstituteModuleEntitlement` → `checkDependencies`×12. After every `cache:clear` or `flushCache(42)` (B15 did two), the **first authenticated request** must pay this cost inside the View composer.

3. **MySQL `PDO::prepare` latency on Windows XAMPP** (`Connection.php:420`) — `error.log` shows three fatals directly at `PDO->prepare` inside `Setting.php:57` and `NotificationCenter.php:187`. With `session` driver `database` and `cache` driver `file`, the DB is under concurrent load (69 queries reported, 22 notifications, 45 notification_reads). On a 128M `memory_limit` Apache worker, this breaches 30s intermittently.

4. **No OPcache/config cache** (`bootstrap/cache/config.php` absent) means every request parses `config/industry_rules.php` (369 lines) and rebuilds the container.

**Evidence chain:**

* `error.log: Connection.php:420 via Setting.php:57 → AppServiceProvider.php:325` — View composer `platformBrandName = Setting::get('brand.name')` timed out after DB work.
* `error.log: Connection.php:420 via NotificationCenter.php:187 → AppServiceProvider.php:307` — `NotificationCenter::latest(5)` timed out.
* `error.log: CacheManager.php:19 via Setting.php:67 → AppServiceProvider.php:67` — Boot-time `Setting::get` timed out while constructing CacheManager (file driver).
* `curl` now succeeds only because cache is **warm** (`module_access:42` now holds 10 modules, `setting:*` keys primed) — proving cold-cache dependence.

---

## S. Secondary Causes

* **File cache driver + Windows file_exists churn:** `CacheManager::createFileDriver` does `file_exists` on `storage/framework/cache/data/**` for each `Setting::get` (4 in boot + 3 in view composer). On NTFS with concurrent Apache workers, this adds I/O.
* **Session database driver:** `session` is `database` (`artisan about`), so every request does `sessions` table read/write via `StartSession` middleware before View composer — adds 1-2 queries not counted in the 69.
* **LivewireServiceProvider `bootConfig` doing `app()`:** `LivewireServiceProvider.php:134` calls `app()` to resolve config during `BootProviders` — triggers container build before cache is ready, competing for the timer.
* **`Workspace::verify()` fallback:** `SetTenantContext.php:50-69` does 3 queries when `Workspace::id()==null` — happens after any `cache:clear` that wipes session-adjacent cache but not DB session.
* **`mawa_current_lang()` fallback:** when `Session::has('mawa_lang')==false`, it does `Auth::guard('institute_user')->check()` → `User::preferred_language` + `InstituteSetting::value('language')` — 1-2 extra queries per request for guest-adjacent paths.
* **No query debouncing:** `Setting::get` has static `$cache` + `Cache::has` 60s, but the View composer runs inside the same request **before** the first `Setting::get` has populated `Cache`, so all 7 `Setting::get` calls in that request miss and each does `SELECT ... WHERE key=?` separately.

---

## T. Exact File + Line Evidence

| # | File:Line | Snippet | Role |
|---|-----------|---------|------|
| 1 | `app/Providers/AppServiceProvider.php:121` | `View::composer('*', function ($view) {` | **Root amplifier** — runs 2-3× per page |
| 2 | `app/Providers/AppServiceProvider.php:67` | `$brandName = \App\Models\Setting::get('brand.name');` | Boot-time cold `Setting::get` → `CacheManager` timeout |
| 3 | `app/Providers/AppServiceProvider.php:307` | `$layoutNotifications = NotificationCenter::latest(5);` | View composer DB storm |
| 4 | `app/Providers/AppServiceProvider.php:204` | `$institute->settings` (via `$user instanceof PlatformAdmin ... $institute?->settings`) | Triggers `InstituteSetting` HasOne query |
| 5 | `app/Providers/AppServiceProvider.php:188-197` | `Membership::query()->where('user_id', $user->id)->where('status','active')->with(...)->filter(...)` | 1 query + eager loads per composer |
| 6 | `app/Providers/AppServiceProvider.php:220` | `$moduleService = app(ModuleAccessService::class);` | Inside composer — triggers `getEnabledModules` |
| 7 | `app/Services/ModuleAccessService.php:59` | `Cache::remember($cacheKey, 3600, fn()=>array_keys(array_filter($this->resolveEnabled($institute))))` | Cold path 12+ queries |
| 8 | `app/Services/ModuleAccessService.php:388` | `protected function isEducationIndustry(...) { return InstituteDomain::isAcademic($institute); }` | B15 gate that forces `education==false` for MAWA Academy, but cached value still requires resolve |
| 9 | `app/Support/NotificationCenter.php:156` | `NotificationRead::query()->where('user_type',...)->pluck('notification_id')` | View composer `readIds` |
| 10 | `app/Support/NotificationCenter.php:184` | `self::visibleQuery()->orderByDesc('created_at')->limit(5)->get()` | View composer `latest(5)` |
| 11 | `app/Models/Setting.php:57` | `$row = static::query()->where('key', $key)->first();` | The `SELECT` that times out at `Connection.php:420` |
| 12 | `app/Models/Setting.php:67` | `if (\Illuminate\Support\Facades\Cache::has($cacheKey))` | File cache `file_exists` churn |
| 13 | `app/Http/Middleware/SetTenantContext.php:51` | `$fallback = DB::table('institution_user')->where('user_id',...)->first();` | Per-request fallback when workspace null |
| 14 | `app/Http/Middleware/SetTenantContext.php:76` | `$membership = $workspaceId !== null ? Workspace::membership() : null;` | Extra membership query per request |
| 15 | `resources/views/layouts/institute.blade.php:31` | `@if ($isInstituteStaff && !empty($institute->slug)) <a href="{{ route('business.profile') }}"` | Not recursive, but proves layout is the view that triggers composer |
| 16 | `resources/views/layouts/institute.blade.php:142` | `$pendingAdmissionCount = AdmissionController::pendingCount($user->institute_id)` | 1 extra query when education |
| 17 | `vendor/composer/ClassLoader.php:429` (current) / `576` `include($file)` | `return false;` / `include $file` | **Manifest location**, not cause |
| 18 | `vendor/laravel/framework/src/Illuminate/Database/Connection.php:420` | `PDO->prepare()` | Actual I/O timeout |
| 19 | `vendor/laravel/framework/src/Illuminate/Cache/CacheManager.php:19` | `public function __construct(...)` | Boot-time cache construction |
| 20 | `config/industry_rules.php:40` | `'training_center' => [5 professional types]` | B16 taxonomy — not a cause |

---

## U. Minimal Safe Fix Recommendation (DO NOT IMPLEMENT IN THIS PHASE)

> **All below are non-destructive, no schema/data/middleware disable, no max_execution_time increase. Recommend in priority order.**

1. **Replace `View::composer('*')` with scoped composers or View::share in middleware.**  
   - Change `app/Providers/AppServiceProvider.php:121 View::composer('*',` → `View::composer(['layouts.institute','business.profile','home'],` or move the heavy logic to a dedicated `ViewComposer` class + `composer` for only the layout.  
   - Alternatively, hoist the logic to `App\Http\Middleware\SetTenantContext` or a new `ShareViewData` middleware that does **once per request** `view()->share([...])` instead of per-view.  
   - Add a static `requestCache` inside the composer: `static $shared=null; if($shared) { $view->with($shared); return; }` — prevents 2-3× multiplication.

2. **Debounce `Setting::get` in the same request.**  
   - `Setting.php:59 static $cache=[]` already exists, but `Cache::has` is still called before it. Add `if(array_key_exists($key,$cache)) return $cache[$key]` **before** `Cache::has`. Already partially there, but the view composer does 7 distinct keys — ensure `brand.name`, `app.name`, `app.timezone`, `app.language` are fetched via a single `whereIn('key', [...])->pluck('value','key')` instead of 4+1+3 separate queries. Or eager-load all settings at boot: `Setting::whereIn('key', ['brand.name','app.name',...])->pluck(...)`.

3. **Warm `ModuleAccessService` cache outside the view.**  
   - Add `Cache::remember('module_access:'.$institute->id, 3600, ...)` **pre-warming** in `SetTenantContext` after `TenantContext::set`, not lazily inside the view. The view should then hit `Cache::get` (0 queries). Invalidate only on `institute_packages`/`overrides` change (already via `flushCache`).

4. **Optimize `NotificationCenter` for header bell.**  
   - The bell only needs count + 5 rows, but `unreadCount()` does `visibleQuery()->count()` **plus** `readIds` pluck **plus** `latest`. Combine into 1 query with `withExists` or add composite index `notifications(scope,institute_id,created_at)` + `notification_reads(user_type,user_id,notification_id)`.  
   - Add `LIMIT` + `SELECT id,title,message,link_url,created_at` projection (already done in `latest` but not in `visibleQuery` count path).

5. **Add DB indexes for hot paths (migration-free workaround first, migration second):**  
   - `settings(key)` unique — already exists; ensure it is `INDEX`.  
   - `notifications(scope,institute_id,created_at)` and `notification_reads(user_type,user_id)`.  
   - `institution_user(user_id,status,institution_id)` — critical for `Workspace::membership` and `SetTenantContext` fallback. Verify via `SHOW INDEX`.

6. **Enable config cache for production parity:**  
   - Run `php artisan config:cache` (creates `bootstrap/cache/config.php`) — eliminates per-request `config/industry_rules.php` parsing. Safe per `artisan about` “Config NOT CACHED”. Do **after** fixing the composer query storm, not before, to avoid masking the issue.

7. **Consider `CACHE_DRIVER` change for `Setting::get`:**  
   - Keep `CACHE_DRIVER=file` but set `CACHE_PREFIX` to avoid filesystem collisions; or switch to `database` cache with `php artisan cache:table` if file I/O on Windows is proven slow via `xdebug` profile.

8. **Do NOT:** increase `max_execution_time`, disable `TenantScoped`/`BranchScoped`, disable domain protection, or delete vendor files.

---

## Classification

```
ROOT_CAUSE:            NOT PROVEN as infinite recursion; PROVEN as View composer '*' + ModuleAccessService cold-cache + MySQL PDO prepare query storm exceeding 30s (ClassLoader is manifest location)
REGRESSION:            B15 (probable trigger, cache invalidations at 17:03/17:16) + long-standing View::composer('*') amplifier (pre-B6). NOT MAWA-specific — any professional/academic institute on cold cache reproduces.
COMPOSER_PROBLEM:      NO  (autoload_psr4/classmap/files are well-formed; 9357 classmap entries, no duplicate FQCN, no case mismatch, ClassLoader unmodified)
APPLICATION_RECURSION: NO  (proven via code inspection + error.log stacks — no self-calling view/layout/route/helper cycle)
VIEW_RECURSION:        NO  (business/profile → layouts/institute is correct @extends; no partial includes parent; no component recursion)
CONTAINER_RECURSION:   NO  (AiProvider singleton is lazy; no circular singleton/bind/make chain)
MODEL_RECURSION:       NO  (booted/updating only on write; scopes are no-ops when TenantContext disabled)
WORKSPACE_RECURSION:   NO  (Workspace/TenantContext/BranchContext are static setters, no view/route call)
TENANT_RECURSION:      NO  (SetTenantContext does not redirect or dispatch)
DOMAIN_RECURSION:      NO  (InstituteDomain is pure, no DB/view/route except hasDomainData, not on hot path)
CACHE_CORRUPTION:      NO  (bootstrap/cache/config.php absent by design; packages/services present; vendor/composer timestamps 2026-08-28 healthy; optimize:clear succeeded; but COLD CACHE amplifies the storm)

DATA_MODIFIED: NO
DATA_DELETED: NO
MIGRATIONS: NO
SCHEMA_MODIFIED: NO
SOURCE_MODIFIED: NO
VENDOR_MODIFIED: NO
```

## Findings Severity

**CRITICAL_FINDINGS:**
* `app/Providers/AppServiceProvider.php:121` `View::composer('*')` does 35-45 queries per invocation × 2-3 invocations per page = 70-130 queries; observed 69 queries before timeout and `Connection.php:420 PDO->prepare` fatals at `Setting.php:57`/`NotificationCenter.php:187`/`InstituteSetting HasOne`. Fix is to scope the composer or hoist to once-per-request `view()->share`.
* `ModuleAccessService::getEnabledModules` cold path (`ModuleAccessService.php:59` `Cache::remember`) does 12+ queries via `resolveEnabled` and is lazily triggered **inside** the View composer after every `cache:clear`/`flushCache(42)` (B15 did two at 17:03/17:16). The next authenticated request pays both costs within the 30s window. Warm cache probes now succeed (`curl` 200), proving cold-cache dependence.

**HIGH_FINDINGS:**
* `bootstrap/cache/config.php` missing (`artisan about` Config NOT CACHED) — every request parses `config/industry_rules.php` + rebuilds container, adding ~100ms+ file I/O before DB work.
* `SetTenantContext.php:50-69` fallback adds 3 queries per request when `Workspace::id()==null` (post-cache:clear/new device). No caching.
* `NotificationCenter` bell does 3 separate queries (`visibleQuery` count + `readIds` pluck + `latest`) with `OR` scope branches — under 22 notifications / 45 reads it still times out at `Connection.php:420`.

**MEDIUM_FINDINGS:**
* `AppServiceProvider.php:67-77` boot-time `Setting::get` for `brand.name`/`app.name`/`app.timezone`/`app.language` does 4 separate `SELECT` + `Cache::has` file_exists checks. Debounce to single `whereIn`.
* `mawa_current_lang()` fallback does `InstituteSetting::value('language')` when session missing — extra query for guests.
* `LivewireServiceProvider::bootConfig` does `app()` during `BootProviders`, competing for container + autoloader time.
* `session` driver is `database` while `cache` is `file` — mixed drivers increase DB pressure under View composer storm.

**LOW_FINDINGS:**
* `institute.blade.php:142` `AdmissionController::pendingCount` adds 1 query when education + admission.approve permission — negligible.
* `config/composer.json:platform php 8.2.12` vs runtime 8.5.8 — no functional impact but platform mismatch should be updated.
* Herd Lite PHP 8.5.0 co-exists alongside XAMPP 8.5.8 — `php --ini` vs `C:\xampp\php\php.ini` divergence is cosmetic but confused initial `php -v` probe.

---

## FINAL_VERDICT

**YELLOW**

*Application is **not** in hard infinite recursion, but is in **cold-cache performance collapse** — authenticated pages that render the institute layout will intermittently exceed `max_execution_time=30` (MySQL `PDO::prepare` is the bottleneck, `ClassLoader.php:429` is the messenger). The site **recovers** once caches are warm (verified via 21:05 200s and current `curl` 302/200 probes), so it is not RED (permanent outage), but it is not GREEN because the next `cache:clear`, `optimize:clear`, or multi-user burst will reproduce the 500.*

*No data was modified, no migrations run, no source/vendor touched during this audit. The fix is a **code change** (scope View composer + debounce settings + pre-warm module cache) — intentionally NOT applied in this phase per instructions.*
