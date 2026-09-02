# SUPER ADMIN UI/UX FORENSIC AUDIT REPORT

**Date:** 2026-08-26
**Scope:** Super Admin / Platform Admin Dashboard ONLY
**Status:** READ-ONLY AUDIT COMPLETE

---

## 1. AUTHENTICATION ARCHITECTURE

| Component | Value |
|-----------|-------|
| **Guard** | `platform_admin` |
| **Model** | `App\Models\PlatformAdmin` |
| **Table** | `platform_admins` |
| **Login URL** | `/admin/login` |
| **Login Controller** | `App\Http\Controllers\Auth\PlatformAdminLoginController` |
| **Middleware** | `auth:platform_admin`, `verified` |
| **Session Driver** | `session` (database-backed) |
| **2FA** | TOTP + SMS + Email (via Fortify `TwoFactorAuthenticatable`) |
| **Password Broker** | `platform_admins` |

### Authentication Flow
1. GET `/admin/login` → `PlatformAdminLoginController@showLoginForm`
2. POST `/admin/login` → `PlatformAdminLoginController@login` (throttle: 5/15min)
3. If 2FA enabled → redirects to `two-factor.login` challenge
4. On success → redirects to `/` (dashboard)
5. Guard cleared on logout; `TenantContext::clear()` called

### Authorization
- No role-based middleware; relies on `auth:platform_admin` guard membership
- Platform admin users are in `platform_admins` table (separate from `institute_users`)

---

## 2. SUPER ADMIN ROUTES (Complete Inventory)

### Database Control Center Routes (`super-admin` prefix)
| Route | Name | Method |
|-------|------|--------|
| `/super-admin/database/control-center` | `super-admin.database.control-center` | GET |
| `/super-admin/database/control-center/json` | `super-admin.database.control-center.json` | GET |
| `/super-admin/database/monitoring` | `super-admin.database.monitoring` | GET |
| `/super-admin/database/monitoring/refresh` | `super-admin.database.monitoring.refresh` | POST |
| `/super-admin/database` | `super-admin.database.dashboard` | GET |
| `/super-admin/database/refresh` | `super-admin.database.refresh` | POST |
| `/super-admin/database/backups` | `super-admin.database.backups` | GET |
| `/super-admin/database/backups/create` | `super-admin.database.backups.create` | POST |
| `/super-admin/database/backups/{backup}/verify` | `super-admin.database.backups.verify` | POST |
| `/super-admin/database/backups/retention/execute` | `super-admin.database.retention.execute` | POST |
| `/super-admin/database/recovery` | `super-admin.database.recovery` | GET |
| `/super-admin/database/recovery/drill` | `super-admin.database.recovery.drill` | POST |
| `/super-admin/database/health` | `super-admin.database.health` | GET |
| `/super-admin/database/performance` | `super-admin.database.performance` | GET |
| `/super-admin/database/integrity` | `super-admin.database.integrity` | GET |
| `/super-admin/database/audit` | `super-admin.database.audit` | GET |
| `/super-admin/database/status` | `super-admin.database.status` | GET |

### Admin Routes (`admin` prefix, `auth:platform_admin`)
| Route | Name | Method |
|-------|------|--------|
| `/admin/institutes` | `admin.institutes.index` | GET |
| `/admin/institutes/bin` | `admin.institutes.bin` | GET |
| `/admin/institutes/{institute}` | `admin.institutes.show` | GET |
| `/admin/institutes/{institute}/edit` | `admin.institutes.edit` | GET |
| `/admin/institutes/{institute}` | `admin.institutes.update` | PUT |
| `/admin/institutes/{institute}/action` | `admin.institutes.action` | POST |
| `/admin/institutes/{institute}/restore` | `admin.institutes.restore` | POST |
| `/admin/institutes/{institute}/force-delete` | `admin.institutes.force-delete` | DELETE |
| `/admin/institutes/{institute}/staff/{kind}/{id}` | `admin.institutes.staff.destroy` | DELETE |
| `/admin/courses` | `admin.courses.index` | GET |
| `/admin/courses/assignment` | `admin.courses.assignment` | GET |
| `/admin/courses/assignment/assign` | `admin.courses.assignment.assign` | POST |
| `/admin/courses/assignment/remove` | `admin.courses.assignment.remove` | POST |
| `/admin/courses/subjects` | `admin.courses.subjects` | GET |
| `/admin/courses/subjects-columns` | `admin.courses.subjects-columns` | GET |
| `/admin/courses/subject-requests` | `admin.courses.subjects-requests` | GET |
| `/admin/courses/subject-requests/{subjectRequest}/action` | `admin.courses.subjects-requests.action` | POST |
| `/admin/courses/subject-requests-columns` | `admin.courses.subjects-requests-columns` | GET |
| `/admin/courses/requests` | `admin.courses.requests` | GET |
| `/admin/courses/requests-columns` | `admin.courses.requests-columns` | GET |
| `/admin/courses/requests/{courseRequest}/action` | `admin.courses.requests.action` | POST |
| `/admin/courses/batches` | `admin.courses.batches` | GET |
| `/admin/courses/archive` | `admin.courses.archive` | GET |
| `/admin/courses/{course}` | `admin.courses.show` | GET |
| `/admin/students` | `admin.students.index` | GET |
| `/admin/students/{student}` | `admin.students.show` | GET |
| `/admin/certificates` | `admin.certificates.index` | GET |
| `/admin/certificates/{certificate}/action` | `admin.certificates.action` | POST |
| `/admin/notifications` | `admin.notifications.index` | GET |
| `/admin/notifications/read-all` | `admin.notifications.read-all` | POST |
| `/admin/notifications/{notification}/read` | `admin.notifications.read` | POST |
| `/admin/settings` | `admin.settings.index` | GET |
| `/admin/settings/staff` | `admin.settings.staff` | GET |
| `/admin/settings/password` | `admin.settings.password` | POST |
| `/admin/settings/language` | `admin.settings.language` | POST |
| `/admin/settings/appearance` | `admin.settings.appearance.update` | POST |
| `/admin/settings/mail-payment` | `admin.settings.mail-payment.update` | POST |
| `/admin/settings/mail-payment/test` | `admin.settings.mail-payment.test` | POST |
| `/admin/settings/ai` | `admin.settings.ai` | GET |
| `/admin/settings/ai` | `admin.settings.ai.update` | POST |
| `/admin/settings/ai/test` | `admin.settings.ai.test` | POST |
| `/admin/settings/staff/{instituteUser}/action` | `admin.settings.staff-action` | POST |
| `/admin/platform-settings` | `admin.platform-settings.index` | GET |
| `/admin/platform-settings/general` | `admin.platform-settings.general` | POST |
| `/admin/platform-settings/email` | `admin.platform-settings.email` | POST |
| `/admin/platform-settings/email/test` | `admin.platform-settings.email.test` | POST |
| `/admin/platform-settings/sms` | `admin.platform-settings.sms` | POST |
| `/admin/platform-settings/sms/test-connection` | `admin.platform-settings.sms.test-connection` | POST |
| `/admin/platform-settings/sms/test` | `admin.platform-settings.sms.test` | POST |
| `/admin/platform-settings/otp` | `admin.platform-settings.otp` | POST |
| `/admin/platform-settings/twofactor` | `admin.platform-settings.twofactor` | POST |
| `/admin/platform-settings/login-security` | `admin.platform-settings.login-security` | POST |
| `/admin/platform-settings/queue/health` | `admin.platform-settings.queue.health` | POST |
| `/admin/platform-settings/payment` | `admin.platform-settings.payment` | POST |
| `/admin/platform-settings/storage` | `admin.platform-settings.storage` | POST |
| `/admin/platform-settings/maps` | `admin.platform-settings.maps` | POST |
| `/admin/platform-settings/notifications` | `admin.platform-settings.notifications` | POST |
| `/admin/platform-settings/ai` | `admin.platform-settings.ai` | POST |
| `/admin/platform-settings/api` | `admin.platform-settings.api` | POST |
| `/admin/platform-settings/branding` | `admin.platform-settings.branding` | POST |
| `/admin/platform-settings/maintenance` | `admin.platform-settings.maintenance` | POST |
| `/admin/platform-audit` | `admin.platform-audit.index` | GET |
| `/admin/industry-settings` | `admin.industry-settings` | GET |
| `/admin/industry-settings/theme` | `admin.industry-settings.theme` | POST |
| `/admin/themes/{theme}` | `admin.themes.update` | PUT |
| `/admin/modules` | `admin.modules.index` | GET |
| `/admin/modules/{module}` | `admin.modules.update` | PUT |
| `/admin/modules/access-logs` | `admin.modules.access-logs` | GET |
| `/admin/packages/{package}/modules` | `admin.packages.modules` | GET |
| `/admin/packages/{package}/modules` | `admin.packages.modules.update` | PUT |
| `/admin/institutes/{institute}/modules` | `admin.institutes.modules` | GET |
| `/admin/institutes/{institute}/modules` | `admin.institutes.modules.update` | PUT |
| `/admin/academic` | `admin.academic.index` | GET |
| `/admin/academic/subjects` | `admin.academic.subjects.index` | GET |
| `/admin/academic/grading` | `admin.academic.grading.index` | GET |
| `/admin/classes` | `admin.classes.index` | GET |
| `/admin/security` | `admin.security` | GET |

### Shared Routes (auth:platform_admin OR institute_user)
| Route | Name |
|-------|------|
| `/account/preferences` | `account.preferences` |
| `/account/preferences` (PUT) | `account.preferences.update` |
| `/account/preferences/theme` (POST) | `account.preferences.theme` |
| `/notifications` | `notifications.index` |
| `/notifications/read-all` | `notifications.read-all` |
| `/notifications/{notification}/read` | `notifications.read` |

---

## 3. SUPER ADMIN VIEWS MAP

### Layouts
| File | Used By |
|------|---------|
| `resources/views/layouts/admin.blade.php` | Institute user pages + Super Admin pages with sidebar |
| `resources/views/layouts/standalone.blade.php` | Platform Settings, Platform Audit, Legacy Settings |

### Super Admin Views (under `resources/views/admin/`)
| Directory | Files | Purpose |
|-----------|-------|---------|
| `institutes/` | `index.blade.php`, `show.blade.php`, `edit.blade.php`, `bin.blade.php`, `entitlements/` | Institute management |
| `courses/` | `index.blade.php`, `show.blade.php`, `assignment.blade.php`, `subjects.blade.php`, `subject_requests.blade.php`, `requests.blade.php`, `batches.blade.php`, `archive.blade.php`, `_tabs.blade.php` | Course management |
| `students/` | `index.blade.php`, `show.blade.php` | Student records |
| `certificates/` | (referenced in routes) | Certificate management |
| `modules/` | `index.blade.php`, `access-logs.blade.php`, `institute-modules.blade.php`, `package-modules.blade.php` | Module & package management |
| `platform-settings/` | `index.blade.php` | Platform Configuration Center |
| `platform-audit/` | `index.blade.php` | Audit history |
| `settings/` | `index.blade.php`, `staff.blade.php`, `password.blade.php`, `appearance.blade.php`, `mail_payment.blade.php`, `ai.blade.php`, `_ai.blade.php`, `account.blade.php` | Legacy admin settings |
| `notifications/` | `index.blade.php` | Notifications center |
| `users/` | `module-access.blade.php` | User module access |
| `industry-settings/` | `index.blade.php` | Industry-specific settings |
| `classes/` | (referenced in routes) | Classes management |
| `academic/` | (referenced in routes) | Academic structure |
| `geo/` | (referenced in routes) | Geographic data |

### Super Admin Database Views (under `resources/views/super-admin/database/`)
| File | Purpose |
|------|---------|
| `control-center.blade.php` | Database control center |
| `dashboard.blade.php` | Database dashboard |
| `backups.blade.php` | Backup management |
| `health.blade.php` | Database health |
| `integrity.blade.php` | Data integrity |
| `performance.blade.php` | Performance metrics |
| `recovery.blade.php` | Disaster recovery |
| `audit.blade.php` | Database audit logs |
| `monitoring.blade.php` | Real-time monitoring |
| `certification.blade.php` | Certification |

---

## 4. SUPER ADMIN FUNCTIONALITY INVENTORY

### Platform Management
- Institutes (list, show, edit, approve/reject/suspend/reactivate, soft-delete, restore, force-delete, recycle bin)
- Institute staff management (destroy staff by kind/id)
- Institute module entitlements

### Education (when industry=education)
- Courses (list, show, assignment, subjects, subject requests, course requests, batches, archive)
- Classes & Subjects
- Students (list, show, registration)
- Certificates (list, action)
- Academic Structure
- Academic Subjects
- Grade Scales

### Modules & Packages
- Module registry (list, toggle active/inactive)
- Package matrix (view/assign modules to packages)
- Module access logs
- Institute-level module access

### Platform Settings (Configuration Center)
- General (app name, timezone, URL, country, currency, language, pagination)
- Email / SMTP (host, port, encryption, credentials, from, test)
- SMS Provider (master control, provider selection, HTTP config, auth, sender, field mapping, response validation, test connection, send test)
- OTP & Verification (email OTP, SMS OTP settings)
- Security / Two-Factor (TOTP, email, SMS, preferred, login protection)
- Queue (driver info, pending/failed jobs, health check)
- Notifications (email/SMS enabled, queue, retry)
- Payment Gateways (provider, mode, currency, credentials)
- Storage (disk, max size, auto resize, WebP, thumbnails)
- Maps & Geolocation (enabled, geocoding, places, API key, defaults)
- AI (enabled, provider, model, base URL, API key)
- API & Webhooks (enabled, URL, secret, retry, timeout)
- Branding (name, footer, colors, logo, favicon)
- Maintenance (mode, allow admin, message)

### Legacy Settings
- Account (name, email, role display)
- Password change
- Language preference
- Theme/appearance
- SMTP settings (duplicate of platform-settings email)
- Payment gateway (duplicate of platform-settings payment)
- Test email
- Staff registration requests
- AI settings

### Security
- Security panel (referenced from settings)

### Database Operations
- Control center
- Database dashboard
- Backups & recovery
- Database health
- Integrity & security
- Performance
- Disaster recovery
- Database audit logs
- Monitoring

### Other
- Industry settings (per-industry theme defaults)
- Notifications center (read/mark read/mark all)
- Platform audit history (filterable by section, action, admin, date range)

---

## 5. ISSUES IDENTIFIED

### CRITICAL UI Issues
1. **No dedicated Super Admin dashboard** — Super Admin gets the same generic dashboard as institute users; no platform-wide overview
2. **Flat, unorganized sidebar** — All items in one long list with no logical grouping
3. **Inconsistent layouts** — Platform Settings and Platform Audit use `standalone.blade.php` while other pages use `admin.blade.php`
4. **Duplicate navigation links** — "SMS Provider" in sidebar is a duplicate link to Configuration Center tab
5. **No breadcrumbs** on any page
6. **Inconsistent page headers** — Some pages have proper headers, others don't

### MODERATE UI Issues
7. **No section grouping in sidebar** — All admin items appear in a single flat list
8. **Industry-conditional items** — Education items show/hide based on industry filter, creating inconsistent sidebar
9. **Settings page confusion** — Two settings pages: Legacy Settings (`admin.settings.index`) and Platform Settings (`admin.platform-settings.index`)
10. **Platform Settings navigation** — 14 tabs in a flat list, no grouping
11. **No active state consistency** — Some sidebar items highlight correctly, others don't
12. **Missing visual hierarchy** — No clear separation between different admin functions

### MINOR UI Issues
13. **Sidebar section label styling** — Inline styles for section labels
14. **No empty state improvements** — Basic "No records" messages
15. **No loading states** — No skeleton loaders on admin pages
16. **Inconsistent badge styling** — Mix of badge styles

---

## 6. PROTECTED FILES (DO NOT MODIFY)

| Category | Files |
|----------|-------|
| **Models** | `app/Models/PlatformAdmin.php`, all other models |
| **Migrations** | `database/migrations/*` |
| **Seeders** | `database/seeders/*` |
| **Routes** | `routes/web.php`, `routes/api.php` |
| **Services** | `app/Services/*` |
| **Auth Controllers** | `app/Http/Auth/*`, `app/Http/Controllers/Auth/*` |
| **Config** | `config/auth.php`, all config files |
| **Business Logic** | All non-UI controllers |
| **Institute Views** | All institute admin, teacher, student, staff views |
| **Public** | Other application areas |

---

## 7. FILES REQUIRING UI CHANGES

| File | Why UI Change Is Needed | Safe? |
|------|------------------------|-------|
| `resources/views/layouts/admin.blade.php` | Sidebar reorganization for Super Admin | YES |
| `resources/views/admin/platform-settings/index.blade.php` | Settings UI grouping and cleanup | YES |
| `resources/views/admin/platform-audit/index.blade.php` | Audit UI cleanup | YES |
| `resources/views/admin/institutes/index.blade.php` | Breadcrumbs, header consistency | YES |
| `resources/views/admin/modules/index.blade.php` | Breadcrumbs, header consistency | YES |
| `resources/views/admin/settings/index.blade.php` | Breadcrumbs, header consistency | YES |
| `resources/views/admin/courses/index.blade.php` | Breadcrumbs, header consistency | YES |
| `resources/views/admin/students/index.blade.php` | Breadcrumbs, header consistency | YES |
| `resources/views/admin/notifications/index.blade.php` | Breadcrumbs, header consistency | YES |
| `resources/views/admin/industry-settings/index.blade.php` | Breadcrumbs, header consistency | YES |
| `public/css/layout.css` | Sidebar section styling | YES |
| `public/css/components.css` | Component consistency | YES |

---

## 8. DUPLICATE FUNCTIONALITY IDENTIFIED

| Function | Location 1 | Location 2 | Resolution |
|----------|-----------|-----------|------------|
| SMTP Settings | `admin.settings.index` (pane-mail-payment) | `admin.platform-settings.index` (pane-email) | Keep both; Platform Settings is canonical |
| Payment Settings | `admin.settings.index` (pane-mail-payment) | `admin.platform-settings.index` (pane-payment) | Keep both; Platform Settings is canonical |
| AI Settings | `admin.settings.index` (pane-ai) | `admin.platform-settings.index` (pane-ai) | Keep both; Platform Settings is canonical |
| SMS Provider link | Sidebar "SMS Provider" | Sidebar "Configuration Center" | Remove duplicate; SMS is part of Configuration Center |
| Recycle Bin | `admin.institutes.index` button | Sidebar "Recycle Bin" | Keep both; button is contextual |

---

## 9. SIDEBAR NAVIGATION CURRENT STATE

Current Super Admin sidebar items (in order):
```
Dashboard (shared)
─────────────────────
[IF education industry selected]
  Institutes
  Courses
  Classes & Subjects
  Student Registration
  Certificates
  Academic Structure
  Academic Subjects
  Grade Scales
─────────────────────
Industry Settings
Modules & Packages
Module Access Logs
Recycle Bin
─────────────────────
[System / Database section]
  Control Center
  Database Dashboard
  Backups & Recovery
  Database Health
  Integrity & Security
  Performance
  Disaster Recovery
  Audit Logs
─────────────────────
[Platform Settings section]
  Configuration Center
  SMS Provider (DUPLICATE)
  Audit History
  Legacy Settings
```

**Issues:**
- No logical grouping
- Education items conditionally shown/hidden
- 30+ items in flat list
- No clear separation of concerns
- Duplicate links

---

## 10. PROPOSED SIDEBAR ORGANIZATION

```
MAIN
  Dashboard

PLATFORM
  Institutes
  Courses
  Classes
  Students
  Certificates

ACADEMIC
  Academic Structure
  Academic Subjects
  Grade Scales

OPERATIONS
  Modules & Packages
  Module Access Logs
  Industry Settings

CONFIGURATION
  Configuration Center
  Legacy Settings

SECURITY
  Platform Audit
  Recycle Bin

DATABASE
  Control Center
  Database Dashboard
  Backups & Recovery
  Database Health
  Integrity & Security
  Performance
  Disaster Recovery
  Database Audit Logs
```

---

## 11. PLATFORM SETTINGS CURRENT TAB STRUCTURE

Current tabs (14):
1. General
2. Email / SMTP
3. SMS Provider
4. OTP & Verification
5. Security / 2FA
6. Queue
7. Notifications
8. Payment Gateways
9. Storage
10. Maps & Geolocation
11. AI
12. API & Webhooks
13. Branding
14. Maintenance

**Proposed grouped structure:**
```
GENERAL
  General
  Branding
  Maintenance

COMMUNICATION
  Email / SMTP
  SMS Provider
  OTP & Verification
  Notifications

SECURITY
  Two-Factor / 2FA
  Login Security

INFRASTRUCTURE
  Queue
  Storage
  Maps & Geolocation

INTEGRATIONS
  Payment Gateways
  AI
  API & Webhooks
```

---

## 12. DESIGN SYSTEM ASSESSMENT

### Current CSS Variables
```css
:root {
    --primary: #0D6EFD;
    --secondary: #FFC107;
    --dark: #212529;
    --light: #F8F9FA;
    --white: #ffffff;
    --border: #e9ecef;
    --muted: #6c757d;
}
```

### Icon System
- Bootstrap Icons (`bootstrap-icons@1.11.3`)
- Consistent use throughout — no mixing

### Typography
- Fonts: Poppins, Hind Siliguri
- Weights: 400, 500, 600, 700, 800

### Framework
- Bootstrap 5.3.7
- No custom component library

---

## 13. RESPONSIVE BEHAVIOR

Current responsive breakpoints:
- Desktop: >768px (sidebar visible)
- Mobile: ≤768px (sidebar as off-canvas drawer)
- Collapsed sidebar: Icon-only mode (68px width)

---

## 14. AUDIT COMPLETE — READY FOR PHASE 2

All read-only analysis complete. No files modified.

**Next Phase:** UI/UX implementation (sidebar reorganization, layout cleanup, breadcrumbs, settings grouping, audit UI improvement)
