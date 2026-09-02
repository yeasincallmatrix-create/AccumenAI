# Prompt Rules

Rules of engagement for any AI assistant working on the Monetix / AccumenAI codebase.
Read `memory.md` first for project state; this file governs **how** you work.

---

## 1. Before writing any code

1. **Read `memory.md`** — it holds the current state, known bugs, active work, and blocked items.
2. **Read `skill.md`** — full capability overview and architecture.
3. **Search before creating** — use `grep`, `glob`, and `read` to understand the existing code before inventing new code. Never duplicate logic that already exists.
4. **Check the route map** (`routes/web.php`) — know where a feature lives before adding routes.

## 2. Communication style

- **Be concise.** The user works in a terminal. Short, direct answers. No filler words.
- **No "Here is the code" preambles.** Just output the code or run the command.
- **State what you did, not what you're about to do** — or just do it silently.
- **When uncertain, ask.** Do not guess at business logic or data model decisions.
- **Never say "I will now..." or "Let me..."** — just execute.

## 3. Code conventions

### PHP / Laravel
- Follow PSR-12. Use `& "C:\xampp\php\php.exe"` for artisan commands.
- Every new model needs `use HasFactory, TenantScoped` (and `BranchScoped` if applicable).
- Routes go in `routes/web.php`. Prefix groups: `admin/` for platform admin, `settings/` for institute academic.
- Controllers: `paginate(20)->withQueryString()` for all list pages.
- **No hardcoded institute IDs, user IDs, or role names** — always use `TenantContext::id()` or `$user->role`.
- Migrations must be **idempotent** (`Schema::hasColumn` / `Schema::hasTable` guards).
- Every model update/delete must check `permission:` gate.

### Blade views
- Use `@extends('layouts.admin')` or `@extends('layouts.institute')`.
- No inline `<style>` blocks — use `components.css` or `pages.css`.
- No inline `<script>` blocks except page-specific IIFEs inside `@push('scripts')`.
- Standard List Page (SLP) pattern: `filter-card` → `admin-card` → `table-toolbar` → table + centered pagination. See `docs/standard-list-page.md`.
- Flash messages: use `session('status')` — the layout renders them globally. Do not duplicate in individual views.
- **Modal stacking context**: modals must be inside `@push('modals')` (rendered before `@stack('scripts')`) to escape `<main>`'s `will-change: opacity` stacking context. Never place `.modal` inside `<main class="content">`.

### CSS
- Theme colors: override via `--bs-primary` in `theme_colors.blade.php`. Never hardcode `#0d6efd`.
- All Bootstrap class-level vars (`.btn-primary`, `.nav-pills`, `.pagination`, `.dropdown-menu`) are re-pointed in `components.css` to use `var(--bs-primary)`.
- Glass effect lives in both `components.css` AND `resources/css/app.css` (Vite source). Both must be synced.
- Cache-bust CSS with `?v={{ File::lastModified(...) }}` — tell user to Ctrl+F5.

### Database
- Tenancy: every table (except global ones) has `institute_id` FK with `TenantScoped`.
- Branch scoping: `branch_id` on direct tables, `whereHas` on indirect. See `memory.md` §Branch authorization.
- **Always back up before dev-DB migrations**: `mysqldump -u root monetix > backup\backup_monetix.sql`
- Run `php artisan migrate --pretend` first.

## 4. Testing

- Run the test suite after any significant change: `& "C:\xampp\php\php.exe" vendor\bin\phpunit`
- Tests use `DatabaseTransactions` — they roll back automatically.
- New features need new tests. Follow existing test naming conventions.
- Base `TestCase::setUp` clears both `TenantContext` and `BranchContext`.

## 5. Views and rendering

- After editing any Blade file: `php artisan view:clear` (and `php artisan view:cache` if caching is on).
- Vite build: `cmd /c "npm run build"` (PowerShell blocks npm/npx directly).
- Verify views work via the temp-script pattern in `public/` (see `memory.md` §Rendering/verification pattern).

## 6. Page markers

- `N-` = page, `N+` = popup/modal. Reference `storage/app/page-markers.json` to resolve.
- If both a page and popup share a number, ask which one is meant.
- Never remove page markers unless the user explicitly asks.

## 7. What NOT to do

- **Never fabricate data.** If a table doesn't exist, don't pretend it does.
- **Never send SMTP or payment config requests to institute staff** — admin-only.
- **Never hardcode `admin@makaanai.com`** — use `TenantContext::id()` or authenticated user.
- **Never create new layouts** unless the user asks. Use existing `layouts/admin.blade.php` or `layouts/institute.blade.php`.
- **Never add payment/subscription to staff end.**
- **Never put inline styles in Blade views** (except page-marker badges).
- **Never place modals inside `<main class="content">`** — use `@push('modals')`.
- **Never commit secrets** — API keys, passwords, tokens go in `.env`, never in code.
- **Never run destructive database commands** without a backup and `--pretend` first.
- **Never create documentation files** unless explicitly asked.
- **Never add comments to code** unless explicitly asked.

## 8. Do not touch unless asked

- **Never delete** any file, route, controller method, model, migration, view, CSS rule, or JS block unless the user explicitly says to delete it.
- **Never modify** existing code, logic, data structure, or behavior unless the user explicitly asks for that change.
- **Never restructure** files, directories, class hierarchy, or architecture unless the user explicitly requests a restructure.
- **Never "clean up"** code you think is unnecessary or redundant — the user put it there for a reason.
- **Never "fix"** something you perceive as broken unless the user reports it as a bug.
- When in doubt, **ask first**. Do not assume intent.

## 8A. "UI" means visual only

- If the user says **"UI"** or **"ui"**, only change **appearance** — CSS, colors, layout, spacing, fonts, alignment, shadows, borders, hover effects, responsive behavior.
- **Do NOT change** any logic, functions, database queries, routes, controllers, models, migrations, JS behavior, data flow, or file structure.
- **Do NOT add, remove, or modify** any functional code — only visual/CSS/styling code.
- If the task requires logic changes to achieve the UI result, **ask first** before touching anything non-visual.

## 9. Undo / revert protocol

When the user says "undo" or "revert":
1. Identify the exact changes made in the current session.
2. Reverse them in logical order (CSS → JS → Blade → Controller → Route → Migration).
3. Rebuild Vite (`cmd /c "npm run build"`) and clear views (`php artisan view:clear`).
4. Verify the revert didn't break other features.
5. State what was undone.

## 10. Restore point before every modification

- **Before editing ANY file**, copy the original to `storage/app/restore/` with a timestamped name (e.g. `exams_index_20260823_153000.blade.php`).
- `storage/app/restore/` is the restore directory. Create it if it doesn't exist.
- When the user says "undo", restore from this backup — not from memory or guessing.
- Never overwrite a restore point. If modifying the same file again, create a new timestamped backup.
- After confirming the change works, old restore points can be cleaned up.
- **Always keep at least 5 restore points per file** so the user can undo up to 5 times.

## 11. Multi-step task execution

- Use `todowrite` to track progress on tasks with 3+ steps.
- Update status in real time: `pending` → `in_progress` → `completed`.
- Mark completed only after verification (tests pass, views render, etc.).
- If blocked, document the blocker and continue with other steps.

## 12. File editing discipline

- Always `read` a file before `edit` or `write`.
- Preserve exact indentation (tabs vs spaces as-is).
- Use `edit` for surgical changes; use `write` for full rewrites.
- After editing: `artisan view:clear` for Blade, `npm run build` for CSS/JS.
- Verify with tests if applicable.

## 13. Database data loss protocol

If the user reports data loss:
1. **Do not panic.** Acknowledge the severity.
2. Check for existing backups: `backup database\monetix.sql`, XAMPP mysql binlogs, `mysqldump` files.
3. Check git history for migration files that may have caused the issue.
4. Document what's missing (`SHOW TABLES`, `SELECT COUNT(*) FROM ...`).
5. Propose recovery options (restore from backup, re-seed, manual insert).

## 14. Session handoff

At the end of a session, update `memory.md` with:
- What was completed.
- What's actively in progress.
- What's blocked.
- Any new files created or modified.
- Test status (pass/fail count).

---

*Last updated: 2026-08-23*
