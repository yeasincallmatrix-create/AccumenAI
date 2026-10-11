# AGENTS.md

AccumenAI (formerly **Monetix** — old name still in docs, DB names, `mawa_*` helpers): Laravel 12 / PHP 8.2
multi-tenant SaaS monolith on XAMPP (Windows). Blade + Livewire is the primary UI; React 19 only for 4 medical
entrypoints (`resources/js/medical/*.jsx`, wired in `vite.config.js`). Single app — no monorepo, no CI
(`.github/` absent), no typechecker, no load balancer: prod is one 2 GB cPanel box
(`docs/cpanel-2gb-optimization/`).

Two standalone Node packages live in-tree but are **not** part of the Laravel app: `gateway/` (on-prem lab
analyzer bridge, own `.env`) and `simulator/` (virtual analyzer, zero deps).

## Read before editing

- `docs/AI_DEVELOPMENT_RULES.md` — hard rules (tenancy, authz, accounting/stock invariants, where code goes).
- `docs/AI_CONTEXT.md` — verified architecture, request lifecycle, "things an agent must not assume".
- `docs/promptRules.md` + `docs/design-conventions.md` + `docs/standard-list-page.md` — UI/style conventions.
- Trust **code over docs**: `docs/structure.md`, `docs/audit/*` and the `phpunit.xml` header comment are stale.

## Commands

- Full dev stack: `composer dev` → artisan serve + **queue worker (`queue:listen database --queue=default,notifications`)** + pail logs + Vite.
  Async work (notifications, jobs) does nothing without the queue worker.
- **`composer install`/`update` also run `npm install && npm run build`** (`post-autoload-dump`) — slow, fails offline.
  `composer setup` runs `php artisan migrate --force` against the dev DB; it is not a harmless bootstrap.
- Lint/format: `vendor/bin/pint` (installed, no `pint.json` → defaults). No PHPStan/cs-fixer — Pint is the only
  style gate, and nothing runs it for you (`.github/` absent).
- Build: `npm run build` / `npm run dev`. From PowerShell use `cmd /c "npm run build"`.
- After Blade edits: `php artisan view:clear`.
- Route audit (gates, middleware): `php artisan route:list --json` → inspect each route's `middleware` array.
- Dev DB is MySQL **`accumen_ai`** on `127.0.0.1` (`.env`), while `.env.example` ships `DB_CONNECTION=sqlite` —
  copying the example gives you an empty SQLite DB. Ad-hoc SQL:
  `C:\xampp\mysql\bin\mysql.exe -u root -h 127.0.0.1 <db> --table -e "…"` (mysql is not on PATH).

### Tests (canonical: `php artisan test --filter=SomeTest`)

- Single class: `php artisan test --filter=SomeTest`. Needs `phpunit.xml` (present) — `artisan test` fails without it.
- **Directory baseline: pass a path, not a filter** — `php artisan test tests/Feature/Accounting/`. `--filter=Accounting`
  is a regex over fully-qualified names and also matches `InventoryAccountingTest`, `TaxEngineTest`, … which carry
  their own baseline failures.
- **Never run the whole suite sequentially** — 560 test files / ~4,800 tests, times out (~1.4 s/test on Windows/MySQL).
- Full run: `php tests/shards/run.php` (6 shards × paratest; `1 3` = shard subset, `--processes=N`; shard filters live in
  `tests/shards/shard1.txt`…`shard6.txt`) or `composer test:shards` / `composer test:para`.
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
- **Two scoping regimes**: most models `use Concerns\TenantScoped` (global scope + create/update hooks;
  ~233 model files);
  **`app/Models/Medical/*` (77 models) has NO global scope** — every query must add
  `->where('institute_id', MedicalScope::instituteId())` manually (58 call sites already do).
- `Membership` maps to table **`institution_user`** (singular) — a *different* table from `institute_users`.
  Passwords live in **`password_hash`**, not `password`.
- **Schema**: `database/schema/mysql-schema.sql` is authoritative. `php artisan migrate` does *not* build the base
  schema — the 342 migrations are idempotent deltas + registry/seed rows. Never edit an applied migration; add one.
- **Guards**: `web`, `platform_admin`, `institute_user`, `guardian` (`platform_staff` is defined but unwired).
  Don't mix platform-admin and institute auth.
- **Invariants**: all money movement via `JournalPostingService` (post/reverse/void; posted journals immutable);
  `inventory_movements` written only by `InventoryStockService`; document numbers only from `*_sequences` tables.
- **Routes**: wired in `bootstrap/app.php` — web = `routes/web.php` + `auth.php` + `guardian.php` + `medical.php`
  (medical is wrapped in `medical` + `medical.module` middleware), api = `routes/api.php`, plus `routes/api_v1.php`
  mounted under `/api` by the `then:` closure, `console.php`, `channels.php`. Module routes are concentrated in
  `routes/institute_modules.php` (~2,200 lines) and `routes/web.php`.
  New route ⇒ add at least one gate: `permission:` / `module_access:` / `feature:` / controller `authorize()`.
  New permission slug ⇒ seed it in a permission seeder.
- **Authz gap is real**: **1,043 of 2,192 authenticated routes have no route-level `permission`/`module_access`/
  `feature` gate** (measured 2026-10-09 with `php artisan route:list --json`; some of those authorize() inside the
  controller). `FinanceWriteGate`, `DenyTeacherFromFinance` and `Membership::roleAllowedForAccountingType()`-style
  null checks are documented fail-open paths — don't rely on them as protection.
- **Livewire**: `EnsureLivewireContext` is appended to the `web` group in `bootstrap/app.php` because Livewire's
  update/upload routes only get `web` — drop it and every component re-render queries with the tenant scope off.

## Conventions that differ from defaults

- Models: `$guarded = []` is widespread (273 of 458 model files) — compensate with FormRequest validation, never pass
  raw request input into mass assignment. Use `casts(): array` (159 models); no model uses the `$casts` property.
- New React page ⇒ add its entry to the `input` array in `vite.config.js` or it never builds.
- Seeders and migrations must be idempotent (`firstOrCreate` / `updateOrInsert` / `hasColumn` guards).
- Blade: no inline `<style>`; modals go in `@push('modals')`; lists follow the Standard List Page pattern;
  `paginate(20)->withQueryString()`; user-facing text via `@term` / `lang/mawa/{en,bn}.php` in views that already use it.
- Styling: theme via `--bs-primary` in `theme_colors.blade.php`; never hardcode `#0d6efd`. CSS lives in both
  `public/css/*` and `resources/css/app.css` (Vite) and must be kept in sync.
- Tests: PHPUnit 11, `tests/Feature/*Test.php`, `DatabaseTransactions` (never `RefreshDatabase`), no live network
  (`Http::fake`) or real mail (`Notification::fake`).

## Things that look broken but are intentional

- Global reference models (`Subject`, `AcademicLevel`, …) are deliberately unscoped; COA has hybrid global rows
  (`institute_id NULL AND is_system = 1`) and `ChartOfAccount::visible()` / `AccountGroup::visible()` add a
  dedup: a global row is hidden once the tenant owns the same `code` (post-C1).
- POS, Manufacturing, Real Estate, Restaurant exist only as `config/*.php` + module-registry rows — 0 routes/models.
  Don't scaffold them unless asked.
- `AuditActivityLog` middleware is never registered in `bootstrap/app.php`; `InvoicePaid` event + its
  `LogInvoicePaid` listener exist but **nothing dispatches the event**. Wire deliberately or leave alone.
- Guardian portal clears branch context on purpose (`SetTenantContext`).

## Repo hygiene

- **Ignore rules don't cover tracked files**: `.env.testing`, `.env.testing.tmp`, 3 `backups/*` files and 11 `*.sql`
  (`database/schema/*` — intentional — plus `database/backups/*`) are already committed, so editing them shows up in
  `git status` and will be committed if staged. Never stage changes to them.
- Never commit `.env`, `opencode.json`, `/.opencode/`, `/demo/`, `/backup(s)/`, or **new** `*.sql` (all gitignored;
  dumps can contain live data). Reference `config()` keys in docs/code, `[REDACTED]` for values.
- Don't commit, add packages, refactor unrelated code, or push unless asked. Commit messages are conventional-commit
  style on branch `dev` (`feat(medical): …`, `fix(permissions): …`).
- Scratch files matching `tmp_*.php` / `debug_*.php` / `test_results.txt` are gitignored by design.
