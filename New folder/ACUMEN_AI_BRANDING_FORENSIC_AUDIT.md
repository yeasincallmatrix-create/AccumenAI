# ACCUMEN AI — BRANDING FORENSIC AUDIT

## New Brand: **Accumen AI**

---

## FILES TO MODIFY (User-Facing Branding — SAFE)

| File | Line | Old Value | Classification | New Value | Risk |
|------|------|-----------|---------------|-----------|------|
| `.env` | 1 | `APP_NAME="MONETIX Academy"` | A — User-facing branding | `APP_NAME="Accumen AI"` | SAFE |
| `.env` | 58 | `MAIL_FROM_NAME="MAWA Academy"` | A — User-facing branding | `MAIL_FROM_NAME="Accumen AI"` | SAFE |
| `.env.testing` | 1 | `APP_NAME="MONETIX Academy"` | A — User-facing branding | `APP_NAME="Accumen AI"` | SAFE |
| `app/Services/Platform/PlatformSettingsService.php` | 32 | `'app.name' => 'MONETIX Academy'` | A — Platform default branding | `'app.name' => 'Accumen AI'` | SAFE |
| `app/Services/Platform/PlatformSettingsService.php` | 33 | `'app.short_name' => 'MONETIX'` | A — Platform default branding | `'app.short_name' => 'AccumenAI'` | SAFE |
| `app/Http/Controllers/Admin/PlatformSettingsController.php` | 25 | `'MONETIX'` (default) | A — Platform default branding | `'AccumenAI'` | SAFE |
| `app/Http/Controllers/Admin/PlatformSettingsController.php` | 425 | `'MAWA Academy test SMS...'` | A — User-facing test message | `'Accumen AI test SMS...'` | SAFE |
| `resources/views/emails/email-otp.blade.php` | 5 | `'MAWA Academy verification code'` | A — User-facing email branding | `'Accumen AI verification code'` | SAFE |
| `resources/views/emails/email-otp.blade.php` | 8 | `'MAWA Academy — Secure verification...'` | A — User-facing email branding | `'Accumen AI — Secure verification...'` | SAFE |
| `resources/views/admin/platform-settings/index.blade.php` | 3 | `'Platform Configuration Center — Monetix'` | A — User-facing page title | `'Platform Configuration Center — Accumen AI'` | SAFE |
| `resources/views/admin/platform-settings/index.blade.php` | 163 | `placeholder="MAWA"` | A — UI placeholder | `placeholder="AccumenAI"` | SAFE |
| `resources/views/admin/platform-settings/index.blade.php` | 164 | `placeholder="MAWA Academy"` | A — UI placeholder | `placeholder="Accumen AI"` | SAFE |
| `resources/views/admin/platform-settings/index.blade.php` | 202 | `'MAWA Academy test SMS...'` | A — UI description text | `'Accumen AI test SMS...'` | SAFE |
| `resources/views/admin/platform-settings/index.blade.php` | 206 | `'MAWA Academy test SMS...'` (×2) | A — UI description + hidden input | `'Accumen AI test SMS...'` | SAFE |
| `resources/views/admin/platform-audit/index.blade.php` | 3 | `'Platform Audit Logs — Monetix'` | A — User-facing page title | `'Platform Audit Logs — Accumen AI'` | SAFE |
| `lang/mawa/en.php` | 974 | `'verified by MAWA Academy'` | A — User-facing platform branding | `'verified by Accumen AI'` | SAFE |
| `lang/mawa/en.php` | 1310 | `'certificate issued by MAWA Academy'` | A — User-facing platform branding | `'certificate issued by Accumen AI'` | SAFE |
| `lang/mawa/en.php` | 1314 | `'issued by MAWA Academy'` | A — User-facing platform branding | `'issued by Accumen AI'` | SAFE |
| `lang/mawa/en.php` | 1318 | `'revoked by MAWA Academy'` | A — User-facing platform branding | `'revoked by Accumen AI'` | SAFE |
| `lang/mawa/en.php` | 1528 | `'For monetix institute staff accounts.'` | A — User-facing login hint | `'For Accumen AI institute staff accounts.'` | SAFE |
| `lang/mawa/bn.php` | 960 | `'verified by MAWA Academy'` | A — User-facing platform branding | `'verified by Accumen AI'` | SAFE |
| `lang/mawa/bn.php` | 1286 | `'certificate issued by MAWA Academy'` | A — User-facing platform branding | `'certificate issued by Accumen AI'` | SAFE |
| `lang/mawa/bn.php` | 1290 | `'issued by MAWA Academy'` | A — User-facing platform branding | `'issued by Accumen AI'` | SAFE |
| `lang/mawa/bn.php` | 1294 | `'revoked by MAWA Academy'` | A — User-facing platform branding | `'revoked by Accumen AI'` | SAFE |
| `lang/mawa/bn.php` | 1504 | `'For monetix institute staff accounts.'` | A — User-facing login hint | `'For Accumen AI institute staff accounts.'` | SAFE |
| `app/Console/Commands/DatabaseCertify.php` | 23 | `"MAWA SaaS"` | A — CLI output branding | `"Accumen AI SaaS"` | SAFE |
| `tests/Feature/E19_1EmailOtpUiTest.php` | 311 | `str_contains($rendered, 'MAWA Academy')` | A — Test assertion (must match new branding) | `str_contains($rendered, 'Accumen AI')` | SAFE |

---

## PROTECTED TECHNICAL REFERENCES (NO CHANGE)

| File | Line | Value | Classification | Reason |
|------|------|-------|---------------|--------|
| `app/Models/User.php` | 289, 301 | `admin@mawa.com` | D — Technical auth identifier | Super admin virtual verification email |
| `app/Models/PlatformAdmin.php` | 101, 113 | `admin@mawa.com` | D — Technical auth identifier | Super admin virtual verification email |
| `app/helpers.php` | all | `mawa_lang()`, `mawa_e()`, etc. | D — Technical function names | Translation helper functions |
| `app/Http/Middleware/SetLocale.php` | 27, 44 | `mawa_lang`, `mawa_current_lang()` | D — Technical session/function | Language persistence |
| `resources/css/app.css` | all | `html.monetix-dark` | D — CSS class name | Dark mode theme class |
| `resources/views/layouts/admin.blade.php` | 3-4 | `monetix-dark`, `monetix-tall-nav` | D — CSS class references | Theme class names |
| `resources/views/layouts/institute.blade.php` | 3-4 | `monetix-dark`, `monetix-tall-nav` | D — CSS class references | Theme class names |
| `resources/views/layouts/standalone.blade.php` | 3 | `monetix-dark` | D — CSS class reference | Theme class name |
| All JS | various | `Monetix.request()`, `Monetix.toast()`, etc. | D — JavaScript API | Client-side framework namespace |
| `config/backup.php` | 157, 201 | `monetix_dr_test`, `monetix` | D — Config identifiers | Backup system config |
| `config/fortify.php` | 18 | `MAWA SaaS` | D — Comment only | Developer documentation |
| `database/backups/*.sql` | various | `monetix` | D — Database name in backups | Historical backup files |
| `app/Console/Commands/PhoneNormalizeCommand.php` | 47, 49 | `monetix_backup_*` | D — File path reference | Backup file detection |
| `app/Services/System/DisasterRecoveryService.php` | 62, 77 | `monetix_dr_test` | D — Config default | Disaster recovery temp DB |
| `app/Services/System/BackupService.php` | 26 | `monetix` | D — Config default | Backup app name |
| `lang/mawa/en.php` | 240 | `'your_academy'` | D — Generic pluralization | Generic "academy" in student context |
| `lang/mawa/bn.php` | 226 | `'your_academy'` | D — Generic pluralization | Generic "academy" in student context |
| `lang/mawa/en.php` | 2166 | `'Graduated students of :academy'` | D — Dynamic placeholder | Generic placeholder |
| `lang/mawa/bn.php` | 2142 | `'Graduated students of :academy'` | D — Dynamic placeholder | Generic placeholder |
| `config/industry_rules.php` | various | `dance_academy`, `music_academy`, etc. | D — Industry type codes | Industry classification codes |
| `database/seeders/LearningStructureSeeder.php` | various | `dance_academy`, `music_academy`, etc. | D — Seed data type codes | Industry type seed data |
| `tests/Feature/OwnerRegistrationTest.php` | 187 | `'Rafiq Academy'` | C — Test institute data | Test fixture institute name |
| `tests/Feature/AiSettingsTest.php` | various | `$this->academy()` | D — Test helper method | Test helper naming |
| `tests/Feature/AiIntegrationTest.php` | various | `$this->academy()`, `Other Academy`, `Fixture Academy` | C/D — Test fixture data | Test helper and fixture names |
| `tests/Feature/AiAssistantAjaxTest.php` | various | `$this->academy()` | D — Test helper method | Test helper naming |
| `tests/Feature/LearningStructureEngine*Test.php` | various | `dance_academy`, `music_academy`, etc. | D — Test assertions | Industry type code tests |
| `tests/Feature/AccountingMultiCurrencyTest.php` | various | `'MAWA ACADEMY'` | C — Test fixture data | Test institute name fixture |

---

## SUMMARY

- **Files to modify:** 14 source files
- **Total branding changes:** 27 individual value changes
- **Technical references preserved:** All internal identifiers, CSS classes, JS namespaces, config keys, function names, test helpers, database references
- **Tenant/institute references:** Test fixture "MAWA ACADEMY" in AccountingMultiCurrencyTest preserved (test data, not platform branding)
- **admin@mawa.com:** Preserved (authentication identifier)
- **mawa_lang/mawa_e functions:** Preserved (technical infrastructure)
