# Design Conventions

UI conventions observed across the codebase. Follow these when building new
Blade views or CSS so the app keeps a single visual language.

## Stack

- **Bootstrap 5.3** (loaded from CDN in `layouts/admin.blade.php`) with
  `data-bs-theme` dark/light switching.
- **Bootstrap Icons 1.11** (`bi bi-*`) for all icons.
- **Fonts:** Poppins (headings/UI) + Hind Siliguri (Bangla) — loaded in
  `base.css` and the layout.
- Custom CSS split by responsibility in `public/css/`:
  - `base.css` — reset, tokens (`:root` variables), body defaults
  - `layout.css` — topbar, sidebar, user card, responsive shell
  - `components.css` — cards, page header, tables, pills, avatars,
    notifications, dark-mode overrides
  - `pages.css` — public marketing page styling (hero, course cards, catalog,
    auth cards) — NOT used by the admin shell
- **Alpine.js** (CDN, `defer`) is available globally (used by
  `<x-connectivity-signal />`); trivial interactions are plain inline JS in
  `@push('scripts')` blocks.

> Keep the shell CSS in `components.css`/`layout.css`. `pages.css` is for the
> public marketing pages only; do not add admin shell rules there.

## Design tokens (CSS variables)

Defined in `base.css` `:root` and overridden in `html.monetix-dark`:

| Token | Value | Usage |
|-------|-------|-------|
| `--primary` | `#0D6EFD` | links, active states, icon accents, primary buttons |
| `--secondary` | `#FFC107` | accent/level badges |
| `--dark` | `#212529` | headings, strong text |
| `--light` | `#F8F9FA` | muted surfaces |
| `--white` | `#ffffff` | card/topbar/sidebar surfaces |
| `--border` | `#e9ecef` | hairlines (1px) |
| `--muted` | `#6c757d` | secondary text |

Rule of thumb: **never hardcode colors in Blade** where a token or a Bootstrap
utility (`text-muted`, `text-primary`, `bg-*`, `alert-*`) exists. Dark mode is
achieved purely by overriding these tokens + Bootstrap vars, so a hardcoded
hex inside a view stays wrong in dark mode.

## Layout shell

- `.topbar` — sticky, white, brand left + action buttons right (notification
  bell, language switcher, dark-mode toggle). Icons live in 38×38 circular
  `.icon-btn` buttons.
- `.sidebar` — 230px, `position:sticky`, nav links with:
  - state = `nav-link active` (blue left border 3px + tinted background)
  - disabled/coming-soon = `nav-link disabled` + `.soon-badge`
  - sub-items = `nav-link sub`
  - collapsible groups use `nav-group` with a rotating `.nav-caret`
- `.content` — `padding:26px 30px` (18px on mobile). Sidebar hides
  (`display:none`) below 768px.
- The sidebar/user card (`.sidebar-user-card`, dropup) holds the workspace
  switcher and security/settings links.

## Page anatomy

Every list/detail page follows this order:

```
1. .page-header  (title + desc left, actions right)
2. .filter-card  (GET form with q/status selects)   [lists only]
3. flash alerts  (auto-dismiss via [data-auto-dismiss])
4. .admin-card   (toolbar + table + pagination) / .dash-card grid
5. @push('scripts') for page JS
```

### Page header

```blade
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Title</h4>
        <p class="page-header-desc">Supporting line</p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="..."><i class="bi bi-arrow-left"></i> Back</a>
    </div>
</div>
```

### Cards

Prefer the markup over generic `.card`:

- `.admin-card` (padding 24px) — main content block, forms, tables
- `.dash-card` — dashboard panels
- `.filter-card` — inline GET filters above a table (`.filter-row` becomes a
  4-column grid on ≥1200px)
- `.stat-card` / `.stat-box` — KPI cards (`.icon` block + `.num` + `.label`)
- `.card` — borderless, rounded, used for centered/standalone content

Forms keep the pattern: rows of `.col-md-*` fields, section cards reuse the
`.table-toolbar` header style (icon + section title), submit button at the end.

### Filters

```blade
<div class="filter-card">
    <form class="d-flex flex-wrap gap-2 mb-0" method="GET" action="{{ route(...) }}">
        <input type="text" class="form-control" style="max-width:340px" name="q" value="{{ $q }}">
        <select name="status" class="form-select" style="max-width:160px">...</select>
        <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Search</button>
        @if ($q || $status)
            <a class="btn btn-outline-secondary" href="{{ route(...) }}"><i class="bi bi-x-lg"></i> Clear</a>
        @endif
    </form>
</div>
```

### Tables

- Wrapped in `.table-responsive`, class `table align-middle mb-0` inside
  `.admin-card`.
- `<th>` are auto-uppercased 12.5px grey by `components.css` (don't repeat in
  markup).
- Row/col pattern:
  - first cell = clickable title (`fw-semibold text-decoration-none` link)
    with a `.text-muted small` secondary line beneath.
  - status columns = `.badge` (see status map below).
  - last column = `text-end text-nowrap` action buttons.
- Row clickability = `.table-row-click`.
- Empty state = `<tr><td colspan="N" class="text-center text-muted py-4">...`.
- Pagination: `{{ $rows->links('pagination::bootstrap-5') }}` with a
  `.text-muted small` count.

### Status badges

Institutes use a fixed status→badge map (reused in `admin.institutes.*`):

```blade
@php $statusBadge = [
    'pending'   => 'text-bg-warning',
    'active'    => 'text-bg-success',
    'suspended' => 'text-bg-danger',
    'expired'   => 'text-bg-dark',
    'cancelled' => 'text-bg-secondary',
]; @endphp
<span class="badge {{ $statusBadge[$institute->status] ?? 'text-bg-secondary' }}">{{ $institute->status }}</span>
```

Use the same style for any enum/status in new tables.

## Buttons

- Primary action = `btn btn-primary` with an icon (`.bi bi-check-lg`) + label.
- Secondary/Back = `btn btn-outline-secondary`.
- Destructive = `btn btn-danger` / `btn btn-outline-danger`.
- Success confirm = `btn btn-success`.
- In-table compact = `btn btn-sm btn-outline-primary`.
- Inline destructive forms are plain `form class="d-inline"` + hidden
  `action` input + `onsubmit="return confirm(...)"` for quick actions; heavier
  confirmations open a Bootstrap modal (see institutes `#deleteModal`).

## Avatars & presence

- `.avatar-circle` (50% round image), `.avatar-initials` fallback
  (primary background, white initials), `.avatar-wrap` positions the
  status dot.
- The sidebar user card shows the live browser connectivity signal via
  `<x-connectivity-signal size=11.2 wrap=11.2 class="cs-avatar-corner" />`.
- Status dot classes: `.is-active` (green) / `.is-inactive` (red) /
  `conn-online|conn-reconnecting|conn-offline` for the browser signal.

## Forms

- Labels: `.form-label` (`form-label` global styling 13px 600).
- Required marker: `<span class="text-danger">*</span>` after the label.
- Inline validation: `@error('field')<div class="text-danger small mt-1">...</div>@enderror`.
- Selects: `.form-select`; radio/checkbox kept native where possible
  (`.lang-option`, `.theme-option` are the selectable-card exceptions).
- Phone inputs reuse `@include('partials.phone', ['name', 'id', 'value', 'country'])`.
- Submission actions never do unsafe GET; state-changing requests use
  `POST` (+ `@method('PUT'|'DELETE')`) with `@csrf`.

## Notifications UI

- Bell = `.notification-trigger` circle + `.bell-dot` (unread count, max `99+`).
- Dropdown = `.notification-menu` → `.notification-menu-header` (with
  `.notification-mark-all`), `.notification-list` of `.notification-item`
  (`.unread` shows a tint + dot), footer link "view all".
- Items carry `data-id` + `data-read-url`; mark-as-read posts JSON with CSRF.
- The notifications center page uses the same `.notification-item.unread`
  pattern in a `.list-group`.

## Alerts

- Success = `alert alert-success`, error = `alert alert-danger`, plus
  warning/info for contextual blocks.
- Flash alerts in `.content` carry `data-auto-dismiss` (opacity fade after 3s).
- Layout already renders `session('status')`, `$errors`, `session('error')`
  — pages should NOT render duplicate error blocks blindly (some pages add
  their own for `photo_upload_error` etc.).

## Localization & theming in views

- Every user-facing string goes through `mawa_e('key')` (or `mawa_lang()`
  with placeholders) — see [localization.md](localization.md).
- Dark mode is toggled on `<html>` via `monetix-dark` + `data-bs-theme`.
  Component-level dark overrides live in `components.css`
  (`html.monetix-dark ...`). Don't add inline dark-mode JS; reuse the existing
  toggle wiring in the admin layout.
- Theme preference persists via `account.preferences.theme` POST and
  `localStorage` (`monetix_ui_dark_admin`).

## Assets & scripts

- Page-specific CSS → `@push('styles')`; JS → `@push('scripts')`.
- Small interactions: IIFE inside the pushed script, no frameworks.
  Confirmation modals follow the `admin/institutes` delete-modal pattern
  (`.del-btn` triggers, form action set from `data-action`).
- The Vite build is configured but the running CSS is the static files in
  `public/css/` — no `@vite` usage required to see changes. Rebuild only if
  you move styling into the Vite pipeline.