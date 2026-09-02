# Super Admin Dynamic Button Convention

A convention for platform-admin ("super admin") sidebar buttons whose label and
action change dynamically based on the currently selected industry.

## Where it applies
- Platform admin sidebar in `resources/views/layouts/admin.blade.php`.
- The selected industry comes from the `?industry=` query parameter on the
  dashboard and is carried through the sidebar links.

## Button behaviour
- **Label** reflects the selected industry:
  - No filter -> `All Industries Settings`
  - `?industry=education` -> `Education Settings`
  - `?industry=retail` -> `Retail Settings`
  - ...and so on, mapping through `config('industries')`.
- **Action (href)** is dynamic too: each industry links to its own per-industry
  settings page `admin/industry-settings?industry=<key>`. "All Industries"
  links to `admin/industry-settings` without the parameter.
- Icon is the settings gear (`bi-gear-fill`).

## Supporting files
- `app/Http/Controllers/Admin/IndustrySettingController.php` — validates the
  industry key against `config('industries')` and renders the settings page.
- `resources/views/admin/industry-settings/index.blade.php` — per-industry
  settings page with an industry selector and placeholder panels.
- `routes/web.php` — `admin/industry-settings` route (platform-admin only).
- `tests/Feature/AdminNavTest.php` — tests for dynamic label, dynamic href,
  education-only nav items, and settings page rendering.

## Rules
- Only platform admin (`auth:platform_admin`) sees these dynamic buttons.
- Education-specific nav items (Institutes, Courses, Classes & Subjects,
  Student Registration, Certificates) appear only when `?industry=education`.
- Unknown or missing industry keys resolve to "All Industries".
- Future industries added to `config('industries')` are picked up automatically.