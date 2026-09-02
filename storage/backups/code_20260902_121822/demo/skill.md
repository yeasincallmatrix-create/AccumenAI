# Skills & Capabilities

Capability overview of the AccumenAI platform — what each portal can do.

## Portals

### 1. Platform Admin (`platform_admin`)
Console for operating the platform.

- **Dashboard** — role-aware landing page with stat cards and institute
  listing; filterable by **Country** and **Industry**.
- **Institutes**
  - List/search by name/slug/code/email; filter by status.
  - View institute profile (details, owner, staff, students, batch counts).
  - Edit: profile, contact, **Country / Industry / Sub-category**, subscription
    (package, expiry), status, verified flag.
  - Approve / reject / suspend / reactivate / soft-delete an institute
    (each action pushes an institute-scoped notification).
  - **Recycle bin** — soft-deleted institutes, filterable by industry; the
    industry filter persists across restore / permanent delete actions.
- **Courses**
  - Browse the platform course catalog.
  - Course assignment (institute ⇄ course).
  - Course requests — approve / reject / add review note; approval
    auto-assigns the course to the institute and notifies it.
- **Students** — global student registry (read).
- **Certificates** — approve / reject certificate requests.
- **Notifications center** — platform-scoped notification stream with
  server-side read tracking and a global "mark all as read" action; the
  header bell counts only unread platform notifications.
- **Settings** — admin profile, staff registration approval queue, change
  password. (Separate **Security** page: 2FA + sessions.)

### 2. Institute Staff (`institute_user`)
Day-to-day operational tooling for a single institute.

- **Dashboard** — institute-scoped overview.
- **Students** — CRUD, photo upload, enrollment into batches, search across
  many identifiers, student ID sequences (`nextStudentNumber`), gender/age
  stats. Permission-gated: `students.view` / `students.manage`.
- **Batches** — CRUD. Permission-gated: `batches.view` / `batches.manage`.
- **Courses & Subjects** — catalog read. Permission: `courses.view`.
- **Certificates** — request/view. Permission: `certificates.view`.
- **Team / Staff invitations** — invite new staff. Permission: `staff.manage`.
- **Offline sync review** — approve/reject offline finance entries.
  `finance.view` / `finance.manage`.
- **Settings** — institute settings (name, language, ...). `settings.manage`.
- **Security** — 2FA + sessions.
- Attend / exams / results / cash memo / reports are scaffolded as **coming
  soon** items in the sidebar (models/tables exist, UI pending).

### 3. Global accounts (`web` / `User`)
Owner & staff accounts that belong to one or many organizations.

- Login/register (owner + staff), email verification.
- **Workspace picker** — switch the active organization when holding
  multiple memberships; single-membership users skip the picker.
- **Preferences** — language + theme, saved per user.
- **Security** — 2FA + sessions.
- Inside an active workspace they behave like institute staff, scoped by the
  active `Membership` and its role permissions.

## Horizontal capabilities

- **Bilingual UI** — English (`en`) and Bangla (`bn`), via
  `mawa_lang()`/`mawa_e()`, switchable through `?lang=bn` persisted in
  session; falls back to user preference → institute setting → English.
- **Config-driven categories** — Country / Industry / Sub-category lists
  live in `config/industries.php` and `config/sub_industries.php`
  (countries in `App\Support\CountryCodes`), displayed through
  `\App\Support\Industries` with both languages.
- **Notification engine** — `notifications` + `notification_reads` tables;
  scopes (`platform`, `institute`, `user`) and per-recipient read markers.
- **Tenant isolation** — every institute-owned model is globally scoped to
  the active `institute_id`.
- **RBAC** — role-based permissions (`role_permissions` matrix), owner
  super-user bypass, `permission:` middleware.
- **Security** — Fortify password reset, email verification, 2FA (TOTP +
  recovery codes), session revocation, login throttling, guarded guest
  redirect.
- **Offline-ready finance** — `offline_sync_queue` with approve/reject
  lifecycle and pending-count badges.