# Project Memory

Long-term memory for the Monetix / AccumenAI project. Read this first in any new
session; keep it updated as decisions are made.

## What this project is

Monetix — "AccumenAI" — a multi-tenant education management platform (Laravel).
Three portals: **Platform Admin** (`platform_admin`), **Institute Staff**
(`institute_user`), and **Global accounts** (`web` / `User`).

See `docs/skill.md` for the full capability overview.
See `docs/promptRules.md` for AI assistant rules of engagement.

## Environment

- App root: `C:\xampp\htdocs\monetix`
- PHP (CLI): `& "C:\xampp\php\php.exe"` (e.g. `& "C:\xampp\php\php.exe" artisan serve`)
- MySQL: `C:\xampp\mysql\bin\mysql.exe -u root monetix` (root, no password)
- Test suite: `& "C:\xampp\php\php.exe" vendor\bin\phpunit` (currently 281 tests / 1300 assertions)
- Code style: `& "C:\xampp\php\php.exe" vendor\bin\pint <paths>`
- `docs/` is the documentation home (markdown below).

## Golden rules (from the user)

- **Pagination box must be centered on every page, for every user.** Count text
  ("149 courses") goes **below** the pagination box. Any new page with
  pagination must follow the centered-column footer pattern.
- **Institute staff must NOT manage their own SMTP / payment config** — that is
  admin-only. The user-facing Settings menu mirrors the admin Settings hub but
  without Mail & Payment.
- **All blue Bootstrap elements follow the active theme.** Buttons, dropdowns,
  nav-pills, pagination, links, focus rings. Native `<select>` *open* dropdown
  option highlight is OS-rendered and cannot be themed.

## How theming works (recent, important)

- Bootstrap ships **hardcoded `#0d6efd` custom properties at the class level**
  (`.btn-primary`, `.btn-outline-primary`, `.dropdown-menu`, `.nav-pills`,
  `.pagination`, `.list-group`). A `:root` override of `--bs-primary` alone does
  **not** reach them. `public/css/components.css` (loaded after Bootstrap)
  re-points those class-level vars to `var(--bs-primary)` / `--bs-primary-hover`
  / `--bs-primary-active` / `--bs-primary-rgb`.
- The theme block is `resources/views/layouts/partials/theme_colors.blade.php`,
  included by both `layouts/admin.blade.php` and `layouts/standalone.blade.php`.
- Theme resolution (composer in `AppServiceProvider`): PlatformAdmin →
  `Theme` model via `preference('theme_id')`; institute staff / web-membership →
  `institute_settings.primary_color` / `secondary_color`.
- Helpers: `mawa_hex_to_rgb()`, `mawa_darken_hex()` in `app/helpers.php`.
- CSS is cache-busted with `?v={{ File::lastModified(...) }}` — hard refresh
  (Ctrl+F5) when CSS "doesn't change".

## Admin Courses pages (recent)

- `admin/courses/index.blade.php` is the **Courses** list (SLP). The Subjects
  list is now its own SLP page at `admin/courses/subjects.blade.php`
  (`admin.courses.subjects`). Both render the shared `admin/courses/_tabs.blade.php`
  tab bar (Courses | Subjects | Course Assignment | Course Requests).
  Course rows are clickable (`table-row-click` + `data-row-href`) and open the
  course detail page.
- `admin/courses/show.blade.php` has two tabs: **Course** (details + Enrolled
  Students / Assigned Institutes) and **Subjects**. Back button is context-aware
  (index when arriving from the catalog, assignment otherwise).
- Route order matters: `admin/courses/requests` and `admin/courses/subjects`
  must be declared **before** `admin/courses/{course}` or they get shadowed.

## Show/hide column preferences (generic, reusable)

- **Generic mechanism for user-side (institute staff / students) tables:**
  `app/Http/Controllers/UiPreferenceController.php::saveColumns(Request)` stores
  `columns_<key>` in the user's `preferences` JSON via `setPreference()`.
  Route `POST ui/columns` (`ui.columns.save`) lives in the
  `auth:platform_admin,institute_user,web` group. Validates `key` against
  `/^[a-z0-9_.-]+$/` and `columns` against the whitelist.
- Controllers define a public const (e.g. `StudentController::STUDENT_COLUMNS`,
  `BatchController::BATCH_COLUMNS`) and pass
  `$visibleColumns = array_values(array_intersect(self::X_COLUMNS,
  (array) $user->preference('columns_x', self::X_COLUMNS)))`.
- Views: a "Columns" dropdown (candle-btn + `col-toggle-menu` / `col-toggle-item`
  in `public/css/components.css`), `data-col` on `th`/`td`, `style="display:none"`
  when hidden, and a `@push('scripts')` block that toggles cells by column index
  and fetches `route('ui.columns.save')` with `key` + visible list
  (`X-CSRF-TOKEN: '{{ csrf_token() }}'` inline — no meta tag in layouts).
- Currently wired: **Students** (`columns_students`) and **Batches**
  (`columns_batches`). Admin Course Assignment uses the same pattern but keyed
  `assignment_columns` with its own route (`admin.courses.assignment-columns`).
- Lang keys: `actions.columns`, `actions.show_hide_columns` (en + bn).

## Settings (institute user) — recent

- User Settings is now a standalone hub mirroring the admin one:
  `settings.index` (hub), `settings.account`, `settings.appearance`
  (PUT `settings.appearance.update`), Security → `account.security`.
- The old tabbed `settings/form.blade.php` and `UpdateInstituteSettingRequest`
  were deleted; `InstituteSettingController` was refactored
  (index/account/appearance/updateAppearance/resolveSetting).
- Mail & Payment was **removed from the user end** (routes, controller methods,
  view). Admin `admin.settings.mail-payment` (platform-wide `Setting::set`)
  is untouched.

## AI integration layer (new)

Built as a **shared, industry-aware, tenant-aware, RBAC-gated** AI engine on top
of the existing SaaS core. Configuration → runtime → request path:

- **Platform-level config**: `config/ai.php` (enabled/provider/model/tokens/
  temperature/global_instructions/logging/features) with env defaults. The
  Super Admin can override at runtime via `Setting::get/set('ai.*')` on
  `admin.settings.ai` (`AiSettingController` → `admin/settings/ai.blade.php`).
  `App\Support\AiConfig` reads settings first, falls back to config. **Platform
  switch is OFF by default** (`AI_ENABLED=false`).
- **Per-institute toggle**: `institute_settings.ai_config` JSON (enabled,
  features[], daily_limit, monthly_limit). Managed on `admin.institutes.edit`
  (checkboxes) and persisted in `InstituteAdminController::update`. 0 = unlimited.
- **Permission slugs**: `ai.assistant`, `ai.analytics`, `ai.content`,
  `ai.reports`, `ai.automation` seeded to institute-owner/admin by the
  idempotent migration `2026_08_15_000300_add_ai_permissions.php`.
- **Routes** (`routes/web.php`):
  - `GET/POST ai/assistant` in `auth:institute_user,web` + `tenant` +
    `ai.enabled:assistant` + `permission:ai.assistant`
    (`Ai\AiAssistantController` → `ai/assistant.blade.php`, fetch-based UI,
    `X-CSRF-TOKEN` header inline).
  - `GET/POST admin/settings/ai` in `auth:platform_admin`.
- **Middleware**: `EnsureAiEnabled` (alias `ai.enabled` in `bootstrap/app.php`)
  checks platform switch + institute toggle + requested feature, else 403.
- **Provider abstraction**: `Contracts\AiProvider` → `OpenAiProvider`
  (`Http::withToken`, `/chat/completions`, tool_choice auto). Bound as a
  singleton in `AppServiceProvider::register()`.
- **Tools**: `Contracts\AiTool` (name/description/parameters/permission/feature/
  mode/handle) + `AiToolRegistry`. Tools are declared per-industry in
  `config/ai-tools.php` (education first: get_students, get_courses,
  get_batches, get_course_enrollment, get_exam_results, get_attendance,
  get_fees, get_certificates — all **read-only**). A tool is offered only if it
  belongs to the tenant's **industry** (or the shared `core` list in
  `ai-tools.php`), the tenant's features include its feature, AND the actor has
  its permission (owner is superuser). `AiContext::resolve()` builds the
  immutable per-request snapshot (actor, institute, industry, aiEnabled,
  enabledFeatures, permissions). `AbstractAiTool` guards on an active
  `TenantContext` before touching any model.
- **Execution**: `AiService::ask()` enforces, in order: platform switch →
  institute `aiEnabled` → `assistant` feature → usage limits (`AiUsageTracker`,
  ai_usage, daily+monthly upsert) — each failing path returns `status: blocked`
  with a safe message. Then it loops tool rounds (≤ `max_tool_rounds`),
  accumulating tokens across rounds, sanitising provider/tool errors to a
  generic client message (full detail via `Log::error`), and appends an
  `AiLanguage::instruction()` line to the system prompt. Audit row to `ai_logs`
  via `AiLogger` (prompt stored only when `store_prompts` is on; prompts and
  errors are redacted via `AiLogger::redact()` — `sk-*`, `Bearer`, password/
  secret/api_key/token=, `AKIA*`).
- **Design decisions per the AI spec §20**: write-mode tools are not
  implemented — any future write tool must set `mode() = 'write'` and go
  through explicit user confirmation before it can be auto-executed.
- **Schema facts the education tools rely on (verified Aug 2026)**: `attendance`
  uses `class_date` (+ `leave` status); `invoices` use `invoice_number`, status
  enum `unpaid/partial/paid/cancelled`, `created_at` (no `invoice_date`), and
  "overdue" is derived (`status in unpaid/partial` AND `due_date < today`);
  `certificates` use `certificate_number`, status enum
  `pending/active/rejected/revoked`; `students.full_name` is a generated column;
  `student_enrollments`/`results` group-by-course aggregation is done in SQL
  (`join courses` + `groupBy courses.name`) to avoid loading all rows.
- Sidebar: AI Assistant link appears for institute staff only when `$aiEnabled`
  (composer share) — platform switch on + institute toggle on + `assistant`
  feature + `ai.assistant` permission.

## Branch authorization (new)

Tenant → Branch → User → Role → Permission. A reusable, data-driven layer that
both the existing modules and the AI engine inherit — no redesign.

- **Data model**: `branches` belong to `institutes`. Direct `branch_id` exists on
  `batches`, `institute_users`, `institution_user`, `notices`, `rooms`,
  `students`, `transactions`. Indirect (via `student`/`batch`): `attendance`,
  `results`, `invoices`, `payments`, `cash_memos`, `certificates`,
  `installments`, `student_enrollments`. `courses`/`subjects` are institute-wide
  catalogs (no branch).
- **Rule**: a user is restricted to their `institute_users.branch_id`; `NULL` =
  all branches (owner → all, admin → configurable, branch-manager/teacher/etc. →
  own branch). No role-name checks.
- **`App\Support\BranchContext`** mirrors `TenantContext` (`set/id/enabled/
  clear`; `enabled()` = id !== null). `SetTenantContext` middleware sets it from
  the authenticated InstituteUser's `branch_id` (and clears it otherwise).
- **`App\Models\Concerns\BranchScoped`** — global scope keyed **`branch`**
  (bypass via `withoutGlobalScope('branch')`, same pattern as `'institute'`).
  Applied to: Student, Batch, Room, Notice, Transaction, InstituteUser.
- **Web**: `CertificateController::index` filters via `whereHas('student', …)`;
  direct modules filter automatically via the global scope (e.g. branch manager
  sees only own-branch students on `/students`, dashboard counts, finance).
- **AI**: `AiContext` carries `branchId` (from `$actor->branch_id`),
  `AbstractAiTool::branchId($context)` helper. All education tools + core
  `get_income_expense` apply an explicit branch filter (direct `branch_id`, or
  `whereHas('student'/'batch')` for indirect tables) so behaviour is identical
  with or without middleware. `CoursesTool` is intentionally NOT scoped (catalog).
- Schema gotchas re-hit: `students.full_name` is a generated column AND the
  Student model defines a `getFullNameAttribute` accessor, so tools must select
  `first_name`/`last_name` (never `full_name`) or the name comes back empty;
  `batches.start_date` is an uncast string (parse with Carbon).
- Covered by `tests/Feature/BranchAuthorizationTest.php` (scope, middleware
  web behaviour, owner-vs-manager, AI context propagation, students/
  batches/income-expense/attendance tool filtering). Base `TestCase::setUp`
  clears both `TenantContext` and `BranchContext`.

## Academic Engine (Education Engine Steps 6–11)

A full education workflow on top of the core catalog/students/batches: global
structure → institute customization → placements/subject selection →
assessments/marks → aggregation → grading → final results → promotions.

- **Global academic structure (admin)** — `admin.education.academic.*`
  (`Admin\AcademicStructureAdminController`, routes under `admin/academic/*`):
  hierarchy `Country → EducationSystem → AcademicLevel → ClassGrade →
  AcademicGroup`, all toggle-able. `Admin\AcademicSubjectAdminController`
  manages the global academic `Subject` catalog, `SubjectAcademicAssignment`
  (per class: `requirement_type` mandatory/optional/selection_group,
  `selection_group_id`, `display_order`, `status`) and
  `AcademicSelectionGroup`. `Admin\AcademicGradingAdminController` manages
  global `GradeScale` + `GradeScaleRow`. Admin sidebar shows Academic
  Structure / Academic Subjects / Grade Scales.
- **Institute academic structure** — routes under
  `auth:institute_user,web` + `tenant` + `permission:education.manage`
  (`settings.academic.*`). `AcademicStructureController` renders the global
  structure with institute overrides (`InstituteAcademicLevel`,
  `InstituteClassGrade`, `InstituteAcademicGroup` — label/override rows) and
  custom labels. `AcademicSubjectController` lists institute-effective
  subjects (global + `InstituteSubject` overrides). **`AcademicSubjectService::effectiveClasses($institute)`
  is the single source of the institute's effective class list** (country
  defaults + overrides) used by every downstream module.
- **Academic years & placements** — `AcademicYear` (institute-owned, one
  `is_current`, `code` unique per institute), `StudentAcademicPlacement`
  (unique `(student_id, academic_year_id)`; statuses
  active/completed/transferred/dropped; branch-scoped `inScope()`),
  `StudentSubjectSelection` (per-placement subject picks).
  `StudentAcademicPlacementController` (SLP index + create/show/edit/update/
  destroy + academic-year CRUD, AJAX `placements/subjects` re-renders the
  shared `_subjects` grid), `StudentAcademicPlacementService` (context
  validation + `storePlacement` enforcing year/class/group + the
  `StudentSubjectSelectionValidator`). Deleting a placement or year is
  **blocked** once it holds final-result or promotion history
  (`placementHasHistory` / `academicYearHasHistory`).
- **Assessments & marks** — `AssessmentType`/`Component` (seeded by
  `AcademicAssessmentSeeder`), `AcademicAssessment` (per year/class/group),
  `AssessmentSubject` (per-subject config), `AssessmentSubjectComponent`
  (`full_mark`/`pass_mark`/`mandatory_pass`), `AcademicStudentMark` (per
  student×subject×component). `AcademicAssessmentController` + marks entry
  via `AcademicMarksController` (`assessments/{assessment}/marks/{subject}`),
  `AcademicAssessmentService`.
- **Aggregation schemes (Step 8)** — `AcademicResultAggregationScheme` (per
  year/class/group) + `AcademicResultAggregationItem` (assessment + weight).
  `AcademicResultAggregationService::store()` / `subjectAggregate()` derives
  per-subject aggregates across assessments. `AcademicAggregationController`
  (SLP + per-subject preview).
- **Grade scales** — global `GradeScale`/`GradeScaleRow` (admin) + institute
  overrides (`AcademicGradingController`, `grading.preview`). `AcademicGradingService`
  resolves band → grade/grade_point/PASS/FAIL from `is_pass`, `gpa_mode`
  (credit_weighted | equal_weight), `optional_subject_gpa`.
- **Final results (Step 10)** — `AcademicFinalResultPolicy` (per scheme) +
  `AcademicFinalResult` lifecycle **review → approved → locked → published**.
  Locking materializes the frozen snapshot `academic_final_result_students` +
  `academic_final_result_rows` computed by `AcademicFinalResultService` from
  Step-8 aggregates + grading (never a live recalculation). At most one
  in-flight result per policy (`AcademicFinalResultLifecycleService`); only a
  **published** result can feed promotions. `AcademicFinalResultController`.
- **Promotions (Step 11)** — `PromotionPolicy` + `PromotionPolicyRule` →
  `PromotionDecision` (**pending → review → approved**) +
  `PromotionDecisionItem` (per student verdict + `reasons` + target
  class/group + `next_placement_id`). Services:
  - `PromotionPolicyService` — closed rule enum (`overall_pass`,
    `gpa_threshold`, `max_failed_subjects`, `mandatory_pass`, `conditional`),
    controlled operators, actions promoted/conditional/repeat/not_promoted/
    completed/graduated; institute ownership re-checked server-side.
  - `PromotionEvaluationService` — read-only, consumes the published snapshot
    via `inputForStudent` (gpa/passed/failed/incomplete/mandatory); each rule
    yields pass_action/fail_action; the **most severe action wins** (severity
    ladder), so multi-branch policies are built from several ordered rules.
  - `PromotionLifecycleService` — `createDecision` accepts **only a PUBLISHED
    final result** (never in-flight), one in-flight decision per result
    (`lockForUpdate`); `review`/`sendBackToReview`/`approve` (terminal).
  - `PromotionPlacementService` — approve creates a **new** next-year
    `StudentAcademicPlacement` (never updates/deletes the source), carries
    forward optional subjects that still exist in the target class/group,
    re-runs the authoritative selection validator, and links
    `next_placement_id` for the history chain.
  - Permission `promotion.manage` (owner + admin, migration
    `2026_08_18_110400_add_promotion_manage_permission`). The Settings hub
    Promotions link renders only for actors with that permission.
- **Security/tenancy** — every academic model uses `TenantScoped`; branch
  scope is a `whereNull('branch_id')->orWhere('branch_id', ctx)` style like
  `PromotionPolicy`/`PromotionDecision`/`AcademicFinalResult`. Institute
  identity comes **only** from the authenticated `InstituteUser` / workspace
  `Membership` (`resolveInstitute()`), never from request input; forged
  institute/year/class/group ids are rejected. Route gates:
  `settings/academic/*` = `education.manage`,
  `settings/academic/promotions/*` = `promotion.manage`.
- **Views** live under `resources/views/institute/academic-*`
  (placements/assessments/aggregations/grading/final-results/promotions);
  admin ones under `resources/views/admin/academic/*`. All module entry
  points are linked from the **Settings hub** (`settings.index`).
- **Tests**: `AcademicStructureTest`, `AcademicSubjectsTest`,
  `AcademicAssessmentTest`, `AcademicResultAggregationTest`,
  `AcademicGradingTest`, `AcademicFinalResultTest`,
  `AcademicPromotionTest`, `StudentAcademicPlacementTest`.

## Standard list page pattern (SLP)

The Course Assignment page is the **canonical list view** — when asked to build
a "standard list page", replicate it 1:1. Full spec:
**`docs/standard-list-page.md`**. Key conventions:

- Anatomy: `.page-header` → `.filter-card` (one GET form) → `.admin-card`
  (`.table-toolbar` + table + centered pagination) → `.print-only` table.
- Filter form: single `.filter-search-row`, `align-items-end`, all controls
  `*-sm`, equal-width search + main field via `flex:1 1 0; min-width:180px`,
  Search (`btn-sm`) + Reset (plain link). Custom searchable dropdown =
  `.inst-dropdown` (text input + hidden value + `.inst-list`).
- Table: `col-handle` (drag), `col-check` (select-all/row-check), data columns
  with `data-col="<col>"` + inline `@if(!in_array(..., $visibleColumns, true))
  style="display:none"`, last `col-action`. Badges via `$statusBadge` map.
- Toolbar: Columns dropdown (`.col-toggle-check`, `data-col`, persists via
  POST `*-columns` → user `preferences` JSON) + Print (`window.print()`) +
  CSV/Excel export (client-side from current table, skips handle+checkbox).
- **Print**: a `.print-only` table `#<page>TablePrint` renders the **full
  filtered dataset** (`allItems` from the controller), data columns only, with
  the same `data-col` + `$visibleColumns` flags (show/hide applies to print);
  `@media print` hides topbar/sidebar/filters/toolbar/pagination + the screen
  table wrapper, resets `.content`/`.admin-card`, keeps only the page header +
  print table.
- JS (one IIFE): select-all, HTML5 drag reorder (FLIP + springy
  `cubic-bezier(.2,.85,.35,1)`, lift `translateY(-4px) scale(1.1)`, left
  `border-left:4px var(--bs-primary)`), column toggle **mirrored into the
  print table**, `.inst-dropdown`, `exportTable()`.
- Controller: shared `$query` (filters via `->when(query)`, `orderBy`), then
  `paginate(20)->withQueryString()` + `(clone $query)->get()` for print;
  `visibleColumns = preference('<key>_columns', self::COLUMNS)` intersect;
  pass `items/allItems/visibleColumns/filters`. `saveColumns` POST endpoint.
- After editing Blade always `artisan view:clear`.

## Page Marker (temporary DEV tool)

Created so the user can reference any screen in chat and have it located
instantly — they say "fix page 12" / "check popup 104" and the number is traced
back to its page via the registry.

**Chat shorthand**: at the start of a message, the user references a marker
number with a sign suffix — `N-` = **page**, `N+` = **popup/modal** (e.g. `18-`,
`104+`). Resolution rules from `storage/app/page-markers.json`:
- Look up the number in both the `page:` and `popup:` namespaces.
- If they used `+` (popup) but the number is only registered as a page — OR used
  `-` (page) but it's only a popup — silently correct the sign and proceed with
  the one that exists.
- If BOTH a page and a popup carry the same number, don't guess — ask which one
  they mean.
- If only one exists, state the resolved route/modal id at the start of the reply
  so they can verify.

- **What it does**: every page and every popup (modal) shows a unique 2–3 digit
  badge pinned top-center. Page badges are yellow (`page-marker`, page of the
  view), popup badges are red (`page-marker-modal`, inside the `.modal-dialog`),
  `pointer-events: none` so they never block clicks.
- **Why**: description/indexing without codenames. The number → page/modal map
  lives in `storage/app/page-markers.json` (keyed e.g. `page:students.index` /
  `popup:editStudentModal`), so any badge number resolves instantly to its route
  name / modal id. Grep that file when the user quotes a number.
- **Implementation**:
  - `app/Support/PageMarker.php` — sequential unique registry. Pages start at 10,
    popups at 100. Atomic write to `storage/app/page-markers.json`.
  - `routes/web.php` — `GET dev/page-marker?key=` (popup lookup for `shown.bs.modal`
    JS) and `POST dev/page-marker/toggle` (navbar switch).
  - `resources/views/partials/page_marker.blade.php` — badge markup + inline CSS
    + modal JS hook (fetches number once per modal, caches, no duplicates).
    Included right after `<body>` in `layouts/admin` and `layouts/standalone`.
    Skips rendering entirely when the switch is OFF.
  - Show/hide: a signpost button in the **admin navbar** (topbar, before the
    dark-mode toggle) posts to `admin.dev.page-marker.toggle`. The switch persists as
    platform `Setting` `dev.page_marker_enabled` (`'1'` default, `'0'` off);
    `PageMarker::enabled()/toggle()` wrap it. The button shows **only to super
    admin** (`@auth('platform_admin')` + the route is inside the
    `auth:platform_admin` admin group), so institute users never see it. Button
    shows ON (yellow filled icon) vs OFF (plain icon).
- **Scope**: covers both layout shells (admin app + standalone settings/auth).
  `auth/*login` pages use an inline template that does NOT swipe the layouts, so
  they have no badge (expected).

### Delete checklist (when development is done)
`app/Support/PageMarker.php`, the two `dev/page-marker*` routes, the two `@include('partials.page_marker')` lines, the navbar toggle button, `resources/views/partials/page_marker.blade.php`, `Setting` key `dev.page_marker_enabled`, and `storage/app/page-markers.json` (gitignored).

## Rendering/verification pattern

Views can be rendered headlessly from `public/` temp scripts:

```php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
View::share('errors', new Illuminate\Support\ViewErrorBag());
Auth::shouldUse('platform_admin');
Auth::loginUsingId(1);
echo view('some.view', [...])->render();
```

- Always `artisan view:clear` after editing blade.
- Delete the temp `public/_*.php` file afterwards.
- `View::shared('themePrimary')` does NOT reflect per-view composer data — a
  known false-negative when probing theme values.

## Gotchas

- `.table-row-click` is only a CSS hover style; row navigation needs the
  delegated `[data-row-href]` click handler (see `admin/courses/index.blade.php`).
- Native `<select>` dropdown option highlight is OS-rendered (un-themable).
- `dashboard/home.blade.php` stat-card icon colors are an intentional
  multi-color palette — do not theme them.
- `public/css/pages.css` is for public marketing pages only, NOT the admin shell
  (see `docs/design-conventions.md`).

## Power-outage / interrupted-command recovery

Electricity can drop mid-command. Code/file edits are safe (plain text on disk);
the only real risk is **database commands** (MySQL DDL is non-transactional, so a
migration can apply partially and leave `migrations` inconsistent). Workflow that
keeps sessions recoverable:

- **Before any dev-DB migration**: `php artisan migrate --pretend` to preview,
  then snapshot `mysqldump -u root monetix > <temp>\backup_monetix.sql`.
- **Test DB is self-healing**: `DatabaseTransactions` + `vendor\bin\phpunit`
  rolls everything back, so a power cut there just means re-running tests.
- **Keep migrations idempotent** — guard with `Schema::hasColumn()` /
  `Schema::hasTable()` so re-running never crashes.
- **Git commit before risky commands** (migrations/refactors); a power cut mid-run
  is then just `git checkout` away.
- **After a cut**: `php artisan migrate:status` to see what applied, then re-run
  the remaining migration(s). `artisan view:clear` after any interrupted Blade
  compile.
- Hardware: a small UPS (~150–300 VA) buys time to finish/abort a running command.
- New schema is always applied in this order: migration file → `--pretend` → dev
  DB (`php artisan migrate`) → test DB (`monetix_test` via the temp-script
  pattern) → run tests.

## Change log (recent)

- **Academic Promotions (Step 11)**: `PromotionPolicy` + closed-enum
  `PromotionPolicyRule` (overall_pass / gpa_threshold / max_failed_subjects /
  mandatory_pass / conditional; controlled operators; actions promoted /
  conditional / repeat / not_promoted / completed / graduated) →
  `PromotionDecision` (pending → review → approved) + per-student
  `PromotionDecisionItem`. `PromotionLifecycleService` accepts **only a
  PUBLISHED final result** as source (one in-flight decision per result);
  `PromotionEvaluationService` derives verdicts from the frozen snapshot via a
  severity ladder (most severe action wins); `PromotionPlacementService`
  creates **new** next-year placements (source never mutated, subject
  selections revalidated against the target class/group, carried-forward
  optionals, `next_placement_id` history link). New `promotion.manage`
  permission (owner + admin) and `settings.academic.promotions.*` route group.
  The Settings hub now shows a **Promotions** link only to actors with
  `promotion.manage` (new `$canPromote` view var). Placement/academic-year
  deletion is blocked once final-result or promotion history references them.
  Views `institute/academic-promotions/{index,policy-form,policy,decision}`.
  Covered by `tests/Feature/AcademicPromotionTest.php` (18 tests).
- **Academic Final Results (Step 10)**: `AcademicFinalResultPolicy` (per
  aggregation scheme) + `AcademicFinalResult` lifecycle review → approved →
  locked → published. Locking materializes the **frozen snapshot**
  (`academic_final_result_students` + `academic_final_result_rows`) computed
  by `AcademicFinalResultService` from Step-8 aggregates + grading — never a
  live recalculation. `AcademicFinalResultLifecycleService` keeps one
  in-flight result per policy; only published results feed promotions.
  `AcademicFinalResultController` + views
  `institute/academic-final-results/{index,policy,show}`. Covered by
  `tests/Feature/AcademicFinalResultTest.php`.
- **Academic grading + aggregation**: global `GradeScale`/`GradeScaleRow`
  (admin) with institute overrides (`AcademicGradingController`, live
  `grading.preview`), `AcademicGradingService` resolving band → grade /
  grade_point / PASS-FAIL with credit-weighted / equal-weight GPA modes.
  `AcademicResultAggregationScheme` + `AcademicResultAggregationItem`
  (assessment weights) with `AcademicResultAggregationService::store()` /
  `subjectAggregate()`; `AcademicAggregationController` SLP + per-subject
  preview. Tests: `AcademicGradingTest`, `AcademicResultAggregationTest`.
- **Academic assessments & marks**: `AssessmentType`/`Component` (seeded),
  `AcademicAssessment` (per year/class/group) + `AssessmentSubject` +
  `AssessmentSubjectComponent` (full/pass marks, mandatory pass) and
  `AcademicStudentMark` entry per student×subject×component.
  `AcademicAssessmentController` + `AcademicMarksController` + service. Test:
  `AcademicAssessmentTest`.
- **Academic placements + subject selection**: `AcademicYear` (institute,
  one current), `StudentAcademicPlacement` (unique student×year) +
  `StudentSubjectSelection`, `StudentAcademicPlacementController` (SLP + AJAX
  subject grid) + `StudentAcademicPlacementService` +
  `StudentSubjectSelectionValidator` enforcing mandatory / optional /
  selection-group rules of the institute-effective curriculum. Test:
  `StudentAcademicPlacementTest`.
- **Academic structure + subjects**: global `Country → EducationSystem →
  AcademicLevel → ClassGrade → AcademicGroup` (admin `academic.*` routes)
  with per-institute overrides/labels (`InstituteAcademicLevel` /
  `InstituteClassGrade` / `InstituteAcademicGroup`, `InstituteSubject`
  overrides) gated by `permission:education.manage`. `SubjectAcademicAssignment`
  ties subjects to classes (mandatory/optional/selection-group) +
  `AcademicSelectionGroup`. `AcademicSubjectService::effectiveClasses()` is the
  single source for downstream modules. Tests: `AcademicStructureTest`,
  `AcademicSubjectsTest`.
- **Page Marker dev tool**: badges on every page/popup (unique 2–3 digit number,
  top-center) + a navbar toggle to switch the whole system on/off. Full details +
  delete checklist in the "Page Marker" section above.
- **"column header filter" command** (was "column header list", see
  `docs/customcommand.md`): every list-table column header can carry a funnel
  filter button. Reusable contract — `th[data-header-filter]` with types
  `options` (`data-filter-param` + JSON `data-filter-values`/`labels`), `sort`
  (Oldest/Latest, or Eldest/Youngest via `data-filter-mode="age"`), `date`
  (Older/Later composing `<param>_before`/`<param>_after`) — driven by
  `public/js/column-filters.js` (loaded in `layouts/admin`) + CSS in
  `public/css/components.css`. Filters are URL-query params, so they survive
  the Columns show/hide toggle (visibility never clears a filter). Wired into
  the user Classes & Courses lists (`ClassController`/`CourseController`):
  index (category_id, mode, status, sort oldest/latest, created_before/
  created_after), subjects (category_id, status), batches & archive (course_id,
  shift, start date older/later, sort by start_date). View data gained
  `filterCategories`, `filterModes`, `filterShifts`.
- **"data backup" command**: whenever the user says **data backup**, run the
  `mysqldump` of the `monetix` database into `backup database\monetix.sql` in
  the project root (exact command in `docs/customcommand.md`). Recorded so it
  persists across sessions.
- **AI API configuration in Super Admin Settings** (`admin/settings/ai` +
  an in-hub "AI" tab on `admin.settings.index`): provider (OpenAI), base URL,
  model, temperature, max output tokens, request timeout, global instructions,
  response language (auto/en/bn), platform daily/monthly usage caps, prompt-log
  toggle. API key is now **encrypted at rest** (`Setting` model encrypts the
  `ai.api_key` value with the app key; legacy plaintext still decrypts), is only
  ever shown as "API Key: Configured", and is replaced (never displayed) via a
  blank-to-keep password field. New `POST admin/settings/ai/test` connection
  test (safe generic error, never logs the key/exception). Audit events recorded
  in `audit_logs` (settings updated / key replaced / connection test) with safe
  metadata only. `AiConfig` gained `timeout`/`responseLanguage`/`dailyLimit`/
  `monthlyLimit` + setting-aware `features`; `AiLanguage::instructionFor()`
  honours a pinned language while keeping `auto` detection; `AiUsageTracker`
  treats platform limits as a hard cap over per-institute limits; hub tab JS
  now mirrors the active pane into the URL hash so `back()` reopens it. All
  AI routes sit in the existing `auth:platform_admin` admin group (server-side
  enforcement for non-super-admins). New `tests/Feature/AiSettingsTest.php`
  (12 tests). **139 tests / 545 assertions green.**
- **Custom commands doc**: added `docs/customcommand.md` — the user's natural-
  language commands mapped to exact actions ("make a standard list view",
  single-line filters, 50-50 equal-width, match heights/keep consistent,
  list/row padding tweaks, drag-and-drop visual commands, print-all + print
  respects columns, undo Nx, AI audit/industry tools, branch authorization).
- **Standard List Page pattern (SLP)**: elevated the Course Assignment page into
  the canonical list-page template. Course Assignment now prints the **full
  filtered dataset** (new `allCourses` = `(clone $query)->get()`, dedicated
  `.print-only` `#assignmentTablePrint` table) instead of just the visible page,
  and the print table respects the show/hide column preference (shared
  `data-col` + `$visibleColumns`, JS toggle mirrors into the print table).
  Added `docs/standard-list-page.md` (full spec: anatomy, filter form, toolbar,
  columns/`data-col` contract, pagination, print rules, JS behaviors, backend
  contract, build checklist) and a memory section. Future "standard list view"
  requests should replicate this page 1:1.
- **Branch authorization model**: `BranchContext` (static, mirrors
  `TenantContext`) + `BranchScoped` model concern (global scope key `branch`,
  bypassable via `withoutGlobalScope('branch')`) added to Student, Batch, Room,
  Notice, Transaction, InstituteUser. `SetTenantContext` now sets/clears the
  branch context from the user's `branch_id` (NULL = all branches; data-driven,
  no role-name logic). Web: `CertificateController` filtered via
  `whereHas('student')`. AI: `AiContext` gains `branchId`, `AbstractAiTool::branchId()`
  helper, and every education tool + core `get_income_expense` applies an
  explicit branch filter (direct `branch_id`, or `whereHas('student'/'batch')`).
  Fixed latent bugs surfaced by the new tests: `StudentsTool` must select
  `first_name`/`last_name` (the `full_name` DB column is shadowed by the model
  accessor → empty names), `BatchesTool` `start_date` is an uncast string.
  `tests/TestCase` now clears both contexts; new
  `tests/Feature/BranchAuthorizationTest.php` covers scope filtering, web
  branch-manager vs owner, AI context propagation, and tool-level branch
  isolation. **127 tests / 486 assertions green.**
- **AI Phase 2 (industry tool layer)**: inspected the live schema/code for Real
  Estate, Transportation and Restaurant — **no domain tables exist** (no
  properties/vehicles/menu/orders/...), so per the "do not fabricate" rule no
  industry-specific tools were invented. The genuinely shared business data
  (general ledger `transactions` + `account_heads` + `branches`) is now exposed
  through one **core** tool `get_income_expense` (income/expense/net, branch +
  date-range + type + account-head filters, group by head/month, BDT, compact
  SQL aggregation). `config/ai-tools.php` wires `real_estate`, `transportation`
  and `restaurant` as empty industry lists (core-only) with commented slots;
  industry isolation verified by tests (each new industry gets core tools only,
  never education tools). New `tests/Feature/AiCoreFinanceTest.php` covers
  aggregation, grouping, branch filter, tenant isolation, spoofed `institute_id`
  ignored, empty/invalid input, date range, limit, RBAC (`finance.view`), the
  platform/institute gates, and English/Bangla/Banglish prompts. Registry count
  for education owner is now 9 (8 + core). **117 tests / 466 assertions green.**
- **AI layer audit round**: rewritten `AiToolRegistry` (industry filter + shared
  `core` list; `available()` now resolves before filtering), `AiService`
  platform/institute/feature gates → `status: blocked`, token accumulation,
  sanitised errors + redacted `ai_logs`, `AiLanguage` Bangla/Banglish/English
  detection, `AiContext` gains `aiEnabled`/`modules`, `AttendanceTool` fixed to
  `class_date`/`leave`, `FeesTool` rewritten for the real invoice schema +
  derived overdue, `CertificatesTool` fixed to `certificate_number` + real
  status enum, SQL-side group-by-course aggregation for enrollment/results.
  New tests: `tests/Unit/AiLanguageTest.php` + 9 new Ai integration tests
  (access gates, industry isolation, core tools, redaction, tenant isolation,
  realistic business questions). **106 tests / 415 assertions green.**
- **AI integration layer**: platform `Setting`-overridable AI config, per-
  institute `ai_config` toggles + usage limits, new `ai.assistant/analytics/
  content/reports/automation` permissions (owner+admin), `AiProvider`
  abstraction + OpenAI client, `AiToolRegistry` + 8 read-only education tools,
  `AiService` tool loop with `ai_logs` audit + `ai_usage` counters,
  `EnsureAiEnabled` middleware (`ai.enabled`), `GET/POST ai/assistant` chat UI +
  sidebar link (gated), `admin.settings.ai` page. Covered by
  `tests/Feature/AiIntegrationTest.php`.
- Theme color propagation across the whole project (buttons/dropdowns/pills/
  pagination/selects follow the active theme).
- Admin Courses page: clickable rows → dedicated course page; 2 tabs (Courses |
  Subjects) on the index; 2 tabs (Course | Subjects) on the detail page.
- Institute Settings redesigned as hub; Mail & Payment removed from user end.
- Pagination centered on all pages with count text below the box.
- Course Assignment show/hide column settings persist per admin user across
  sessions via the `preferences` JSON column (`assignment_columns` key) —
  POST `admin.courses.assignment-columns` (`saveAssignmentColumns`).
- Generic user-side column visibility: `UiPreferenceController::saveColumns`
  (POST `ui/columns`, key `columns_students` / `columns_batches`) on the
  Students and Batches pages, mirroring Course Assignment.
- Admin dashboard now has a "Course Requests" panel listing the latest pending
  course requests (institute, course, requested by, date) with a review link —
  `DashboardController::platformDashboard()` passes `pendingCourseRequests`
  (last 10 pending, `course_requests.status = pending`). Covered by
  `tests/Feature/AdminActionsTest.php::test_dashboard_shows_pending_course_requests`.
- Course Requests page (`admin/courses/requests.blade.php`) rebuilt as a
  **Standard List Page (SLP)** per `docs/standard-list-page.md`: filter-layout
  form (q search + searchable institute dropdown + status), toolbar with
  Pending/Total badges + Columns/Print/CSV/Excel, drag-reorder + column-toggle
  (mirrored to print table), centered pagination. Backend: `requests()`
  (filters q/institute_id/status, `allItems` for print) + `saveRequestsColumns()`
  (POST `admin.courses.requests-columns`, `requests_columns` preference).
  `REQUESTS_COLUMNS` const in `CourseAdminController`. Covered by
  `test_requests_page_filters_by_status_and_search` +
  `test_requests_columns_preference_is_saved` in `AdminActionsTest.php`.
- All DB columns of `course_requests` are available as toggleable Columns:
  Institute, Course, Requested by, Status, Requested at, Review note,
  Reviewed by, Reviewed at, Updated at, Action (dropdown + table + print
  table, mirrored). Added `reviewed_at => 'datetime'` cast to
  `CourseRequest` so `->format()` works in the blade.
- Course section unified under one tabbed area: new `admin/courses/_tabs.blade.php`
  partial (Courses | Subjects | Course Assignment | Course Requests, links with
  count badges). The Courses index, Assignment, and Requests pages each render it
  (active tab per route). Sidebar now has a single "Courses" item active on any
  `admin.courses.*` route (Assignment/Requests links removed). Tab badges get
  counts from each controller (`coursesCount`, `subjectsCount`,
  `assignmentCount`, `requestsCount`). Covered by
  `test_course_pages_share_tab_navigation` in `AdminNavTest.php`.
- **Serial no. column added to every SLP list** (order: handle → checkbox →
  serial → data → action; content N+1 = `firstItem() + loop->index` on screen,
  `loop->iteration` in the print table) and it's included in the show/hide
  Columns toggle (`data-col="serial"`, label `#`, first in the COLUMNS const +
  dropdown). Applied to: `admin/courses/index`, `admin/courses/assignment`,
  `admin/courses/requests`, `students/index`, `batches/index`. Spec updated in
  `docs/standard-list-page.md` and `docs/customcommand.md`.
- **Subjects now a Standard List Page** (new): dedicated route
  `admin.courses.subjects` (GET) + `admin.courses.subjects-columns` (POST),
  `subjects()` + `saveSubjectsColumns()` in `CourseAdminController`,
  `SUBJECTS_COLUMNS` = `serial, name, code, type, category, institute, status`
  (no action column — no subject detail route). New view
  `admin/courses/subjects.blade.php` (full SLP: filter q/category/institute/status,
  toolbar Columns/Print/CSV/Excel, `subjects_columns` preference, print table).
  Tabs partial's Subjects link now targets `admin.courses.subjects`; the subjects
  pane was removed from `admin/courses/index.blade.php` (index now passes only a
  `subjectsCount` count for the badge). Covered by
  `test_subjects_page_filters_by_search_and_status` +
  `test_subjects_columns_preference_is_saved` in `AdminActionsTest.php` and the
  updated `test_course_pages_share_tab_navigation` in `AdminNavTest.php`.
- **Admin no longer sees notifications from its own actions** in the notification
  bell / panel. `NotificationCenter::visibleQuery()` and `isVisible()` now exclude
  notifications where `created_by_type = 'platform_admin'` and `created_by_id` =
  the current admin (e.g. "Certificate revoked", course request approved/rejected,
  institute suspended/activated). The institute-scoped notification is still
  created and stays visible to the **respected user** (the institute user). Also
  excludes rows where `created_by_type`/`created_by_id` are NULL to keep
  legacy/system notifications visible. Covered by
  `test_admin_does_not_see_own_action_notification` in `AdminActionsTest.php`.
- **Fixed duplicate flash "Certificate revoked." on admin pages**: `layouts/admin.blade.php`
  already renders `session('status')` globally (top of `<main>`), and several admin
  views ALSO rendered it in their own `@section('content')`, showing the flash
  twice. Removed the per-page `@if (session('status'))` blocks from
  `admin/certificates/index`, `admin/courses/requests`, `admin/courses/subjects`.
  Pages keep a single global flash; the layout is the single renderer.
- **Smooth flash animation**: `[data-auto-dismiss]` alerts now slide+fade in
  (`monetix-flash-in` keyframes, ease-in-out) and collapse their height/padding/
  margin to 0 before removal (`.is-collapsing` transition) so the page content
  below eases up instead of jumping when the flash dismisses. **Centralized
  app-wide**: CSS in `public/css/base.css`, behavior in `public/js/flash.js`,
  loaded by `layouts/admin`, `layouts/standalone`, and every `auth/*` page
  (per-view inline auto-dismiss scripts removed). Fade happens in the last 600ms.
- **Certificate "Cancel Revoke" action**: admin certificates list action column now
  shows a "Cancel Revoke" button for `revoked` certificates (direct form + confirm,
  no reason required). `CertificateAdminController::action()` accepts
  `revoke-cancel` → restores status to `active`, clears `revoked_reason`, and sends
  an institute-scoped "Certificate revocation cancelled" notification (still hidden
  from the acting admin via the self-created-notification rule). Covered by
  `test_certificate_revoke_can_be_cancelled` in `AdminActionsTest.php`.
- **Certificates now a Standard List Page**: `CertificateAdminController::index()`
  rebuilt as full SLP (q searches student first/last name + `certificate_number` +
  course name — student `full_name` is an accessor, not a column; institute_id +
  status filters; eager-loads student/course/batch/institute; paginate 20
  `withQueryString`). New `CERTIFICATES_COLUMNS` const
  `serial, certificate_no, student, course, batch, institute, issue_date, status,
  action`, `certificates_columns` preference via new POST
  `admin.certificates.columns` route (`saveColumns()`). View
  `admin/certificates/index.blade.php` is a full SLP (filter-card q/institute/
  status, toolbar Columns/Print/CSV/Excel, `#certificatesTable`, drag reorder,
  print table with `loop->iteration`, review modal for reject/revoke reasons).
  Drag CSS selectors in `components.css` extended with `#certificatesTable`.
  Action buttons stay icon-only (approve `btn-success`, reject/revoke
  `btn-outline-danger`, cancel revoke `btn-outline-success`). Covered by
  `test_certificates_page_renders_and_filters` +
  `test_certificates_columns_preference_is_saved` in `AdminActionsTest.php`.
- **QR code + public certificate verification**: added `chillerlan/php-qrcode`
  (pure PHP, no GD — the GD-less environment blocked `simplesoftwareio/simple-qrcode`).
  New `qr_svg()` helper in `app/helpers.php` renders an inline SVG
  (`outputInterface => QRMarkupSVG::class`, `outputBase64 => false`; EccLevel M).
  Public route `GET /verify/certificate/{certificate_number}` →
  `VerifyCertificateController@show` (no auth) renders `verify/certificate.blade.php`
  on the standalone layout: VALID CERTIFICATE / REVOKED / REJECTED / PENDING
  REVIEW badge, student/course/batch/institute/issue-date details, and reason
  alerts for revoked/rejected/pending. Unknown number → 404. Admin printable page
  `GET /admin/certificates/{certificate}` → `CertificateAdminController@show`
  (route-model bound by id) renders `admin/certificates/show.blade.php`: a
  bordered "Certificate of Completion" sheet with student/course/institute/
  certificate-no/issue-date, inline QR + "Scan to verify" link (only when
  `certificate_number` exists), a diagonal REVOKED watermark, and a Print button
  (print CSS hides chrome). The certificates SLP action column now leads with a
  "View certificate" icon button (`bi-award`) linking to the show page. Covered by
  `test_public_certificate_verification_page`,
  `test_admin_certificate_show_page_renders_qr`,
  `test_admin_certificate_show_page_requires_auth` in `AdminActionsTest.php`.
- **QR code column on the certificates SLP**: added a toggleable `qr` column
  (`CERTIFICATES_COLUMNS` now ends `..., status, qr, action`, label "QR Code").
  For issued certificates the cell shows a small inline QR thumbnail
  (`qr_svg(..., 3)`, CSS `.qr-thumb svg { width: 52px }`) that is a download link;
  pending/rejected/revoked-without-number show `—`. Print table mirrors the
  column. New `GET /admin/certificates/{certificate}/qr`
  (`admin.certificates.qr`, `CertificateAdminController@downloadQr`) streams the
  QR as an SVG attachment (`Content-Type: image/svg+xml`,
  `Content-Disposition: attachment; filename="MNT-....svg"`) — platform admin
  (super user) can download ONLY the QR code, not the certificate. 404 when the
  certificate has no number. Covered by `test_admin_certificate_qr_download`.
- **Certificates page split into tabs**: new `admin/certificates/_tabs.blade.php`
  partial renders **Certificate Request** | **Certificates** tabs with count
  badges (following the courses tabs convention). New
  `admin.certificates.requests` (GET) route →
  `CertificateAdminController::requests()` lists ALL incoming requests
  (status `pending` + `rejected`) with the requester data — institute (who sent
  it), student, course, batch, requested at (`created_at`), status, remarks,
  action — via a full SLP (`admin/certificates/requests.blade.php`,
  `CERTIFICATE_REQUESTS_COLUMNS` = `serial, institute, student, course, batch,
  requested_at, status, remarks, action`, `certificate_requests_columns`
  preference, new POST `admin.certificates.requests-columns` /
  `saveRequestsColumns()`). `admin.certificates.index` is now scoped to issued
  certificates (`active` + `revoked`) with status filter restricted accordingly.
  `action()` redirects approve/reject to the requests tab, revoke/revoke-cancel
  to the certificates tab. Admin sidebar Certificates item now links to the
  requests tab and is active on any `admin.certificates.*` route. Covered by
  `test_certificate_requests_page_shows_requester_data` +
  `test_certificate_requests_columns_preference_is_saved` in
  `AdminActionsTest.php`; redirect assertions updated in
  `test_certificate_approve_and_revoke` and
  `test_public_certificate_verification_page`.
- **Certificate action column changes**: the "View certificate" (`bi-award`)
  button now only renders when `certificate_number` exists (i.e. issued certs) —
  pending/rejected requests no longer show it. A Delete button (`bi-trash`,
  red outline, confirm dialog) was added to every certificate row on both the
  requests and certificates tabs via new `DELETE /admin/certificates/{certificate}`
  (`admin.certificates.destroy`, `CertificateAdminController::destroy`) which hard
  deletes the row and redirects back with a flash. Covered by
  `test_admin_certificate_delete`.
- **Certificates are soft-deleted to a recycle bin**: `Certificate` model now
  uses `SoftDeletes`; migration `2026_08_14_000600_add_deleted_at_to_certificates_table`
  adds `deleted_at` (applied to both the dev DB and `monetix_test`). `destroy()`
  now soft-deletes ("Certificate moved to the recycle bin."). New
  `GET /admin/certificates/bin` (`admin.certificates.bin`,
  `CertificateAdminController::bin`, view `admin/certificates/bin.blade.php`)
  lists trashed certificates with Restore + Delete permanently (password-
  confirmed via the same force-delete modal pattern as institutes). New
  `POST admin.certificates.restore` and `DELETE admin.certificates.force-delete`
  (`->withTrashed()` on both routes so trashed models resolve). Restore/force-delete
  redirect to the unified Recycle Bin.
- **Recycle Bin is unified**: `admin.institutes.bin` (view
  `admin/institutes/bin.blade.php`) now lists BOTH trashed institutes and trashed
  certificates in two sections, each with Restore + Delete permanently (password-
  confirmed force-delete modal, shared). The separate `admin.certificates.bin`
  route, `CertificateAdminController::bin()`, `admin/certificates/bin.blade.php`,
  and the "Certificates Bin" sidebar item were removed; `CertificateAdminController`
  redirects now target `admin.institutes.bin`. The sidebar Recycle Bin badge
  (`recycleCount`) sums trashed institutes + certificates in the view composer.
  Soft-deleted certs are excluded from index/requests lists and the public verify page.
  Covered by `test_admin_certificate_soft_delete_restore_and_force_delete`.
- **Certificate design** (`admin/certificates/show.blade.php`) renders on an A4
  landscape sheet (`@page { size: A4 landscape; margin: 0 }`, `297mm × 210mm`,
  aspect-ratio scaled on screen, exact size on print). Layout: QR + "Scan to
  verify" in the top-left corner; a single corner card top-right with Student ID,
  Registration No, Certificate No; centered header with institute logo (embedded
  as a base64 data URI when the stored path exists) + `institute.name` (uppercase)
  + `short_name` tagline; title CERTIFICATE OF COMPLETION; "This certificate is
  proudly presented to" → "This is to certify that" → student name uppercase →
  guardian line "Son of"/"Daughter of"/"Child of" from `father_name` + `gender` →
  DOB `d F Y` and NID meta strip → "has successfully completed the prescribed
  training course" → course name uppercase → fulfillment line; "completed
  subjects" chips from `course_id` → `course.subjects` (course_subjects pivot);
  footer signature area titled Authorized Issuer with two aligned signature
  blocks (empty line + institute name below, empty line + "Instructor / Trainer"
  + "Training Department"); Issue Date line at the bottom. No verify URL text is
  shown. `CertificateAdminController::show()` eager-loads `course.subjects` and
  passes `subjects` + `logoDataUri`. Covered by
  `test_admin_certificate_show_page_renders_qr` (asserts uppercase name).

### Test suite baseline
Full suite: **501 tests / 2192 assertions** (all green), PHPUnit 11.5.56, PHP 8.5.8.
