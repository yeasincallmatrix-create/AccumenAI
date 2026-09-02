# Monetix — Routing, Views & Config/Tenant Audit

Date: 2026-08-17
Scope: `routes/*`, `resources/views`, `config/database.php`, `config/auth.php`, `.env` (structural), `database/migrations`, `composer.json/package.json/vite.config.js`.
Mode: read-only research. No application code was modified.

---

## 1. Routing

### Route files

| File | Routes registered | Notes |
|------|-------------------|-------|
| `routes/web.php` | 177 `Route::` calls | All portal + tenant + admin HTTP routes |
| `routes/auth.php` | 31 `Route::` calls | Fortify-style auth: password reset, 2FA, email verification, account security |
| `routes/console.php` | 1 artisan command | `inspire` (skeleton only) |
| `routes/api.php` | — | Does not exist |
| `routes/tenant.php` | — | Does not exist; tenancy is not subdomain-based |

- **API route count: 0** (no `routes/api.php` and no `api:` route registration in `bootstrap/app.php`). JSON responses exist but are served inside web middleware (`expectsJson()` handling in exception rendering and block-level AJAX endpoints like `geo.*`, `dev/page-marker`).
- **Web/monolith count: 208** total `Route::` invocations across `web.php` + `auth.php`; 190 of them are HTTP verb definitions.
- HTTP verb breakdown (web+auth): `GET` 85, `POST` 86, `PUT` 10, `DELETE` 9, `PATCH` 0.

### Route registration (bootstrap/app.php)

`withRouting(web: [routes/web.php, routes/auth.php], commands: routes/console.php, health: '/up')`. No API channel. Middleware aliases registered: `tenant`, `permission`, `setlocale`, `verified`, `fortifyguard`, `ai.enabled`. `SetLocale` appended to the `web` group; `SetTenantContext` is reordered ahead of `SubstituteBindings`; guests redirected to `admin.login`.

### Route groups in `web.php`

1. **Public — certificate verification** (no auth): `GET/POST verify/certificate` (index, check) + `GET verify/certificate/{certificate_number}` (show).
2. **Public — portal auth** (guest, throttled): `login` (UserLoginController), `admin/login` (PlatformAdminLoginController), `institute/login` (InstituteUserLoginController), `institute/register`, `register` (OwnerRegisterController), `POST logout`.
3. **DEV — page marker** (public): `GET dev/page-marker` (JSON, gated by `App\Support\PageMarker::enabled()`).
4. **Authenticated any-actor** — `auth:platform_admin,institute_user,web`:
   - `account/preferences` (edit/update + theme POST), `POST ui/columns`.
   - `geo.*` AJAX: `countries`, `levels/{country}`, `units`, `resolve` (cascading address selectors).
5. **Tenant dashboard** — `auth:platform_admin,institute_user,web` + `tenant` → `GET /` (DashboardController).
6. **Owner/workspace** — `auth:web`: `workspace` picker, `workspace/switch/{institutionId}`, `workspace/create` (get/post).
7. **Students** — `auth:institute_user,web` + `tenant`, prefix `students`, permission-gated: index, create, store, show, enroll, edit, update, photo upload, destroy.
8. **Sync** — prefix `sync`, `auth:institute_user,web` + `tenant`: index, upload, approve, reject (offline sync queue; permission:finance.*).
9. **Batches** — prefix `batches`: index, show, store, update, destroy, archive, unarchive, transfer (student), remove-student.
10. **Exams** — prefix `exams` (permission:exams.manage): index, send-to-exam (from batch), show, update, save marks, destroy.
11. **Courses / certificates / institute settings** (no prefix group): `courses` index/subjects/request/updateSubject/archive/show/syncSubjects; `certificates` index; `verify`; `settings` index/account/appearance/updateAppearance/updateGeneral/updatePassword.
12. **Staff** — prefix `staff`: invite (create/store).
13. **Recycle bin** — prefix `recycle`: index, restore/force-delete for students and batches (withTrashed).
14. **Notifications** — prefix `notifications`: index, read-all, mark-read.
15. **AI** — prefix `ai`, `tenant + ai.enabled:assistant + permission:ai.assistant`: assistant index + send.
16. **Platform admin** — `auth:platform_admin`, prefix `admin` (largest group, reused across many controllers — see controller matrix below). Includes DEV page-marker toggle, institutes bin/restore/force-delete, per-list column presets, industry settings, GeoAdmin CRUD + GeoImport run/validate/status, AI settings, themes, notifications, and settings (account/staff/password/language/appearance/mail-payment).

### Controller coverage matrix (endpoints)

**Tenant / institute portal:**
| Controller | Coverage |
|-----------|----------|
| `StudentController` | full CRUD + enroll + photo + destroy (9 routes, permission-gated) |
| `BatchController` | index/show/store/update/destroy + archive/unarchive/transfer/remove-student (9) |
| `ExamController` | index/show/update/destroy + send-to-exam + saveMarks (6) |
| `CourseController` | index/subjects/archive/show + subject request + updateSubject + syncSubjects (7) |
| `CertificateController` | index (1; admin manages the rest) |
| `InstituteSettingController` | verify/index/account/appearance + updateAppearance/updateGeneral/updatePassword (8) |
| `StaffInvitationController` | create/store (2) |
| `OfflineSyncController` | index/store/approve/reject (4) |
| `RecycleBinController` | index + restore/forceDelete x2 (5) |
| `UserNotificationController` | index/read-all/read (3) |
| `AiAssistantController` | index/send (2) |
| `DashboardController` | single-route invokable (1) |
| `WorkspaceController` | picker/switch (2) |
| `InstituteCreationController` | create/store (2) |
| `GeoController` | countries/levels/units/resolve (4 AJAX) |
| `UserPreferenceController` | edit/update/updateTheme (3) |
| `UiPreferenceController` | saveColumns (1) |

**Auth (routes/auth.php):**
| Controller | Coverage |
|-----------|----------|
| `ForgotPasswordController` | request + sendResetLinkEmail (2) |
| `ResetPasswordController` | showResetForm + reset (2) |
| `TwoFactorChallengeController` | create + store (2) |
| `VerificationPromptController` | show notice (1) |
| `VerifyEmailController` | verify signed link (1) |
| `EmailVerificationNotificationController` | resend (2, reused for both portals) |
| `SecurityController` | account/security + admin/security + 2FA enable/confirm/disable/qr/recovery x2 portals + sessions revoke x2 (12) |

**Auth (in web.php):** `UserLoginController` (showLoginForm/login), `PlatformAdminLoginController`, `InstituteUserLoginController`, `InstituteUserRegisterController`, `OwnerRegisterController`, `LogoutController` (invokable) — 1 route each style.

**Platform admin (prefix `admin`):**
| Controller | Coverage |
|-----------|----------|
| `InstituteAdminController` | index/saveColumns/bin/show/edit/update/action/restore/force-delete (9) |
| `CourseAdminController` | index + index-columns, assignment + columns + assign/remove, requests + columns + requestAction, subjects + columns, subjects-requests + columns + subjectRequestsAction, batches + columns, archive, show (16) |
| `ClassAdminController` | index + columns, subjects + columns, batches + columns, archive (7) |
| `StudentAdminController` | index/saveColumns/show (3) |
| `CertificateAdminController` | index + columns, requests + columns, show, downloadQr, action, restore, destroy, force-delete (9) |
| `NotificationController` | index/read-all/read (3) |
| `IndustrySettingController` | index + updateTheme (2) |
| `GeoAdminController` | index/createCountry/storeCountry/edit/update/toggleStatus (7) |
| `GeoImportController` | index/store/validatePackage/run/status (5) |
| `ThemeController` | update (1) |
| `SettingController` | index/account/staff/password(+update)/updateLanguage/appearance(+update)/mail-payment(+update + test)/staffAction (12) |
| `AiSettingController` | index/update/test (3) |
| Closure | dev/page-marker/toggle POST (1) |

---

## 2. Views

`resources/views` — **96 blade files total** across top-level dirs:

| Directory | Blade files | Notes |
|-----------|------------|-------|
| `admin/` | 41 | Largest; platform-admin screens + shared admin partials |
| `auth/` | 7 | Login/register/2FA/password/verification pages |
| `courses/` | 6 | Tenant institute course + subject management |
| `classes/` | 5 | Class/subject/batch admin |
| `students/` | 4 | Student CRUD + show/index/create/edit |
| `layouts/` | 4 | `admin.blade.php`, `standalone.blade.php`, `partials/theme_colors`, `partials/topbar` |
| `settings/` | 3 | Institute settings pages |
| `components/` | 3 | `address`, `connectivity-signal`, `photo-crop-modal` (Blade components) |
| `exams/` | 3 | Exam views |
| `workspace/` | 2 | Workspace picker + create |
| `security/` | 2 | 2FA/session security |
| `verify/` | 2 | Public certificate verification |
| `partials/` | 2 | `page_marker`, `phone` |
| `batches/` | 2 | Batch list/show |
| `account/`, `ai/`, `certificates/`, `notifications/`, `recycle/`, `staff/`, `sync/` | 1 each | 7 files |
| `vendor/` | 1 | `pagination/bootstrap-5.blade.php` override |
| `home.blade.php`, `welcome.blade.php` | 2 | Skeleton root pages (pre-existing) |

### Frontend approach — **plain Blade (server-rendered), no Livewire, no Vue**

Evidence:
- Layouts (`layouts/admin.blade.php`, `layouts/standalone.blade.php`) use `@yield`/`@stack` (classic Blade, not Livewire or Vue component mounting). No `@livewire` directive anywhere in `resources/views`.
- **Bootstrap 5.3** loaded via CDN (`cdn.jsdelivr.net/npm/bootstrap@5.3.7`) in both layouts, plus Bootstrap Icons CDN and local compiled CSS (`asset('css/base.css')`, `layout.css`, `components.css`) — i.e. a Bootstrap-assisted Blade design.
- **Tailwind CSS v4** wired through Vite (`@tailwindcss/vite` plugin; `resources/css/app.css` starts with `@import 'tailwindcss'`) alongside `laravel-vite-plugin` (`resources/css/app.css`, `resources/js/app.js`). So both Bootstrap and Tailwind are present (mixed styling stack).
- **Alpine.js 3.14** loaded via CDN in `admin.blade.php` for small client-side components (notably `<x-connectivity-signal />`); the component docs explicitly say "Alpine, not Livewire: pure client-side browser connectivity".
- `package.json` has no `livewire`/`alpinejs`/`vue` npm deps — only tailwind/css, vite, axios, laravel-vite-plugin, concurrently. `resources/js/app.js` just imports `bootstrap` (axios bootstrap).
- Interactive lists (e.g. admin column presets) use POST + `checkSession`-style JSON/Bootstrap patterns rather than a JS framework, evidenced by routes like `institutes/columns`, `geo/imports/{import}/status`.

---

## 3. Config & Environment

### `config/database.php`
- **5 DB connections defined**: `sqlite`, `mysql`, `mariadb`, `pgsql`, `sqlsrv` (default Laravel template; no custom connections added).
- **Redis**: 2 logical connections (`default`, `cache`), client `phpredis`.
- Default connection: `env('DB_CONNECTION', 'sqlite')`.
- **No `central`/`tenant` connection definitions** — no per-tenant database wiring in config.

### `config/auth.php`
- Default guard `web`, default password broker `users`.
- **3 session guards**: `web` → User (owner/global account), `platform_admin` → PlatformAdmin, `institute_user` → InstituteUser. All three drive route middleware strings seen in the router (`auth:…`).
- **3 Eloquent providers** (User, PlatformAdmin, InstituteUser) and 3 matching password brokers (all using `password_reset_tokens` table).

### `.env` (structural facts only — no secrets printed)
- `APP_ENV=local`, `APP_DEBUG=true`.
- `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=monetix` (single database).
- `SESSION_DRIVER=database`, `QUEUE_CONNECTION=sync`, `CACHE_STORE=file`, `FILESYSTEM_DISK=local`, `MAIL_MAILER=log`.
- **No `TENANT_*`, `CENTRAL_*`, or multi-DB variables present.** Standard Laravel 12 `.env` otherwise (APP_*, LOG_*, MAIL_*, REDIS_*, AWS_*, VITE_APP_NAME).

### Tenancy scheme — **single-database, row-scoped multi-tenancy**
- Not server-per-tenant / database-per-tenant. One MySQL DB (`monetix`) with all tenants shared in the same schema.
- `institute_id` column on tenant tables + `App\Models\Concerns\TenantScoped` global scope (`addGlobalScope('institute', …)`) that filters any tenant model to `TenantContext::id()` when a tenant context is active.
- `TenantContext` (app/Support/TenantContext.php) is a static in-request holder set by `SetTenantContext` middleware (runs after auth; binds institute for `InstituteUser`, or the active workspace membership for `User`; also binds `BranchContext` for branch-level scoping; cleared for platform admins/guest).
- `BranchContext` gives a secondary branch-level scope (BranchScoped) for branch-owned rows.
- `Workspace` (app/Support/Workspace.php) tracks the active organization for the global `web` account via membership validation.
- Multi-tenant catalogs (courses/subjects) deliberately do NOT use `TenantScoped` (per the trait's own docblock) — they are shared/global reference data.
- Indication in routes: tenancy is enforced via the `tenant` middleware alias on institute-facing groups, and `SetTenantContext` is reordered before `SubstituteBindings` to prevent cross-tenant route-model binding.
- There is no subdomain-based tenant routing.

---

## 4. Migrations & Seeders

- **35 migration files** in `database/migrations`, newest dated **2026-08-17** (`2026_08_17_020000_add_pass_marks_to_exam_subjects_table.php`).
- Latest group (2026-08-16/17) covers: Geo/address system, archived batches + soft-deletes, exam subjects/marks, subject requests (tenant), attendance marks, pass marks.
- **No central/tenant DB separation migration** — consistent with single-DB row tenancy. The users table was successively remodeled (`expand_users_table`, `add_uuid_defaults`, `add_account_type_to_users_table`), and `institutes`/`institute_users`/`platform_admins` auth columns were added (2026-08-13).
- Notable domain migrations: `create_geo_tables` + `add_global_address_columns` (administrative units hierarchy), `create_ai_logs/ai_usage`, `add_ai_permissions`, `create_exam_subjects`, `create_subject_requests`, sessions/password-reset tables, settings + institute settings columns.
- **Seeders**: only `database/DatabaseSeeder.php` present. The `2026_08_12_000000_seed_default_role_permissions.php` migration itself seeds default roles/permissions (a migration-as-seeder pattern).

---

## 5. Framework Version

- `composer.json`: **`laravel/framework` ^12.0**, `php` **^8.2** (platform pinned `8.2.12` in composer config). Other notable deps: `laravel/fortify` ^1.38, `chillerlan/php-qrcode` ^6.0, `laravel/tinker` ^2.10; dev: tailwind-less — pail/pint/sail/phpunit ^11.5.
- Installed (composer.lock): **laravel/framework v12.66.0**.
- Frontend toolchain: Tailwind CSS ^4 (via `@tailwindcss/vite`), Vite ^7, laravel-vite-plugin ^2, axios ^1.11, concurrently ^9.

---

## Key takeaways

- Monolithic web app; **0 API routes**; all traffic through `web.php` + `auth.php`.
- Three auth guards (web/platform_admin/institute_user) with a `tenant` middleware gate over institute-scoped groups; enforce permission middleware per route.
- Row-level tenancy in a single MySQL DB via `institute_id` + `TenantScoped`/`BranchContext` global scopes — NOT separate central + per-tenant databases.
- Views: 96 blades, Bootstrap 5.3 + Tailwind v4 + Alpine (CDN) mixed stack; no Livewire/Vue; frontend is form/JSON-post driven.
- Laravel 12 (v12.66.0 installed), PHP ^8.2.