# Standard List Page (SLP) — Reference Pattern

The Course Assignment page (`admin/courses/assignment.blade.php` +
`CourseAdminController::assignment`) is the **canonical standard list page**.
When a "standard list view" is requested, replicate this page's anatomy,
classes, JS behaviors, and backend contract exactly. This document is the
specification for that pattern.

---

## 1. Page anatomy (top → bottom)

1. `<div class="page-header">` — title (`page-header-title`) + one-line
   description (`page-header-desc`). `page-header-desc` is **hidden in print**.
2. `<div class="filter-card">` — a single `<form class="filter-layout"
   method="GET">` submitting to the same route (server-side filters).
3. `<div class="admin-card">`
   - `.table-toolbar` → `.toolbar-info` (summary badges + hint) and
     `.toolbar-actions` (Columns dropdown + Print / CSV / Excel).
   - `.table-responsive > table#<page>Table` — the interactive table.
   - Pagination block (`.links` + count text), centered below the table.
4. `.print-only` block — a second table `#<page>TablePrint` with **all**
   rows (unpaginated) used only for printing.

---

## 2. Filter form (server-side, GET)

- One `<form class="filter-layout" method="GET">` → the same route. Filters
  re-read from `$request->query()` in the controller and echoed back via
  `value="{{ $filters['q'] ?? '' }}"` / `@selected(...)`.
- Layout is a single `.filter-search-row` (flex, `flex-wrap:wrap`,
  `align-items-end`):
  - `.filter-search` — search input with `bi-search` icon. **Equal width with
    the widest filter field** via `flex:1 1 0; min-width:180px` on both, so
    no element dominates and everything stays on one line.
  - `.filter-span` — each labeled filter control (`form-label mb-1` + small
    control). `form-control-sm` / `form-select-sm` for ALL inputs/selects.
  - `.filter-actions` — `btn btn-primary btn-sm` (Search) + `btn btn-outline-secondary btn-sm` (Reset → plain link to the bare route).
- **Custom searchable dropdown** (e.g. Institute): `.inst-dropdown` with an
  inner text `input` + hidden `input[name="institute_id"]` + `.inst-caret` +
  `.inst-list` (`ul.inst-item`). See §6 JS.
- All controls share the **same height** (`form-control-sm`/`form-select-sm`/
  `btn-sm`). Everything should fit a single row; wrap gracefully on narrow
  screens.

## 3. Table toolbar

- `.toolbar-info`: summary badges (`badge badge-soft text-bg-*`) + an optional
  muted hint (`d-none d-lg-inline`).
- `.toolbar-actions`:
  1. **Columns dropdown** — `dropdown > button.btn-outline-primary btn-sm` with
     `bi-layout-three-columns`, menu id `colToggleMenu`, class
     `col-toggle-menu`. Each row is a checkbox label with
     `class="col-toggle-check"` + `data-col="<col>"`, checked from
     `$visibleColumns`.
  2. **Print** — `button[onclick="window.print()"]` (`bi-printer`).
  3. **CSV** — `button#exportCsvBtn` (`bi-filetype-csv`).
  4. **Excel** — `button#exportExcelBtn` (`bi-file-earmark-excel`).

## 4. Table structure

`<table class="table align-middle mb-0" id="<page>Table">`

| Column | Classes / attrs | Purpose |
|--------|-----------------|---------|
| 1. Handle | `th.col-handle` (w42px), `td.col-handle` + `i.bi-grip-vertical.drag-handle[draggable]` | drag reorder (visual) |
| 2. Checkbox | `th.col-check` (select-all), `td.col-check` + `input.row-check` | row selection |
| 3. Serial | `th[data-col="serial"]` + `td.col-serial` showing `{{ $items->firstItem() + $loop->index }}` | sequential number (N+1) across pages; **included in the show/hide Columns toggle** |
| 4..n Data | `data-col="<col>"` on th + every td | toggleable columns |
| last. Action | `th[data-col="action"].text-end`, `td.text-end.text-nowrap.col-action` | per-row buttons/forms |

**Rules:**
- Column order is **always: handle → checkbox → serial → data columns → action**.
- Serial content is N+1: `{{ $items->firstItem() + $loop->index }}` on the
  paginated screen table; `{{ $loop->iteration }}` on the `.print-only` table
  (unpaginated). `serial` is a **toggleable** `data-col` like any other — it
  appears in `self::COLUMNS` and the Columns dropdown (label `#`).
- Every toggleable `th` and matching `td` carry `data-col="<col>"` (including
  `serial`) and the inline
  `@if(!in_array('<col>', $visibleColumns, true)) style="display:none" @endif`.
- Action column uses `col-action`; checkbox `col-check`; handle `col-handle`
  (these three are never in `$visibleColumns`'s data set; handle/check are
  always on-screen, `action` is toggleable).
- Badges for enum statuses map via a `$statusBadge` array (e.g. active/inactive/
  draft → `text-bg-*`).
- Empty state: single row `<td colspan="20" class="text-center text-muted py-4">`.

## 5. Pagination

```blade
<div class="mt-4 d-flex flex-column align-items-center gap-2">
    {{ $items->links('pagination::bootstrap-5') }}
    <span class="text-muted small">{{ $items->total() }} {{ $label }}</span>
</div>
```

Centered, with the total count line beneath. Print intentionally does **not**
use this paginator (it uses the print table).

## 6. JS behaviors (`@push('scripts')`, one IIFE)

1. **Select all** — `#selectAll` toggles every `.row-check`.
2. **Drag reorder** (visual only, session) — HTML5 DnD on `.drag-handle`:
   - `dragstart` adds `.dragging`; `dragover` inserts the dragged row before/
     after the hovered row (by cursor vs. row midpoint) via FLIP
     (`reorderAndAnimate`); `dragend` clears state.
   - CSS: `.dragging td` lifts `translateY(-4px) scale(1.1)` with a springy
     `cubic-bezier(.2,.85,.35,1)` transition (magnet feel). Left border line:
     `border-left:4px solid var(--bs-primary)` on `td:first-child` (this is the
     "green line" when the theme primary is green).
3. **Column visibility toggle** — `change` on `.col-toggle-check`:
   - finds `th[data-col]`, computes the column index, hides/shows that index in
     every `tbody tr` of `#<page>Table`, **and mirrors the same `data-col` into
     `#<page>TablePrint`** (print stays in sync with the screen).
   - persists via `saveCols()` → POST JSON `{ columns: [...] }` to the
     `*-columns` route (`X-CSRF-TOKEN` header).
4. **Searchable dropdown** — `.inst-dropdown`: focus/click opens `.inst-list`;
   typing filters `.inst-item`; click sets the hidden value + active item;
   outside click closes.
5. **Export** — `exportTable(fileName)` builds CSV from the current visible
   `#<page>Table` (skips the handle + checkbox columns, quotes cells), emits a
   BOM-prefixed Blob, downloads as `*.csv` / `*.xls`.

## 7. Print handling (the important part)

- A dedicated **print-only table** `#<page>TablePrint` lives in
  `.print-only { display:none }` (shown only in `@media print`).
- The controller passes **`allCourses`/`allItems` = the full filtered query**
  (`(clone $query)->orderBy(...)->get()`) — printing shows **every** matching
  row, not just the current page.
- The print table contains the **data columns only** (no handle/check/action)
  and carries `data-col` + the same `$visibleColumns` inline-hide flags, so
  show/hide settings are respected on paper.
- `@media print` rules:
  - Hide `.topbar, .sidebar, .sidebar-backdrop, .filter-card, .table-toolbar,
    .page-header-desc, .pagination, .d-none, .table-responsive`.
  - Reset `.content` (margin/padding 0) and strip `.admin-card` chrome.
  - Show `.print-only`; style `#<page>TablePrint` at `font-size:11px`,
    cell padding `4px 6px`, `white-space:nowrap`.
- Net print output = **page header line + the full data table**, nothing else.

## 8. Backend contract

```php
public function index(Request $request): View
{
    $query = Model::query()
        ->with('relations')
        ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) =>
            $w->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%"))
        )
        ->when($request->query('category_id'), fn ($q, $id) => $q->where('category_id', (int) $id))
        ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
        ->orderBy('name');

    $items = (clone $query)->paginate(20)->withQueryString();
    $allItems = (clone $query)->get();                       // for print

    $visibleColumns = $request->user()->preference('<key>_columns', self::COLUMNS);
    $visibleColumns = array_values(array_intersect(self::COLUMNS, (array) $visibleColumns));

    return view('...', [
        'items' => $items,
        'allItems' => $allItems,
        'visibleColumns' => $visibleColumns,
        'filters' => [
            'q' => $request->query('q'),
            'category_id' => $request->query('category_id'),
            'status' => $request->query('status'),
        ],
    ]);
}

public function saveColumns(Request $request): JsonResponse
{
    $request->validate(['columns' => 'array']);
    $request->user()->setPreference('<key>_columns', $request->input('columns', []));
    return response()->json(['ok' => true]);
}
```

- `self::COLUMNS` = ordered list of `data-col` keys **starting with `serial`**
  and **ending with `action`**.
- Preferences persist per admin user in the `preferences` JSON column.
- Route: the save endpoint is POST with `auth:platform_admin` (or the page's
  guard), CSRF via JSON header.

## 9. Checklist when building a new standard list page

- [ ] Single GET filter form re-populated from query, one-line equal-width
      layout, all controls `*-sm`.
- [ ] `.page-header` title + description; description hidden in print.
- [ ] `.table-toolbar` with info badges + Columns dropdown + Print/CSV/Excel.
- [ ] Table: handle + checkbox columns, **serial column (`#`, N+1) right after
      the checkbox**, `data-col` on every toggleable th/td (serial included),
      inline hide from `$visibleColumns`, `col-action` on the action column.
- [ ] `.print-only` table with all rows (serial via `$loop->iteration`) + same
      `data-col` + same hide flags.
- [ ] Pagination centered + total count.
- [ ] JS: select-all, drag reorder, column toggle (mirroring the print table),
      searchable dropdown (if any), CSV/Excel export.
- [ ] Controller: shared `$query`, paginated + `allItems`, `visibleColumns`
      from the user preference, `filters` array, `saveColumns` POST endpoint.
- [ ] Run `artisan view:clear` after editing the Blade.
