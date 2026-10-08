# Schema Drift Register — F-009 / N-006 (TASK C3)

Status: **closed 2026-10-08**. Guarded by `tests/Feature/Database/SchemaIntegrityTest.php`.

## Sources of truth (order of authority)

1. **`accumen_ai` (live)** — the runtime schema. Always authoritative.
2. **`database/schema/mysql-schema.sql`** — Laravel-style snapshot of live: DDL for every
   table **plus** `INSERT INTO migrations` rows so a fresh `php artisan migrate` loads the
   base schema, marks everything applied, and runs only genuinely new migrations.
   Regenerated from live; never hand-edit.
3. **`database/migrations/*.php`** — deltas only, applied once per database.

`database/schema/schema.sql` is the pure-DDL twin used by the `AppServiceProvider`
parallel-testing imports. It deliberately contains **no** `INSERT INTO migrations` rows:
`migrations_data.sql` supplies those rows in the same import and duplicate primary keys
would collide.

## What was wrong (census, 2026-10-08)

| # | Finding |
|---|---------|
| 1 | `mysql-schema.sql` was **UTF-16 LE** (`FF FE` BOM). Fresh `php artisan migrate` died with `ERROR: ASCII '\0' appeared in the statement` while Laravel loaded the schema dump. `schema.sql` was the only ASCII dump. |
| 2 | The dump lagged live: **427 vs 494 tables** (67 only-in-live: `backups`/`restore_*`, `dealership_*`, `training_*`, `expenses`, …) plus **20 tables with column/index drift** (`appointments` 19→25, `hr_employees` 33→49, `institutes` 55→59, `users` 38→40, `chart_of_accounts` 25→28, …). |
| 3 | `monetix_test` diverged from live in **5 spots**: missing `expenses` table; `invoice_items` missing `reference_type`/`reference_id`; `module_registry.coming_soon` NOT NULL instead of NULL; `dealership_incentives` carried migration-declared helper indexes (`di_sr_earned_idx`, `di_status_idx`, `…_institute_id_index`, `…_sales_force_id_index`) instead of live's single `fk_di_sr` key; `migrations.BATCH` stored uppercase. |
| 4 | **366 orphan rows** in live `migrations` (row, no file): 49 × `0000_00_00_*` baselines, 317 dated names whose files were deleted/renamed historically (e.g. `2026_08_19_010000_create_accounting_core_tables`), and `0001_01_01_000002_create_jobs_table`. The test database carried 2 orphans of its own. |
| 5 | Retired stale dumps: `full_data_utf8.sql` (UTF-8 BOM, 354 CREATE, untracked → deleted) and `full_data_only.sql` (0 CREATE, tracked → `git rm`). |
| 6 | `mysql`/`mysqldump` are **not on PATH** on this machine; the schema-load path invokes bare `mysql`. `AppServiceProvider` hardcodes `C:\xampp\mysql\bin\mysql.exe` for the same reason. Add `C:\xampp\mysql\bin` to PATH before running fresh installs. |

## What was done

- **`2026_10_08_110000_schema_reconcile_with_live.php`** — guarded, idempotent, no-op
  `down()` (reverting would reintroduce drift). Five steps, all guarded so they no-op on
  live: create `expenses` (Blueprint copied verbatim from `create_expenses_table.php`,
  verified column-by-column against live); add `invoice_items.reference_type`/`reference_id`;
  `module_registry.coming_soon` → NULL; align `dealership_incentives` indexes/FK to live
  (drop helper indexes, swap the FK's backing index to `fk_di_sr` — MariaDB 10.4 has no
  `RENAME INDEX`, so drop-FK → drop-index → add-index → add-FK); case-fix
  `migrations.BATCH` → `batch`.
- **49 placeholder files** `0000_00_00_*.php` (no-op `up()`) so every baseline row has a
  source file. The 317 dated orphans were left as rows-only (documented below) — they
  represent historical files, not missing baselines.
- **Dumps regenerated** from live (commands below): `mysql-schema.sql` = DDL + 665
  migration rows, `schema.sql` = pure DDL, both 494 tables, UTF-8, `AUTO_INCREMENT=`
  counters stripped so regeneration diffs stay minimal.
- **Fresh install validated**: empty DB → `migrate` → schema dump loads (25 s) →
  `Nothing to migrate`. Structure digests: **dump == live == monetix_test (0/0/0)**.

## How to regenerate the dumps

Run after **any** migration that changes table structure (both commands, then re-check
`SchemaIntegrityTest`):

```powershell
$flags = "-u root -h 127.0.0.1 --no-tablespaces --skip-add-locks --skip-comments --skip-set-charset --tz-utc --routines --no-data"
cmd /c "C:\xampp\mysql\bin\mysqldump.exe $flags accumen_ai > database\schema\mysql-schema.sql"
cmd /c "C:\xampp\mysql\bin\mysqldump.exe -u root -h 127.0.0.1 --no-tablespaces --skip-add-locks --skip-comments --skip-set-charset --tz-utc --no-create-info --skip-extended-insert --skip-routines --compact --complete-insert accumen_ai migrations >> database\schema\mysql-schema.sql"
cmd /c "C:\xampp\mysql\bin\mysqldump.exe $flags accumen_ai > database\schema\schema.sql"
# then strip AUTO_INCREMENT=<n> from both files and keep them UTF-8 without BOM
```

## Known, accepted, out-of-scope

- **317 dated orphan rows** (+ `0001_01_01_*`) in live `migrations`: rows without files.
  Harmless while untouched, but **new migration filenames must not collide with them** —
  a colliding file would be treated as already applied. Listed via
  `SELECT migration FROM migrations m WHERE NOT EXISTS (…) …` (see git history of this file
  for the full census).
- **`full_data_safe.sql`** (gitignored, 359 CREATE): kept because `scripts/safe-import.sh`
  and `scripts/safe-dump.sh` depend on it; it is stale relative to the 494-table schema.
  Regenerate locally (`mysqldump accumen_ai > database/schema/full_data_safe.sql`) before
  relying on it for a restore.
- **Data dumps** (`full_data.sql`, `migrations_data.sql`, `seed_data.sql`): reference/data
  artifacts, not schema artifacts — untouched by C3.
- **`schema.sql` must stay pure DDL** (no `migrations` INSERTs) — see Sources of truth.

## Rules going forward

1. Structural migration merged ⇒ regenerate both dumps in the same commit.
2. After pulling migrations: run `composer test:setup` before the test suite
   (`SchemaIntegrityTest` fails otherwise, by design).
3. Never edit an applied migration; add a new guarded one.
4. New migration filenames must not match an orphan row name (see above).
5. Do not reintroduce `full_data_only.sql` / `full_data_utf8.sql` — the test asserts they
   stay gone.
