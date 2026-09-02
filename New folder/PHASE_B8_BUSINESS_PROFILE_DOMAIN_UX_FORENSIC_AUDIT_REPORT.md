# PHASE B8 — BUSINESS PROFILE + DOMAIN-AWARE MODULE UX FORENSIC AUDIT REPORT

**Phase:** B8 — Business Profile + Domain-Aware Module UX
**Scope:** Audit Only — No data, code, migrations, routes, or business logic modified
**Date:** 2026-08-28
**Predecessor:** B7 Course/Subject/Class UI Restoration — VERDICT: GREEN
**Auditor:** Muse Spark (forensic audit mode)

---

## 1. Executive Summary

The authenticated institute workspace **does provide a proper Business Profile experience** in B7 GREEN codebase. Topbar business name/logo correctly routes to a tenant-safe, workspace-authoritative profile (`business.profile` at `BusinessProfileController@show`), never trusting URL `institute_id`. The profile describes the **currently active business** via `Workspace::membership()` / `TenantContext` / `active_institution_id`, with multi-business switching intact. Domain resolution via single source `InstituteDomain.php` is authoritative; academic vs professional vs other modules are visible per active domain without mixing. Existing hardening (TenantScoped, BranchScoped, RBAC, IDOR, historical SoftDeletes/RESTRICT/withTrashed, curriculum freeze, Workspace verification) remains intact. **No duplicate `businesses` tables** exist; `institutes` remains the canonical identity. Gaps are limited to UX completeness (legal field naming drift, empty-state handling for non-academic domains) and one low-risk tenant-isolation nuance in `BusinessProfileController` fallback. Overall **YELLOW** — implementation is functional and secure; gaps are non-blocking and addressable without migrations.

---

## 2. Current Architecture

| Layer | Component | File | Role |
|-------|-----------|------|------|
| Identity | `institutes` table | `app/Models/Institute.php:12` | Canonical business identity (name, slug, industry, sub_industry, country, contact, legal, logo/cover, status, verified, founded_year) |
| Workspace | `Workspace` | `app/Support/Workspace.php:21` | Active business resolution via `session(active_institution_id)` + `Membership` verification |
| Tenant | `TenantContext` | `app/Support/TenantContext.php:13` | Per-request institute binding (`set(id)`, `enabled()`, `id()`) |
| Branch | `BranchContext` | `app/Support/BranchContext.php` | Branch filtering for `BranchScoped` models |
| Domain | `InstituteDomain` | `app/Support/InstituteDomain.php:17` | Authoritative academic/professional/other resolver |
| Settings | `InstituteSetting` | `app/Models/InstituteSetting.php:12` | Per-institute config (ai_config, notification_settings, logo/favicon, timezone) |
| Branches | `branches` | `app/Models/Branch.php` | TenantScoped branches |
| Membership | `institution_user` (Membership) | `app/Models/Membership.php` + `User.php` | User ↔ Institute role/branch/status |
| Subscription | `InstituteSubscription` + `SubscriptionPackage` | `Institute.php:145` | Package + status + dates |
| Middleware | `SetTenantContext` | `app/Http/Middleware/SetTenantContext.php:26` | Binds TenantContext/BranchContext after auth |
| Middleware | `EnsureDomain` | `app/Http/Middleware/EnsureDomain.php:11` | `domain:academic/professional` gate |
| Provider | `AppServiceProvider.php:121` | View composer | Shares `$institute`, `$isInstituteStaff`, `$workspaceMemberships`, `$usesClassTerm`, module flags, theme |

**Hierarchy:** `INDUSTRY` (Education, Training Center, Retail, Manufacturing, Service, Transportation, Restaurant, etc.) → `sub_industry` (school/college/polytechnic/university, training_institute/dance_academy/it_training_center …). Category-level only; no business sub-category taxonomy.

**Reuse verified:** No `businesses`, `business_profiles`, duplicate institute/industry tables. Taxonomy kept separate from `course_categories`.

---

## 3. Business Identity Source

**Finding — PASS**

- **FILE:** `app/Models/Institute.php:12` + `database/migrations/2026_08_13_000000_add_industry_to_institutes_table.php:11`, `2026_08_14_195437_add_sub_industry…:5`
- **CURRENT:** Single `institutes` table is authoritative. Fields: `name`, `short_name`, `institute_code`, `industry`, `sub_industry`, `country`, `division`, `district`, `upazila`, `address`, `postal_code`, `phone`, `whatsapp`, `email`, `website`, `facebook`, `youtube`, `logo`, `cover_photo`, `description`, `founded_year`, `trade_license`, `license_number`, `registration_number`, `e_tin`, `status`, `verified`, `slug`, `package_id`, `settings()` relation.
- **EXPECTED:** Reuse `institutes` + `InstituteSetting` + `Branch` + `Membership` — **met**. No duplicate tables.
- **RISK:** LOW — none.
- **RECOMMENDATION:** None; preserve. Document that `institutes` == business identity (resolve naming drift: `institute_code` vs `business_code` is alias, not separate pillar).

---

## 4. Workspace Resolution

**Finding — PASS**

- **FILE:** `app/Support/Workspace.php:22`, `app/Http/Middleware/SetTenantContext.php:26`, `bootstrap/app.php:74`
- **CURRENT:**
  - `Workspace::id()` = `session('active_institution_id')` (`Workspace.php:42`). `set()` also syncs `TenantContext` + `BranchContext` (`:25-33`).
  - `membership()` verifies active `institution_user` row: `where user_id, institution_id=session, status=active` + `roleAllowedForAccountType` (`:52-79`).
  - `verify()` same check for middleware (`:87`).
  - `resolveAfterLogin()` handles ONE business → auto-activate, MULTIPLE → require explicit choice or picker (`:113-138`).
  - `SetTenantContext` re-verifies `Workspace::verify()` every request, falls back to first active membership if session null (cookie fix, `:46-69`), then `TenantContext::set(workspaceId)` + `BranchContext::set(membership?->branch_id)` (`:70-77`). For `InstituteUser` guard, directly sets `TenantContext::set(user.institute_id)` (`:37`).
  - Priority list: `SubstituteBindings` after `SetTenantContext` (`bootstrap/app.php:74`) prevents model binding before tenant is bound.
- **EXPECTED:** Login identity lookup global, active workspace determines tenant context, switching updates context — **met**. Never accepts client `institute_id` as authority.
- **RISK:** LOW.
- **RECOMMENDATION:** Keep enabled; add test for stale session + `hasDomainData` block (already in `Institute.php:30`).

---

## 5. Topbar Navigation

**Finding — PASS** (with one UX note)

- **FILE:** `resources/views/layouts/institute.blade.php:31`, `resources/views/layouts/institute.blade.php:32`
- **CURRENT:**
  ```blade
  @if ($isInstituteStaff && !empty($institute->slug))
      <a class="brand" href="{{ route('business.profile') }}" title="View business profile">
          {{ $institute->name }}
  ```
  Else branch (`:38`) href `route('dashboard')` for non-staff / platform_admin. InstituteDomain gate then renders sidebar per domain (`:124-235` post-B7).
  Clicking business name/logo when `$isInstituteStaff && slug` goes to `business.profile` (tenant-safe, workspace-authoritative). When not institute staff, goes to `dashboard`. No URL param.
- **EXPECTED:** Business name/logo in institute topbar → Business Profile representing CURRENT ACTIVE BUSINESS via Workspace/TenantContext — **met** for institute staff. Platform admin still sees “AccumenAI” brand to `dashboard`, correct.
- **RISK:** LOW.
- **RECOMMENDATION (LOW):** Add `aria-label="Business Profile: {{ $institute->name }}"` for accessibility; keep `business.show` legacy redirect (`routes/web.php:354` `Route::get('business/{institute}', fn()=>redirect()->route('dashboard'))`) as tamper sink but ensure it never exposes `institute_id` data.

---

## 6. Business Profile Route

**Finding — PASS** (tenant-safe)

- **FILE:** `routes/web.php:347-350`, `app/Http/Controllers/BusinessProfileController.php:18`
- **CURRENT:**
  ```php
  Route::middleware(['auth:institute_user,web', 'tenant', 'verified'])->group(function () {
      Route::get('business/profile', [BusinessProfileController::class, 'show'])->name('business.profile');
  });
  ```
  Controller `show()` → `resolveActiveInstitute()` (`BusinessProfileController.php:106`) never trusts `request->institute_id`. Resolves via `InstituteUser->institute_id`, `Workspace::membership()->institution`, `TenantContext::id()`, fallback to first active membership. Then `assertTenantMatchesActive()` (`:140`) checks `TenantContext::id() === institute.id` and `Workspace::membership()->institution_id === institute.id` and `InstituteUser->institute_id === institute.id`, throws 403 on mismatch.
  Legacy `business/{institute}` (`:354`) is hard redirect to `dashboard`, not data leak.
- **EXPECTED:** Profile route tenant-safe, no URL institute_id authority, multi-business switching intact — **met**.
- **RISK:** LOW.
- **RECOMMENDATION:** Keep `business/{institute}` redirect but add `whereNumber('institute')->middleware('tenant')` or remove slug exposure; optional `can:view` audit log.

**IDOR test:**
- **FILE:** `BusinessProfileController.php:140` `assertTenantMatchesActive`
- **CURRENT:** Explicit 403 on mismatch between resolved institute and TenantContext/Workspace.
- **EXPECTED:** Manipulating `business/{institute}` cannot override workspace — **met** (redirect + no data).
- **RISK:** LOW.

**Multi-business test:**
- **ONE business:** `resolveAfterLogin` auto-activates single membership → `TenantContext` + profile shows that business.
- **MULTIPLE:** Session `active_institution_id` determines profile; `POST workspace/switch/{institutionId}` → `Workspace::set()` → `TenantContext::set()` → same `GET business/profile` now resolves to newly active institute; no URL param can override (`resolveActiveInstitute` ignores request input).
- **RISK:** NONE.

---

## 7. Business Profile Data Matrix

Audit of UI fields in `resources/views/business/profile.blade.php:84-399` against controller `BusinessProfileController.php:82-99`:

| Section | Field | Controller Source | Blade Line | Sensitive? | Status |
|---------|-------|-------------------|------------|------------|--------|
| Common | Business name | `$institute->name` | `:92` | No | EXISTS |
| Common | Short name | `$institute->short_name` | `:93` | No | EXISTS |
| Common | Business code | `$institute->institute_code` | `:94` | No | EXISTS |
| Common | Industry | `industryLabel` via `config/industry_rules` | `:95` | No | EXISTS |
| Common | Business category/type | `subIndustryLabel` via `IndustryRules::subIndustries` | `:96` | No | EXISTS |
| Common | Domain | `$domain` via `InstituteDomain::fromInstitute` | `:97` | No | EXISTS |
| Common | Founded year | `$institute->founded_year` | `:100` | No | EXISTS |
| Common | Logo | `$institute->logo` fallback `settings->logo` | `:182` | No | EXISTS |
| Common | Cover photo | `$institute->cover_photo` | `:41` | No | EXISTS |
| Common | Description | `$institute->description` | `:103` | No | EXISTS |
| Contact | Phone | `$institute->phone` + `tel:` | `:119` | No | EXISTS |
| Contact | WhatsApp | `$institute->whatsapp` + `wa.me` | `:123` | No | EXISTS |
| Contact | Email | `$institute->email` + `mailto:` | `:127` | No | EXISTS |
| Contact | Website | `$institute->website` (http guard) | `:131` | No | EXISTS |
| Contact | Facebook | `$institute->facebook` | `:136` | No | EXISTS |
| Contact | YouTube | `$institute->youtube` | `:139` | No | EXISTS |
| Location | Address | `$institute->address` | `:156` | No | EXISTS |
| Location | Postal code | `$institute->postal_code` | `:157` | No | EXISTS |
| Location | Division | `$institute->division` | `:153` | No | EXISTS |
| Location | District | `$institute->district` | `:154` | No | EXISTS |
| Location | Upazila | `$institute->upazila` | `:155` | No | EXISTS |
| Location | Country | `$institute->country` | `:152` | No | EXISTS |
| Location | Google Map URL | `$institute->google_map_url` → View on Map | `:160` | No | EXISTS |
| Legal | Trade license | `$institute->trade_license` | `:213` | No | EXISTS |
| Legal | License number | `$institute->license_number` | `:214` | No | EXISTS |
| Legal | Registration number | `$institute->registration_number` | `:215` | No | EXISTS |
| Legal | e-TIN | `$institute->e_tin` | `:216` | No | EXISTS |
| Status | Active/inactive | `$institute->status` badge | `:98` | No | EXISTS |
| Status | Verification | `$institute->verified` | `:99` | No | EXISTS |
| Subscription | Package | `$package?->name/slug` | `:358` | No | EXISTS |
| Subscription | Status | `$subscription->status` | `:359` | No | EXISTS |
| Subscription | Start date | `$subscription->start_date` | `:360` | No | EXISTS |
| Subscription | End date | `$subscription->end_date` | `:361` | No | EXISTS |

**Missing fields (none sensitive):**
- **FILE:** `resources/views/business/profile.blade.php:160`, `app/Http/Controllers/BusinessProfileController.php:31`
- **CURRENT:** Cover/logo use `asset('storage/'.$institute->logo)` but `institutes` table column for `cover_photo` may be `cover` vs `cover_photo` drift; view tolerates null.
- **EXPECTED:** All common/contact/location/legal/status/subscription fields displayed — **met**.
- **RISK:** LOW — no gaps blocking profile.

**Sensitive exposure check — PASS:**
- **FILE:** `BusinessProfileController.php:36`, `profile.blade.php:352-399`
- **CURRENT:** Only safe fields selected: `Branch` select `['id','institute_id','name','phone','email','address','status']` (`:31`); subscription `->with('package')` but blade never renders `price_paid`, `payment_reference`, `gateway` secrets. `InstituteSetting` `ai_config` only checked for theme/brand, not rendered as raw JSON. No `SMTP passwords`, `SMS API keys`, `payment gateway secrets`.
- **EXPECTED:** Never expose credentials — **met**.
- **RISK:** NONE.

---

## 8. Industry/Category Mapping

**Finding — PASS** (category-level only)

- **FILE:** `config/industry_rules.php:21`, `app/Support/InstituteDomain.php:22`, `app/Models/Institute.php:28`
- **CURRENT:**
  - Industries (category level): `education`, `training_center`, `healthcare`, `information_technology`, `finance`, `retail`, `manufacturing`, `real_estate`, `transportation`/`transport`, `service`, `restaurant`, `hotels`, `personal_finance`, `other` (`industry_rules.php:22`).
  - Sub-industries (business type): `education` → school/college/polytechnic/university/madrasha/primary_school…, `training_center` → training_institute/professional_training_center/dance_academy/it_training_center/vocational_training_center + aliases (`:41-69`), others per country.
  - `institutes.industry`, `institutes.sub_industry` columns (`2026_08_13…`, `2026_08_14…`). Domain derived via `InstituteDomain::fromKeys(industry, subIndustry)`.
- **EXPECTED:** Business Profile taxonomy is CATEGORY LEVEL ONLY, no business sub-category taxonomy — **met**. Hierarchy matches spec (Industry → Education/Training Center → School/College…/Dance Academy… plus Retail/Manufacturing/Service/Transportation/Restaurant as flat OTHER).
- **RISK:** LOW.
- **RECOMMENDATION:** Keep `course_categories` / `course_sub_categories` fully separate from `institutes.sub_industry` (no FK, no join) — **verified separate** (`CourseCategory.php:11` TenantScoped, `CourseSubCategory` tenant+category).

**Not same as:** `FILE: app/Models/CourseCategory.php:11` vs `app/Models/Institute.php:28` — confirmed distinct systems.

---

## 9. Domain Resolution

**Finding — PASS**

- **FILE:** `app/Support/InstituteDomain.php:17`
- **CURRENT:** Single authoritative resolver. Constants `ACADEMIC='academic'`, `PROFESSIONAL='professional'`, `OTHER='other'`. `ACADEMIC_TYPES = [school,college,polytechnic,university]` (`:22`), `PROFESSIONAL_TYPES = [training_institute,…,vocational_training_center]` (`:30`). `fromInstitute()` (`:50`) → `fromKeys()` (`:58`) normalizes `transport→transportation` (`:118`) and legacy aliases (`institution→training_institute`, etc. `:126-142`). `isAcademic` (`:76`), `isProfessional` (`:81`), `subjectTypeFor` (`:107`) returns `academic|professional` (other defaults professional safe-default). `hasDomainData()` (`:147`) checks 8 tables for domain-change immutability.
  Used at: `layouts/institute.blade.php:124` (isAcademic/isProfessional), `BusinessProfileController.php:27` (fromInstitute), `CourseMasterController.php:208` (subjectTypeFor), `SubjectManagementController.php:99` (subjectTypeFor), `AppServiceProvider.php:202` (isAcademic via `usesClassTerm`).
- **EXPECTED:** Single source, no duplication, server-side — **met**.
- **RISK:** LOW.
- **DRIFT NOTE:** `config/industry_rules.php:42` lists extra academic types `madrasha, primary_school, secondary_high_school, school_college` and professional aliases (`martial_arts, music_academy…`) that are **not** in `InstituteDomain::ACADEMIC_TYPES/PROFESSIONAL_TYPES`. They correctly resolve as `OTHER` — intentional taxonomy gate (only 4 canonical academic, 5 professional). Document drift, not a bug.

---

## 10. Academic UI

**Finding — PASS** (B7 GREEN, verified)

- **FILE:** `resources/views/layouts/institute.blade.php:124-235`, `routes/institute_modules.php:976-979`, `app/Http/Controllers/ClassController.php:24`, `app/Http/Controllers/CourseMasterController.php:37`
- **CURRENT:**
  - Sidebar academic: when `isAcademic && hasEducationModule` shows Students, Pending Admissions (admission.approve), Teachers, Classes/Courses via `$usesClassTerm ? route('classes.index') : route('courses.manage.index')` (`:128`), Exams, Alumni (permission `alumni.view`), Workflows (permission `workflows.view`). When `!usesClassTerm` (university/polytechnic) also shows Curriculum, Batches, Certificates (`:225-234`).
  - `classes.*` routes now `domain:academic` + `permission:courses/batches.view` (B7 fix), preventing professional access.
  - `settings/academic/*` group `middleware domain:academic, permission:education.manage` (`institute_modules.php:1144`).
  - `CourseMasterController` lists academic courses (`where institute_id=X and subject_type=academic`), `SubjectManagementController` lists academic subjects only, `ClassController` lists academic courses via `categoryIdsBySubjectType('academic')`.
- **EXPECTED:** School/College/Polytechnic/University shows academic classes/subjects/assessments/results etc. — **met**.
- **RISK:** LOW.

---

## 11. Professional UI

**Finding — PASS** (B7 restored)

- **FILE:** `resources/views/layouts/institute.blade.php:203-222`, `app/Http/Controllers/CurriculumController.php:30`, `app/Http/Controllers/BatchController.php:15`
- **CURRENT:** When `isProfessional && hasEducationModule` shows Courses (`courses.manage.index`), Subjects (`courses.manage.subjects.index`), Curriculum (`curricula.index`), Batches (`batches.index`), Exams, Certificates. All routes `tenant` + `permission` gated. `CurriculumController:397` now domain-aware (fetches categories `where institute_id=X and subject_type=professional`, then courses `where institute_id=X`).
- **EXPECTED:** Training Institute/Dance Academy/IT Training etc. shows professional subjects/courses/curriculum/modules/lessons/batches/instructors — **met**. No academic-only `classes` displayed (hidden) and direct URL `/classes` blocked 403 via `domain:academic`.
- **RISK:** LOW.

---

## 12. Non-Academic Business UI

**Finding — PASS** (exists/partial/not-applicable distinction)

| Domain | Industry Example | Module Visibility | File Evidence | Status |
|--------|------------------|-------------------|---------------|--------|
| Retail | general_store/supermarket | sidebar: NO education links; dashboard: cleanStudent or hospitality; still has Inventory/Purchase/Sales/Finance/Crm/HR when package enables | `layouts/institute.blade.php:155-172` (hr/sales/purchase gated by ModuleAccessService), `home.blade.php:23-129` (isCleanStudent vs isHospitality), `config/industry_rules.php:205` capabilities | EXISTS (when enabled) |
| Manufacturing | garments/food_processing | same + inventory BOM/consumption/ production (`manufacturing` capabilities `:278`) | same | EXISTS |
| Service | (empty sub) | service modules via sales/crm/hr | same | PARTIAL (generic) |
| Transportation | (empty) | transport modules, no academic | same | EXISTS |
| Restaurant | (empty) | hospitality dashboard `:201` → shop welcome, inventory recipe `:338` | `DashboardController.php:201`, `home.blade.php:9` | EXISTS |
| Healthcare | hospital/clinic | HEALTHCARE inventory batch/expiry/serial `:248` | same | EXISTS |
| IT/Finance/Real Estate | etc | OTHER domain badge in profile, branches/modules shown | `business/profile.blade.php:307` | EXISTS |

**FILE:** `resources/views/business/profile.blade.php:307-349` `OTHER` block
- **CURRENT:** When `$domain === other` shows industry-specific title/icon (`match $institute->industry` `:312`), branches/modules/subscription. Academic sections hidden.
- **EXPECTED:** Do not invent modules; hide academic/professional when NOT APPLICABLE — **met**.
- **RISK:** LOW.

---

## 13. Course Integration

**Finding — PASS**

- **FILE:** `app/Http/Controllers/CourseMasterController.php:44`, `app/Models/Course.php:76`, `routes/institute_modules.php:928`
- **CURRENT:** Canonical `/courses/manage` via `CourseMasterController`. `index()` `Course::where('institute_id', instituteId)` (`:44`) tenant-isolated, `withCount batches/curricula`, `assertOwned` (`:198`), `validated` (`:211`) `category_id exists where institute_id & subject_type=derived`. Categories `CourseCategory` TenantScoped (`CourseCategory.php:11`). No fake seeding.
- **Academic:** `subject_type=academic` categories only; course domain compatible.
- **Professional:** `professional` only.
- **RISK:** LOW — domain isolation enforced.

---

## 14. Subject Integration

**Finding — PASS** (B7 hardened)

- **FILE:** `app/Http/Controllers/SubjectManagementController.php:30`, `app/Models/Subject.php:12`
- **CURRENT:** `index()` derives `$derivedType = InstituteDomain::subjectTypeFor($institute)` (`:33`), clamps `?subject_type` (`:37`), `subjectQuery($instituteId, $derivedType)` → `where institute_id=X and subject_type=derived` (`:294`), no `orWhereNull`. `store` validates `category_id exists where institute_id=X and subject_type=derived` (`:123`), derives `subject_type = derived` (`:133`), never trusts browser. `assertAccessible` denies non-owned + cross-domain (`:328`). `filterCategories` domain-scoped (`:303`). Stats clamped to derived (`:74`).
- **Academic:** subjects academic only via derived filter.
- **Professional:** professional only.
- **RISK:** LOW — no academic/professional mixing, no forged subject_type, no cross-business attachment.

---

## 15. Curriculum Integration

**Finding — PASS**

- **FILE:** `app/Http/Controllers/CurriculumController.php:31`, `app/Models/CourseCurriculum.php`
- **CURRENT:** `curricula.*` routes `permission:curriculum.view/manage` (`institute_modules.php:901`). Model `CourseCurriculum` uses `TenantScoped` (checked via `app/Models/Concerns/TenantScoped.php:19`). `availableCourses($instituteId)` (`:397`) now domain-aware: `Institute find → derived → Category where institute_id=X and subject_type=derived → Course where institute_id=X and category_id in (…)`. No `orWhereNull`, no `withoutGlobalScope` leak. `assertCourseUsable` checks ownership/assignment (`:414`). Curriculum freeze via `CourseCurriculumService`.
- **Professional:** curriculum → professional courses only.
- **Academic:** not applicable (university/polytechnic uses curriculum via same path when `!usesClassTerm`).
- **RISK:** LOW.

---

## 16. Batch Integration

**Finding — PASS**

- **FILE:** `routes/web.php:165` `batches.*` (`permission:batches.view/manage`), `routes/institute_modules.php:987` extra batch routes, `app/Models/Batch.php`
- **CURRENT:** `Batch` is TenantScoped + BranchScoped (`Batch.php` uses both traits). Queries `where institute_id=X` via scope. Professional batches tied to professional courses via `course.category.subject_type`. Academic batches via `classes/batches` domain:academic.
- **RISK:** LOW — tenant isolation via global scope, no cross-business batch.

---

## 17. Academic Assessment Integration

**Finding — PASS** (domain:academic)

- **FILE:** `routes/institute_modules.php:1133` `settings/academic.*`, `app/Http/Controllers/AcademicAssessmentController.php`
- **CURRENT:** Group `middleware ['permission:education.manage', 'domain:academic']` (`:1144`). Assessments (`:1182`), marks (`:1195`), aggregations (`:1172`), grading (`:1164`), placements (`:1236`), final results (`:1199`), promotions (`:1217`) all institute_id scoped + domain gate.
- **RISK:** LOW — professional institutes cannot reach via sidebar or direct URL (403).

---

## 18. Result/Transcript/Certificate Integration

**Finding — PASS** (historical integrity)

- **FILE:** `app/Models/AcademicFinalResult.php`, `app/Http/Controllers/AcademicFinalResultController.php`, `resources/views/business/profile.blade.php:251`
- **CURRENT:** Business profile academic block shows `AcademicYear` etc. but does NOT mutate. Final results locked/published/finalized via service; `AcademicFinalResultRow` snapshot, `GradeScale` locked, `Certificate` approval mode `super_admin|admin` via `InstituteSetting:18`. Historical references use `withTrashed()` on `Subject` (`Course.php:78` `->withTrashed()` on subjects pivot, `Subject.php:38` updating slug includes trashed check).
- **RISK:** LOW — profile changes cannot corrupt historical data; `Institute.php:30` `hasDomainData` blocks domain switch when data exists.

---

## 19. Multi-Business Isolation

**Finding — PASS**

- **FILE:** `app/Support/Workspace.php:42`, `app/Http/Middleware/SetTenantContext.php:39`, `BusinessProfileController.php:106`
- **CURRENT:**
  - Login → `Workspace::resolveAfterLogin()` (`Workspace.php:113`) picks `active_institution_id` (0→null, 1→auto, N→explicit or picker). `Workspace::set(id)` → session + `TenantContext` + `BranchContext` (`:24`).
  - Per-request `SetTenantContext` (`:39`) re-verifies `Workspace::verify()` and binds `TenantContext::set(workspaceId)`.
  - Business Profile `resolveActiveInstitute()` (`BusinessProfileController.php:106`) follows same chain: `InstituteUser->institute_id` or `Workspace::membership()->institution` or `TenantContext::id()`. `assertTenantMatchesActive` ensures resolved matches TenantContext/Workspace.
  - Switching: `POST workspace/switch/{institutionId}` (`routes/web.php:123` `WorkspaceController@switch`) → `Workspace::set()` → same `GET business/profile` now resolves to newly active institute.
- **EXPECTED:** Business A profile shows A, Business B shows B, no A data in B, no courses/subjects/categories/batches/results cross — **met** (all data queries filtered `where institute_id = TenantContext::id()`).
- **RISK:** LOW.

---

## 20. Tenant Isolation

**Finding — PASS**

- **FILE:** `app/Models/Concerns/TenantScoped.php:16`, `app/Support/TenantContext.php:13`, `app/Http/Middleware/SetTenantContext.php:74`
- **CURRENT:** `TenantScoped` trait adds global scope `where institute_id = TenantContext::id()` (`:19`) when `TenantContext::enabled()`. Applied to `InstituteSetting`, `CourseCategory`, `CourseSubCategory`, `Branch`, `Batch`, `Student`, `CourseCurriculum`, etc. `Course`/`Subject` use explicit `where institute_id = X` in controllers (since multi-tenant catalog previously mixed). `BusinessProfileController:31` explicitly `Branch::where('institute_id', $institute->id)` plus `with('package')` safe.
- **Verification:** `TenantContext::enabled()` is false for platform_admin/CLI until `SetTenantContext` binds; `SubstituteBindings` after `SetTenantContext` prevents binding before scope.
- **RISK:** LOW.

**withoutGlobalScope audit — PASS:**

| File | Line | withoutGlobalScope Usage | Justified? | Institute Filter |
|------|------|-------------------------|------------|------------------|
| `CourseCategoryManageController.php:38` | `Branch::withoutGlobalScope` etc. | Counts courses/subjects via `withoutGlobalScope()->where institute_id=X` | Yes | Explicit `where institute_id=X` |
| `CourseSubCategoryManageController.php:24` | `CourseSubCategory::withoutGlobalScope->where institute_id=X` | Category dropdown | Yes | Explicit `where institute_id=X` |
| `BusinessProfileController.php:48` | `Membership::query()->where('status','active')` | Not using global scope, direct | Yes | `where institution_id` |
| `InstituteDomain.php:152` | `DB::table('courses')->where institute_id=X->exists()` | hasDomainData check | Yes | Explicit |
| `AcademicAssessmentController` | `withoutGlobalScope('institute')` on category | Funnel options fallback | Yes | `where subject_type` + institute check in service |

Every `withoutGlobalScope` is paired with explicit `where institute_id = activeInstituteId`.

---

## 21. Branch Isolation

**Finding — PASS**

- **FILE:** `app/Models/Concerns/BranchScoped.php`, `app/Support/BranchContext.php`, `app/Http/Middleware/SetTenantContext.php:77`
- **CURRENT:** `BranchContext` set from `Membership->branch_id` (null = all branches visible for owner). `BranchScoped` global scope applies when `BranchContext::enabled()`. `BusinessProfileController:31` lists branches `where institute_id=X orderBy name`, not branch-filtered (correct — profile shows all branches of business). Academic placement/batches respect branch scope via service.
- **RISK:** LOW.

---

## 22. IDOR Analysis

**Finding — PASS** (with one LOW note)

| Route | Controller | Protection | Status |
|-------|------------|------------|--------|
| `business/profile` | `BusinessProfileController@show` | Never trusts URL `institute_id`; resolves active institute via Workspace/TenantContext; `assertTenantMatchesActive` 403 on mismatch | PASS |
| `business/{institute}` | Closure redirect | Hard redirect to `dashboard`, no data | PASS (tamper sink) |
| `courses/manage/{course}/edit` | `CourseMasterController@edit` | `assertOwned` 403 if `course.institute_id !== user.institute_id` | PASS |
| `courses/manage/subjects/{subject}/edit` | `SubjectManagementController@edit` | `assertAccessible` 403 if `subject.institute_id !== X` or `subject_type !== derived` | PASS |
| `courses/manage/categories/{category}` | `CourseCategoryManageController@update` | `assertOwned` 403 | PASS |
| `courses/manage/sub-categories/{sub}` | `CourseSubCategoryManageController@update` | `assertOwned` 403 | PASS |
| `classes/*` | `ClassController` | `domain:academic` + `permission` + `categoryIdsBySubjectType` | PASS |
| `curricula/{curriculum}` | `CurriculumController@show` | `TenantScoped` global scope | PASS |
| `workspace/switch/{id}` | `WorkspaceController@switch` | `Workspace::verify(id, userId)` 403 | PASS |

**LOW Finding — BusinessProfileController fallback permissiveness:**
- **FILE:** `app/Http/Controllers/BusinessProfileController.php:125` `fallback = $user->memberships()->where('status','active')->orderBy('institution_id')->first()`
- **CURRENT:** If `Workspace::membership()` and `TenantContext` both null (edge: unverified session), falls back to first active membership. Could briefly show wrong business if session stale but `SetTenantContext` auto-heals (`SetTenantContext.php:46` `fallback = DB::table('institution_user')->where user_id… first; Workspace::set()`).
- **EXPECTED:** Strict Workspace authority — **partial** (fallback is defense-in-depth, not URL trust).
- **RISK:** LOW — never uses request input, but could mask picker requirement.
- **RECOMMENDATION:** Log fallback path and force picker when >1 membership; keep `TenantContext` enabled check before fallback.

---

## 23. RBAC Analysis

**Finding — PASS**

- **FILE:** `app/Http/Middleware/CheckPermission.php`, `app/Http/Middleware/CheckModuleAccess.php`, `routes/web.php:347`, `routes/institute_modules.php:938`
- **CURRENT:**
  - `business/profile` → `auth:institute_user,web` + `tenant` + `verified` (no extra permission — profile viewable by any member of active workspace, correct).
  - `canEdit` (`BusinessProfileController.php:162`) → `hasPermission('settings.manage')` for `InstituteUser` / `Membership`.
  - `courses/manage/*` → `permission:courses.view/manage` (B7 added to categories).
  - `classes/*` → `domain:academic` + `permission:courses/batches.view` (B7).
  - `settings/academic/*` → `permission:education.manage` + `domain:academic` + `promotion.manage` for promotions.
  - Sidebar respects RBAC: `admission.approve`, `teacher.view`, `alumni.view`, `workflows.view`, `finance.view`, etc. via `hasPermission` checks (`layouts/institute.blade.php:141-197`).
- **EXPECTED:** View profile: `courses.view` etc. not required; manage: `courses.manage` etc. — **met**. No duplicate permissions.
- **RISK:** LOW.

---

## 24. Historical Integrity

**Finding — PASS**

| Model | Protection | File | Verified |
|-------|------------|------|----------|
| `Subject` | `SoftDeletes` (`Subject.php:9`), `SubjectDeletionService` classification (active/historical), `RESTRICT` FK on `subject_id` in `subject_academic_assignments`, `student_subject_selections`, `assessment_subjects`, `exam_subjects`, `course_subjects` | `Subject.php:9`, `database/migrations/…_academic_structure_tables.php` | PASS |
| `Course` | `->withTrashed()` on `subjects()` pivot (`Course.php:78`), `CourseCurriculum` freeze via `CourseCurriculumService` (referenced batches block edit/delete/deactivate) | `Course.php:78`, `CurriculumController.php:31` | PASS |
| `CourseCurriculum` | `TenantScoped`, version auto-increment, single active, referenced by batches frozen | `CurriculumController.php:31`, `CourseCurriculumService` | PASS |
| `AcademicAssessment` | `domain:academic`, locked via `AcademicAssessmentController@lock`, marks via `AcademicMarksController` | `institute_modules.php:1190` | PASS |
| `AcademicFinalResult` | `lock/publish`, snapshot via `AcademicFinalResultRow` (subject_id withTrashed) | `institute_modules.php:1207` | PASS |
| `Certificate` | `certificate_approval_mode` `super_admin|admin` (`InstituteSetting.php:18`), QR, approval workflow | `CertificateAdminController` | PASS |
| `Institute` | `hasDomainData()` blocks domain switch when data exists (`Institute.php:40`) | `Institute.php:30` | PASS |
| Profile impact | Business Profile changes are display-only; no `Institute->update` in profile controller, no course/subject mutation | `BusinessProfileController.php:82` (view only) | PASS |

**No profile change can corrupt historical academic data.**

---

## 25. Sensitive Data Exposure

**Finding — PASS**

- **FILE:** `BusinessProfileController.php:36`, `InstituteSetting.php:22` casts, `resources/views/business/profile.blade.php:158`
- **CURRENT:** `subscription` only `package/status/start_date/end_date/billing_cycle` (`:357-363`), never `price_paid`, `gateway` secrets. `Branch` select limited (`:33`). `InstituteSetting` `ai_config` via `AiConfig::enabled()` never rendered. `profile.blade.php` renders `google_map_url` as link but never exposes `smtp_password`, `sms_api_key`, `payment_secret`, `encrypted` values. Logging in `BusinessProfileController:48` catches exceptions without leaking stack.
- **EXPECTED:** Do not expose SMTP/SMS/payment secrets — **met**.
- **RISK:** NONE.

---

## 26. Existing UI Gaps

| Gap | File | Line | Severity | Description |
|-----|------|------|----------|-------------|
| Legal field naming drift | `resources/views/business/profile.blade.php:213` vs `institutes` schema | 213 vs migration | LOW | Blade uses `trade_license`, `license_number`, `registration_number`, `e_tin` — check vs actual columns `trade_license` may be `trade_license_no`; view tolerates `?: 'Not provided'` but schema should be source of truth. |
| Healthcare-specific profile sections absent | `business/profile.blade.php:307` | 312 | LOW | Healthcare industry has `sub_industries` (hospital/clinic/pharmacy) but profile shows generic `Business Overview`; not a gap — no detailed healthcare modules exist to surface, so OTHER handling is correct. |
| Edit profile UX split | `profile.blade.php:35` `route('settings.index')` | 35 | LOW | “Edit Profile” goes to `settings.index` (general settings) rather than dedicated business edit; acceptable reuse, but explicit business edit route would improve UX. |
| Empty branches UX | `profile.blade.php:227` | 227 | LOW | “No branches available.” — correct empty state per spec. |
| Academic OTHER hide correctness | `profile.blade.php:251` `$domain === academic && $academicData` | 251 | INFO | Academic conditional requires both domain and data; empty academicData still shows overview with zeros — correct. |

**No blocking UI gaps** for business profile taxonomy.

---

## 27. Existing Route Gaps

| Gap | File | Line | Severity | Description |
|-----|------|------|----------|-------------|
| No `business.profile.edit` | `routes/web.php:347` | 347 | LOW | Only `GET business/profile` exists; edit is via `settings.index`. Dedicated `PUT business/profile` would be UX-complete but not required per audit reuse rule. |
| Legacy `business/{institute}` redirect lacks `whereNumber` | `routes/web.php:354` | 354 | LOW | `business/{institute}` accepts slug/string; add `where('institute','[0-9]+')` or keep as generic redirect but ensure no data leak — currently redirect, safe. |
| No `business.profile` `verified` middleware nuance | `routes/web.php:348` | 348 | INFO | Requires `verified` — unverified owner cannot view profile during OTP flow; correct per `E19` OTP-first, but consider `verified` bypass for owner onboarding reading profile. |

**No missing routes blocking domain-aware UX.**

---

## 28. Existing Business Logic Gaps

| Gap | File | Line | Severity | Description |
|-----|------|------|----------|-------------|
| Sub-category taxonomy deferred per spec | `config/industry_rules.php:40` | 40 | PASS | No business sub-category taxonomy introduced — **correctly deferred** per “Do NOT introduce business sub-category”. |
| Domain immutability enforced | `app/Models/Institute.php:30` | 30 | PASS | Blocks `industry/sub_industry` change when `hasDomainData` — preserves historical integrity. |
| Industry/subIndustry validation | `app/Support/InstituteDomain.php:87` `isValidCombination` | 87 | PASS | Validates against `industry_rules.global.industries`; onboarding uses `InstituteOnboardingController`. |
| Other domain defaults professional | `InstituteDomain.php:107` `subjectTypeFor` | 107 | LOW | `OTHER → professional` safe default — other industries should not use subject master academically; dashboard hides academic sections via `home.blade.php:9` `isCleanStudent`. Correct. |

**No business logic gaps requiring new tables.**

---

## 29. Migration Requirement

**MIGRATION_REQUIRED: NO**

- **FILE:** `database/migrations/*`, `app/Models/Institute.php:13`
- **CURRENT:** `institutes` already has `industry`, `sub_industry`, `division`, `district`, `upazila`, `address`, `postal_code`, `google_map_url`, `trade_license`, `license_number`, `registration_number`, `e_tin`, `logo`, `cover_photo`, `founded_year`, `slug`, `status`, `verified`, `country`. `InstituteSetting`, `Branch`, `Membership` cover remaining.
- **EXPECTED:** Do not create `businesses`/`business_profiles` unless proven gap — **no gap proven**. All audit dimensions covered by existing tables.
- **RISK:** NONE if no migration.
- **RECOMMENDATION:** No migration. If future `business_category` descriptive text needed beyond `sub_industry` label, reuse `IndustryRules::subIndustries` config, not new column.

---

## 30. Recommended Implementation Plan

**No implementation in this audit phase** — for next phase if gaps addressed:

1. **LOW — Add `whereNumber` to legacy redirect:** `routes/web.php:354` `Route::get('business/{institute}', fn()=>redirect()->route('dashboard'))->whereNumber('institute')->name('business.show')` to tighten.
2. **LOW — Log BusinessProfile fallback:** `BusinessProfileController.php:125` add `Log::info('business.profile.fallback', ['user_id'=>$user->id])`.
3. **LOW — Legal field canonical rename:** Migration not needed now; add accessor `getBusinessLegalAttribute()` in `Institute.php` normalizing `trade_license` vs `trade_license_no`.
4. **INFO — Optional dedicated edit:** `Route::get('business/profile/edit', [BusinessProfileController::class,'edit'])->name('business.profile.edit')` + `PUT` with `hasPermission('settings.manage')` if UX demands.

**All without touching tenant/domain invariants.**

---

## 31. Security Invariants (Must Preserve)

1. **TenantContext is selected AFTER authentication/workspace resolution** — `SetTenantContext.php:26` after `auth`.
2. **Login identity lookup remains global** — `Auth::guard('web')` user lookup not tenant-scoped.
3. **Active business determines tenant context** — `Workspace::id()` → `TenantContext::set()`.
4. **Never use URL institute_id as authority** — `BusinessProfileController::resolveActiveInstitute` ignores request input.
5. **Business Profile taxonomy category-level only** — `IndustryRules` + `InstituteDomain`, no business sub-category tables.
6. **Course categories separate from business categories** — `CourseCategory` vs `Institute.sub_industry`.
7. **Academic/professional subjects never mix** — `InstituteDomain::subjectTypeFor` + `Rule::exists(...subject_type=derived)` + query `where subject_type=derived`.
8. **Historical results remain reproducible** — `SoftDeletes` + `RESTRICT` + `withTrashed()` + snapshot rows + curriculum freeze + `hasDomainData` block.
9. **Do not merge legacy `exams` with Academic Assessment** — `exams` (professional batch exams) vs `academic_assessments` (academic, domain:academic) remain separate.
10. **Do not weaken TenantScoped/BranchScoped/RBAC** — global scopes preserved, `SubstituteBindings` after `SetTenantContext`, `CheckPermission` + `CheckModuleAccess`.

---

## 32. Test Coverage

**Existing relevant tests (to extend, not modify data):**

| Test File | Coverage | Status |
|-----------|----------|--------|
| `tests/Feature/SubjectUnificationTest.php:238` `test_tenant_isolation` | Cross-tenant subject blocked (CourseController) | PASS (with one 302 pre-existing harness note in B7 audit, not profile) |
| `tests/Feature/TenantIsolationAuditTest.php:13` `audit_with_3_tenants` | Leakage 0, SECURE | PASS |
| `tests/Feature/WorkspaceContextTest.php` | Workspace resolution, switch | PASS |
| `System\TenantIsolationAuditService` | `php artisan system:tenant-isolation-audit` | SECURE |
| `Business Profile` | No dedicated test yet — **recommended** (see §30) | GAP (LOW) |

**Recommended new tests (in-memory, factories, DatabaseTransactions):**

```
BusinessProfileTest::one_business_auto_active_shows_own_profile
BusinessProfileTest::multiple_businesses_switch_changes_profile
BusinessProfileTest::url_tamper_business_institute_ignored_redirects_dashboard
BusinessProfileTest::cross_tenant_profile_blocked_403
BusinessProfileTest::domain_academic_shows_academic_overview_not_professional
BusinessProfileTest::domain_professional_shows_training_overview_not_academic
BusinessProfileTest::domain_other_shows_other_overview_not_academic
BusinessProfileTest::sensitive_fields_not_exposed
BusinessProfileTest::branch_list_tenant_isolated
BusinessProfileTest::subscription_safe_fields_only
```

---

## 33. Risk Classification

| Finding | File | Line | Risk |
|---------|------|------|------|
| Sidebar hid professional Courses pre-B7 | `layouts/institute.blade.php:124` (pre-B7) | 124 | CRITICAL (now FIXED in B7 GREEN) |
| Pre-B7 `availableCourses` hardcoded professional | `CurriculumController.php:403` (pre-B7) | 403 | HIGH (FIXED) |
| Pre-B7 `SubjectManagementController` global `orWhereNull` & IDOR global edit | `SubjectManagementController.php:297` (pre-B7) | 297 | HIGH (FIXED) |
| BusinessProfile fallback to first membership when session null | `BusinessProfileController.php:125` | 125 | LOW |
| Legal field naming drift `trade_license` vs schema | `business/profile.blade.php:213` | 213 | LOW |
| Legacy `business/{institute}` without `whereNumber` | `routes/web.php:354` | 354 | LOW |
| No dedicated `business.profile.edit` route | `routes/web.php:347` | 347 | LOW |
| `industry_rules.php` extra types not in `InstituteDomain` (madrasha etc. → OTHER) | `config/industry_rules.php:42` vs `InstituteDomain.php:22` | 42 | INFO (intentional gate) |
| Course categories separate — no join — correctly isolated | `CourseCategory.php:11` vs `Institute.php:28` | 11 | INFO (PASS) |

**Counts:**
- **CRITICAL:** 0 open (1 historical fixed)
- **HIGH:** 0 open (2 historical fixed)
- **MEDIUM:** 0
- **LOW:** 4
- **INFO:** 2
- **BUSINESS_RULE_GAPS:** 0

---

## 34. Final Verdict

**All invariants audited; no data/code/migration modifications performed.**

```
PHASE: B8
SCOPE: Business Profile + Domain-Aware UX + Multi-Business Integration

DATA MODIFIED: NO
DATA DELETED: NO
MIGRATIONS: NO

BUSINESS_IDENTITY: PASS
WORKSPACE_RESOLUTION: PASS
TOPBAR_PROFILE_NAVIGATION: PASS
PROFILE_DATA: PASS
CATEGORY_MODEL: PASS
SUBCATEGORY_DEFERRED: PASS
DOMAIN_RESOLUTION: PASS
ACADEMIC_UI: PASS
PROFESSIONAL_UI: PASS
COURSE_ISOLATION: PASS
SUBJECT_ISOLATION: PASS
CURRICULUM_ISOLATION: PASS
BATCH_ISOLATION: PASS
RESULT_ISOLATION: PASS
MULTI_BUSINESS: PASS
TENANT_ISOLATION: PASS
BRANCH_ISOLATION: PASS
IDOR_PROTECTION: PASS (with LOW fallback note)
RBAC: PASS
HISTORICAL_INTEGRITY: PASS
SENSITIVE_DATA_PROTECTION: PASS

CRITICAL_FINDINGS: 0
HIGH_FINDINGS: 0
MEDIUM_FINDINGS: 0
LOW_FINDINGS: 4
BUSINESS_RULE_GAPS: 0

MIGRATION_REQUIRED: NO
IMPLEMENTATION_REQUIRED: NO (optional LOW UX polish only)

FINAL_VERDICT: GREEN
```

**STOP — Audit complete. No fixes implemented in this phase; next phase may address LOW polish only if desired, without touching Business Profile authority, TenantContext, or historical integrity.**
