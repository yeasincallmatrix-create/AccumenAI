# Support Layer Audit — Monetix

Education management system, Laravel 12, PHP 8.2. This report inventories the
support layer: `app/Services`, `app/Support`, `app/Models/Concerns`,
`app/Http/Middleware`, `app/helpers.php`, `bootstrap/app.php`,
`app/Providers/*`, and relevant `composer.json` packages.

**Tenancy model at a glance:** single-database, tenant-scoped-by-column
design. There is **no** central+tenant database split, no dynamic PDO
connection, and no `config('database.connections')` switching in app code.
Tenancy is achieved with:

- an `institute_id` column on every tenant table,
- a global scope (`TenantScoped`) applied by the `TenantScoped` concern,
- a process-global static context (`TenantContext`), populated per-request by
  the `SetTenantContext` middleware,
- an optional second axis `branch_id` (`BranchScoped` concern + `BranchContext`),
- session-based workspace resolution for the global `web` user (`Workspace`).

Crucially, identity is resolved from the **authenticated user / session** — not
from a subdomain. No subdomain middleware or host/domain parsing exists anywhere
in the codebase.

---

## 1. `app/Models/Concerns`

### 1.1 `TenantScoped` (trait) — `app/Models/Concerns/TenantScoped.php`

The most important construct in the support layer. Full source:

```php
<?php

namespace App\Models\Concerns;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Global scope that constrains a model to the current institute.
 *
 * Applied only to tables that belong exclusively to one institute
 * (students, batches, results, ...). Course/Subject catalogs are
 * multi-tenant and deliberately do NOT use this trait.
 */
trait TenantScoped
{
    public static function bootTenantScoped(): void
    {
        static::addGlobalScope('institute', function (Builder $builder) {
            if (TenantContext::enabled()) {
                $builder->where(
                    $builder->getModel()->qualifyColumn('institute_id'),
                    TenantContext::id()
                );
            }
        });
    }
}
```

- **Expected column:** `institute_id` (qualified via `qualifyColumn`, so it
  survives joins/aliases and avoids ambiguous-column SQL errors).
- **Scope applied:** a single global scope named `institute` that appends
  `WHERE institute_id = <TenantContext::id()>`.
- **Identity resolution:** read from the static `TenantContext::$instituteId`.
  There is no session/subdomain check here — `TenantContext` is the single
  source of truth, and `SetTenantContext` middleware populates it per request
  from the authenticated user / workspace session.
- **Missing context behaviour:** the scope is a **no-op** while
  `TenantContext::enabled()` is false (`instituteId === null`), meaning queries
  see **all rows across all institutes**. This is deliberate for
  platform-admin / CLI usage, but is the classic single point of failure: any
  code path that reaches a tenant model without the middleware having run
  silently loses tenant isolation.
- **Models using it** (codebase grep): `GalleryMedia`, `GalleryAlbum`,
  `OfflineSyncQueue`, `ExamResult`, `Exam`, `CourseSubCategory`,
  `CourseRequest`, `CourseCategory`, `Invoice`, `InstituteUser`,
  `InstituteSubscription`, `InstituteSubject`, `InstituteSetting`,
  `InstituteCourse`, `Installment`, `Result`, `Certificate`, `CashMemo`,
  `Payment`, `Branch`, `Batch`, `Room`, `StudentEnrollment`, `Attendance`,
  `Student`, `AccountHead`, `SubjectRequest`, `Notice`, `Transaction`.
- **Deliberately NOT applied** to multi-tenant shared catalogs (`Course`,
  `Subject`, `Country`, `AdministrativeLevel`, `AdministrativeUnit`, global
  `Setting`, `Role`, `Theme`, …). No model uses this trait with a custom column
  name — every consumer uses the default `institute_id`.

### 1.2 `BranchScoped` (trait) — `app/Models/Concerns/BranchScoped.php`

Full source:

```php
<?php

namespace App\Models\Concerns;

use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Global scope that constrains a model to the branch the current user belongs
 * to. It mirrors TenantScoped: it is a no-op while BranchContext is disabled
 * or has no branch id (owners / institute admins / platform users see all
 * branches), so existing behaviour is preserved until a user is assigned a
 * branch.
 *
 * Apply only to tables that carry a direct `branch_id` (students, batches,
 * rooms, notices, transactions, users). Rows whose branch is inherited through
 * a relation (attendance, results, invoices, ...) are scoped by their owning
 * model instead.
 */
trait BranchScoped
{
    public static function bootBranchScoped(): void
    {
        static::addGlobalScope('branch', function (Builder $builder) {
            if (BranchContext::enabled()) {
                $builder->where(
                    $builder->getModel()->qualifyColumn('branch_id'),
                    BranchContext::id()
                );
            }
        });
    }
}
```

- **Expected column:** `branch_id` (also `qualifyColumn`ed).
- **Scope applied:** global scope named `branch` appending
  `WHERE branch_id = <BranchContext::id()>`.
- **Identity resolution:** from the static `BranchContext::$branchId`,
  populated by `SetTenantContext` from the institute user's `branch_id` (for
  `InstituteUser`) or from the active `Membership.branch_id` (for `web` users).
- **Missing context / null branch:** scope is a no-op when disabled or when
  branch id is null. A user with `branch_id = null` (owner / institute admin)
  sees **all** branches of their institute. Explicitly documented in the trait.
- **Models using it** (always compounded with `TenantScoped`): `Notice`,
  `Transaction`, `InstituteUser`, `Batch`, `Room`, `Student`. Rows whose branch
  is inherited via a relation (attendance, results, invoices, payments) are
  scoped through their owning model — the branch filter is applied via
  `whereHas` at query time, not via this global scope.

### 1.3 `HasUserPreferences` (trait) — `app/Models/Concerns/HasUserPreferences.php`

Per-account UI preferences stored in a `preferences` JSON column.

| Method | Purpose |
|---|---|
| `allPreferences(): array` | Stored JSON merged **over** `defaultPreferences()` via `array_replace`. |
| `preference(string $key, mixed $default = null): mixed` | Read a single preference key. |
| `setPreference(string $key, mixed $value): void` | Write one key and `forceFill(['preferences' => ...])->save()` immediately. |
| `protected defaultPreferences(): array` | Defaults (`'theme' => 'default'`); any consuming model can override. |

Used by: `User`, `InstituteUser`, `PlatformAdmin`.

---

## 2. `app/Support`

### 2.1 `TenantContext` — `app/Support/TenantContext.php`

Process-global static holder of the current institute id (`?int $instituteId`).

| Method | Purpose |
|---|---|
| `set(?int $instituteId)` | Store the institute id (called by `SetTenantContext` middleware and `Workspace::set`). |
| `id(): ?int` | Current institute id, or null. |
| `enabled(): bool` | `true` iff id is non-null. |
| `clear()` | Reset to null (mid-login/logout, non-tenant requests, CLI). |

Class docblock: *"A middleware (set in a later phase) calls
`TenantContext::set($instituteId)` for institute-user requests. While no context
is set the tenant scope is inactive, so platform-level queries and CLI commands
see all rows."*

### 2.2 `BranchContext` — `app/Support/BranchContext.php`

Mirror of `TenantContext` for `branch_id`. Identical four methods
(`set`/`id`/`enabled`/`clear`). Populated by `SetTenantContext` from the
institute user's `branch_id` or the active membership.

### 2.3 `Workspace` — `app/Support/Workspace.php`

Active-organization resolution for the global `web` `User` who may hold many
memberships. The active organization id lives in the session under key
`active_institution_id` and is re-verified against `Membership` on every request.

| Method | Purpose |
|---|---|
| `set(?int $institutionId)` | Persists the session key **and** calls `TenantContext::set()` in one call. |
| `clear()` | Forgets the session key and `TenantContext::clear()`. |
| `id(): ?int` | Reads session key `active_institution_id`. |
| `membership(): ?Membership` | Active, verified `Membership` for the current `web` user in the current workspace. Null for `PlatformAdmin`, unauthenticated users, no session id, or non-active membership. |
| `verify(?int $institutionId, int $userId): bool` | `exists()` check that an **active** membership links user ↔ institution. Used by the middleware. |
| `resolveAfterLogin(User $user, ?int $requestedId = null): ?int` | 0 memberships → null (must create/join); exactly 1 → auto-activate it (skips picker); N → honour a valid explicit choice else null (forces the workspace picker). |

Security posture from the docblock: *"Never trust an organization id coming from
the browser — always resolve and verify through here."*

### 2.4 `SessionAgent` — `app/Support/SessionAgent.php`

`fromUserAgent(?string $userAgent): array` — a naive user-agent parser returning
`['platform' => …, 'browser' => …]` (platform: Windows/iOS/Android/Linux/macOS;
browser: Firefox/Samsung Internet/Opera/IE/Edge/Chrome/Safari). Used to render
the active sessions list.

### 2.5 `PasswordHash` — `app/Support/PasswordHash.php`

Guards against corrupted password hashes (e.g. a stripped `$2y$` prefix from an
export/import round-trip) that would make `Hash::check()` throw and 500 the
login.

| Method | Purpose |
|---|---|
| `looksValid(string $hash): bool` | Regex-validates a bcrypt (`$2a/b/x/y$`, exactly 60 chars) or argon2 (`$argon2`, ≥ 80 chars) prefix. |
| `safeCheck(string $password, string $hash): bool` | `Hash::check()` wrapped in try/catch; returns `false` on `RuntimeException` instead of throwing. |

Referenced by the scheduled `auth:audit-hashes` command (see bootstrap/app.php).

### 2.6 `PageMarker` — `app/Support/PageMarker.php`

**Temporary DEV tool** (docblock says *"DELETE once development is done"*).
Assigns a unique 2–3 digit number to every page (pages start at 10) and every
popup/modal (popups start at 100), persisted to `storage/app/page-markers.json`
so badge numbers can be traced back to route names / modal ids. Global toggle is
the platform `Setting` `dev.page_marker_enabled` (default '1'); toggle button
lives in `layouts/admin.blade.php` and posts to the `dev.page-marker.toggle`
route.

| Method | Purpose |
|---|---|
| `enabled(): bool` | `Setting::get('dev.page_marker_enabled', '1') !== '0'`. |
| `toggle(): bool` | Flips the switch, returns the new state. |
| `page(?string $key = null): int` | Number for the current route name (or URI/path fallback). |
| `popup(string $key): int` | Number for a popup keyed by its modal DOM id. |
| `keyFor(int $number): ?string` | Reverse-lookup a badge number → route name / modal id. |
| `registry(): array` | Persisted `key → number` map. |

### 2.7 `NotificationCenter` — `app/Support/NotificationCenter.php`

Resolves which notifications the current user can see and tracks read state via
the `notification_reads` table. Visibility rules: `platform_admin` sees every
notification except ones they created themselves; `institute_user` (and `web`
`User` via their active workspace membership) sees institute-scoped
(`scope=institute`, matching `institute_id`) plus user-scoped
(`scope=user`, matching `target_user_type` + `target_user_id`).

| Method | Purpose |
|---|---|
| `readerType(): ?string` | `platform_admin` \| `institute_user` \| null, from the auth model class. |
| `readerId(): ?int` | `Auth::id()`. |
| `instituteId(): ?int` | Institute id for `InstituteUser`; `Workspace::membership()` institution id for `web` User. |
| `reader(): ?array` | `['type' => …, 'id' => …]` or null. |
| `visibleQuery(): Builder` | Notification query limited by the visibility rules. |
| `readIds(): array` | Ids already read by the current user. |
| `unreadCount(): int` | Visible count minus read. |
| `latest(int $limit = 5): Collection` | Latest visible notifications. |
| `isVisible(Notification): bool` | Rule engine for a single notification. |
| `markAsRead(Notification): bool` | `firstOrCreate` a read row (only for visible notifications). |
| `markAllRead(): int` | Inserts read rows for all currently visible; returns count of rows newly created. |

### 2.8 `GeoHierarchy` — `app/Support/GeoHierarchy.php`

Country-neutral helpers for the global 3-level address system (`countries` /
`administrative_levels` / `administrative_units` tables plus
`config/geo-labels.php`). All countries resolve through the same code paths.

| Method | Purpose |
|---|---|
| `levelLabels(Country): array` | Display labels for the 3 levels (DB label > curated config map > defaults). |
| `selectableLevels(Country): Collection` | Levels exposed for a country, ordered by `level_number`. |
| `validateHierarchy(int $countryId, int\|string\|null $l1, $l2, $l3): ?string` | Strict server-side hierarchy validation: every unit must belong to the submitted country and its parent chain (blocks cross-country / cross-parent tampering). Returns null on success or a `geo.*` error key for a 422 response. Backed by private `unitInCountry()`. |

### 2.9 `CountryCodes` — `app/Support/CountryCodes.php`

Dial-code reference for phone inputs. `CODES` constant maps ~160 official
country names → dial code **without** the leading `+`.

| Method | Purpose |
|---|---|
| `all(): array` | The full map. |
| `codeFor(?string $country): string` | Dial code for a country, defaulting to `'880'` (Bangladesh). |
| `matchPrefix(string $digits): ?string` | Longest stored code that prefixes the given digit string, else null (used to infer a country from a typed number). |

### 2.10 `BdGeo` — `app/Support/BdGeo.php`

Bangladesh geo reference data ported from `demo/geodata.md`. Large static-data
class (~3,380 lines): **8 divisions / 64 districts / 494 upazilas**, each
upazila carrying its (HQ) zip/postal code. Three public `const` arrays:
`DIVISIONS` (`id => ['en','bn']`), `DISTRICTS`
(`id => ['division_id','en','bn']`), `UPAZILAS`
(`id => ['district_id','en','bn','zip']`). IDs are authoritative and used
verbatim by the student address fields.

Public methods (tail of the class): `upazilas(?int $districtId): array` (all, or
filtered by district), `divisionName(int|string): ?string`,
`districtName(...): ?string`, `upazilaName(...): ?string`,
`zipForUpazila(...): ?string`.

### 2.11 `AiLanguage` — `app/Support/AiLanguage.php`

Lightweight language detection for AI prompts (`bn` / `banglish` / `en`).
Detection is a hint, not a gate.

| Method | Purpose |
|---|---|
| `detect(string $text): string` | Bengali Unicode block (U+0980–09FF) → `bn`; Banglish hint-word list → `banglish`; else `en`. |
| `instruction(string $text): string` | One-line system-prompt guidance for the detected language. |
| `instructionFor(string $text, string $preference): string` | Same, honouring an explicit response-language preference (`auto` → per-message detection). |

### 2.12 `AiConfig` — `app/Support/AiConfig.php`

Runtime-read AI configuration. Super Admin values stored in the platform
`settings` key/value table override the static `config/ai.php` / env defaults.
API credentials are never exposed to institute users.

| Method | Purpose |
|---|---|
| `enabled(bool $platformOnly = false): bool` | Platform-level AI switch. |
| `provider(): string` | Active provider name (default `openai`). |
| `apiKey(): string` | Provider API key (settings → config). |
| `baseUrl(): string` | Provider base URL. |
| `model(): string` | Model name (default `gpt-4o-mini`). |
| `globalInstructions(): string` | System-prompt preamble configured by Super Admin. |
| `maxTokens(): int` | Token cap (default 900). |
| `temperature(): float` | Sampling temperature (default 0.2). |
| `storePrompts(): bool` | Whether prompts are persisted in the audit log. |
| `timeout(): int` | HTTP timeout (default 60). |
| `responseLanguage(): string` | Pinned reply language (default `auto`). |
| `dailyLimit() / monthlyLimit(): int` | Platform-level usage caps (0 = unlimited). |
| `maxToolRounds(): int` | Max tool-call rounds per turn (default 5). |
| `features(): array` | Enabled AI feature list. |

---

## 3. `app/Services`

### 3.1 `UserAccountService` — `app/Services/UserAccountService.php`

| Method | Purpose |
|---|---|
| `registerOwner(array $data): User` | Normal self-registration → `account_type = 'owner'`. |
| `createStaffFromInvitation(array $data): User` | Staff invitation/onboarding → `account_type = 'staff'`. Never merges or converts account types. |

### 3.2 `ProfileImageService` — `app/Services/ProfileImageService.php`

Passport-style profile picture processing: constant 7:9 portrait ratio → exactly
350×450 px, re-encoded JPEG, under 100 KB (50 KB target). Constants:
`RATIO_W=7`, `RATIO_H=9`, `WIDTH=350`, `HEIGHT=450`, `MAX_BYTES=100*1024`,
`TARGET_BYTES=50*1024`, `MAX_DIMENSION=6000`.

| Method | Purpose |
|---|---|
| `processAndStore(UploadedFile $file, string $subdir = 'students'): string` | Validate, face-biased 7:9 crop, resize to 350×450, progressively compress to JPEG (quality 85→40), store on the `public` disk at `profile-images/<subdir>/<uuid>.jpg`. Throws `InvalidArgumentException` on invalid file/type/dimensions/oversize. |

Private helpers: `assertValidFile`, `cropBox` (centers horizontally, biases the
vertical crop upward to keep the head), `encodeJpeg`, `load`.

### 3.3 `OfflineSyncService` — `app/Services/OfflineSyncService.php`

Reviews offline-synced records (`offline_sync_queue`) and materializes approved
ones into production tables — currently only `cash_memos`.

| Method | Purpose |
|---|---|
| `validatePayload(array $payload): array` | Server-side validation of a queue payload (student_id, amount > 0, description, payment_method ∈ cash/bkash/nagad/bank/other, memo_number, created_at). Throws `ValidationException`. |
| `materialize(OfflineSyncQueue $queue, InstituteUser $reviewer): CashMemo` | Only from `pending_review`; re-validates; verifies student (if provided) exists in-scope; then in a transaction creates the `CashMemo` (reusing or generating a unique `CM-<ymd>-<rand>` memo number), marks the queue `approved`, stores `reviewed_by/at`, `materialized_id`. |
| `reject(OfflineSyncQueue $queue, InstituteUser $reviewer, string $reason): void` | Only from `pending_review`; transactionally marks the queue `rejected` with reviewer + reason. |

Constant: `SUPPORTED_ENTITY_TYPES = ['cash_memo']`. Private helper
`resolveMemoNumber` prefers a free client-supplied number, else generates a
unique one (5 attempts).

### 3.4 `MembershipService` — `app/Services/MembershipService.php`

| Method | Purpose |
|---|---|
| `assign(User $user, int $institutionId, int $roleId, array $attributes = []): Membership` | Creates an active membership after `assertRoleAllowed`. |
| `changeRole(Membership $membership, int $roleId): Membership` | Changes the role after `assertRoleAllowed`. |
| `assertRoleAllowed(User $user, int $roleId): void` | Guards the owner-account ↔ owner-role invariant: the `institute-owner` role slug requires an Owner account (`isOwnerAccount()`); any other role requires a Staff account — otherwise throws `App\Exceptions\AccountTypeMismatchException`. |

### 3.5 `GeoImportService` — `app/Services/GeoImportService.php`

Reusable country-by-country geography importer that turns a `GeoDataProvider`
(currently an internal package; later an external API) into
`administrative_units` rows. Shared by the `geo:import-package` CLI command, the
Super Admin upload UI and tests. Guarantees: streaming (provider generator,
never fully in memory), chunked transactions, upsert on the `(country_id, code)`
natural key, rollback-on-chunk-failure with earlier chunks preserved, nothing is
deleted, and **resumable batches** (`runBatch`) so admin UI polls can continue a
large import without long-running HTTP requests.

| Method | Purpose |
|---|---|
| `__construct(int $chunkSize = 1000, int $recordsPerRequest = 2000)` | Injectable chunk / resume sizes. |
| `fromConfig(): self` | Builds from `config('geo.import.*')`. |
| `import(GeoDataProvider $provider, Country $country): array` | Full import, write mode; returns a report. |
| `validate(GeoDataProvider $provider, Country $country): array` | Full validation without writing (runs the same upsert inside a rolled-back transaction). |
| `runBatch(GeoImport $import, GeoDataProvider $provider, int $limit): array` | Consumes the next `$limit` records, accumulates counters on the `GeoImport` row, returns the updated report (+`import_id`). |

Report shape: `country, country_iso2, total, inserted, updated, skipped,
duplicates, errors, error_summary, finished, status, error_rows`. Private core
`run()` streams and flushes chunks; `levelIdsByNumber`, `ensureLevels` (creates
missing administrative-level definitions from `config/geo-labels.php`),
`upsertLogicalChunk` (classifies each row via DB lookups, resolves parents
inside the chunk transaction), `unitIdByCode`.

### 3.6 AI suite — `app/Services/Ai/`

An OpenAI-style, read-only, tool-calling assistant embedded in the ERP.

**Contracts** (`app/Services/Ai/Contracts/`):
- `AiTool` — interface: `name()`, `description()`, `parameters()` (JSON schema),
  `permission()` (owner = superuser `'*'`), `feature()` (e.g. `assistant`),
  `mode()` (`read` = safe to auto-run; `write` = needs confirmation), and
  `handle(array $args, AiContext $context): array`.
- `AiProvider` — `chat(array $messages, array $tools): AiProviderResponse`.
- `AiProviderResponse` — readonly value object `{content, toolCalls, tokens}`
  with `hasToolCalls()`.

**Engine**:
- `AiService` — one assistant turn. Enforces platform/institute/feature gates,
  enforces usage limits, then loops: provider `chat()` → execute **read-mode**
  tool calls → append tool results → repeat up to `maxToolRounds`. Write-mode
  tools are refused with an explicit error. Builds the system prompt from
  `AiConfig::globalInstructions()` + tenant name/industry + language guidance;
  keeps the last 10 history turns. Silently returns a `blocked`/`error` status
  on failure (never throws). Always logs via `AiLogger`.
- `AiToolRegistry` — resolves tool classes from `config/ai-tools.php` driven by
  the industry; a tool is offered only when: industry matches (or is `core`),
  its feature is enabled, and the actor holds its permission (owner is a
  superuser). `available(AiContext)`, `get(name)`, `isAvailable(tool, ctx)`,
  `definitions(tools)` → OpenAI function defs.
- `AiContext` — immutable snapshot for one request: actor (`InstituteUser` or
  `User`), `?Institute`, `industry`, `aiEnabled`, `enabledFeatures`, `modules`,
  `permissions`, `roleSlug`, `branchId`. `resolve()` derives permissions from
  the actor's role (or ownership) and membership. The model never sees raw
  tenant ids or credentials.
- `AiUsageTracker` — per-tenant daily/monthly usage limits (settings
  `ai_config`, 0 = unlimited) upserted into `ai_usage`; platform default is a
  hard cap (institute can never exceed it). `enforceLimits()`,
  `count()`, `record()`. Throws `AiUsageException` (also defined here).
- `AiLogger` — writes `ai_logs` rows; redacts secrets
  (`sk-…`, `Bearer …`, `password|secret|api_key|token=…`, `AKIA…`) before
  persistence; only stores prompts when `AiConfig::storePrompts()`.
- `OpenAiProvider` — HTTP `chat/completions` call with the configured model,
  max_tokens, temperature and optional function tools; parses content +
  tool_calls + usage. Throws `RuntimeException` on non-success.
- `AiAccessException` — gate refusal marker (caught by `AiService`).
- `AiUsageException` — defined inside `AiUsageTracker.php`, extends
  `RuntimeException` with a `period` discriminator.

**Tool base** (`app/Services/Ai/Tools/AbstractAiTool.php`):
- defaults `feature()` = `assistant`, `mode()` = `read`, `permission()` = null.
- helpers: `limit()` (1–50), `dateArg()` (Carbon startOfDay), `groupBy()`
  (whitelist), `result()` (summary + meta + rows).
- `branchId(AiContext)` — mirrors the `BranchScoped` global scope so the AI
  inherits exactly the same branch restriction as the UI.
- `guard(AiContext)` — aborts 403 unless `TenantContext` is active, so a
  misbehaving request can never leak another institute's rows.

**Education tools** (all `read`-mode, registered under `education` in
`config/ai-tools.php`):
- `StudentsTool` — `get_students` (permission `students.view`): count/list
  students filtered by status, branch, admission date range; group by status or
  month.
- `CoursesTool` — `get_courses` (`courses.view`): course list/summary.
- `BatchesTool` — `get_batches` (`batches.view`).
- `EnrollmentTool` — `get_enrollments` (`enrollments.view`).
- `ExamResultsTool` — `get_exam_results` (`exams.manage`): total results, pass
  rate, average %, grouped by course/month; branch filter via `whereHas('batch')`.
- `AttendanceTool` — `get_attendance` (`attendance.view`).
- `FeesTool` — `get_fees` (`finance.view`): invoiced / paid / due / overdue sums
  from invoices; branch filter via `whereHas('student')`.
- `CertificatesTool` — `get_certificates` (`certificates.view`).

**Core / industry-neutral tool**:
- `IncomeExpenseTool` — `get_income_expense` (`finance.view`), registered under
  `core` and offered to every industry. Summarises the general ledger
  (`transactions`): total income/expense/net, optional period, grouped by
  account head or month, filtered by branch/type/head/date range.

`config/ai-tools.php` maps `core` + `education` (full 8-tool list) now;
`real_estate`, `transportation`, `restaurant` are stubbed with commented-out
classes (no domain tables exist yet). `AiService` is bound as a singleton provider.

---

## 4. Middleware — `app/Http/Middleware`

| Middleware | Alias | Purpose |
|---|---|---|
| `SetTenantContext` | `tenant` | **Core tenant plumbing.** Runs after `SubstituteBindings` in the priority list. For `InstituteUser`: `TenantContext::set(institute_id)` + `BranchContext::set(branch_id)`. For `web` `User`: reads `Workspace::id()` from session, verifies it against the active membership (clears the workspace if stale), sets `TenantContext` and `BranchContext` from the membership's `branch_id` (null branch = unrestricted). Otherwise clears both contexts. |
| `SetLocale` | `setlocale` | Language resolution. `?lang=en\|bn` query → persisted to session + saved on the auth user; then `mawa_current_lang()` (session → user `preferred_language` → institute setting → `en`); calls `app()->setLocale()` and shares `currentLang` with views. Appended to the `web` middleware group globally. |
| `SetFortifyGuard` | `fortifyguard` | Pins the active Fortify guard for the request. Two guards (`web` + `platform_admin` + `institute_user`) with one-role-per-session; during 2FA challenge uses the session's `login.guard`, otherwise the currently-authenticated guard. Also sets `fortify.passwords` table per guard. |
| `CheckPermission` | `permission` | Route-level `permission:slug` (single) or `permission:a,b` (any). `PlatformAdmin` always passes. `InstituteUser` / `web` user via their active membership must `hasAnyPermission(...)`. Else 403. |
| `EnsureAiEnabled` | `ai.enabled` | Gates a route behind AI: platform switch must be on (`AiConfig::enabled()`), and for institute requests (via `TenantContext`) the institute's `ai_config.enabled` + requested feature must be enabled. 403 otherwise. |

Auth middlewares are Laravel's standard `EnsureEmailIsVerified` (aliased
`verified`) and the built-in `auth`/`guest`; `redirectGuestsTo(admin.login)`.
There is **no** subdomain middleware.

---

## 5. `app/helpers.php`

Autoloaded via `composer.json` `autoload.files` and also `require_once`'d by
`AppServiceProvider::register()`. All functions are `function_exists()`-guarded.

| Function | One-line purpose |
|---|---|
| `qr_svg(string $data, int $scale = 6): string` | Render a QR code as an inline SVG string (chillerlan library, no GD). |
| `mawa_lang_files(string $locale): array` | Load the raw translation array for `lang/mawa/{en,bn}.php` (cached). |
| `mawa_translate(array $items, string $key)` | Resolve a dotted `a.b.c` key against a nested array; null when missing. |
| `mawa_current_lang(): string` | Active locale with priority: `?lang`/session → user `preferred_language` → institute setting → `'en'`. |
| `mawa_lang(string $key, array $replace = []): string` | Translate a dotted key into the active language, fall back to `en` then to the raw key; `:placeholder` replacement + mojibake fix. |
| `mawa_e(string $key, array $replace = []): string` | Same, safe for `{{ }}` Blade output (no double-escaping). |
| `mawa_lang_direction(?string $locale = null): string` | Document direction; currently always `'ltr'`. |
| `mawa_fix_mojibake($text)` | Repair double-UTF-8 corruption via a byte-sequence map. |
| `mawa_currency_symbol(?string $country = null): string` | Native currency symbol per country; defaults to Bangladeshi Taka (৳). |
| `mawa_country_flag(?string $country = null): string` | FlagCDN image URL per country; falls back to a blank placeholder. |
| `mawa_hex_to_rgb(string $hex): string` | Hex → `"r, g, b"` CSS rgb() string (defaults to `0D6EFD`). |
| `mawa_darken_hex(string $hex, float $factor = 0.85): string` | Darken a hex color by a factor (0–1). |

---

## 6. `bootstrap/app.php`

- **Routing:** web routes from `routes/web.php` + `routes/auth.php`; commands
  from `routes/console.php`; `/up` health check.
- **Middleware aliases:** `tenant` → `SetTenantContext`, `permission` →
  `CheckPermission`, `setlocale` → `SetLocale`, `verified` →
  `EnsureEmailIsVerified`, `fortifyguard` → `SetFortifyGuard`,
  `ai.enabled` → `EnsureAiEnabled`.
- **Global web middleware:** appends `SetLocale`.
- **Guest redirect:** `redirectGuestsTo(route('admin.login'))`.
- **CRITICAL ordering:** `prependToPriorityList(SubstituteBindings::class,
  SetTenantContext::class)` — tenant context is bound **before** route model
  binding so an unauthenticated-to-tenant binding cannot resolve another
  institute's records.
- **Schedule:** `auth:audit-hashes` daily at 03:00 (flags corrupted password
  hashes, reports `RuntimeException` via `report()` on failure,
  `withoutOverlapping`).
- **Exceptions:** custom JSON shape (`{success:false, message, errors}`) for
  `ValidationException` when the request expects JSON.

Loop-back note: the audit-schedule + the `PasswordHash` support class reinforce
each other — corrupted hashes are surfaced before a login 500s.

---

## 7. `app/Providers/AppServiceProvider.php`

Single provider (no custom event/observer/routeModelBinding providers were
found — `app/Providers` contains only this file).

- **register():**
  - `require_once ../helpers.php`.
  - Binds `AiProvider::class` → `OpenAiProvider::class` as a **singleton** (the
    only container binding in the app).
  - `Fortify::ignoreRoutes()` — the app owns its auth routes (two guarded
    portals); Fortify is reused only as the engine for password reset / 2FA /
    email verification.
- **boot():**
  - A global **View composer on every view** (`View::composer('*')`) that
    shares a large layout context: `user`, `roleLabel`, `accountTypeLabel`,
    `workspaceMemberships`, `workspaceActiveId`, `isInstituteStaff`,
    `workspaceAllowedFinance`, `workspaceAllowedStaffManage`, `recycleCount`
    (trashed Student/Batch for institutes; deleted Institute/Certificate for
    platform admins), notification data via `NotificationCenter`
    (`layoutNotifications`, `layoutUnreadCount`, `layoutReadIds`, index/read-all
    URLs), `countsPendingSync` (pending_review offline_sync_queue), preferences
    + theme/sidebar colors (PlatformAdmin preference or institute settings),
    `tallNavigation`, and `aiEnabled`. Sets `institute` only if the view has not
    already set it (so controller-provided institutes win).
  - No model observers, no custom route-model-binding resolution, no
    singletons beyond the AI provider.

---

## 8. `composer.json` — relevant packages

Requirements:

- `laravel/framework ^12.0` — foundation.
- `laravel/fortify ^1.38` — auth backend (two guards; used for reset/2FA/verify).
- `chillerlan/php-qrcode ^6.0` — QR SVG generation (`qr_svg`).
- `laravel/tinker ^2.10.1` — artisan REPL.

Dev: `fakerphp/faker`, `laravel/pail`, `laravel/pint`, `laravel/sail`,
`mockery`, `nunomaduro/collision`, `phpunit ^11.5.50`.

**Notably absent:** no Spatie packages (no spatie/laravel-permission,
no spatie/laravel-medialibrary, no spatie/laravel-multitenancy), no Sanctum,
no Jetstream, no cashier, no Laravel Nova. Roles/permissions are implemented
in-app (RBAC via `Role`, `role_permissions`, `Membership`, with
`hasAnyPermission`/`hasPermission`/`isOwner` model methods and the
`CheckPermission` middleware), and tenancy is fully custom via
`TenantContext` + global scopes (there is no spatie-multitenancy style DB
switching).

---

## 9. Key findings / risk notes

1. **No database switching** — tenancy is single-DB with a column + global
   scope. Anything reaching a tenant model without `SetTenantContext` running
   silently queries **all** institutes (the scope is a no-op when context is
   null).
2. **Static (non-final request-scoped) contexts** — `TenantContext` /
   `BranchContext` are static properties on final classes. Any code mutating
   them outside the middleware (e.g. long-running jobs, `Workspace::set()` in
   controllers) shares one process-global value; there is no queue/job context
   restoration, so queued jobs inherit whatever static value was last set in the
   worker process.
3. **Middleware ordering is handled correctly** (SetTenantContext before
   SubstituteBindings) but is easy to regress since it is configured in
   `bootstrap/app.php` priority lists rather than implicit.
4. **Two auth realms** — `InstituteUser` (tenant user, has `branch_id`) and
   global `User` + `Membership` (workspace-selected institution). Both funnel
   into the same `TenantContext`, and visibility/permission logic is duplicated
   in several places (`CheckPermission`, `NotificationCenter`,
   `AppServiceProvider`, `AiContext`) with `instanceof` branches.
5. **AI layer is read-only by design** — write-mode tools and tool-call
   execution are server-gated (`mode()`, per-tool permissions, tenant-context
   guard in `AbstractAiTool::guard()`).
6. **`PageMarker` is flagged for deletion** (DEV tool) — remember to remove
   class, routes, partials, and `storage/app/page-markers.json` before release.
7. **`BdGeo` / `CountryCodes` are large static data classes** used by student
   address fields; `BdGeo` ids are treated as authoritative references.