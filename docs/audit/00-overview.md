# Monetix — Architecture Overview

Consolidated read-only audit. Date: 2026-08-17.
Detailed per-layer reports: `docs/audit/01-models.md`, `02-controllers.md`, `03-support.md`, `04-routes-views-config.md`.

**Stack:** Laravel 12 (v12.66.0) / PHP ^8.2 (8.2.12) · MySQL single DB `monetix` · Blade + Bootstrap 5.3 (CDN) + Tailwind v4 (Vite) + Alpine 3 via CDN · Fortify (auth engine only) · chillerlan/php-qrcode.
**No:** Spatie, Sanctum, Jetstream, Livewire, Vue, API routes, subdomain tenancy, per-tenant databases.

---

## 1. What the system is

A **multi-tenant education-management ERP ("monetix")** with:

- **Three account realms**, all served by one monolith:
  - `User` (`users`) — global person-level account with many `Membership`s across institutes (owner vs staff account types).
  - `InstituteUser` (`institute_users`) — legacy per-institute account (still fully functional, parallel realm).
  - `PlatformAdmin` (`platform_admins`) — super-admin, runs the `admin/*` area.
- **Three session guards** (web / platform_admin / institute_user), three providers, three password brokers (all `password_reset_tokens`).
- **Institutes (tenants)** with branches, rooms, batches, students, courses/subjects (shared catalog), exams, results, certificates, attendance, fees (invoices/payments/cash memos/transactions), notices, gallery, offline sync queue, and an **AI assistant**.

## 2. Tenancy model (row-scoped, single DB)

- One MySQL database, all tenants share the schema. Tenant isolation is by **column + global scope**:
  - `institute_id` + `Concerns\TenantScoped` global scope `institute` → `WHERE institute_id = TenantContext::id()`.
  - Optional second axis `branch_id` + `Concerns\BranchScoped` global scope `branch`.
- `TenantContext` / `BranchContext` are **static** request-scoped holders populated by the `SetTenantContext` middleware (aliased `tenant`), which runs **before** `SubstituteBindings` (prepended in `bootstrap/app.php`) — preventing cross-tenant route-model binding.
- `Workspace` resolves the active organization for the global `web` user from the session key `active_institution_id`, re-verified against an **active** `Membership` every request.
- **Critical caveat:** when `TenantContext` is not enabled (null id) the scope is a **no-op** — all rows are visible. Correct for platform-admin/CLI, but any unguarded code path silently loses isolation. Static contexts also mean queued/job workers inherit whatever was last set. No request-scoped restoration exists.
- Shared catalogs (Course, Subject, Country, AdministrativeLevel/Unit, Role, package, settings) deliberately do **not** use `TenantScoped`.

## 3. Identity & RBAC

- Global `User` → many `Membership`s (table `institution_user`, legacy pivot name). Membership carries `role_id`, `branch_id`, employee/payroll columns, soft-deletes, and **enforces the owner↔staff account-type invariant** (throws `AccountTypeMismatchException` at create/update via `MembershipService`; `User` also validates account-type→membership consistency on `saving`).
- `Batch::teacher()` points at a **Membership**, not an `InstituteUser`.
- Roles are slug-based (`institute-owner` …); permissions come from the `role_permissions` matrix (`RolePermission`). `hasRole`/`hasPermission`/`hasAnyPermission`/`isOwner` are duplicated on `InstituteUser` and `Membership`.
- Route-level authz via `permission:slug` / `permission:a,b` middleware (`CheckPermission`); `PlatformAdmin` always passes. Owner is a superuser (`'*'`).
- Password hashes stored in `password_hash` (custom `getAuthPassword*`), guarded from corrupted-hash 500s by `Support\PasswordHash` (audited daily by scheduled `auth:audit-hashes`).
- 2FA: Fortify-backed, challenge route completes any of the 3 guards (`TwoFactorChallengeController`), single-role-per-session.

## 4. Domain model (education ERP)

| Area | Models / tables | Managing controller |
|---|---|---|
| Tenants | `Institute` (`package_id` → `SubscriptionPackage`), `InstituteSetting` (incl. `ai_config`), `InstituteSubscription`, `Branch`, `Room` | Institute settings; `Admin\InstituteAdminController` |
| Students | `Student` (dual-BD-geo + new country hierarchy address cols, `search` scope, `nextStudentNumber()` w/ soft-deletes), `StudentEnrollment` (student↔batch pivot), `RegNoSequence` | `StudentController`, `RecycleBinController`, `Admin\StudentAdminController` |
| Courses | `Course`, `CourseCategory`, `CourseSubCategory`, `InstituteCourse` (assignment), `Subject`, `InstituteSubject` (assignment), `CourseSubject`, `CourseRequest`, `SubjectRequest` | `CourseController` / `ClassController` (academic vs professional split), `Admin\CourseAdminController` / `Admin\ClassAdminController` |
| Batches | `Batch` (branch-scoped, soft deletes, `B###` code), shifts/status | `BatchController` |
| Exams | `Exam`, `ExamSubject` (per-subject marks incl. pass_marks), `ExamResult` (student×subject, `entered_by`), `ExamType`, `GradingScale` | `ExamController` |
| Results | `Result` (course-level, `published_by`, hasOne `Certificate`) vs `ExamResult` (exam×subject) | `ExamController` |
| Certificates | `Certificate` (tenancy+branch filtered list; lifecycle in admin) | `CertificateController` (list) + `Admin\CertificateAdminController` (issue/revoke/QR) + public `VerifyCertificateController` |
| Attendance | `Attendance` (branch inherited via batch) | — (no controller) |
| Finance | `Invoice`, `InvoiceItem`, `Installment`, `Payment`, `Transaction` (GL, branch-scoped), `AccountHead`, `CashMemo` (offline-originated) | Writes only via `OfflineSyncService` approval; no direct CRUD controllers |
| Notifications | `Notification` + `NotificationRead`, `Support\NotificationCenter` | `NotificationController` (root + Admin, duplicated) |
| Content | `Notice`, `GalleryAlbum`, `GalleryMedia` | — (no controllers) |
| Offline sync | `OfflineSyncQueue` (`payload` JSON, `client_uuid` idempotency) | `OfflineSyncController` + `OfflineSyncService` (approve → materializes `CashMemo`) |
| Geo | `Country`, `AdministrativeLevel` (≤3 levels selectable), `AdministrativeUnit` (parent hierarchy), `GeoImport`; `Support\GeoHierarchy`, `BdGeo`, `CountryCodes` | `GeoController` (AJAX), `Admin\GeoAdminController`, `Admin\GeoImportController`, `GeoImportService`, CLI |
| AI | `AiLog`, `AiUsage` (+ `Setting` key `ai.api_key` encrypted at rest) | `Ai\AiAssistantController` + 18-file `app/Services/Ai` suite (read-only tool calling) |
| Platform | `PlatformAdmin`, `Theme`, `IndustrySetting`, `Setting` (KV, encrypted keys), `Role`, `Permission`, `RolePermission`, `ActivityLog`, `AuditLog`, `LoginAttempt`, `LegacyUser` (dual model on `users`!) | `Admin\SettingController`, `Admin\ThemeController`, `Admin\IndustrySettingController`, `Admin\AiSettingController` |

Notable quirks:
- `User` and `LegacyUser` both map to `users` (dual models, one table).
- `$guarded = []` is the norm (only Student/User/Country/AdminLevel/AdminUnit use `$fillable`).
- Exam results: `Exam::subjects()` ordered by id; legacy plain-marks path (`saveLegacyMarks`).

## 5. HTTP surface

- **0 API routes.** 208 route calls (web 177 + auth 31); 190 verbs (GET 85 / POST 86 / PUT 10 / DELETE 9). JSON via `expectsJson()` inside web middleware and block-level AJAX (`geo.*`, page-marker).
- Groups: public verify + auth · authenticated any-actor (`auth:platform_admin,institute_user,web`) · tenant portal (`auth:institute_user,web` + `tenant`): students/batches/exams/courses/certificates/settings/sync/staff/recycle/notifications/ai · workspace owner (`auth:web`) · platform admin (`auth:platform_admin`, prefix `admin`).
- **45 controllers** (44 concrete, all extending empty abstract `Controller`; no `HasMiddleware`, no ApiController). 5 invokable.
- **5 FormRequests** only (student + offline-sync flows); all other validation inline in controllers. Only `StudentFormRequest` defines `authorize()`.

## 6. Frontend & views

- **96 Blade files**; `admin/` 41 largest. Plain server-rendered Blade (`@yield`/`@stack`), **no Livewire/Vue**. Bootstrap 5.3 + Bootstrap Icons (CDN), Tailwind v4 via Vite, Alpine 3 (CDN) for small components (`x-connectivity-signal`, photo crop). Form/JSON-post-driven; per-user column-visibility preferences stored in `preferences` JSON (`HasUserPreferences`, `UiPreferenceController`).

## 7. Support layer

- **Traits:** `TenantScoped`, `BranchScoped` (global scopes), `HasUserPreferences`.
- **Support:** `TenantContext`, `BranchContext`, `Workspace`, `SessionAgent`, `PasswordHash`, `PageMarker` (DEV tool flagged for deletion), `NotificationCenter`, `GeoHierarchy`, `CountryCodes`, `BdGeo`, `AiLanguage`, `AiConfig`.
- **Services:** `UserAccountService`, `ProfileImageService`, `OfflineSyncService`, `MembershipService`, `GeoImportService`, AI suite (`AiService`, `AiToolRegistry`, `AiContext`, `AiUsageTracker`, `AiLogger`, `OpenAiProvider`, `AbstractAiTool`, 8 education tools + core `get_income_expense`). AI is read-only by design; write tools refused, secrets redacted in logs, per-tool permissions, tenant-context guard in tools.
- **Middleware:** `SetTenantContext` (`tenant`), `SetLocale`, `SetFortifyGuard`, `CheckPermission` (`permission:`), `EnsureAiEnabled` (`ai.enabled`).
- **Helpers:** 13 `mawa_*` functions + `qr_svg` (i18n/currency/flag/color utilities).
- **Providers:** only `AppServiceProvider` — singleton `AiProvider`→`OpenAiProvider`, `Fortify::ignoreRoutes()`, global view composer sharing layout context (user, notifications via `NotificationCenter`, pending sync counts, theme/preferences, `aiEnabled`, recycle counts).

## 8. Data & infra

- **Migrations:** 35, newest 2026-08-17 (exam subjects/marks, subject requests, geo). Roles/permissions seeded inside a migration. Only `DatabaseSeeder`.
- **DB config:** default Laravel 5-connection template; active = single `mysql` DB. No central/tenant split. Redis (phpredis) for cache; queue `sync`; sessions DB.
- **Auth:** 3 guards / 3 providers / 3 brokers; email verification + 2FA + lockout (5/15min) on login; register for owner (global) and institute staff (inactive until admin approves).

---

## 9. Risk notes & refactor candidates

1. **Tenant isolation is no-op when context is null** — the single biggest correctness risk. Scope is global-scope based; any path without the `tenant` middleware silently sees all institutes.
2. **Static `TenantContext`/`BranchContext`** — non-final, process-global; no queue/job context restoration. Long-running workers can serve the wrong tenant.
3. **Three parallel auth realms** duplicate visibility/permission logic (`CheckPermission`, `NotificationCenter`, AppServiceProvider view composer, `AiContext`, `InstituteSettingController`, `StaffInvitationController`) with `instanceof`/guard branches — consolidation opportunity.
4. **Level of duplication:** `CourseController` ↔ `ClassController` (near-verbatim, subject_type split); `Admin\CourseAdminController` (804-line god-controller, 20 methods, 5 screens) ↔ `Admin\ClassAdminController`; root & Admin `NotificationController` identical; 3 login controllers repeat lockout/2FA scaffold.
5. **Finance has no direct write controllers** (Invoice/Payment/CashMemo/Transaction) — only offline-sync approval materializes cash memos. Direct finance workflow may be incomplete.
6. **No controllers for** Attendance, Notice, Room, Gallery, Membership, SubscriptionPackage — models exist but no management surface.
7. **`User`/`LegacyUser` dual models** on `users` — intentional-looking but confusing; confirm `LegacyUser` is a deliberate migration/read-only shim.
8. **Dev leftovers:** `Support\PageMarker` (+ routes/partials/`storage/app/page-markers.json`, `dev.page_marker_enabled` setting) flagged "DELETE once development is done".
9. **Mixed styling stack** (Bootstrap 5.3 + Tailwind v4) may cause CSS friction.
10. **Inline validation everywhere** (except student/offline-sync) and duplicated password-confirm force-delete blocks.