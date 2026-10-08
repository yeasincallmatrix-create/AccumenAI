# AGENTS.md

AccumenAI (formerly **Monetix** — old name still in docs, DB names, `mawa_*` helpers): Laravel 12 / PHP 8.2
multi-tenant SaaS monolith on XAMPP (Windows). Blade + Livewire is the primary UI; React 19 only for a
handful of medical pages. Single app — no monorepo, no CI, no typechecker.

## Read before editing

- `docs/AI_DEVELOPMENT_RULES.md` — hard rules (tenancy, authz, accounting/stock invariants, where code goes).
- `docs/AI_CONTEXT.md` — verified architecture, request lifecycle, "things an agent must not assume".
- `docs/promptRules.md` + `docs/design-conventions.md` + `docs/standard-list-page.md` — UI/style conventions.
- Trust **code over docs**: `docs/structure.md`, `docs/audit/*` and the `phpunit.xml` header comment are stale.

## Commands

- Full dev stack: `composer dev` → artisan serve + **queue worker (`queue:listen database`)** + pail logs + Vite.
  Async work (notifications, jobs) does nothing without the queue worker.
- Lint/format: `vendor/bin/pint` (installed, no `pint.json` → defaults). No PHPStan/cs-fixer, no CI (`.github/` absent).
- Build: `npm run build` / `npm run dev`. From PowerShell use `cmd /c "npm run build"`.
- After Blade edits: `php artisan view:clear`.

### Tests (canonical: `php artisan test --filter=SomeTest`)

- Single class: `php artisan test --filter=SomeTest`. Needs `phpunit.xml` (present) — `artisan test` fails without it.
- **Never run the whole suite sequentially** — ~550 test files / ~4,800 tests, times out (~1.4 s/test on Windows/MySQL).
- Full run: `php tests/shards/run.php` (6 shards × paratest; `1 3` = subset, `--processes=N`) or `composer test:para`.
- **`php artisan test --parallel`, `composer test:parallel`, `composer ci:test` are broken** — paratest provisions
  per-token empty DBs and the schema is a data-only dump. Use the shard runner / paratest instead.
- Test DB: `monetix_test` (`.env.testing`, loaded when `APP_ENV=testing`). Pre-seeded from
  `database/schema/full_data.sql`; tests use `DatabaseTransactions` — **migrations are never run**, so the DB must
  already exist and contain reference data. `composer test:setup` applies migration deltas to it.
- Parallel runs occasionally hit MySQL deadlocks (shared DB) — expected; the shard runner retries only deadlock failures.
- **Baseline failures exist** (e.g. `IndustryRulesTest`, seeder-idempotency assertions on a dirty DB, ~1,000 in the
  full run per `memory.md`). Establish your change's baseline with a filtered run before blaming/fixing unrelated tests.
- `tests/TestCase.php::setUp` auto-seeds missing global reference data (currencies, roles, permissions, industry
  taxonomy) and clears `TenantContext`/`BranchContext`. Feature tests seed tenant fixtures themselves.

## Architecture facts that bite

- **Tenancy column is `institute_id`** (never `tenant_id`/`organization_id`). `SetTenantContext` runs *before*
  `SubstituteBindings` in `bootstrap/app.php` — that ordering is load-bearing, don't reorder.
- **Two scoping regimes**: most models `use Concerns\TenantScoped` (global scope + create/update hooks);
  **`app/Models/Medical/*` has NO global scope** — every query must add
  `->where('institute_id', MedicalScope::instituteId())` manually.
- `Membership` maps to table **`institution_user`** (singular) — a *different* table from `institute_users`.
  Passwords live in **`password_hash`**, not `password`.
- **Schema**: `database/schema/mysql-schema.sql` is authoritative. `php artisan migrate` does *not* build the base
  schema — the 285 migrations are idempotent deltas + registry/seed rows. Never edit an applied migration; add one.
- **Guards**: `web`, `platform_admin`, `institute_user`, `guardian` (`platform_staff` is defined but unwired).
  Don't mix platform-admin and institute auth.
- **Invariants**: all money movement via `JournalPostingService` (post/reverse/void; posted journals immutable);
  `inventory_movements` written only by `InventoryStockService`; document numbers only from `*_sequences` tables.
- **Routes**: no `routes/modules/*.php` split — module routes live in `routes/web.php`,
  `routes/institute_modules.php`, `routes/medical.php` (medical is wrapped in `medical` + `medical.module` middleware).
  New route ⇒ add at least one gate: `permission:` / `module_access:` / `feature:` / controller `authorize()`.
  New permission slug ⇒ seed it in a permission seeder.
- **Authz gap is real**: ~700 authenticated routes have no permission gate. `FinanceWriteGate`,
  `DenyTeacherFromFinance` and `Membership::roleAllowedForAccountingType()`-style null checks are documented
  fail-open paths — don't rely on them as protection.

## Conventions that differ from defaults

- Models: `$guarded = []` is widespread (255 models) — compensate with FormRequest validation, never pass raw
  request input into mass assignment. Use `casts(): array`, not `$casts`.
- Seeders and migrations must be idempotent (`firstOrCreate` / `updateOrInsert` / `hasColumn` guards).
- Blade: no inline `<style>`; modals go in `@push('modals')`; lists follow the Standard List Page pattern;
  `paginate(20)->withQueryString()`; user-facing text via `@term` / `lang/mawa/{en,bn}.php` in views that already use it.
- Styling: theme via `--bs-primary` in `theme_colors.blade.php`; never hardcode `#0d6efd`. CSS lives in both
  `public/css/*` and `resources/css/app.css` (Vite) and must be kept in sync.
- Tests: PHPUnit 11, `tests/Feature/*Test.php`, `DatabaseTransactions` (never `RefreshDatabase`), no live network
  (`Http::fake`) or real mail (`Notification::fake`).

## Things that look broken but are intentional

- Global reference models (`Subject`, `AcademicLevel`, …) are deliberately unscoped; COA has hybrid global rows
  (`institute_id NULL AND is_system = 1`).
- POS, Manufacturing, Real Estate, Restaurant exist only as `config/*.php` + module-registry rows — 0 routes/models.
  Don't scaffold them unless asked.
- `AuditActivityLog` middleware and `InvoicePaid` event exist but are never wired — wire deliberately or leave alone.
- Guardian portal clears branch context on purpose (`SetTenantContext`).

## Repo hygiene

- Never commit `.env`, `.env.testing`, `opencode.json`, `/demo/`, `/backups/`, `*.sql` (all gitignored, several hold
  real keys). Reference `config()` keys in docs/code, `[REDACTED]` for values.
- Don't commit, add packages, or refactor unrelated code unless asked. Commit messages are conventional-commit style
  on branch `dev` (`feat(medical): …`, `fix(permissions): …`).
- Scratch files matching `tmp_*.php` / `debug_*.php` / `test_results.txt` are gitignored by design.
