# Controllers & FormRequests Audit — monetix

Date: 2026-08-17 · Read-only inventory of `app/Http/Controllers` and `app/Http/Requests`.

---

## Summary numbers

| Item | Count |
|---|---|
| Files under `app/Http/Controllers` | **45** |
| — Concrete controllers | 44 |
| — Abstract base `Controller` | 1 |
| Sub-packages | Root (18), `Admin` (12), `Auth` (13), `Ai` (1) |
| Files under `app/Http/Requests` | **5** |
| — Concrete FormRequests | 4 |
| — Abstract base `StudentFormRequest` | 1 |

### Conventions observed
- **No `ApiController` exists.** Every concrete controller extends the custom abstract base `App\Http\Controllers\Controller` (which itself is empty — just `abstract class Controller {}`).
- **No controller implements `HasMiddleware`**; all middleware is applied via routes (`routes/web.php`, `routes/auth.php`).
- **None use resource/invokable route helpers in the class** except 5 invokable controllers (`__invoke`): `DashboardController`, `Auth\LogoutController`, `Auth\SecurityController`, `Auth\VerifyEmailController`, `Auth\VerificationPromptController`.
- **No controller uses `TenantScoped`/`BranchScoped` model traits directly** (those live on the models). Multi-tenancy in controllers is handled via:
  - `TenantContext` + the `tenant` route middleware (`InstituteUserLoginController`, `UserLoginController`, `LogoutController`, `TwoFactorChallengeController`, `AiAssistantController`),
  - `Workspace` (membership-based org selection for global accounts; `WorkspaceController`, `InstituteCreationController`, `StaffInvitationController`, `InstituteSettingController`, `DashboardController`, `OwnerRegisterController`, `UserLoginController`),
  - `BranchContext` in `CertificateController` (branch-filtered certificate list).
- `permission:...` route middleware granularity is used for institute-facing CRUD (`students.*`, `batches.*`, `exams.*`, `courses.*`, `certificates.*`, `settings.*`, `staff.*`, `finance.*`). Platform admin area is `auth:platform_admin`.
- No dedicated FormRequests for batches, exams, courses, institutes, settings, etc. — validation is inline `$request->validate([...])` in the controllers. FormRequests exist **only** for the Student and OfflineSync flows.
- Authorisation in FormRequests: only `StudentFormRequest::authorize()` gates on a permission (`students.manage`). `StoreOfflineSyncRequest` and `RejectOfflineSyncRequest` rely on route middleware for auth (no `authorize()` override).

---

# PART 1 — Base controller

## `app/Http/Controllers/Controller.php`
- **Class:** `App\Http\Controllers\Controller` (`abstract class Controller {}`, 8 lines).
- **Parent:** none (top of hierarchy).
- **Public methods:** none. Empty abstract marker used by all 44 concrete controllers below.
- **Notes:** no `use ApiController`, no shared traits/services. All cross-cutting behaviour comes from route middleware, `App\Support\TenantContext`, `App\Support\Workspace`, `App\Support\NotificationCenter`, and Services injected per controller.

---

# PART 2 — Root controllers (`app/Http/Controllers`)

## `app/Http/Controllers/StudentController.php`
- **Class:** `App\Http\Controllers\StudentController extends Controller` (513 lines).
- **Parent:** custom base `Controller`.
- **Type:** Plain controller (mostly full-CRUD resource shape, non-standard method names for extras).
- **Constructor injection:** `private readonly ProfileImageService $profileImage` (photo/doc handling).
- **Public methods:** `index`, `create`, `store(StoreStudentRequest)`, `show`, `enroll` (custom), `edit`, `update(UpdateStudentRequest)`, `uploadPhoto` (custom), `destroy`.
- **Models/services used:** Student, Batch, Branch, Country, AdministrativeUnit, Institute, Result, StudentEnrollment, `ProfileImageService`, `BdGeo`, `GeoHierarchy`, `Storage`, `DB`, `Validator`.
- **Tenant/scoping:** institute scoping via `$request->user()->institute_id`; `Student::nextStudentNumber()` for per-institute numbering; no global-scope bypass in this controller.
- **Custom details:** JSON-aware responses (`expectsJson()`), photo/document storage on `public`, quick-edit modal data (`studentEditData`), address cascade helpers (country-neutral `present_`/`permanent_admin_1..3`), institute defaults. `destroy` is a soft delete (recycle bin restore in `RecycleBinController`).

## `app/Http/Controllers/BatchController.php`
- **Class:** `App\Http\Controllers\BatchController extends Controller` (450 lines).
- **Parent:** custom base `Controller`.
- **Type:** Plain controller (no `create`/`edit`; store/update inline-validated; many custom actions).
- **Constructor injection:** none.
- **Public methods:** `index`, `show(Batch)`, `store`, `update`, `destroy`, `archive`, `unarchive`, `transferStudent`, `removeStudent`. (8 methods)
- **Models/services used:** Batch, Branch, Course, CourseCategory, Exam, InstituteCourse, InstituteSubject, Student, StudentEnrollment, Subject, `DB`, `Rule`.
- **Tenant/scoping:** explicit `institute_id` filters + `withoutGlobalScope('institute')` in `nextBatchCode()` (per-institute `B###` sequence). Soft-restore/unarchive sets status `running`.
- **Custom details:** `private const SHIFTS/STATUSES`; `validated()` shared for store/update; `destroy` blocked when the batch has attended exams; `transferStudent` marks old enrollment `transferred` and frees seat counters in a DB transaction.

## `app/Http/Controllers/CertificateController.php`
- **Class:** `App\Http\Controllers\CertificateController extends Controller` (53 lines).
- **Parent:** custom base `Controller`.
- **Type:** Plain controller — **list only** (`index`). Issue/approve/revoke/restore lives in `Admin\CertificateAdminController`.
- **Constructor injection:** none.
- **Public methods:** `index`.
- **Models/services used:** Certificate, Branch, `BranchContext`.
- **Tenant/scoping:** **`BranchContext`** — when branch context is enabled (`BranchContext::enabled()`) the certificate list is filtered to the current branch via `whereHas('student', branch_id = BranchContext::id())`. Institute scoping comes from model global scope.

## `app/Http/Controllers/ClassController.php`
- **Class:** `App\Http\Controllers\ClassController extends Controller` (450 lines).
- **Parent:** custom base `Controller`.
- **Type:** Plain controller — «Classes & Subjects» for institute users (academic subject type).
- **Constructor injection:** none.
- **Public methods:** `index`, `subjects`, `batches`, `archive`.
- **Models/services used:** Batch, Branch, Course, CourseCategory, InstituteCourse, InstituteSubject, Subject.
- **Tenant/scoping:** institute assignment lists with fallback; catalog shared category list bypasses `institute` global scope (`withoutGlobalScope('institute')`) deliberately.
- **Custom details:** **Near-verbatim copy of `CourseController` scoped to `subject_type = 'academic'`** (same private helpers: `categoryIdsBySubjectType`, `subjectQuery`, `academicCourses`, `coursesCount`, `subjectsCount`, `batchesCount`, `archiveCount`, `courseOptionsBySubjectType`, `subjectCategoriesBySubjectType`, `batchShiftsBySubjectType`).

## `app/Http/Controllers/CourseController.php`
- **Class:** `App\Http\Controllers\CourseController extends Controller` (699 lines).
- **Parent:** custom base `Controller`.
- **Type:** Plain controller — Courses/Subjects/Batches/Archive tabs for institute users (professional subject type).
- **Constructor injection:** none.
- **Public methods:** `index`, `subjects`, `batches`, `archive`, `show(Course)`, `syncSubjects`, `requestSubject`, `updateSubject`.
- **Models/services used:** Batch, Branch, Course, CourseCategory, InstituteCourse, InstituteSubject, Subject, SubjectRequest, `Carbon`, `Str`, `Rule`.
- **Tenant/scoping:** institute assignment lists w/ catalog fallback; `withoutGlobalScope('institute')` on shared category catalog; `requestSubject` creates a **pending `SubjectRequest`** (platform admin approves).
- **Custom details:** Column-visibility preferences via `$request->user()->preference('columns_*')`; JSON/redirect dual responses; pivot sync for `course_subjects`; subject proposal flow locking course category after request.

## `app/Http/Controllers/DashboardController.php`
- **Class:** `App\Http\Controllers\DashboardController extends Controller` (138 lines).
- **Parent:** custom base `Controller`.
- **Type:** **Invokable** (`__invoke`).
- **Constructor injection:** none.
- **Public methods:** `__invoke` + protected `instituteDashboard`, `platformDashboard`, `workspaceDashboard`.
- **Models/services used:** Batch, Certificate, Course, CourseRequest, Exam, Institute, InstituteCourse, InstituteUser, Result, Student, User, `Workspace`, `Auth`.
- **Guards/middleware:** dispatches on the active guard: `institute_user` → institute dashboard; `web` → workspace dashboard (requires `Workspace::membership()`); else → platform dashboard.

## `app/Http/Controllers/ExamController.php`
- **Class:** `App\Http\Controllers\ExamController extends Controller` (422 lines).
- **Parent:** custom base `Controller`.
- **Type:** Plain controller (index/show/update/destroy + heavy custom actions).
- **Constructor injection:** none.
- **Public methods:** `index`, `sendToExam(Batch)`, `show(Exam)`, `update`, `saveMarks`, `destroy` (+ private `saveLegacyMarks`).
- **Models/services used:** Batch, Exam, ExamResult, InstituteUser, Result, `DB`, `Carbon`, `Rule`.
- **Tenant/scoping:** scope via model global scopes + explicit `institute_id` on create; `created_by`/`entered_by` set only for `InstituteUser` guard.
- **Custom details:** Batch → one exam with per-subject mark breakdowns (`written/practical/viva/attendance/other`); `saveMarks` upserts `ExamResult` rows, computes pass/fail vs pass_marks; legacy plain-marks path for pre-subject exams; unique exam title scoped per institute.

## `app/Http/Controllers/GeoController.php`
- **Class:** `App\Http\Controllers\GeoController extends Controller` (185 lines).
- **Parent:** custom base `Controller`.
- **Type:** Plain controller — pure **AJAX JSON** endpoints.
- **Constructor injection:** none.
- **Public methods:** `countries`, `levels(Country)`, `units`, `resolve`.
- **Models/services used:** AdministrativeUnit, Country, `GeoHierarchy`.
- **Tenant/scoping:** none (global geo data; all authenticated portal users via `auth:platform_admin,institute_user,web`).
- **Custom details:** Returns standard `{ success, message, data }` JSON envelope; server-side country/level/parent hierarchy enforcement (422 on invalid combos); `limit(200)` caps.

## `app/Http/Controllers/InstituteCreationController.php`
- **Class:** `App\Http\Controllers\InstituteCreationController extends Controller` (98 lines).
- **Parent:** custom base `Controller`.
- **Type:** Plain controller (create/store).
- **Constructor injection:** none (`MembershipService` resolved via `app()`).
- **Public methods:** `create`, `store`, protected `uniqueSlug`.
- **Models/services used:** Institute, Role, User, `MembershipService`, `Workspace`, `DB`, `Str`.
- **Guard/staffing:** `auth:web` only; `abort_unless($user->isOwnerAccount())`. Owner creates an org inside a transaction reusing the existing global `User`; switches `Workspace` to the new institute.

## `app/Http/Controllers/InstituteSettingController.php`
- **Class:** `App\Http\Controllers\InstituteSettingController extends Controller` (187 lines).
- **Parent:** custom base `Controller`.
- **Type:** Plain controller (views + update actions; **not** a settings-form-request user).
- **Constructor injection:** none.
- **Public methods:** `verify`, `index`, `account`, `appearance`, `updateAppearance`, `updateGeneral`, `updatePassword`.
- **Models/services used:** IndustrySetting, Institute, InstituteSetting, InstituteUser, PlatformAdmin, Theme, User, `Workspace`, `DB`, `Hash`, `Password`, `Rule`.
- **Tenant/scoping:** dual-mode: `InstituteUser` (legacy per-institute) vs global `User` via `Workspace::membership()`. `resolveInstituteId()` unifies both. `updatePassword` writes `password_hash` with bcrypt.
- **Middleware:** `settings.manage` permission on appearance/general updates; account/password self-service.

## `app/Http/Controllers/NotificationController.php` (root)
- **Class:** `App\Http\Controllers\NotificationController extends Controller` (41 lines).
- **Parent:** custom base `Controller`.
- **Type:** Plain controller — inbox for institute/workspace users.
- **Public methods:** `index`, `markRead`, `markAllRead`.
- **Models/services used:** Notification, `NotificationCenter` support class.
- **Notes:** Routes use alias `UserNotificationController` (`use ...NotificationController as UserNotificationController` in `routes/web.php`). Behaviour is **identical** to `Admin\NotificationController` except the view path.

## `app/Http/Controllers/OfflineSyncController.php`
- **Class:** `App\Http\Controllers\OfflineSyncController extends Controller` (131 lines).
- **Parent:** custom base `Controller`.
- **Type:** Plain controller (JS/HTTP hybrid).
- **Constructor injection:** `private readonly OfflineSyncService $syncService`.
- **Public methods:** `index`, `store(StoreOfflineSyncRequest)`, `approve`, `reject(RejectOfflineSyncRequest)`.
- **Models/services used:** OfflineSyncQueue, Student, `OfflineSyncService`, `DB`.
- **Tenant/scoping:** `institute_id`/`created_by` stamped from authed user; queue review counts per status.
- **Middleware:** `permission:finance.view` (index), `permission:finance.manage` (approve/reject); upload route `sync.upload` unguarded by permission.
- **Custom details:** idempotent ingestion keyed on `client_uuid`; approve materializes a cash memo via the service inside a transaction; reject stores a reason.

## `app/Http/Controllers/RecycleBinController.php`
- **Class:** `App\Http\Controllers\RecycleBinController extends Controller` (176 lines).
- **Parent:** custom base `Controller`.
- **Type:** Plain controller.
- **Constructor injection:** none.
- **Public methods:** `index`, `restore(Student)`, `forceDelete(Student)`, `restoreBatch(Batch)`, `forceDeleteBatch(Batch)`.
- **Models/services used:** Batch, Student, Exam (`\App\Models\Exam` inline), `Hash`, `Storage`.
- **Tenant/scoping:** soft-deleted rows scoped by institute model global scope.
- **Middleware:** `permission:students.manage` (student ops), `permission:batches.manage` (batch ops). **Password confirmation (`Hash::check`) is required for force-deletes** and batch force-delete re-checks the attended-exams guard.

## `app/Http/Controllers/StaffInvitationController.php`
- **Class:** `App\Http\Controllers\StaffInvitationController extends Controller` (111 lines).
- **Parent:** custom base `Controller`.
- **Type:** Plain controller (create/store).
- **Constructor injection:** none (`UserAccountService`, `MembershipService` resolved via `app()`).
- **Public methods:** `create`, `store`, protected `resolveInstitutionId`, `membersFor`.
- **Models/services used:** InstituteUser, Membership, Role, User, `MembershipService`, `UserAccountService`, `Workspace`, `Password`.
- **Tenant/scoping:** dual-mode resolution through `InstituteUser` or global `Workspace::membership()`; `abort_if` on missing org; owner role can't be invited.
- **Middleware:** `permission:staff.manage`.

## `app/Http/Controllers/UiPreferenceController.php`
- **Class:** `App\Http\Controllers\UiPreferenceController extends Controller` (27 lines).
- **Parent:** custom base `Controller`.
- **Type:** Plain controller — single AJAX endpoint.
- **Public methods:** `saveColumns`.
- **Models/services used:** none (writes to `preferences` JSON column via `setPreference`).
- **Notes:** generic column-visibility saver for any portal user (`auth:platform_admin,institute_user,web`); `key` whitelisted by regex.

## `app/Http/Controllers/UserPreferenceController.php`
- **Class:** `App\Http\Controllers\UserPreferenceController extends Controller` (59 lines).
- **Parent:** custom base `Controller`.
- **Type:** Plain controller.
- **Public methods:** `edit`, `update`, `updateTheme`.
- **Models/services used:** `Auth` (guard-agnostic current account); session flash of `mawa_lang`.
- **Notes:** theme (`default/light/dark`) + language (`en/bn`) preferences per account; email verification and auth come from route middleware group `auth:platform_admin,institute_user,web`.

## `app/Http/Controllers/VerifyCertificateController.php`
- **Class:** `App\Http\Controllers\VerifyCertificateController extends Controller` (35 lines).
- **Parent:** custom base `Controller`.
- **Type:** Plain controller — **public** certificate verification (no auth middleware).
- **Public methods:** `index`, `check`, `show(string $certificateNumber)`.
- **Models/services used:** Certificate (+ eager `student`, `course`, `batch`, `institute`).
- **Notes:** `check` normalises input → GET `/verify/certificate/{number}`; `firstOrFail` by `certificate_number`.

## `app/Http/Controllers/WorkspaceController.php`
- **Class:** `App\Http\Controllers\WorkspaceController extends Controller` (59 lines).
- **Parent:** custom base `Controller`.
- **Type:** Plain controller.
- **Public methods:** `picker`, `switch(int $institutionId)`, protected `activeMembershipsFor`.
- **Models/services used:** Membership, User, `Workspace`.
- **Guard/staffing:** `auth:web` only; `abort_unless($user instanceof User)`.

---

# PART 3 — `app/Http/Controllers/Admin`

All extend the base `Controller`; all wrapped by `auth:platform_admin` route middleware.

## `app/Http/Controllers/Admin/AiSettingController.php`
- **Class:** `Admin\AiSettingController extends Controller` (165 lines).
- **Public methods:** `index`, `update`, `test`.
- **Models/services used:** Setting (platform settings KV), AuditLog, `AiConfig`, `Ai\Contracts\AiProvider`.
- **Notes:** `AVAILABLE_PROVIDERS = ['openai']`, `IMPLEMENTED_FEATURES = ['assistant']`. API key stored only when replaced (never echoed); connection `test()` swallows raw provider errors and records an AuditLog.

## `app/Http/Controllers/Admin/CertificateAdminController.php`
- **Class:** `Admin\CertificateAdminController extends Controller` (381 lines).
- **Public methods:** `index`, `requests`, `saveColumns`, `saveRequestsColumns`, `show`, `downloadQr`, `destroy`, `restore`, `forceDelete`, `action`.
- **Models/services used:** Certificate, Institute, Notification, `Hash`, `Rule`; inline `qr_svg()` helper (global function).
- **Notes:** Platform-side certificate lifecycle: review pending/rejected requests → approve/reject/revoke/revoke-cancel (`action`), issue cert numbers `MNT-YEAR-#####`, soft-delete/restore/force-delete to institute recycle bin, per-admin column prefs, QR SVG download. Notifies the institute via `Notification` rows.

## `app/Http/Controllers/Admin/ClassAdminController.php`
- **Class:** `Admin\ClassAdminController extends Controller` (249 lines).
- **Public methods:** `index`, `saveIndexColumns`, `subjects`, `saveSubjectsColumns`, `batches`, `saveBatchesColumns`, `archive`.
- **Models/services used:** Batch, Course, CourseCategory, Institute, InstituteCourse, InstituteSubject, Subject.
- **Notes:** «Classes» = academic courses (category `subject_type='academic'`). Counters shared across tabs. **Mirrors `Admin\CourseAdminController` structure but academic-only** and without the assignments/requests tabs.

## `app/Http/Controllers/Admin/CourseAdminController.php`
- **Class:** `Admin\CourseAdminController extends Controller` (804 lines — largest controller).
- **Public methods (20):** `index`, `saveIndexColumns`, `show`, `assignment`, `saveAssignmentColumns`, `assignmentAssign`, `assignmentRemove`, `requests`, `saveRequestsColumns`, `subjects`, `saveSubjectsColumns`, `batches`, `archive`, `saveBatchesColumns`, `requestAction`, `subjectRequests`, `saveSubjectRequestsColumns`, `subjectRequestsAction`; protected `syncCategorySubjects`, `notifyInstitute`.
- **Models/services used:** Batch, Course, CourseCategory, CourseRequest, Institute, InstituteCourse, InstituteSubject, Notification, StudentEnrollment, Subject, SubjectRequest, `Str`, `Rule`.
- **Notes:** Platform knowledge hub: professional catalog (+type toggle), per-institute course assignment, course-request review (`requestAction`: approve assigns `InstituteCourse` + syncs category subjects), subject-request review (`subjectRequestsAction`: approve creates `Subject` + `InstituteSubject`), tab shared counters, per-tab column preferences. One large god-controller bundling 5 screens.

## `app/Http/Controllers/Admin/GeoAdminController.php`
- **Class:** `Admin\GeoAdminController extends Controller` (147 lines).
- **Public methods:** `index`, `createCountry`, `storeCountry`, `edit`, `update`, `toggleStatus`.
- **Models/services used:** AdministrativeLevel, Country.
- **Notes:** Country definitions + the 3 administrative-level **labels** per country (upserts/inactivates `AdministrativeLevel` rows); actual location data comes via `GeoImportController`. Notifies via `mawa_lang('admin_geo.*')`.

## `app/Http/Controllers/Admin/GeoImportController.php`
- **Class:** `Admin\GeoImportController extends Controller` (185 lines).
- **Public methods:** `index`, `store`, `validatePackage`, `run`, `status`.
- **Models/services used:** Country, GeoImport, `LocalPackageProvider`, `GeoImportService`, `Storage`.
- **Notes:** AJAX-driven, resumable import (.jsonl/.json/.csv/.ndjson); mirrors the `geo:import-package` CLI through the shared service; upload caps configurable (`geo.import.*`); polling `status` endpoint.

## `app/Http/Controllers/Admin/IndustrySettingController.php`
- **Class:** `Admin\IndustrySettingController extends Controller` (78 lines).
- **Public methods:** `index`, `updateTheme`.
- **Models/services used:** IndustrySetting, Theme, config `industries`/`countries`/`sub_industries`.
- **Notes:** Default theme per industry key (or `all`); theme must be `active`.

## `app/Http/Controllers/Admin/InstituteAdminController.php`
- **Class:** `Admin\InstituteAdminController extends Controller` (357 lines).
- **Public methods:** `index`, `saveColumns`, `show`, `edit`, `update`, `action`, `bin`, `restore`, `forceDelete`; protected `deleteInstitute`, `notifyInstitute`.
- **Models/services used:** Certificate, Institute, InstituteSetting, InstituteUser, Membership, Notification, SubscriptionPackage, `DB`, `Hash`, `Rule`.
- **Notes:** Institute lifecycle: approve/reject/suspend/reactivate/soft-delete (password-confirmed) `action`; `update` syncs AI config into `InstituteSetting::ai_config`; bin lists soft-deleted institutes + trashed certificates; restore/reactivates `InstituteUser`s; forceDelete toggles FK checks and clears `institute_courses` pivot. Notifies institutes via `Notification` rows.

## `app/Http/Controllers/Admin/NotificationController.php`
- **Class:** `Admin\NotificationController extends Controller` (42 lines).
- **Public methods:** `index`, `markRead`, `markAllRead`.
- **Models/services used:** Notification, `NotificationCenter`.
- **Notes:** Same logic as root `NotificationController`, different blade path (`admin.notifications.index`). Trivially duplicated.

## `app/Http/Controllers/Admin/SettingController.php`
- **Class:** `Admin\SettingController extends Controller` (284 lines).
- **Public methods:** `index`, `account`, `staff`, `password`, `updatePassword`, `updateLanguage`, `staffAction`, `appearance`, `updateAppearance`, `mailPayment`, `updateMailPayment`, `testMail`.
- **Models/services used:** InstituteUser, PlatformAdmin, Setting (KV platform settings), Theme, `AiConfig`, `DB`, `Hash`, `Mail`, `Password`.
- **Notes:** Platform admin settings hub: pending staff registration approve/reject (`staffAction`), password/language prefs, theme-appearance prefs, SMTP/payment gateway config stored via `Setting::set('smtp.*' | 'payment.*')`, live `testMail` reconfiguring the `mail.smtp` mailer.

## `app/Http/Controllers/Admin/StudentAdminController.php`
- **Class:** `Admin\StudentAdminController extends Controller` (76 lines).
- **Public methods:** `index`, `saveColumns`, `show`.
- **Models/services used:** Institute, Student.
- **Notes:** Global student registry (all institutes), filterable by institute/type (enrollment course category subject_type)/status; read-mostly (no create/update here).

## `app/Http/Controllers/Admin/ThemeController.php`
- **Class:** `Admin\ThemeController extends Controller` (51 lines).
- **Public methods:** `index`, `update`.
- **Models/services used:** Theme, `Str`, `Rule`.
- **Notes:** Theme editor; setting `is_default` clears it on all other themes; redirects back to industry settings.

---

# PART 4 — `app/Http/Controllers/Ai`

## `app/Http/Controllers/Ai/AiAssistantController.php`
- **Class:** `Ai\AiAssistantController extends Controller` (76 lines).
- **Public methods:** `index`, `send`.
- **Constructor/Method injection:** `AiService` (method-injected on `send`).
- **Models/services used:** Institute, InstituteUser, User, `AiContext`, `AiService`, `TenantContext`, `Workspace`.
- **Tenant/scoping:** `activeInstitute()` resolves org from `InstituteUser::institute_id`, else `TenantContext::id()` / `Workspace::id()`.
- **Middleware:** `auth:institute_user,web` + `tenant` + `ai.enabled:assistant` + `permission:ai.assistant`.

---

# PART 5 — `app/Http/Controllers/Auth`

All extend the base `Controller`. Guards: `web` (global account), `institute_user` (legacy per-institute), `platform_admin`. All 3 variants share near-identical login flows.

## `app/Http/Controllers/Auth/UserLoginController.php`
- Global-account login (`web` guard), 170 lines. **Duplicated structure** with `InstituteUserLoginController`/`PlatformAdminLoginController`.
- Public methods: `showLoginForm`, `login`; protected `recordFailedAttempt`, `validateLogin`, `throttleKey`.
- Models: User, `PasswordHash`, `Workspace`.
- Details: lockout after 5 failed attempts/15 min, 2FA challenge hand-off (session keys `login.id/.remember/.guard`), resolves workspace via `Workspace::resolveAfterLogin()`, logs out other guards, `rate:limiter` = `login:web:{ip}`.

## `app/Http/Controllers/Auth/InstituteUserLoginController.php`
- Legacy per-institute login (`institute_user` guard), 159 lines.
- Public methods: `showLoginForm`, `login`; protected `recordFailedAttempt`, `validateLogin`, `throttleKey`.
- Models: InstituteUser (`withoutGlobalScopes`), `PasswordHash`, `TenantContext`.
- Details: same lockout/2FA pattern; on success sets `TenantContext::set($user->institute_id)`; corrupt-hash guard logging.

## `app/Http/Controllers/Auth/PlatformAdminLoginController.php`
- Platform admin login (`platform_admin` guard), 120 lines.
- Public methods: `showLoginForm`, `login`; protected `validateLogin`, `throttleKey` (no lockout counter — password-corrupt check only).
- Models: PlatformAdmin, `PasswordHash`, `TenantContext` (cleared on login).

## `app/Http/Controllers/Auth/InstituteUserRegisterController.php`
- Self-registration for institute staff, 67 lines.
- Public methods: `showRegisterForm`, `register`.
- Models: Institute, InstituteUser, Role.
- Details: default role `institute-admin`, status `inactive` until admin approves (`Admin\SettingController::staffAction`); throttle `10,15`.

## `app/Http/Controllers/Auth/OwnerRegisterController.php`
- Global owner-account registration, 67 lines.
- Public methods: `showRegisterForm`, `register`.
- Models: `UserAccountService` (`registerOwner`), `Workspace`.
- Details: keeps `Account` login only if a workspace is resolved; else redirects to `workspace.picker`.

## `app/Http/Controllers/Auth/ForgotPasswordController.php`
- 33 lines. Public methods: `showLinkRequestForm`, `sendResetLinkEmail`.
- Methods: brute-force probes all 3 password brokers (`users`, `institute_users`, `platform_admins`) with identical responses (no account disclosure).

## `app/Http/Controllers/Auth/ResetPasswordController.php`
- 70 lines. Public methods: `showResetForm`, `reset`; protected `brokerForEmail`.
- Models: PlatformAdmin, User. Resets via the broker that owns the email; clears lockout counters.

## `app/Http/Controllers/Auth/TwoFactorChallengeController.php`
- 120 lines. Public methods: `create`, `store`; protected `hasValidCode`.
- Models: User, PlatformAdmin, InstituteUser; `Fortify` + `TwoFactorAuthenticationProvider`.
- Details: completes 2FA for any of the 3 guards, single-role-per-session logout, sets `TenantContext`/`Workspace` after success, recovery-code rotation, throttled `5,1`.

## `app/Http/Controllers/Auth/SecurityController.php`
- 143 lines. **Invokable** (`__invoke`) + enable/confirm/disable 2FA, `qrCode` (JSON), `recoveryCodes` (JSON), `regenerateRecoveryCodes`, `revokeSessions`.
- Models: PlatformAdmin (guard detection); Fortify actions (`Enable/Confirm/DisableTwoFactorAuthentication`, `GenerateNewRecoveryCodes`), `DB` sessions, `Hash`.
- Mounted under both `auth:institute_user,web` and `auth:platform_admin` route groups (`fortifyguard` pins the guard). Password confirmation (`confirmCurrentPassword`) gates 2FA ops; `verified` middleware on 2FA routes.

## `app/Http/Controllers/Auth/EmailVerificationNotificationController.php`
- 23 lines. `store` → resends verification email (`auth:platform_admin,institute_user` + throttle).

## `app/Http/Controllers/Auth/VerificationPromptController.php`
- 22 lines. **Invokable** → shows verify-email prompt or redirects.

## `app/Http/Controllers/Auth/VerifyEmailController.php`
- 31 lines. **Invokable** — custom hash check (SHA1 of email) since 3 tables share one route; fires `Verified` event; signed+throttled route.

## `app/Http/Controllers/Auth/LogoutController.php`
- 34 lines. **Invokable** — logs out whichever of `web`/`institute_user`/`platform_admin` is active, invalidates session, clears `TenantContext` + `Workspace`.

---

# PART 6 — FormRequests (`app/Http/Requests`)

## `app/Http/Requests/StudentFormRequest.php` (abstract base, 139 lines)
- **Class:** `App\Http\Requests\StudentFormRequest extends FormRequest` (**abstract**).
- **authorize():** returns true only when the user has the `students.manage` permission (`method_exists($user,'hasPermission')`).
- **rules() — validated fields:**
  - identity: `first_name` (required), `last_name`, `gender` (male/female/other), `dob` (≤ today), `blood_group`, `religion`, `nationality`, `registration_number`, `roll_number`, `status` (active/completed/dropped/suspended), `admission_date` (required)
  - contact: `phone`, `guardian_phone`, `email`
  - id docs (unique per-institute ignoring soft-deletes & self): `nid_number` (10/13/15 digits), `birth_cert_number`, `passport_number` (9 alnum), `national_id_or_birth_certificate`
  - files: `photo` (image, max 100KB... actually mimes jpeg/png/jpg/webp, max:100), `document` (pdf/csv/svg, max 10MB)
  - org: `branch_id` (exists within the institute)
  - country-neutral address: `country` (config key), `present_*`/`permanent_*` (`address`, `country_id`, `admin_1_id`, `admin_2_id`, `admin_3_id`, `post_office`, `zip_code`) plus legacy BD ids (`present/permanent division/district/upazila` vs `BdGeo` keys)
- **messages():** NID/passport regex messages.
- **withValidator():** server-side hierarchy check via `GeoHierarchy::validateHierarchy` for every chosen `present`/`permanent` address.

## `app/Http/Requests/StoreStudentRequest.php` (concrete, 16 lines)
- Extends `StudentFormRequest`. `rules()` = `parent::rules()` unchanged.

## `app/Http/Requests/UpdateStudentRequest.php` (concrete, 30 lines)
- Extends `StudentFormRequest`. `rules()` = parent rules minus `student_id_number` (immutable after create) and `registration_number` unique rule ignoring the current route `student`.

## `app/Http/Requests/StoreOfflineSyncRequest.php` (30 lines)
- **authorize():** none (route/auth dependent). Extends `FormRequest`.
- **rules():** `records` = array 1..100; per record: `client_uuid` (uuid+distinct), `entity_type` in `OfflineSyncService::SUPPORTED_ENTITY_TYPES`, `created_offline_at` (date), `payload` (array), `payload.student_id` (nullable int), `payload.amount` (numeric > 0), `payload.description`, `payload.payment_method` (cash/bkash/nagad/bank/other), `payload.memo_number`, `payload.created_at`.

## `app/Http/Requests/RejectOfflineSyncRequest.php` (19 lines)
- **authorize():** none.
- **rules():** `reject_reason` = required string ≤ 255. Used by `OfflineSyncController::reject`.

---

# PART 7 — Gaps, stubs & duplication

## Stubs / empty
- **`Controller.php`** is the only near-empty file (abstract, comment only) — intentional base.
- No controller contains the literal `TODO` marker; nothing is a placeholder stub. All 44 concrete controllers have real logic.

## Notable duplicated CRUD patterns
1. **`CourseController` ↔ `ClassController`** — near-identical (~450 lines each), differing only on `subject_type` constant (`'professional'` vs `'academic'`); same private helper set, same views tabs (list/subjects/batches/archive), same preference keys pattern. Highest-value extraction candidate.
2. **`Admin\CourseAdminController` (804 lines) ↔ `Admin\ClassAdminController` (249 lines)** — same screen skeleton (index/subjects/batches/archive + column savers), academic/professional split; `CourseAdminController` additionally bundles assignments + course/subject request review.
3. **Root `NotificationController` ↔ `Admin\NotificationController`** — byte-for-byte identical logic; only the blade path differs.
4. **Login controllers** — `UserLoginController`, `InstituteUserLoginController`, `PlatformAdminLoginController` repeat the same lockout / 2FA-handoff / `recordFailedAttempt` / `throttleKey` scaffolding (~150/160/120 lines).
5. **`StudentAdminController`, `InstituteAdminController`, `CertificateAdminController`, admin `ClassAdmin`/`CourseAdmin`** — all repeat the same `{entity}_columns` preference pattern + `saveColumns`/`saveXColumns` endpoints (one JSON endpoint per table).
6. **Recycle/force-delete blocks** — `RecycleBinController::forceDelete`, `Admin\InstituteAdminController::deleteInstitute`, `Admin\CertificateAdminController::forceDelete` all re-implement the password-confirmation (`Hash::check`) + JSON/redirect branch.

## FormRequest coverage gap
- Only Student and OfflineSync have FormRequests. Every other mutation (Batch, Exam, Course sync/request, Institutes, Settings, Auth, Geo, Themes, Industry, AI, Certificate action) validates inline in the controller. This is a consistent, deliberate convention rather than an accident — but it means `authorize()`-style permission gating lives only in `StudentFormRequest` and route middleware.

## Models without a dedicated controller (gaps)
Models with **no** controller method today (some are write-side only, some fully unmanaged):
- **Finance/accounting:** `AccountHead`, `CashMemo` (writes go through `OfflineSyncService` → `OfflineSyncController::approve`), `Invoice`, `InvoiceItem`, `Installment`, `Membership` (viewed via `WorkspaceController::membersFor`/`InstituteAdminController::show`), `Payment`, `Transaction`, `SubscriptionPackage` (used read-only by `InstituteAdminController`).
- **Academic support:** `Attendance`, `ExamResult`/`ExamSubject`/`Result` (managed via `ExamController`), `ExamType`, `GradingScale`, `RegNoSequence` (internal), `Room` (referenced by `BatchController::show` loads), `Notice`, `GalleryAlbum`, `GalleryMedia`.
- **Identity/ops:** `AdministrativeUnit` (managed via `GeoImportController`/`GeoAdminController`), `InstituteSubscription` (subscription stats on institute), `LoginAttempt`, `AuditLog`/`ActivityLog`/`AiLog`/`AiUsage` (write-only), `LegacyUser`.
- **Notable:** no controller manages `CashMemo`/`Payment`/`Invoice` directly (the offline-sync approve path materializes cash memos); no CRUD for `Notice`, `Room`, `Attendance`, or `Gallery*`.

## Structural notes
- `/` dashboard is a single invokable dispatching on guard (`institute_user` / `web` / platform).
- Public (unauthenticated) surface: `VerifyCertificateController` (verification), auth flow, forgot/reset password, owner + institute registration.
- Multi-tenancy is enforced at the endpoint level by `tenant` middleware + model global scopes; controllers deliberately `withoutGlobalScope('institute')` only for the shared catalog (Courses/Subjects/Categories) and `nextBatchCode`.
- Branch scoping exists only for certificates (`BranchContext`); no other module is branch-gated in controllers at present.