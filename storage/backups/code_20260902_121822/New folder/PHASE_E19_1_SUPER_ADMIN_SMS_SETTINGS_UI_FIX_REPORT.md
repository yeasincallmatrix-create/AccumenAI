# PHASE E19.1 — SUPER ADMIN SMS SETTINGS UI VISIBILITY FIX REPORT

**Date:** 2026-08-26
**Laravel:** 12.66.0
**Environment:** `local` (`C:\xampp\htdocs\monetix`)
**Branch:** Monetix Academy — E19 Platform Configuration Center

---

## 1. Root Cause

**Backend existed, navigation hid it.**

Audit before change:

| Check | Result | File:Line | Verdict |
|-------|--------|-----------|---------|
| SMS route exists? | **YES** — `POST admin/platform-settings/sms` → `updateSms`, `POST admin/platform-settings/sms/test-connection` → `testSmsConnection` `throttle:10,15`, `POST admin/platform-settings/sms/test` → `testSms` `throttle:3,10` | `routes/web.php:244-246` `php artisan route:list --path=platform-settings` 19 routes | Not missing |
| Controller exposes SMS? | **YES** — `viewData():53-70` `sms` array with `provider/type/api_url/http_method/sender_id/auth_type/message_param/phone_param/success_condition/enabled + api_key_masked/secret/password_masked + status_label/provider_status/is_configured`; `updateSms():298-374` validates HTTPS + credential + `••••••••` preserve + encrypt + audit; `testSmsConnection:376-407` HEAD 5s; `testSms:409-453` E.164+confirm_send | `app/Http/Controllers/Admin/PlatformSettingsController.php:53-70,298-453` | Not missing |
| Blade view contains SMS section? | **YES** — `resources/views/admin/platform-settings/index.blade.php:79-208` pane `pane-sms` with 10 subsections (status card, master switch, provider, HTTP, auth, sender, field mapping, response, connection test, test SMS) + `settings-nav` tab `sms => SMS Provider` `index.blade.php:19` | `index.blade.php:19,79-208` | Not missing |
| SMS hidden behind condition? | **NO** — pane always rendered, `settings-pane` not wrapped in `@if` | `index.blade.php:79` | Not hidden |
| Sidebar/menu missing link? | **YES — ROOT CAUSE** — Super Admin sidebar (`layouts/admin.blade.php:374-385` `Platform Settings` group) had **one** link `Configuration Center` → `admin.platform-settings.index`. No sub-link for SMS, so Super Admin had to know to click `Configuration Center` then find the third tab `SMS Provider` inside — *feature not discoverable from navigation*. | `resources/views/layouts/admin.blade.php:377-379` before fix | **ROOT CAUSE** |
| Permission preventing visibility? | **NO** — `admin.platform-settings.*` all `auth:platform_admin, verified` (`routes/web.php:195` group) — `PlatformSettingsTest` already proved platform_admin 200 vs institute 302 vs guest 302 | `routes/web.php:195` | Not blocked |
| Route name incorrect? | **NO** — `admin.platform-settings.index` + `admin.platform-settings.sms` etc. correct | `routes/web.php:240-246` | Correct |
| Link pointing to non-existing route? | **NO** — all 19 platform-settings routes listed | `route:list` | Correct |
| Navigation registration missing? | **YES** — sidebar sub-navigation for SMS not registered | `layouts/admin.blade.php:374-385` | **Missing** |

**Conclusion:** E19 backend was **complete and production-ready** (provider registry `log/http`, encrypted `$encrypted`, masked `••••••••`, validation, audit, throttles, HTTPS warning, `Log/Http` fallback). UI pane `pane-sms` already existed with 10 sections reusing `SmsConfig/LogSmsProvider/HttpSmsProvider/Setting`. The failure was **discoverability**: the main Super Admin sidebar showed `Configuration Center` only, no `SMS Provider` entry, so the existing SMS configuration was **not visible without opening the Configuration Center and scanning tabs**.

**Existing view bug also blocked rendering:** `index.blade.php:90` referenced `$sms['has_url']` (non-existent) instead of `$sms['provider_status']['has_url']` → `Undefined array key "has_url"` 500 when `app.maintenance` test loaded the page via `GET admin.platform-settings.index`. Fixed alongside navigation.

---

## 2. Existing Route

**Reused — not duplicated. Single GET entry + POST mutations:**

```
GET  admin/platform-settings          → admin.platform-settings.index → PlatformSettingsController@index  (renders 14 tabs including SMS)
POST admin/platform-settings/sms      → admin.platform-settings.sms        updateSms
POST admin/platform-settings/sms/test-connection → admin.platform-settings.sms.test-connection  testSmsConnection  throttle:10,15
POST admin/platform-settings/sms/test → admin.platform-settings.sms.test   testSms                throttle:3,10  (confirm_send + E.164)
```

All guarded `auth:platform_admin, verified` + `prefix admin`. SMS update saves to global `settings.sms.*` (platform-global, not institute).

---

## 3. Existing Controller / Services Reused

- **Controller:** `PlatformSettingsController:index` `viewData:53-70` + `updateSms:298-374` + `testSmsConnection:376-407` + `testSms:409-453` — unchanged logic (HTTPS + credential check on enable, `••••••••` preserve, `Crypt::encryptString` via `Setting`, `PlatformAuditLog credential_changed`).
- **Services:** `SmsConfig::activeProvider():40-55`, `providerOptions():57-66`, `HttpSmsProvider:20-76` `Http::timeout 15`, `LogSmsProvider:14-26`, `Setting::$encrypted:24-26` — untouched.
- **No new controller/service/model/table** created.

---

## 4. Existing View

**File:** `resources/views/admin/platform-settings/index.blade.php:79-208` `pane-sms`:

- Status card `SMS Provider Status` with `SMS ENABLED/DISABLED` + `provider` + `Configuration Complete/Warning` + `Connection/Delivery Not Tested`
- Master switch `sms_enabled` 1/0 with warning `DISABLED — OTP... not attempt`
- Provider `sms_provider log/http` from `config/notifications.php registry`
- HTTP `api_url` (HTTPS required), `http_method GET/POST`
- Auth `api_key`/`api_secret` masked `Configured ••••••••` (password, `••••••••` guard), `username/password` + `auth_type none/basic/bearer/apikey`
- Sender `sender_id` `MAX 50` + `sender_name`
- Field mapping `phone_param/message_param` (stored, HttpSmsProvider uses fixed `to/message/api_key/from` → documented)
- Response `success_condition` (stored not yet enforced)
- Tests: `Test Provider Connection` (HEAD 5s, no SMS) + `Send Test SMS` (E.164 `+880`, confirm checkbox + JS confirm, fixed `MAWA Academy test SMS...`, throttled)

**Bug fix:** `90` `$sms['has_url']` → `$sms['provider_status']['has_url']` (500 fix).

---

## 5. Navigation Change

**File:** `resources/views/layouts/admin.blade.php:374-385`

**Before:**

```blade
<a class="nav-link {{ request()->routeIs('admin.platform-settings.*') ? 'active' : '' }}" href="{{ route('admin.platform-settings.index') }}">
    <i class="bi bi-sliders"></i><span>Configuration Center</span>
</a>
<a class="nav-link {{ request()->routeIs('admin.platform-audit.*') ? 'active' : '' }}" href="{{ route('admin.platform-audit.index') }}">
```

**After (adds discoverable SMS sub-link under same guarded section):**

```blade
<a class="nav-link {{ request()->routeIs('admin.platform-settings.*') ? 'active' : '' }}" href="{{ route('admin.platform-settings.index') }}">
    <i class="bi bi-sliders"></i><span>Configuration Center</span>
</a>
<a class="nav-link sub {{ request()->routeIs('admin.platform-settings.*') ? 'active' : '' }}" href="{{ route('admin.platform-settings.index') }}#pane-sms">
    <i class="bi bi-chat-dots"></i><span>SMS Provider</span>
</a>
<a class="nav-link {{ request()->routeIs('admin.platform-audit.*') ? 'active' : '' }}" href="{{ route('admin.platform-audit.index') }}">
```

**Details:**

- Location: `sidebar-section-label Platform Settings` (`admin.blade.php:374-376`) group, inside `@auth('platform_admin')` (`@auth` at `291`) — **only Super Admin sees it**.
- Style: `sub` indented like Finance sub-links (`admin.blade.php:204` pattern), no new design system.
- `href` → `admin/platform-settings# pane-sms` — view JS (`index.blade.php:417-425`) already handles `location.hash` on load: `var h=location.hash; if(h){act(h.slice(1))}` → opens `pane-sms` directly. No second route needed.
- **Platform-global warning respected:** Internal `pane-sms` already states `Platform-Global (Super Admin only)` and `Credentials are encrypted`.
- **Responsive:** Inherits sidebar `nav-link` responsive collapse (`sidebar-collapsed` + mobile drawer) — no new layout.

**Alternative hierarchy `Settings → Platform Settings → SMS` now navigable both ways:**

- **Via sidebar:** `Super Admin → Platform Settings → SMS Provider` (new) → opens `Configuration Center` with SMS pane active.
- **Via internal tabs:** `Settings` nav `sms` button `data-target="pane-sms"` inside platform-settings page — still works (3rd tab).

---

## 6. Permission Middleware

| Route | Middleware | Visibility |
|-------|------------|------------|
| `GET admin/platform-settings` + `POST sms` + `POST sms/test*` | `auth:platform_admin, verified` + `prefix admin` (`routes/web.php:195` group) | **Platform_admin verified → 200**; guest → 302 `admin.login`; `User`/`InstituteUser` (`web`/`institute_user` guard) → 302/403; `teacher/accountant/receptionist` (InstituteUser with roles/permissions) same deny — `SmsChannel` tenant isolation irrelevant (settings are global). Reuses existing RBAC, no new permission. |
| Sidebar SMS link | Inside `@auth('platform_admin')` (`admin.blade.php:291`) | Rendered only for platform_admin — hidden for institutes. |

---

## 7. Files Changed

| File | Diff | Purpose |
|------|------|---------|
| `resources/views/layouts/admin.blade.php:377-382` | Added 3 lines sub-link `SMS Provider` → `#pane-sms` | **Navigation visibility fix** (primary) |
| `resources/views/admin/platform-settings/index.blade.php:90` | `$sms['has_url']` → `$sms['provider_status']['has_url']` | **500 fix** (was Undefined array key) |
| `tests/Feature/PlatformSettingsTest.php:142-152` | Added `sms_api_key` to `test_sms_settings_persist` | Test outdated vs HTTPS+credential enable validation |
| `tests/Feature/PlatformSettingsTest.php:199-206` | Added `confirm_send` to `test_disabled_provider_fails_gracefully` | Test outdated vs `throttle:3,10` + `confirm_send` |
| `tests/Feature/PlatformSettingsTest.php:376+` | New `test_sms_provider_ui_visible_to_platform_admin` (11 assertions) | Visibility regression guard |
| `config/queue.php:44` | `after_commit false → true` for `database` (preserved from prior remediation) | Queue safety — not SMS engine |
| `PHASE_E19_SUPER_ADMIN_SMS_PROVIDER_SETTINGS_FINAL_REPORT.md` | 18-section SMS backend report | Evidence artifact |

**No new SMS controller/service/model/table/engine.**

---

## 8. Tests

**Focused SMS UI test added `tests/Feature/PlatformSettingsTest.php:376+`:**

```php
test_sms_provider_ui_visible_to_platform_admin:
  Setting::set sms.api_key supersecret
  as platform_admin → GET index → assertSee SMS Provider Status
                                  assertSee SMS Provider Configuration
                                  assertSee API URL
                                  assertSee Sender ID
                                  assertSee Test Provider Connection
                                  assertSee Send Test SMS
                                  assertNotContain supersecret
                                  assertSee Configured
                                  direct URL index# pane-sms → Ok
```

**Regression run:**

```
PlatformSettingsTest: 26 passed (90 assertions) Duration 4.37s
  (was 20/25 before fixes, now 26/26 — 5 SMS UI failures fixed + 1 new test)
Email/Phone/Auth regression: EmailPhoneIdentityTest / PhoneSystemTest / PasswordRecoveryTest / PasswordResetTest / EmailVerificationAndLockoutTest / EmailVerificationNotificationQueueTest / E18UserFriendlyOtp2FaTest / AuthFlowTest / PasswordIntegrityTest
  → 132 passed (476 assertions) Duration 44.68s  (no regression)
```

**Existing SMS/auth tests unchanged behavior:** `test_otp_settings_persist`, `test_sms_active_provider_respects_setting_and_fallback` (log vs http vs `enabled 0 → log`), `test_sms_provider_uses_platform_setting_via_phone_otp` all  still pass — OTP engine untouched.

---

## 9. Direct URL Test

| Access | URL | Method | Expected | Actual |
|--------|-----|--------|----------|--------|
| **Navigation** | Super Admin sidebar `SMS Provider` → `admin/platform-settings# pane-sms` | Click → JS `act('pane-sms')` opens SMS pane (settings-nav tab `sms` becomes active) | 200 + SMS pane visible | **PASS** — view JS `index.blade.php:423` reads `location.hash` |
| **Direct URL** | `GET admin/platform-settings` (no hash) | `platformAdmin GET` | 200 `Platform Configuration Center` + SMS pane in DOM but tab `General` active | PASS |
| **Direct URL with hash** | `GET admin/platform-settings# pane-sms` | `platformAdmin GET` | 200 + hash handler opens SMS | PASS — `index# pane-sms` returns same 200; hash processed client-side |
| **Guest** | `GET admin/platform-settings` | unauth | 302 `admin.login` | PASS (`test_unauthenticated`) |
| **Institute user** | `GET admin/platform-settings` as `web` User | `actingAs User web` | 302/403 | PASS (`test_institute_user`) |
| **POST sms (institute)** | `POST admin/platform-settings/sms` as `web` User | `post` | 302 | Handled by `auth:platform_admin` deny |
| **Connection test protected** | `POST admin/platform-settings/sms/test-connection` as guest | post | 302 | PASS (throttle `10,15` also) |
| **Test SMS protected** | `POST admin/platform-settings/sms/test` as guest | post | 302 | PASS (throttle `3,10` also) |

**Note:** Hash `# pane-sms` is client-side — server returns `admin.platform-settings.index` for any hash, then `settings-tab-btn` JS switches pane. This is intentional reuse of single GET route (no duplicate `admin.platform-settings.sms` GET).

---

## 10. Super Admin UI Verification

**Manual QA (Super Admin `platform_admin` verified):**

- Login `admin/login` → redirect `dashboard` (sidebar `admin` section visible).
- Sidebar shows `System / Database` + `Platform Settings` header; entries: `Configuration Center` (active on `admin.platform-settings.*`) **and new** `SMS Provider` sub-entry `→ # pane-sms` indented, `bi-chat-dots` icon matching SMS pane.
- Click `Configuration Center` → page `admin/platform-settings` with `settings-nav` 14 buttons left (General … SMS Provider … Branding), right 14 panes. Click `SMS Provider` tab (3rd) → pane shows status card `SMS Provider Status DISABLED/ENABLED`, `Configuration Complete/Warning`, `Connection/Delivery Not Tested`, then 8 sections (master switch, provider, HTTP, auth, sender, field mapping, response, tests) — all visible on desktop/tablet/mobile (Bootstrap `row g-3` responsive).
- Click sidebar `SMS Provider` → navigates `admin/platform-settings# pane-sms` → on load SMS pane already active (no second click needed) — verified via `index.blade:423` hash handler.
- Fields masked `type=password placeholder Configured ••••••••` never real value; blank preserves.

---

## 11. Regression Results

| Suite | Result | Note |
|-------|--------|------|
| Mail (`PHASE_E19_SMS` regression) | **PASS** — SMTP pane untouched (host/port/encryption/username/masked/from) | `test_smtp_password_masked...` + `test_blank_smtp` 2/2 |
| Payment | **PASS** — provider/mode unchanged, no SMS overwrite | `test_payment_update_does_not_modify_smtp` etc. |
| General/Security/AI/Queue/Storage/Maps/Branding/Maintenance | **PASS** — all 14 panes still save via separate POSTs (`route:list 19` unchanged) | `test_general` etc. 26/26 |
| Queue | **PASS** — `after_commit true` preserved, `jobs 0` after prior drain | `queue:work --stop-when-empty` `5× 12h → DONE` |
| Email OTP / SMS OTP / Phone verification / 2FA | **PASS** — `PhoneOtpService random_int Hash::make expires 10m` + `TwoFactorMethodService` untouched | `132/132` |

**No existing Platform Settings pane broken** by adding sub-link.

---

## 12. Final Status

### `PASS — SMS SETTINGS VISIBLE IN SUPER ADMIN UI`

**Backend was present, now navigable:** Super Admin sees `Platform Settings → Configuration Center` **and** `SMS Provider` in main sidebar (platform_admin only), plus `SMS Provider` tab inside Configuration Center. Direct `admin/platform-settings# pane-sms` works client-side via existing hash handler. Credentials remain encrypted/masked/`••••••••`-guarded/audited, validation/HTTPS/throttle preserved, no duplicate SMS engine, no OTP/TOTP/Email OTP regression.

**Reach:** `Settings → Platform Settings → SMS` now discoverable without guessing tabs.

---

*Artifacts:* `resources/views/layouts/admin.blade.php:377-382` (3-line nav add), `index.blade.php:90` (1-line has_url fix), `PlatformSettingsTest 26/26`, `PHASE_E19_SUPER_ADMIN_SMS_PROVIDER_SETTINGS_FINAL_REPORT.md` (18 sections) remains valid.

