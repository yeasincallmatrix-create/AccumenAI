# AccumenAI — Static UI Demo — Elements Documentation

> Purpose: Make every UI element self-documenting so an AI/agent (or developer) can convert the static demo (`root/demo/`) into real Laravel Blade + Eloquent without ambiguity. **No backend logic is implemented — all data is static.**

Generated: 2026-08-24 | Stack: Laravel 12.66.0, Blade, Bootstrap 5.3.7, Bootstrap Icons 1.11.3, Chart.js 4.4.0, Poppins/Hind Siliguri

---

## 1. Where Everything Lives

```
root/demo/                          # ← ONLY writable area per instruction (main project frozen)
├── index.html                      # Dashboard — static HTML (also mirrored as Blade at resources/views/ui-preview/dashboard.blade.php)
├── pages/
│   ├── students.html               # Inactive list — 19 cols, drag handle, filters, Show page/Columns/Print+CSV+Excel
│   ├── teachers.html ...           # 19 other modules — placeholders (same shell)
│   └── tax-vat.html
├── assets/css/
│   ├── base.css, layout.css, components.css  # copied from public/css (do not edit)
│   └── demo.css                    # design tokens (see §2)
├── assets/js/app.js                # sidebar, toasts, demo-form guard
└── pdf/dashboard.pdf               # Brave headless print

resources/views/ui-preview/         # Blade mirror (real implementation target)
├── layouts/ui-preview.blade.php
├── components/ui/sidebar.blade.php, stat-card.blade.php
├── dashboard.blade.php
└── students/index.blade.php        # canonical 19-col inactive list (see §4)
```

**Real app routes for preview:** `routes/web.php` → `prefix('ui-preview')` 19 `Route::view` (no controller, no DB). Demo HTML is served directly via Apache `http://localhost/monetix/demo/...`, Blade via `http://localhost/monetix/public/ui-preview/...` — both share same shell.

---

## 2. Design System (single source of truth)

All pages **must** use these tokens — do not style per-page.

| Token | Value | Usage |
|-------|-------|-------|
| `--primary` | `#0d6efd` | buttons, links, active nav |
| `--success` | `#198754` | `candle-btn` border, Paid badge |
| `--warning` | `#d97706` | Pending badge |
| `--danger` | `#dc3545` | Expense, Delete |
| `--purple` | `#6f42c1` | Batches, Teachers |
| `--info` | `#0aa2c0` | Exams |
| `--border` | `#e9ecef` | card/table borders |
| `--radius` | `.5rem` | card, table-responsive |
| `--shadow` | `0 1px 3px rgba(0,0,0,.06)` | card |
| Font | `Poppins` (EN) / `Hind Siliguri` (BN) | headings `700`, body `400` |
| Spacing | `py-3` content, `gap-2/3` toolbar, `p-3` card | consistent |
| Icons | `bootstrap-icons@1.11.3` | `bi-*` only |

**Components — props & usage**

- `stat-card` (`components/ui/stat-card.blade.php`): `@props(['icon','label','value','sub','color','bg'])` → `<div class="stat-card"><div class="icon" style="background:{{bg}};color:{{color}}"><i class="bi {{icon}}"></i></div><div class="num">{{value}}</div><div class="label">{{label}}</div></div>`
- `sidebar` (`components/ui/sidebar.blade.php`): `ui_active($path)` helper, 17 nav-links, `request()->is()` active, collapses via `sidebar-open` + `sidebarBackdrop`
- `page-header`: `div.page-header > div.page-header-text > h4.page-header-title + p.page-header-desc` + `btn-primary btn-sm` action
- `table` + `admin-card`: `div.admin-card > div.table-responsive > table.table.align-middle` + `thead` with funnel filters + `tbody` rows + centered pagination `flex-column align-items-center p-4 border-top`
- `candle-btn`: `padding:8px 14px; border:1px solid #198754; border-radius:6px; hover:#198754 bg` — used for `Show page`, `Columns`, `Print|CSV|Excel` group (`btn-group` joined)
- `funnel` filter: `<a class="text-dark"><i class="bi bi-funnel text-dark" style="font-size:.65rem"></i></a>` inside `<th>` + `data-demo-toast`
- `drag-handle`: `<i class="bi bi-grip-vertical drag-handle" draggable="true">` + JS `dragstart/dragover/dragend` + `col-handle` (42px)

**Responsive:** `col-6 col-md-3` cards, `table-responsive` horizontal scroll, sidebar `sidebar-open` drawer ≤768px, `col-handle` hidden on mobile via `table-responsive`.

---

## 3. App Shell (all pages share)

```html
<div class="topbar"> <!-- fixed, left:230px, z-index:1030 -->
  brand + sidebarToggle + search(280px) + bell + translate + darkToggle + avatar
</div>
<div class="layout">
  <aside class="sidebar"> <!-- 17 nav-links, see §4 inventory -->
  <main class="content"><div class="container-fluid px-4 py-3" style="padding-top:.75rem"> <!-- page-header → toolbar → card -->
```

Topbar, sidebar, backdrop, layout CSS from `public/css/layout.css` (do not duplicate). JS `assets/js/app.js`: sidebar toggle, `demo-form` guard (`preventDefault` + toast), `data-demo-toast` toast.

---

## 4. Page Inventory — Checklist (implement every page)

Every page: `page-header` + breadcrumb (removed per request) + toolbar (search + filters + `Show page` + `Columns` + `Print|CSV|Excel` + `Reset`) + `admin-card` table + pagination `1 of 12` + empty state.

| # | Route (Blade) | Demo HTML | Static Data Example | Real Implementation Hint (for agent) |
|---|---------------|-----------|---------------------|--------------------------------------|
| 1 | `ui-preview.dashboard` | `demo/index.html` | 8 stat-cards (Revenue $124k…), 3 charts (line/doughnut/bar), Recent Admissions (3), Low Stock (2) — `@php $stats = [...]` | Replace `@php $stats` with `DashboardController@__invoke` queries (`Student::count()` etc), `Chart.js` datasets from `FinancialReportService` |
| 2 | `ui-preview.students.index` | `demo/pages/students.html` | **Inactive 19-col** (`# Student ID Roll Number Name Phone Email Registration No. Gender DOB Age Blood Group Religion Nationality NID Passport Branch Guardian Phone Status Actions`) 4 rows (`STD-005 Tanvir Islam` etc, `data-status="Inactive"`, `Re-activate` btn) | Replace static `@php $students` with `Livewire\StudentList` (`baseQuery()->with('branch')`, `searchableColumns()` 9 fields, `filterableColumns()` status/gender/religion/branch_id) — keep `col-handle` + `row-check` + `drag-handle` + `col-toggle` (19 `data-col` 2–20, `localStorage` key `demo-columns-students`) |
| 3 | `ui-preview.teachers.index` | `pages/teachers.html` | Placeholder 3 rows | `TeacherController@index` + `Hr\HrEmployee` |
| 4 | `courses` | `pages/courses.html` | Placeholder | `CourseController@index` |
| 5 | `batches` | `pages/batches.html` | Placeholder | `BatchController` |
| 6 | `admissions` | `pages/admissions.html` | Placeholder | `AdmissionController` |
| 7 | `attendance` | `pages/attendance.html` | Placeholder | `AcademicAttendanceController` |
| 8 | `exams` | `pages/exams.html` | Placeholder | `ExamController` (6 routes: index/sendToExam/show/update/saveMarks/destroy) |
| 9 | `results` | `pages/results.html` | Placeholder | `AcademicResult` |
| 10 | `certificates` | `pages/certificates.html` | Placeholder | `CertificateController` |
| 11 | `payments` | `pages/payments.html` | Placeholder | `FinancePaymentController` |
| 12 | `invoices` | `pages/invoices.html` | Placeholder | `FinanceInvoiceController` |
| 13 | `expenses` | `pages/expenses.html` | Placeholder | `FinanceBudgetController` |
| 14 | `accounting` | `pages/accounting.html` | Placeholder | `AccountingDashboardController` + `AccountingReportController::trialBalance|profitAndLoss|balanceSheet|cashFlow|generalLedger|accountLedger` |
| 15 | `inventory` | `pages/inventory.html` | Placeholder | `Inventory*` |
| 16 | `purchases` | `pages/purchases.html` | Placeholder | `PurchaseOrderController` |
| 17 | `sales` | `pages/sales.html` | Placeholder | `Sales*` |
| 18 | `tax-vat` | `pages/tax-vat.html` | Placeholder per spec §10: Tax Dashboard, VAT Dashboard, Rates, Categories, Rules, Sales/Purchase VAT, Input/Output, Adjustments, Exemptions, Reports, Return Summary, Compliance Calendar, Documents | Create `TaxVatController` (UI only) |
| 19 | `reports` | `pages/reports.html` | Placeholder | `ReportsHubController` |
| 20 | `settings` | `pages/settings.html` | Placeholder | `InstituteSettingController` + `Admin\SettingController` (7 routes: password/language/appearance/mail-payment/ai) |
| 21 | `students/create,show` | `students/create,show` | Static forms/profile | `StudentController@create/show` |

All 21 are linked in `components/ui/sidebar.blade.php` via `url('/ui-preview/...')` — no dead `#`.

---

## 5. Students Inactive List — Spec (canonical example)

**Header (19 cols):** `# | Student ID | Roll Number | Name | Phone | Email | Registration No. | Gender | Date of Birth | Age | Blood Group | Religion | Nationality | NID No. | Passport No. | Branch | Guardian Phone | Status | Actions`

**Funnel filters (black `text-dark`):** on `#` removed per request — now on `Gender, Age, Blood Group, Religion, Nationality, Branch, Status` only (`bi-funnel` 0.65rem, `data-demo-toast="Filter … — demo"`)

**Toolbar (order per real `livewire/students/list.blade.php:23`):** search (280px) + `All Courses` select + `Reset` (`bi-arrow-counterclockwise`) + `Show page` dropdown (25/50/75/100/200) + `Columns` dropdown (19 checkboxes `data-col` 2–20, `max-height:320px`, `localStorage` preserved) + `Print|CSV|Excel` joined `btn-group` (`window.print()` + `exportTable()` Blob CSV)

**Table:** `admin-card d-flex flex-column min-height: calc(100vh - 180px)` + `table-responsive flex-grow-1` + `col-handle` (grip) + `col-check` (checkbox) + `table-row-click` hover + draggable JS (see `resources/views/ui-preview/students/index.blade.php:62`)

**Pagination:** centered `flex-column align-items-center p-4 border-top` + `1 of 12` below.

Static data:

```php
$students = [
 ['id'=>'STD-005','roll'=>'RL-2023-005','name'=>'Tanvir Islam','phone'=>'01555-567890','email'=>'tanvir@example.com','reg'=>'REG-2023-005','gender'=>'Male','dob'=>'2002-04-12','age'=>22,'blood'=>'O+','religion'=>'Islam','nationality'=>'Bangladeshi','nid'=>'1234567890','passport'=>'—','branch'=>'Main','guardian'=>'01711-000001','status'=>'Inactive'],
 // ... 3 more
];
```

Agent: replace with `Student::with('branch')->where('status','inactive')->paginate(25)` and keep `visibleColumns` preference (`$user->preference('columns_students')`).

---

## 6. How Agent Converts Static → Real

1. **Copy shell** (`layouts/ui-preview.blade.php` + `components/ui/sidebar`) to `layouts/app.blade.php` (or keep `ui-preview` as `preview` prefix).
2. **For each page** in §4: replace `@php $static = [...]` with controller query (see hint column). Keep HTML structure, class names, `admin-card`, `table`, `pagination` — only swap data source.
3. **Filters:** wire `wire:model.live` (Livewire) or `request()->query()` (Blade) to `search`/`filters.*` (see `StudentList.php:29`). Keep frontend JS as progressive enhancement, replace `data-demo-toast` with real `route()` form actions.
4. **Columns:** keep `col-toggle` JS but replace `localStorage` with `fetch(route('ui.columns'), {method:'POST', body:{columns:visible}})` if you want server persistence (see `livewire` `toggleColumn()`).
5. **Actions:** replace `data-demo-toast="Re-activate"` with `form method="POST" action="{{ route('students.update', $student) }}"` etc.

**Do NOT** add these in demo: `DB::`, `Auth::`, `Gate::`, `FormRequest` validation, `migrate`.

---

## 7. Verification (agent QA)

- [ ] Every sidebar link (`url('/ui-preview/...')`) returns 200 (`php artisan route:list | grep ui-preview` 19 routes)
- [ ] `demo/index.html` charts render (Chart.js CDN)
- [ ] `demo/pages/students.html` 19-col table, funnel black, drag handle, Show page/Columns/Print+CSV+Excel, wide `calc(100vh - 180px)`, pagination `1 of 12`
- [ ] All HTML under `root/demo/` only (except `resources/views/ui-preview` Blade mirror) — `git status` shows no `app/`/`database/` changes
- [ ] No console errors, responsive (sidebar drawer ≤768px, `table-responsive` scroll)

---

*Keep this file with the demo — an agent can implement the real project by following §4 hints without re-inspecting the codebase.*
