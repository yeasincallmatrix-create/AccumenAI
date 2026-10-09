# APP_KEY Rotation Runbook

**Service:** AccumenAI / Monetix
**Command under test:** `php artisan app-key:reencrypt`
**Last verified:** local integration test (see §7), branch `security/audit-fixes`

> **Rule:** never print a key. Report only lengths or hashes —
> `OLD key = <redacted, 51 chars>`.

---

## 0. What this rotates, and why

`APP_KEY` is **leaked**. It is present in the git history in plaintext:

- reachable from `HEAD` (`5d8b0f93` is an ancestor)
- last containing commit: **`687e982a`**

The same leaked `.env` blob also contains a **non-placeholder `MAIL_PASSWORD`**
(21 chars, identical to today's value). Rotating `APP_KEY` does **not** rotate
`MAIL_PASSWORD` — it is a separate secret in the same leak and must be rotated
with the mail provider.

### Encrypted stores

**A. Non-Fortify — handled by `app-key:reencrypt`**
All use `serialize = false` (verified in framework source: `HasAttributes.php:1438`
`encrypt($v,false)`, `:1426` `decrypt($v,false)`, `Encrypter.php:141/206`).

| # | Table | Column(s) |
|---|---|---|
| 1 | `institute_payment_gateways` | `credentials` |
| 2 | `settings` | `value` (the 22 keys in `Setting::$encrypted`) |
| 3 | `platform_service_configs` | `value` where `is_encrypted = 1` |
| 4 | `institute_settings` | `smtp_password_enc` |
| 5 | `institute_settings` | `sms_api_key_enc` |
| 6 | `ai_api_keys` | `api_key` |

**B. Fortify 2FA — NOT handled by the command (serialize = `true`)**

`Fortify::currentEncrypter()->encrypt($value)` / `->decrypt($value)` use the
defaults, i.e. `serialize()`. Present on **five** tables:

```
guardians | institute_users | platform_admins | platform_staffs | users
         (columns: two_factor_secret, two_factor_recovery_codes)
```

**C. Deliberately out of scope**

- `institute_settings.payment_config_enc` — **no read/write exists in app code**
  (`app/`, `resources/` searched). It also carries `CHECK json_valid(...)`,
  which is inconsistent with storing ciphertext. Treat as a dead column.
- `institute_payment_gateways.credentials` — schema enforces
  `CHECK json_valid(credentials)` while the model casts it `encrypted:array`
  (base64). The two are mutually exclusive; see §7.1.

---

## 1. Pre-flight

**Gate: production row counts are required before §3.** Local dev counts are
not a substitute.

```sql
SELECT 'gateway' s, COUNT(*) n FROM institute_payment_gateways WHERE credentials IS NOT NULL AND credentials<>''
UNION ALL SELECT 'settings', COUNT(*) FROM settings
UNION ALL SELECT 'psc', COUNT(*) FROM platform_service_configs WHERE is_encrypted=1
UNION ALL SELECT 'inst_smtp', COUNT(*) FROM institute_settings WHERE smtp_password_enc IS NOT NULL AND smtp_password_enc<>''
UNION ALL SELECT 'inst_sms',  COUNT(*) FROM institute_settings WHERE sms_api_key_enc   IS NOT NULL AND sms_api_key_enc<>''
UNION ALL SELECT 'ai',        COUNT(*) FROM ai_api_keys       WHERE api_key IS NOT NULL AND api_key<>''
UNION ALL SELECT '2fa_admin', COUNT(*) FROM platform_admins    WHERE two_factor_secret IS NOT NULL AND two_factor_secret<>''
UNION ALL SELECT '2fa_users', COUNT(*) FROM users              WHERE two_factor_secret IS NOT NULL AND two_factor_secret<>'';
```

Then:

1. **Backups** — DB dump + `.env` copy. Use `mysqldump -r <file>` (do **not**
   shell-redirect: PowerShell `>` writes UTF-16 and corrupts SQL).
2. Confirm `config:clear` / `config:cache` state.
3. Note running workers (queue, scheduler, Octane) — they cache config at boot.
4. Take a **git bundle / branch backup** of the repo (history rewrite is a
   separate, blocked task).

---

## 2. Dry-run

```bash
php artisan app-key:reencrypt --dry-run
```

Read-only. Classifies every value:

| Status | Meaning |
|---|---|
| `NEEDS_REENCRYPT` | ciphertext decrypts with `--old-key` only |
| `ALREADY_REENCRYPTED` | already on the new key (idempotent) |
| `PLAINTEXT_SKIPPED` | not a Laravel payload — left untouched |
| `FAILED` | decrypts with **neither** key → **abort, nothing written** |

Expected on an untouched DB: `would_reencrypt = <count from §1>`, `failed = 0`.

To also exercise the decrypt path (proves the old key is the right one):

```bash
php artisan app-key:reencrypt --dry-run --old-key="$OLD" --new-key="$NEW"
```

Counts must match the bare dry-run. A mismatch means `--old-key` is wrong.

---

## 3. Rotation

**Recommended: zero-downtime, using Laravel's native previous-key support.**

`config/app.php:104` already reads a comma-separated `APP_PREVIOUS_KEYS`.
`Encrypter::decrypt()` tries every key (`Encrypter.php:170`), while
`encrypt()` always uses the primary key only (`:110`, `:122`).

```bash
# 1. generate a fresh key (never echo it)
php artisan key:generate --show        # take the value, do not print it

# 2. .env
APP_KEY=base64:<NEW>
APP_PREVIOUS_KEYS=base64:<OLD>

# 3. apply
php artisan config:clear && php artisan queue:restart
#    restart php-fpm / web server / scheduler / Octane too

# 4. migrate the rows off the old key
php artisan app-key:reencrypt --old-key="$OLD" --new-key="$NEW"

# 5. confirm
php artisan app-key:reencrypt --dry-run --old-key="$OLD" --new-key="$NEW"
#    expect: would_reencrypt=0  failed=0
```

**Why `APP_PREVIOUS_KEYS` matters** — while it is set:

- old ciphertext still decrypts → **no unreadable-data window**
- cookies/sessions still decrypt → **no mass logout**
- **Fortify 2FA keeps working on all five tables** → no forced 2FA reset

Scoped runs (`--table=...`) let you migrate one store at a time; valid values
are `institute_payment_gateways|settings|platform_service_configs|institute_settings|ai_api_keys`.

Rotate `MAIL_PASSWORD` separately with the mail provider, then update `.env`.

### Removing the previous key

Only remove `APP_PREVIOUS_KEYS` once **all** rows report `ALREADY_REENCRYPTED`
**and** Fortify 2FA secrets have been dealt with (§6). Removing it while any
row still needs the old key breaks that row permanently.

---

## 4. Rollback

Safe at any point, because the old key is still available:

```bash
# restore .env
APP_KEY=base64:<OLD>
APP_PREVIOUS_KEYS=          # clear

php artisan config:clear && php artisan queue:restart

# restore the DB dump taken in §1
mysql -u root accumen_ai -e "source <backup.sql>"
```

Notes:

- Rows already rewritten with the new key are **still readable** if
  `APP_KEY=<NEW>` + `APP_PREVIOUS_KEYS=<OLD>` remain — so "re-encrypt with
  old→new, then decide" is reversible by simply putting OLD back as primary
  and NEW into `APP_PREVIOUS_KEYS`.
- `git revert` / restore the `.env` backup; verify with a SHA-256 comparison
  against the pre-change hash.
- Nothing is written until classification completes: a `FAILED` value aborts
  **before** any `UPDATE`, and each store writes inside its own transaction
  that rolls back on error.

---

## 5. Post-verify

1. `--dry-run` → `would_reencrypt=0`, `failed=0`.
2. Round-trip a sample of each store through the app's own `Crypt` while the
   **new** key is installed in `.env`, comparing against known plaintext.
3. Confirm no worker still holds the old config (`queue:restart`, reload FPM).
4. Confirm `.env` permissions and that no key was echoed into logs:
   `Log::info` in the command records only `table` / `row` / `columns` / `status`.
5. Confirm the leaked secrets are rotated: `APP_KEY` **and** `MAIL_PASSWORD`.
6. Rotate the *git* credential as well — rewriting history does not invalidate
   any token already issued.

---

## 6. Emergency override and the 2FA window

`SuperAdmin\EmergencyOverrideController` (`routes/web.php:41-42`,
`auth:platform_admin` + `verified`) is a legitimate feature, not a bypass:
it demands **2FA + a 50-char reason + typing `I UNDERSTAND THE RISK`**, and it
only reads `platform_admins.two_factor_secret`.

**The trap:** `EmergencyOverrideController.php:64-68` refuses to run when
2FA is *not* enabled and confirmed. So if you disable 2FA to get past a
rotation problem, **the emergency override is also unavailable** — the
escape hatch disappears exactly when you need it.

**Recommended: do not disable 2FA at all.**

With `APP_PREVIOUS_KEYS=<OLD>` set (§3), Fortify keeps decrypting old
`two_factor_secret` values, so:

- no user loses 2FA
- the emergency override stays usable throughout

`EmergencyOverrideController::decryptSecret()` (`:180-187`) already tolerates
both serializations (tries `Crypt::decrypt`, falls back to
`Crypt::decryptString`), and both resolve to the same `Encrypter` instance —
so it inherits `previousKeys` automatically.

**If `APP_PREVIOUS_KEYS` must be removed**, choose one *before* doing so:

| Option | Action | Cost |
|---|---|---|
| **1. Manual reset** | Set `two_factor_secret`/`two_factor_recovery_codes` to `NULL` for affected rows (1–3 rows) | Users re-enroll; override unavailable until they do |
| **2. Programmatic migration** | One-off re-encrypt with **`serialize = true`** (note: opposite of the six §0-A stores) | Needs building — not implemented yet |
| **3. Keep previous key** | Leave `APP_PREVIOUS_KEYS` in place | Old key stays usable; weakens the rotation |

**Counts decide:** `0` affected rows → no action. Otherwise pick 1 or 2.

---

## 7. Local integration test record

Verified on local MariaDB with a seeded fixture (7 rows / 8 values), then
**fully restored** from dump (all counts and both `json_valid` CHECKs back,
`.env` SHA-256 unchanged).

| Step | Result |
|---|---|
| Bare dry-run | `rows=12 needs=6 plaintext=2 failed=0` ✅ |
| Dry-run with keys | identical counts ✅ (proves `--old-key` correct) |
| `--table=settings` write | exactly `2` written ✅ |
| Full write | `already_ok=2`, `4` written ✅ |
| Idempotent re-run | `needs=0`, `0` written, exit 0 ✅ |
| Round-trip under new key | `7/7` encrypted stores + `Setting::get()` matched ✅ |
| Restore | DB + `.env` byte-identical ✅ |

Two real defects were found and fixed by this test
(`84f13eee`, `cd148de4`):

1. `use Illuminate\Encryption\DecryptException` **does not exist** in this
   Laravel version — the catch never matched and `classify()` threw. It only
   surfaced on the idempotency path (a value already on the new key).
2. `$this->row()` is not a console method — the command crashed on first use.

### 7.1 Known schema conflict (pre-existing, not introduced here)

`institute_payment_gateways.credentials` is `encrypted:array` in the model but
the column carries `CHECK json_valid(credentials)`. Ciphertext is base64, so
`json_valid()` returns `0` and the insert fails. The table is actively used
(`PaymentGateway\Gateways\BkashGateway.php:21`,
`FinanceOnlinePaymentController.php:45`). This blocks **writes and reads** of
gateway credentials on MariaDB/MySQL and needs its own fix; it does not affect
the other five stores.

---

## Quick reference

```bash
# classify only
php artisan app-key:reencrypt --dry-run

# prove the old key is correct
php artisan app-key:reencrypt --dry-run --old-key="$OLD" --new-key="$NEW"

# migrate everything
php artisan app-key:reencrypt --old-key="$OLD" --new-key="$NEW"

# migrate one store
php artisan app-key:reencrypt --table=settings --old-key="$OLD" --new-key="$NEW"
```
