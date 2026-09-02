# PHASE B6 — BUSINESS PROFILE FORENSIC AUDIT REPORT

**Date:** 2026-08-28
**Mode:** READ-ONLY forensic audit — no migrations, no data modification, no architecture redesign
**Scope:** Dedicated Business Profile page for the currently active business/institute

---

## 1. EXECUTIVE SUMMARY

The project **already has a complete business/institute identity system** anchored on the `institutes` table. Every profile field requested in Phase B6 exists in the DB today; no new table or column is required to display a Business Profile page. What is *missing* is a **tenant-scoped, workspace-resolved authenticated view** at a canonical URL (e.g. `GET /business/profile`) that shows *only* the active workspace's institute, plus a clickable topbar integration. A public standalone profile (`resources/views/business/show.blade.php` + stub route `business/{institute}`) exists but is **not** tenant-scoped and currently redirects to dashboard.

**Verdict on migration necessity:** **No migration is required.** All data lives in `institutes`, `institute_settings`, `branches`, `institute_subscriptions` + `subscription_packages`, `institution_user` (Membership), `countries`/`geo` tables. Duplicating into a new table would violate the constraint and is forensically unnecessary.

---

## 2. SOURCES INSPECTED

| Area | File / Table | Lines / Columns inspected |
|---|---|---|
| Institute model | `app/Models/Institute.php:1-219` | fillable/guarded, softDeletes, booted domain immutability guard, relations |
| Institutes table | `DB::SHOW COLUMNS FROM institutes` (live DB `monetix`) | 44 columns (see §3) |
| Membership model | `app/Models/Membership.php:1-172` | institution_user mapping, roleAllowedForAccountType |
| Membership table | `institution_user` columns dump | 26 columns |
| User model | `app/Models/User.php:1-329` | global account, getInstituteIdAttribute via Workspace/TenantContext |
| Branch model | `app/Models/Branch.php:1-48` | TenantScoped, institute_id FK |
| Branches table | live dump | 10 columns |
| InstituteSetting model | `app/Models/InstituteSetting.php:1-50` | TenantScoped, casts |
| InstituteSetting table | live dump | 26 columns |
| InstituteSubscription | `app/Models/InstituteSubscription.php:1-27` | billing_cycle, price_paid, start/end |
| SubscriptionPackage | `app/Models/SubscriptionPackage.php:1-30` | slug, enabled modules |
| Tenant infra | `app/Support/TenantContext.php:1-35`, `Workspace.php:1-139`, `BranchContext.php:1-37` | session key, verify(), resolveAfterLogin() |
| Middleware | `app/Http/Middleware/SetTenantContext.php:1-85`, `EnsureInstituteContext.php:1-57`, `EnsureDomain.php:1-52` | tenant binding, auto fallback, domain guard |
| Domain resolver | `app/Support/InstituteDomain.php:1-164` | ACADEMIC/PROFESSIONAL/OTHER, fromKeys(), hasDomainData() |
| Industry taxonomy | `config/industry_rules.php:1-369` | canonical industries + sub_industries |
| Layout / topbar | `resources/views/layouts/institute.blade.php:25-52`, `app/Providers/AppServiceProvider.php:121-381` | brand link, workspace switcher, view composer |
| Settings UI | `app/Http/Controllers/InstituteSettingController.php:1-236`, `resources/views/settings/index.blade.php` | existing edit surfaces |
| Workspace controller | `app/Http/Controllers/WorkspaceController.php:1-85` | picker + switch (verify-guarded) |
| Dashboard | `app/Http/Controllers/DashboardController.php:1-245` | workspace vs institute_user branches |
| Routes | `routes/web.php:1-403` (lines 349, 114-138) | `business.show` stub, workspace routes, tenant + verified groups |
| Business view | `resources/views/business/show.blade.php:1-219` | public standalone layout |
| Roles/Permissions | `database/migrations/2026_08_12_000000*`, `app/Http/Middleware/CheckPermission.php` | `settings.manage`, `staff.manage` etc |
| Concerns | `app/Models/Concerns/TenantScoped.php:1-52`, `BranchScoped.php` | global scopes + institute_id forcing |

Historical forensic docs reviewed: `PHASE_B1_*.md`, `PHASE_B2_*.md`, `PHASE_B5_*.md`, `PHASE_B6_REAL_BROWSER_LOGIN_FORENSIC_REPORT.md`.

---

## 3. EXISTING BUSINESS IDENTITY SOURCE (Canonical)

### 3.1 `institutes` table — authoritative

```
id, uuid, name, short_name, slug, logo, cover_photo, description,
founded_year, industry, sub_industry, institute_code, country, country_id,
division, district, upazila, admin_level_1/2/3_id, address, postal_code,
phone, whatsapp, email, website, facebook, youtube, google_map_url,
license_number, trade_license, registration_number, e_tin,
package_id, subscription_expiry, status[pending|active|suspended|expired|cancelled],
verified,bool, onboarded_at, deleted_at/deleted_by, deletion_requested_at/by,
created_at, updated_at, is_test
```

**File evidence:** `app/Models/Institute.php:13-26` (`$table='institutes'`, `SoftDeletes`, `$guarded=[]`, `$casts is_test bool`). No `TenantScoped` trait — it *is* the tenant root.

**Key observations:**

- **Industry taxonomy** (`industry` + `sub_industry`) is the domain key. Canonical lists in `config/industry_rules.php:22-71`. Academic = `education:{school,college,polytechnic,university,...}`. Professional = `training_center:{training_institute, professional_training_center, dance_academy, it_training_center, vocational_training_center,...}`. Other = every remaining industry.
- **Domain immutability** enforced at model level: `Institute::booted() updating` guard (`Institute.php:30-48`) checks `isDirty industry|sub_industry`, computes old vs new domain via `InstituteDomain::fromKeys()` and throws `ValidationException` if `hasDomainData()` returns true. This is the correct reuse point for edit protection.
- **Slots unused for B6:** `founded_year` exists (good for Established Date), `slug` exists (public URL), `cover_photo` exists (banner), `short_name` exists.
- **Missing / not stored:** `alternative_phone` beyond `whatsapp`; `domain` is *derived* (not stored separately — correctly via `InstituteDomain`); `currency`, `date_format` not per-institute columns — they live in `institute_settings.language/timezone` + global config (see §6). No fake fields needed; absent fields must render "Not provided".

**Sub-ind quirk:** `PHASE_B1` legacy migration `add_industry_to_institutes_table` + `add_sub_industry...` introduced `industry`/`sub_industry` as nullable varchars. Normalized via `InstituteDomain::normalizeIndustry/SubIndustry`: `transport→transportation`, legacy aliases (`institution→training_institute`, `computer_it→it_training_center`, etc.) (`InstituteDomain.php:118-142`).

### 3.2 What is NOT the identity source

- `Theme` / `IndustrySetting` — presentation only, not identity.
- `Setting::get('brand.*')` — platform brand, not institute brand.
- No duplicate `businesses` table exists nor is needed.

---

## 4. MEMBERSHIP / OWNERSHIP RELATIONSHIP

### 4.1 Dual system (historical + canonical)

| Layer | Table | Model | Guard | Relationship |
|---|---|---|---|---|
| **Canonical (Phase B2+)** | `institution_user` | `Membership.php:10-172` + `User.php:170-182` | `web` (User) | `User --hasMany--> Membership --belongsTo--> Institute` + `Institute --belongsToMany--> User via institution_user` |
| **Legacy** | `institute_users` | `InstituteUser.php:17-209` | `institute_user` | `InstituteUser --belongsTo--> Institute` (still active for legacy login path, `SetTenantContext` supports both) |

**Forensic finding — do not duplicate:** The platform has **migrated to Membership** but keeps `InstituteUser` for backward compatibility / direct staff logins. Both resolve to an institute:

- `Workspace::membership():?Membership` (`Workspace.php:52-80`) — verifies active membership + `roleAllowedForAccountType`.
- `SetTenantContext::handle()` (`SetTenantContext.php:27-84`) — branches on `Guardian | InstituteUser | User`. For `User`, reads `Workspace::id()` from session, verifies via `Workspace::verify()`, auto-fallbacks to first active membership if session is stale (cache:clear), then `TenantContext::set($workspaceId)` + `BranchContext::set($membership?->branch_id)`.
- `Workspace::verify(int $institutionId, int $userId):bool` (`Workspace.php:87-104`) — single source for switch validation.
- `Workspace::resolveAfterLogin(User, ?int requestedId):?int` (`Workspace.php:113-138`) — 0→null, 1→auto-activate, N→must choose; filters by `roleAllowedForAccountType`.

**Roles/ownership:** `Membership::isOwner()` checks `role.slug === 'institute-owner'` (`Membership.php:163-166`). `Membership::hasPermission()` gives owner super-user semantics. `User::isOwnerAccount()` vs `isStaffAccount()` on `account_type` enum (`users.account_type owner|staff`) enforced at create+update via `assertRoleAllowedForAccountType()` (`Membership.php:60-77`, `User.php:114`).

**Multi-business behavior:** A single `User` row can own many `Membership` rows. Active selection is session-scoped `active_institution_id` (`Workspace::SESSION_KEY = 'active_institution_id'`). Switching is POST `workspace/switch/{institutionId}` (`WorkspaceController.php:36-49`) which calls `Workspace::verify()` then `Workspace::set()` (which also sets `TenantContext` + `BranchContext`).

**Forensic gap for B6:** No `GET /workspace` leak — IDs are POST and verified. The same pattern must be used for Business Profile: **no URL-accepted institute_id**. Resolve from `Workspace::membership()` / `TenantContext::id()` only.

---

## 5. EXISTING PROFILE FIELDS (Inventory vs UI)

### 5.1 Fields that EXIST in DB

| Section per spec | DB location | Field(s) | Edit UI today? | Display UI today? |
|---|---|---|---|---|
| Business Header (name, code, industry, type, status) | `institutes.name`, `short_name`, `institute_code`, `industry`, `sub_industry`, `status`, `verified`, `logo`, `cover_photo` | all present | creation via `InstituteCreationController` + `InstituteOnboardingController`; no dedicated edit for name/code after creation (except super-admin `InstituteAdminController::update`) | `business/show.blade.php:27-53` renders all, `layouts/institute.blade.php:31-50` topbar renders `institute->name` |
| Basic Business Info | same as above + `founded_year`, `slug` | present | no tenant self-edit for code/year | partially via public view |
| Contact | `institutes.phone`, `whatsapp`, `email`, `website`, `facebook`, `youtube` | present | none for tenant | public view  `business/show:156-192` |
| Address/Location | `institutes.address`, `postal_code`, `country→countries table`, `country_id`, `division`, `district`, `upazila`, `admin_level_1/2/3_id`, `google_map_url` | present | address set at registration (`register/address` flow) | public view + geo selects (`geo-select.js`) |
| Branding | `institutes.logo`, `cover_photo`, `description` + `institute_settings.logo`, `favicon`, `primary_color`, `secondary_color`, `theme`, `sidebar_color`, `tall_navigation` | present (split across two tables) | appearance pane `InstituteSettingController::appearance/updateAppearance` | public avatar + cover |
| Business Settings | `institute_settings.timezone`, `language`, `notification_settings`, `ai_config`, `sales_config`, `purchase_config`, `academic_unit_label`, `structure_template_id`, `certificate_approval_mode` | present | `settings.index` panes `pane-general` + `pane-appearance` | via settings |
| Branches | `branches.id, institute_id, name, manager_user_id, phone, email, address, status` | present | branch CRUD exists under institute Modules (`Branch` creation elsewhere — verify `branches` migration) | `business/show:135-152` lists branches |
| Subscription | `institute_subscriptions` + `institutes.package_id` + `subscription_packages.slug/name` + `packageModules` | present | not tenant-editable (platform) | not on public view (DB supports it) |
| Membership summary | `institution_user` counts, `branches.count`, `students.count` via TenantScoped models | derived | — | dashboard stats `DashboardController:51-56, 176-180` but not profile card |

### 5.2 Fields that DO NOT EXIST (must show "Not provided", never invent)

- `alternative_phone` beyond `whatsapp`
- `currency` as institute column (currency is per-transaction/accounting, not instit profile)
- `date_format` as institute column (format is presentation)
- Explicit `domain` column (domain is derived via `InstituteDomain`)
- `established_date` as date (exists as `founded_year` year only — use it, don't fabricate day/month)
- `banner` distinct from `cover_photo` (reuse `cover_photo`)
- `favicon` is institute_settings only
- `payment credentials`, `smtp_password_enc`, `sms_api_key_enc`, `payment_config_enc` — **exist but must never be rendered** (see §10)

### 5.3 "Edit Profile" — reuse vs create

- **Existing edit:** `InstituteSettingController::index()` (`settings.index`) handles general/appearance; `InstituteAdminController::update` (platform-admin only) can edit institute row. There is **no tenant self-service route to edit `institutes.name/phone/address/...` directly** — that gap should be filled by *reusing* the existing `InstituteSettingController` or a new tenant-guarded `BusinessProfileController::update` that writes to `institutes` and `institute_settings` through the same validation + `TenantContext` + `InstituteDomain` guard already enforced at model boot. Do **not** create duplicate update logic bypassing the domain guard.
- Domain-defining fields (`industry`, `sub_industry`) must remain immutable when `hasDomainData()=true` (`Institute.php:40-43`). UI must either disable them or show read-only + helper text referencing that exception.

---

## 6. EXISTING SETTINGS

### 6.1 `institute_settings` (26 cols, live DB) — `app/Models/InstituteSetting.php:10-50`

```
institute_id FK,
theme, primary_color, secondary_color, sidebar_color, tall_navigation,
ai_config JSON, notification_settings JSON, sales_config JSON, purchase_config JSON,
logo, favicon, smtp_host/port/username/password_enc/encryption,
sms_provider/api_key_enc, payment_gateway/config_enc,
timezone, language, certificate_approval_mode, academic_unit_label, structure_template_id
```

- TenantScoped (`InstituteSetting.php:10`). `updateAppearance` (`InstituteSettingController.php:82-111`) + `updateGeneral` already verified; `sales_config`/`purchase_config`/`ai_config` are mutated via other controllers but reachable.
- **B6 reuse:** timezone (`Asia/Dhaka`), language (`bn|en`), branding (`logo`/`favicon`/`theme`), `notification_settings`, `academic_unit_label` are valid Business Settings subsections.

### 6.2 Global platform settings

`Setting::get('brand.*'|'app.*')` in `AppServiceProvider.php:66-78` — not institute-specific; must not appear on Business Profile.

---

## 7. EXISTING BRANDING

| Asset | DB | Upload path | Viewer |
|---|---|---|---|
| Logo (institute) | `institutes.logo` | `storage/<path>` | `business/show:29-33` `asset('storage/'.logo)` |
| Cover/Banner | `institutes.cover_photo` | same | `business/show:18-25` |
| Logo (settings override) | `institute_settings.logo` | same | settings pane |
| Favicon | `institute_settings.favicon` | same | layout `<head>` variant |
| Theme | `institute_settings.theme` + `themes` table | — | `settings.index:58`, `InstituteSettingController::resolveSetting()` |
| Colors | `institute_settings.primary_color/secondary_color/sidebar_color` | — | same |
| Description | `institutes.description` | — | `business/show:103-109` About block |

No separate `branding_settings` table — reuse these. Do not invent `business_profile_image`.

---

## 8. EXISTING BRANCH INFORMATION

**Table `branches`** (`Branch.php:10-11` TenantScoped): `id, institute_id, name, manager_user_id FK→institute_users.id, phone, email, address, status[active|inactive], deleted_at`. `institute_id` FK enforces tenancy. `BranchContext` ties `branch_id` from membership.

**Display:** `business/show.blade.php:135-152` already iterates `$branches`. Count available via `$institute->branches` (`Institute.php:55-58`). Dashboard `cleanStudentDashboard` groups students by branch.

**Branch isolation for B6:** Branch list must be `Branch::where('institute_id', $institute->id)` or `$institute->branches` — never an unscoped `Branch::all()`. TenantScoped scope already does this when `TenantContext::enabled()` (`TenantScoped.php:19-24`).

---

## 9. EXISTING SUBSCRIPTION INFORMATION

- `institutes.package_id → subscription_packages.id` (+ fallback auto-assigned `PREMIUM` in testing via `AppServiceProvider.php:112-119`).
- `institute_subscriptions` : `institute_id, package_id→packages, billing_cycle[monthly|yearly|trial], price_paid, start_date, end_date, payment_reference, status[active|expired|cancelled]` (`InstituteSubscription.php`).
- `Institute::package() BelongsTo SubscriptionPackage`, `instituteSubscriptions() HasMany`.
- Module entitlements: `institute_module_entitlements`, `institute_module_overrides`, `package_modules`.

**Security for B6:** Show only `package.name`, `status`, `start_date`, `end_date`, usage counts (students/branches/module enabled list via `isModuleEnabled()`/`enabledModules()`). Never expose `price_paid` or `payment_reference` or `payment_config_enc` / `sms_api_key_enc` / `smtp_password_enc`.

**Existing gate:** `ModuleAccessService` + `isEnabled()` checked in layout composer (`AppServiceProvider.php:220-289`) and `DashboardController`. Reuse for subscription section badge.

---

## 10. EXISTING DOMAIN / INDUSTRY INFORMATION

**Authoritative resolver:** `App\Support\InstituteDomain` ONLY (`InstituteDomain.php:16-164`). No second resolver exists.

- Constants: `ACADEMIC='academic'`, `PROFESSIONAL='professional'`, `OTHER='other'`
- `ACADEMIC_TYPES = [school,college,polytechnic,university]` under `education`
- `PROFESSIONAL_TYPES = [training_institute, professional_training_center, dance_academy, it_training_center, vocational_training_center]` under `training_center`
- `fromInstitute(?Institute)` → `fromKeys(industry, sub_industry)` with normalization; `isAcademic()`, `isProfessional()`, `subjectTypeFor()`, `isValidCombination()`, `hasDomainData(int instituteId):bool`, `normalizeIndustry/SubIndustry()`.

**Display spec for B6:**

```
Industry:        $institute->industry            (label via IndustryRules::industries()[key] ?? ucwords)
Business Type:   $institute->sub_industry        (str_replace '_' → ' ' )
Domain:          InstituteDomain::fromInstitute($institute)  // Academic | Professional | Other
```

Example mapping already correct: `TBN Dance Academy  → industry=training_center, sub_industry=dance_academy  → domain=professional`. School `education+school → academic`. Proven via `InstituteDomain::isAcademic($institute)` usage in `dashboard.blade` and `layouts/institute.blade.php:124`.

**Phase B2 immutability:** `Institute::booted updating` guard throws if domain would change and `hasDomainData()==true`. `hasDomainData` probes `courses, subjects, course_curricula, batches, student_academic_placements, academic_assessments, academic_final_results, academic_student_marks` via direct `DB::table()->exists()`. This is the exact gate B6 edit must respect — read-only fields when blocked.

No migration of legacy `madrasha/primary_school/...` aliases required for display (they normalize at read time, preserved in DB for audit).

---

## 11. EXISTING EDIT / UPDATE UI

| UI | Route | Controller | Auth + Guard |
|---|---|---|---|
| Settings hub | `GET settings` → `settings.index` | `InstituteSettingController@index` | `tenant+verified`, permission `settings.manage` |
| Appearance | `PUT settings/appearance` | `updateAppearance` | same |
| General (tz/lang) | custom? `updateGeneral` (route `settings.general.update` expected, not in web.php excerpt — register in institute_modules.php) | `updateGeneral` | same |
| Password | stored via `PasswordService` | `InstituteSettingController@updatePassword` | same |
| Institute row edit | `PUT admin/institutes/{institute}` | `InstituteAdminController@update` | `platform_admin` only |
| Workspace onboarding | `GET/POST workspace/onboarding` | `InstituteOnboardingController@choose` | `auth:web` |
| Branch mgmt | via institute_modules routes (module-gated) | `Inventory* / Crm*` | tenant |

**Finding for B6:** The `institutes` row self-edit surface for tenants is **absent** (tenant can edit settings but not name/address/phone directly without super-admin). This is intentional least-privilege. For Business Profile "Edit" button:

- If `membership->hasPermission('settings.manage')` or `instituteUser->hasPermission(...)` → show Edit linking to **existing** `settings.index# pane` (prefer reuse).
- If deeper fields need editing (name, address, phone), extend `InstituteSettingController@update` or new `BusinessProfileController@update` that updates only safe institutes columns (phone, email, website, address, postal_code, division/district/upazila/country_id, description, short_name) after per-field validation, **never** `industry/sub_industry/package/status/verified`. Route must be `auth:web`/`auth:institute_user` + `tenant` + `CheckPermission:settings.manage`.

---

## 12. EXISTING TOPBAR BUSINESS-NAME IMPLEMENTATION

**Template:** `resources/views/layouts/institute.blade.php:31-51`

```blade
@if ($isInstituteStaff && !empty($institute->slug))
  <a class="brand" href="{{ route('business.show', $institute->slug) }}"> {{ $institute->name }} + verified badge </a>
@else
  <a class="brand" href="{{ route('dashboard') }}">
     @if($isInstituteStaff) {{ $institute->name }} @endif
     @auth('platform_admin') AccumenAI @endauth
  </a>
@endif
```

**Composer:** `AppServiceProvider.php:170-175` resolves `$institute` as:

```php
match(User|InstituteUser|PlatformAdmin):
  InstituteUser → Institute::find($user->institute_id)
  User+Membership → Institute::find($membership->institution_id)
  else null
```

`$isInstituteStaff = $user instanceof InstituteUser || ($user instanceof User && $membership!==null)` (`AppServiceProvider.php:199`).

**Current route `business.show`:** `routes/web.php:349`

```php
Route::get('business/{institute}', fn($institute)=>redirect()->route('dashboard'))->name('business.show');
```

— **placeholder redirect**, not a tenant profile. The public standalone blade `business/show.blade.php` expects `$institute, $courses, $branches, $studentsCount...` but is unreachable via this stub. No auth requirement.

**Workspace switcher** lives *inside the sidebar user card*, not topbar (`institute.blade.php:424-450`), so topbar remains the sole business name display on desktop; mobile collapses topbar + sidebar. B6 change is **only** to turn the existing brand anchor into `/business/profile` (workspace-resolved) instead of `business/{slug}` redirect, preserving responsive behavior.

**Mobile/desktop parity:** Both share the same `.brand` anchor; no secondary selector to maintain.

---

## 13. EXISTING ROUTES / CONTROLLERS / VIEWS

### 13.1 Routes (web.php canonical)

- `GET /` → `DashboardController` (`auth:platform_admin,institute_user,web` + `tenant` for institute users, `verified`)
- `GET workspace` → `WorkspaceController@picker` (`auth:web verified`)
- `POST workspace/switch/{institutionId}` → `WorkspaceController@switch` (verify-guarded) — **pattern to replicate**
- `GET business/{institute}` → redirect stub (`auth:institute_user,web` tenant verified)
- `GET settings` / `PUT settings` etc.
- No `GET business/profile`, no `BusinessProfileController` yet.

### 13.2 Controllers relevant

- `WorkspaceController` — verify pattern
- `InstituteSettingController` — edit reuse
- `DashboardController` — stats aggregation tenant-scoped
- `Business` — none; `business/show.blade.php` is orphan public view.

### 13.3 Views

- `layouts/institute.blade.php` — institute tenant shell (topbar brand + sidebar + composer vars)
- `layouts/standalone.blade.php` — used by `business/show` (public, no auth)
- `business/show.blade.php` — cover/avatar/name badges/stats/courses/branches/contact/legal blocks (good style reference but must be reimplemented as `business/profile.blade.php` inside `layouts.institute`)
- `settings/index.blade.php` — tabbed settings (reuse style)
- `home.blade.php` — dashboard cards (reuse cards/typography)

No `business/profile.blade.php` exists.

---

## 14. EXISTING PERMISSIONS

Seeded in `2026_08_12_000000_seed_default_role_permissions.php:19,36,53`:

- Super-admin owns all. Owner (`institute-owner`) is super-user inside institute (`hasPermission()` true).
- Staff roles gate via `role_permissions` matrix; relevant: `settings.manage`, `staff.manage`, `students.view/manage`, `courses.view/manage`, `finance.view/manage`, `hr.*`, `crm.view`, `ai.assistant`, etc.

**For B6:** Define `business_profile.view` and `business_profile.manage` (or reuse `settings.manage` / `institutes.view` / `institutes.manage`). Audit finds **no `business_profile.*` permission yet**. Spec §7 step 11 says separate view vs edit if RBAC supports it — it does (`CheckPermission` middleware `CheckPermission.php`). Minimal fix is reuse `settings.manage` for edit and allow any `isInstituteStaff` to view, since profile is ownership-neutral read. If stricter isolation desired, seed new permissions and attach to owner/staff seeds — but not required for read-only milestone; record as follow-up.

**Middleware:** `CheckPermission` (`app/Http/Middleware/CheckPermission.php`) string-matches `permission:foo.view`. `CheckModuleAccess` gates modules.

---

## 15. TENANT ISOLATION

- **Mechanism:** `TenantScoped` trait on `Student, Batch, InstituteCourse, InstituteSetting, Branch, InstituteUser, Membership, ...` adds `where institute_id = TenantContext::id()` when `TenantContext::enabled()` (`Concerns/TenantScoped.php:19-24`). `Institute` itself is *not* scoped (it's the tenant boundary).
- **Activation:** `SetTenantContext` middleware (`SetTenantContext.php`) runs after auth on every `tenant` group; `AppServiceProvider@boot` composer also sets but middleware is authoritative per-request.
- **IDOR hardening:** `TenantScoped::creating` forces `institute_id = TenantContext::id()` regardless of mass-assigned value; `updating` blocks dirty `institute_id` (`TenantScoped.php:28-51`).
- **Current scope coverage:** Counts in `DashboardController` use TenantScoped models without explicit where — e.g. `Student::count()` is already scoped. `Business Profile` must follow same pattern for branch list, counts, package.

**Forensic check — global/unscoped query risk:** `Institute::query()->find($id)` used in `InstituteSettingController::verify()` (`InstituteSettingController.php:32`) bypasses tenant — but that method is deprecated/verify page. For B6, `Institute::withoutGlobalScopes()->find(...)` appears only inside `SetTenantContext` fallback and `AppServiceProvider`; the profile controller **must not** use arbitrary `Institute::find($request->input('institute_id'))`. It must resolve from `Workspace::membership()` or `TenantContext::id()`.

---

## 16. IDOR RISKS (As-Is)

| Risk | Status | Evidence |
|---|---|---|
| `GET business/{institute}` accepts path slug and redirects without membership check | **BAD — not IDOR into data** but stub is unscoped; if reactivated to render `business/show`, any authenticated or unauth user could enumerate slugs | `web.php:349` closure takes `$institute` and blindly redirects — no `Workspace::verify`, no `TenantContext` |
| `GET settings` before fix | Low — gated by `tenant+verified` + permission, resolves via `Workspace::membership()->institution_id`, not client input |
| `POST workspace/switch/{institutionId}` | **Safe** — calls `Workspace::verify($institutionId, $user->id)` (`WorkspaceController.php:42-44`) |
| Future `GET business/profile/{id}` if implemented naively | **Would be IDOR** — must be blocked. Spec calls for `GET business/profile` with no param, resolved from context. If param unavoidable, must `Workspace::verify()` + membership check, return 404/403. |
| TenantScoped bypass via `withoutGlobalScopes()` | Only in trusted middleware/composer; profile must never use it on tenant models. |

**Mitigation for B6:** Profile route `GET business/profile` under `auth:web,institute_user` + `tenant` + `verified` (or `EnsureInstituteContext`) and controller resolves:

```php
$institute = match(true){
  $request->user() instanceof InstituteUser => Institute::find($request->user()->institute_id),
  $request->user() instanceof User => Workspace::membership()?->institution,
};
abort_unless($institute,'403');
```

Never `$request->route('institute')` trust. Branch and settings queries remain TenantScoped.

---

## 17. MULTI-BUSINESS BEHAVIOR

**Verified scenario:** User A holds memberships M1→TBN Dance Academy (training_center/dance_academy), M2→XYZ School (education/school). Session `active_institution_id` = M1. `DashboardController@workspaceDashboard` + `SetTenantContext` + `TenantScoped` guarantee all counts are scoped to TBN. `WorkspaceController@switch(M2)` flips session + TenantContext + BranchContext. Topbar composer shows M-active's institute name. Spec §4 requires switching changes profile atomically — the same mechanism will satisfy `GET business/profile` (it reads context, not slug).

**Workspace picker:** `resources/views/workspace/picker.blade.php` loops `$memberships` (filtered `roleAllowedForAccountType`). After login, `Workspace::resolveAfterLogin` handles 1-vs-N memberships. Profile page must 302 to `workspace.picker` or `workspace.onboarding` when no active membership, as `EnsureInstituteContext` already does (`EnsureInstituteContext.php:34-38`).

**Branch multi-business:** `BranchContext::set($membership?->branch_id)` ensures staff limited to branch sees only that branch's TenantScoped rows. Owner sees all branches of active institute (branch_id NULL). Profile branch list must respect `BranchContext` — however for a profile overview, showing all active-institute branches is intended even for branch-scoped staff? The spec says "Only branches belonging to current institute" — not "only own branch". Decision: show all active-institute branches to any staff who can view profile (or filter by BranchScoped if existing permission governs). Document as policy choice during implementation.

---

## 18. BRANCH ISOLATION

- FK `branches.institute_id` + `TenantScoped` scope (`Branch.php:9-11`). Global scope applies when `TenantContext::enabled()` — which profile route guarantees (middleware `tenant`).
- `Institute::branches():HasMany` is *not* scoped by itself but will be when queried via scoped relationship under context; still, controller should do `Branch::query()->where('institute_id', $institute->id)->get()` or `$institute->branches` (the global scope adds institute_id twice harmlessly when context active, but `withoutGlobalScopes()` never used).
- `SetTenantContext` also forces branch isolation via `BranchContext` for models using `BranchScoped`. Branches table itself is TenantScoped only, not BranchScoped.
- No cross-institute branch leakage seen in `business/show:135-152` (uses `$branches` derived from correct institute). B6 must copy that derivation.

---

## 19. FIELDS MISSING FROM CURRENT PROFILE UI (B6 Display Gap)

Count these as **UI gaps, not DB gaps** — DB already has them, current profile rendering (`business/show`) does not surface:

| Spec section | DB field(s) | Current public display? | Action |
|---|---|---|---|
| Domain resolver badge | derived `InstituteDomain::fromInstitute()` | No (raw `industry/sub_industry` badges only) | Add domain badge + domain description |
| Status badge (Active etc) | `institutes.status` | Partial (warning only when !=active) | Add Active badge |
| Institute code | `institute_code` | Yes but small | Promote to Basic Info card |
| Founded year / Established | `founded_year` | Yes (icon only) | Card field with "Not provided" fallback |
| Postal / address composition | `postal_code`, `admin_level_*` vs `division/district/upazila` | Partial (collects strings) | Normalize: show division/district/upazila + postal + map link |
| Branding favicon | `institute_settings.favicon` | No | Add to Branding card |
| Business Settings block | `institute_settings.timezone/language/currency-absent` | No | Add timezone/language card; currency row as "Not provided" (no column invented) |
| Domain/Business Structure | `industry/sub_industry + domain` | Partial raw badges | New card with Industry / Business Type / Domain |
| Subscription card | `institute_subscriptions + package + expiry` | Not rendered | New card (requires controller fetch) |
| Membership summary | counts (`Membership` + `branches` + `students` + `batches`) | Stats only for education (3 cards) ; not present for non-academic | New summary card tenant-scoped counts, permission-gated |
| Edit entry point | link to settings | No | Edit Profile button (`settings.manage` gate) linking to `settings.index` |

No DB field is missing requiring migration.

---

## 20. WHETHER ANY MIGRATION IS ACTUALLY NECESSARY

**No.**

Atomic proof:

1. `institutes` already holds every Basic/Contact/Address/Industry/Cover field (`SHOW COLUMNS` ≡ §3.1).
2. `institute_settings` holds timezone/language/branding/payment/smtp (masked).
3. `branches` holds per-institute branches.
4. `institute_subscriptions` + `subscription_packages` holds package + dates.
5. `institution_user` holds membership/role/branch per business; `users` holds global identity.
6. `InstituteDomain` + `config/industry_rules.php` holds domain/industry taxonomy.
7. `TenantContext` + `Workspace` provide multi-business isolation without new tables.

**Anti-patterns avoided per spec:** No new `businesses`, `business_profiles`, or `business_settings` tables. No duplication of `institutes` into tenant branch. No new domain resolver. No hard-coded `dance_academy` column — use existing `sub_industry`.

**Only migration that could be justified (optional, not required now):** A `business_profile`+`business_profile.manage` permission seed if tenant wants distinct view vs edit gates beyond `settings.manage`. This is a **seed row in `permissions`/`role_permissions`**, not a schema change, and can ship as a permissions seeder migration later. Not needed to render the page.

---

## 21. ADDITIONAL FORENSIC FINDINGS

### 21.1 `business/show.blade.php:1` is standalone hijack
`$extends('layouts.standalone'...)` means public page intentionally bypasses institute shell (no auth, no workspace switcher). Converting it to authenticated `layouts.institute` would break its anonymous "share your business page" use-case. B6 should create **separate** `resources/views/business/profile.blade.php` that extends `layouts.institute`, reusing the visual language (cover/avatar/badges/cards) but not altering the public route. Keep both alive, or redirect public slug to authenticated profile only for owners (design choice to confirm).

### 21.2 Route alias `owner.profile` is a silent redirect
`web.php:385 Route::get('owner/profile', fn()=>redirect()->route('settings.index'))->name('owner.profile');` — this plus `layouts/institute:414 owner.profile` link already points staff dropdown → settings. B6 topbar brand link going to `business/profile` is distinct; ensure no slug collision.

### 21.3 Sensitive fields present but must stay hidden
`institute_settings.smtp_password_enc`, `sms_api_key_enc`, `payment_config_enc`, `smtp_username`, `payment_gateway` (`SHOW COLUMNS institute_settings`) exist and are encrypted (`_enc` suffix). `InstituteSetting::casts` does NOT decrypt them — safe. Profile view must never `->toArray()` the settings model without excluding these. Same for `inventories` no, just these. Audit must assert `grep payment_config_enc|smtp_password_enc|sms_api_key_enc` never appears in blade.

### 21.4 Established Date type
`institutes.founded_year` is `YEAR(4)` nullable — render as year only (e.g., "2018"). Do not synthesize full date. If spec asks Established Date, map to this column; show "Not provided" when null.

### 21.5 `country` vs `country_id` duality
Institutes stores both free-text `country VARCHAR(80) NOT NULL` and `country_id FK bigint` + geo `admin_level_*_id`. The canonical display string is `$institute->country` (text) or join via `Country` model (`Institute::country():BelongsTo`). Some rows may have `country_id` null (registration without geo picker). B6 should prefer `$institute->country` text fallback and never assume `country_id` exists.

### 21.6 RBAC for viewing own institute vs viewing arbitrary slug
`business/show.blade.php` currently has *no permission check* — anonymous users can view any `GET business/{slug}` (after stub redirect is removed). That's intentional public marketing. The authenticated `GET business/profile` must *not* be public; it requires `auth:web|institute_user` and shows only active workspace. The two routes must be namespaced apart (`business.show` slug vs `business.profile` canonical) to avoid confusion.

### 21.7 Branches status values
Branches `status enum(active,inactive)` (not `active|archived` like batches). Render badge with mapped colors; inactive shows warning.

### 21.8 `verified` bool + `status` enum
Profile should badge both: `verified` shows ✅ (`patch-check-fill`) per `business/show:38-42` + layout brand, `status` shows pill (Active/Suspended/...). Already implemented in public view.

---

## 22. CHECKLIST — SPEC §1 AUDIT MANDATE

| Required audit point | Finding | Section |
|---|---|---|
| 1. Existing business identity source | `institutes` table 44 cols, model `Institute.php` | §3 |
| 2. Existing membership/ownership | `institution_user` + `Membership` + `User` + `InstituteUser` dual; `Workspace` resolve | §4 |
| 3. Existing profile fields | All present; gaps are display-only | §5 |
| 4. Existing settings | `institute_settings` 26 cols, notification/ai/sales/purchase JSON | §6 |
| 5. Existing branding | `institutes.logo/cover_photo` + `institute_settings.logo/favicon/theme/colors` | §7 |
| 6. Existing branch info | `branches` TenantScoped | §8 |
| 7. Existing subscription | `institute_subscriptions` + `packages` + `ModuleAccessService` | §9 |
| 8. Existing domain/industry | `InstituteDomain` + `config/industry_rules.php` canonical taxonomy | §10 |
| 9. Existing edit/update UI | `InstituteSettingController` panes; tenant institute row self-edit absent but addable without migration | §11 |
| 10. Existing topbar implementation | `layouts/institute.blade.php:31-51` brand anchor `business.show` stub redirect | §12 |
| 11. Existing routes/controllers/views | `web.php:349` stub + `business/show.blade.php` (standalone) ; no `business/profile` yet | §13 |
| 12. Existing permissions | `settings.manage`, `staff.manage`, etc.; no `business_profile.*` yet | §14 |
| 13. Tenant isolation | `TenantScoped` + `TenantContext` + `SetTenantContext` middleware | §15 |
| 14. IDOR risks | `business/{slug}` stub unscoped; future `business/{id}/profile` would be IDOR if naively implemented | §16 |
| 15. Multi-business behavior | `Workspace.cur active_institution_id` + switch verify + composer | §17 |
| 16. Branch isolation | `Branch` TenantScoped + `BranchContext` | §18 |
| 17. Fields missing from profile UI | 11 display gaps (domain badge, settings block, subscription, summary, favorite etc.) | §19 |
| 18. Whether migration necessary | **NO** | §20 |

---

## 23. IMPLEMENTATION PRESCRIPTION (STOP — Do Not Implement Yet Per Spec)

The audit **stops here**. After approval, implement strictly within these guardrails (already captured as forensic constraints for the implementation phase):

- **Reuse existing data:** `institutes.*`, `institute_settings.*`, `branches`, `institute_subscriptions`, `IndustryRules`, `InstituteDomain`.
- **Single canonical route:** `GET business/profile` (`name business.profile`) under `auth:web,institute_user` + `tenant` + `verified` — never accept user-supplied `institute_id`.
- **Controller resolution:** `Workspace::membership()` / `TenantContext::id()` → `Institute`. For `User` guard use `Membership->institution`; for `InstituteUser` guard use `Institute::find($user->institute_id)`. No `withoutGlobalScopes` on Institute for traversal; no `request->input('institute_id')`.
- **Topbar click:** change `layouts/institute.blade.php:32` `href="{{ route('business.show', $institute->slug) }}"` → `href="{{ route('business.profile') }}"` (keep slug page or alias it separately).
- **Branched list:** `$institute->branches` or `Branch::query()->get()` under TenantAware — no IDOR param.
- **Subscription block:** gated by `$membership->hasPermission('settings.manage')` or visible to owner; mask sensitive cols.
- **Edit:** reuse `settings.index` or add `BusinessProfileController@update` guarded by `permission:settings.manage`, reusing the `Institute` boot domain guard.
- **UI style:** replicate `business/show.blade.php` card rhythm + `settings/index` tab cadence inside `layouts.institute` (not standalone). Responsive grid: header + two-column info grid + branches + subscription/summary.
- **Empty handling:** any nullable column renders "Not provided" (project equivalent is empty-state muted text in `business/show` — keep).
- **Security invariants §7 (15):** enforced by TenantScoped + Workspace::verify + RBAC split + InstituteDomain authoritative single.
- **Zero migrations:** do not create `business_profiles` or add columns. If a new permission is seeded, do it as data seeding inside existing role seeder, not as dedicated business identity migration.
- **Keep historical integrity:** no delete/modify of `academic_assessments` etc. via profile page.
- After implementation produce `PHASE_B6_BUSINESS_PROFILE_IMPLEMENTATION_REPORT.md` with files changed/created, routes, tenant/IDOR tests (20 cases per spec), rollback steps, data modified counts (0).

---

## 24. RISK REGISTER IF AUDIT IGNORED

| Risk | If ignored |
|---|---|
| Duplicate `businesses` table | Dual-write bugs, FK drift from `institutes`, ETL needed |
| Accept `institute_id` from URL without verify | Cross-business IDOR, tenant leak between co-owned businesses |
| Global `Institute::find($id)` without `Workspace::verify` | Enumerate any institute slug/cross-tenant read |
| Unscoped `Branch::all()` | Branch of other tenant rendered |
| Hard-coding `dance_academy` branch | Breaks school/retail/transport |
| Creating separate `domain` column | Forks from `InstituteDomain`, taxonomy drift |
| Allowing `industry` edit from UI when `hasDomainData()=true` | Breaks academic placements/results integrity (validated exception bypassed) |
| Rendering `smtp_password_enc` etc. | Credential leak |
| Removing topbar slug redirect without alternative | Bookmarks dead, no public profile |

---

## 25. FORENSIC VERDICT

| Dimension | Status |
|---|---|
| Business identity source found (existing) | ✅ Clear — `institutes` |
| Membership/ownership found | ✅ Clear — `institution_user` + `Workspace` |
| All spec fields already stored | ✅ Yes — no missing column, use "Not provided" for currency/date_format |
| Branding + branches + subscription + domain found | ✅ Clear (§7-10) |
| Existing profile UI reusable | ✅ `business/show.blade.php` as visual reference |
| Topbar integration point located | ✅ `layouts/institute.blade.php:31-51` + `web.php:349` stub |
| Tenant isolation verified | ✅ `TenantScoped` + `SetTenantContext` + `Workspace` |
| IDOR vectors identified & mitigatable | ✅ via `Workspace::verify` pattern |
| Migration required? | ❌ **NO** |
| Ready to implement after approval | ✅ **Go — subject to spec constraints** |

**Overall audit result:** PASS. No data modification performed, no migration created, existing architecture preserved. Await approval to proceed to Steps 2-9.

---

*Evidence produced by read-only inspection only. All `SHOW COLUMNS` outputs captured from live `monetix` MySQL on 2026-08-28. File/line citations are verbatim. Reproduction: `php C:\Users\Fast\AppData\Local\Temp\opencode\dump_cols.php`.*
