# Custom Commands (voice commands → actions)

Natural-language commands the user gives during dev sessions and the exact
actions they map to. When a command below is issued, perform the listed action
and verify (view:clear / tests) afterwards.

---

## List page commands

### "make a standard list view" / "standard list page"
Build a new list page replicating the Course Assignment page 1:1. See
`docs/standard-list-page.md` (canonical spec) — anatomy, filter form, toolbar,
columns/`data-col` contract, pagination, print rules, JS behaviors, backend
contract, and the build checklist.

**Every SLP list must have a Serial no. column:**
- Placed **right after the checkbox column** (order: handle → checkbox →
  **serial** → data → action).
- Content is **N+1**: `{{ $items->firstItem() + $loop->index }}` on the
  paginated screen table; `{{ $loop->iteration }}` on the `.print-only` table.
- The serial column **must be added to the show/hide Columns toggle**
  (`data-col="serial"`, label `#`, first entry in `self::COLUMNS` + dropdown).

### "make all input fields and dropdowns in a single line"
Restructure the `.filter-card` form to one `.filter-search-row` (flex,
`flex-wrap:wrap`, `align-items-end`) containing search + every filter field +
Search/Reset actions in a single row.

### "make search bar shorter" / "make [X] wider"
Adjust flex widths inline, e.g. `flex:0 1 220px; max-width:280px` on the
search, or `min-width:320px` on the wider field.

### "50-50" / "make them equal width" / "imagine [A] and [B] as 1, 50-50"
Give the two fields **equal width** while keeping the rest on the same line:
`flex:1 1 0; min-width:180px` on **both** (share remaining space equally).
Do **not** use `flex:1 1 50%` — that pushes later fields (Category/Status/
buttons) to the next line.

### "match the height of the search bar" / "keep consistent"
Make every control the same size: `form-control-sm` / `form-select-sm` on all
inputs & selects, `btn btn-primary btn-sm` / `btn btn-outline-secondary btn-sm`
on the actions.

### "decrease/increase list padding by N px"
Target the `.inst-list` padding (Institute dropdown): default `padding:4px`,
`+2px` → `6px`, `-2px` → `2px`. Reset button always returns to `4px`.

### "make each row N px wider" / "increase table row padding"
Cell padding for the page table, e.g. `#assignmentTable td{ padding-top:Xpx;
padding-bottom:Xpx; }` (Bootstrap default is `8px`; `.table-row-click td` uses
`12px`). Remove the rule to restore default.

### "column header filter" (was "column header list")
Give every list-table column header a **funnel filter** (little funnel icon
next to the heading). Every headline is inspected regardless of whether the
column is visible or hidden — filtering is URL-query-based, so hiding a column
never clears its filter.

- Add a funnel button to each `th[data-header-filter]`; a popover renders the
  controls. Contract (`public/js/column-filters.js` + CSS in
  `public/css/components.css`):
  - `data-header-filter="options"` + `data-filter-param` +
    `data-filter-values` (JSON) → **filter by one of its options** (e.g. Status,
    Category, Mode, Shift, Course). Clicking an option sets
    `<param>=<value>` in the query string.
  - `data-header-filter="sort"` → **"Oldest" / "Latest"** buttons (value
    `sort=oldest|latest`); add `data-filter-mode="age"` to render
    **"Eldest" / "Youngest"** instead.
  - `data-header-filter="date"` + `data-filter-param` → **"Older than" /
    "Later than"** date inputs composing `<param>_before` / `<param>_after`.
  - any other/absent value → **none** (serial, code, counts, action: no funnel).
- Every list controller honoring a funnel must read those params: options via
  `->when(request <param>, where ...)`, `sort` via
  `->when(sort===oldest|latest, orderBy <col>)`, and `_before`/`_after` via
  `where(<col>, '<'|'>', date)`.
- Ordering: options + sort make sense on Index/Subjects/Batches/Archive tables.
  Add `data-filter-values`/labels from controller-computed distinct options so
  the funnel never guesses.

### "print will show all data (e.g. 149 courses)"
The print must include the **full filtered dataset**, not just the current
page: controller passes `allItems = (clone $query)->get()`, rendered in the
`.print-only` `#<page>TablePrint` table.

### "print should respect column show/hide"
Print table columns carry `data-col` + the same `@if(!in_array(..., $visibleColumns))`
inline hide; the JS column toggle also mirrors state into `#<page>TablePrint`.

### "when printing only show heading + table (no handle/checkbox/action)"
`@media print` rules: hide `.topbar/.sidebar/.sidebar-backdrop/.filter-card/
.table-toolbar/.page-header-desc/.pagination/.d-none/.table-responsive`,
reset `.content`/`.admin-card` chrome, show `.print-only` table. The print
table already omits handle/checkbox/action columns.

---

## Drag-and-drop commands (visual row reorder)

CSS lives in `public/css/components.css` under
`/* Drag rows (visual reorder) */` (`.dragging` on `#assignmentTable tbody tr`).

| Command | Change |
|---------|--------|
| "no line/left line animation while dragging" | remove `transform` from `.dragging td` |
| "line stays but no animation" | keep `border-left:4px solid var(--bs-primary)` on `td:first-child`, remove `transform`/`transition` |
| "increase size by 10% / 15%" | `transform:translateY(-4px) scale(1.1)` / `scale(1.15)` |
| "magnet like feeling" | springy easing `cubic-bezier(.2,.85,.35,1)` on row transforms (CSS + JS `tr.style.transition`) |

Note: the "green line" is `border-left:4px solid var(--bs-primary)` — it shows
as green when the theme primary is green.

---

## Flash / toast commands

### "collapse flash" / "smooth flash" / "make flash ease in"
Flash messages (`[data-auto-dismiss]` alerts) behave as follows — keep these
settings whenever asked to adjust the flash:

- **One renderer only**: `layouts/admin.blade.php` renders `session('status')` /
  `session('error')` / `$errors` globally once at the top of `<main>`. Admin
  pages must **not** add their own `@if (session('status'))` block (it caused a
  duplicate flash — never reintroduce it).
- **Appear**: slide + fade in via `@keyframes monetix-flash-in`
  (`opacity 0→1`, `translateY(-10px)→0`, `.45s cubic-bezier(.2,.85,.35,1) both`).
- **Auto-dismiss** after `3000ms`.
- **Collapse (dismiss)**: add `.is-collapsing` → height/padding/margin animate to
  `0` over `.5s cubic-bezier(.4,0,.2,1)` (`overflow:hidden`) so the page content
  below **eases up smoothly** instead of jumping.
- **Fade timing**: opacity stays `1` during the collapse and fades in the
  **last 600ms** — opacity transition is `.6s ease`, JS sets `opacity:0` at
  `100ms`; element removed at `720ms`.
- **Applies to ALL flash messages app-wide** via shared assets (single source of
  truth, keep in sync):
  - CSS in `public/css/base.css` (loaded by every layout + auth page).
  - Behavior in `public/js/flash.js` (`data-auto-dismiss`, auto-dismiss after
    `3000ms`); loaded by `layouts/admin`, `layouts/standalone`, and every
    `auth/*` page. Do **not** add per-view inline auto-dismiss scripts.

---

## Undo

### "undo" / "undo Nx"
Revert the last change (or N changes) exactly, in reverse order, restoring the
previous file state. Verify each revert lands on the exact prior values.

---

## Backup

### "data backup"
Create a fresh copy of the database in the root folder. Exact action: create
`backup database\` folder (if missing) and run
`& "C:\xampp\mysql\bin\mysqldump.exe" -u root --host=127.0.0.1 --routines --single-transaction monetix`
writing `monetix.sql` into it. Confirm the file exists (size + table count).

---

## AI / authorization commands

### "audit the AI tools" / "fix AI tools"
Run the AI audit pass: verify each `app/Services/Ai/Tools/**` tool against the
real schema (columns, enums, relations), fix wrong SQL, add tests, run Pint +
PHPUnit, update `docs/memory.md`, deliver a report.

### "implement AI tools for [industry]"
Only build tools for **real existing domain tables** — never fabricate. If the
industry has no domain tables (e.g. real estate/transportation/restaurant),
expose only the shared core tool (`get_income_expense`) and wire empty industry
lists in `config/ai-tools.php`.

### "branch authorization model"
Apply the reusable Tenant → Branch → User → Role → Permission layer:
`BranchContext` + `BranchScoped` concern (global scope key `branch`), middleware
sets branch from `institute_users.branch_id` (NULL = all branches), AI tools get
`branchId` from `AiContext` and explicit filters (direct `branch_id` or
`whereHas('student'/'batch')`). Courses catalog stays unscoped.

---

## Verification after any Blade/CSS change

- `php artisan view:clear` (after editing Blade).
- For controller/model changes: `vendor\bin\pint <paths>` then
  `vendor\bin\phpunit`.
- Keep `docs/memory.md` changelog updated for feature-level changes.
