# Unified Invoices (Finance + Sales Soft Merge)

**Date:** 2026-10-15 · **Branch:** `dev` · **Status:** shipped (Tasks 0–8)

Finance and Sales invoices share a single `invoices` table (`sales_order_id` is
NULL for Finance invoices, non-NULL for Sales). This build gives both modules one
read-only list + detail experience without touching the write flows of either
module.

## Routes

| Route | Name | Controller | Gates |
| --- | --- | --- | --- |
| `GET /invoices` | `invoices.index` | `UnifiedInvoiceController::index` | `permission:finance.view` + `module_access:finance` |
| `GET /invoices/{invoice}` | `invoices.show` | `UnifiedInvoiceController::show` | same + `Gate::authorize('view', $invoice)` (`InvoicePolicy`) |

Both live in `routes/institute_modules.php` inside the existing `$tenant`
middleware group (so `SetTenantContext`, `EnsureLivewireContext`, auth, etc.
apply exactly as on the finance routes). There is **no controller constructor
middleware** — gating is route-level only, keeping the controller a thin read
shell.

`DenyTeacherFromFinance` is **URI-scoped to `finance*` / `accounting*`** paths,
so `/invoices` bypasses it. This is harmless: teachers have neither
`finance.view` nor `module_access:finance`, so the route gates already return
403 before the teacher-deny middleware would matter.

## Permissions decision

No new permission slug was introduced. Access reuses the existing finance
slugs (`finance.view` for the routes, `finance.manage` for cancel — unchanged
on the existing `finance.invoices.cancel` route). A 2026-10-15 audit of the dev
DB (`accumen_ai`) found **no role that has `sales.invoices.view` without
`finance.view`**, so no currently-provisioned role loses visibility. Verify the
same query on the production DB before deploy:

```sql
SELECT r.id, r.name FROM roles r
JOIN role_permissions rp ON rp.role_id = r.id
JOIN permissions p ON p.id = rp.permission_id
WHERE p.slug = 'sales.invoices.view'
  AND r.id NOT IN (
    SELECT rp2.role_id FROM role_permissions rp2
    JOIN permissions p2 ON p2.id = rp2.permission_id
    WHERE p2.slug = 'finance.view'
  );
```

Empty result ⇒ safe. Non-empty ⇒ those custom roles would need `finance.view`
(or the query result documented as accepted loss).

## Source model

- **Source detection** is structural: `invoice->sales_order_id !== null` ⇒
  **Sales**, `NULL` ⇒ **Finance**. There is no `source` column, no accessor,
  no migration.
- The unified list (`App\Livewire\UnifiedInvoiceList` extends `DataTable`)
  renders a **Source** filter (All / Finance / Sales, default All via
  `?source=`), a Source column badge (`text-bg-secondary` Finance /
  `text-bg-primary` Sales), and the merged column set
  (serial, invoice no., customer, source, total, payable, paid, due, status,
  date, action). Column toggles persist under the user preference key
  `columns_unified_invoices`.
- Customer cell resolution: `party?->name ?? student?->full_name ?? '—'`.

## Show page

`resources/views/institute/invoices/show.blade.php` is a read-only clone of the
finance show template extended with:

- Source badge (Finance/Sales) in the header.
- Conditional cards: Sales Order (number + link to `sales.orders.show`), Student
  (uses the `full_name` accessor), Progressive Billing
  (`is_progressive` / milestone fields).
- Shared sections: items + totals, journal link (read-only), payments history
  (read-only), installments, documents.
- **Cancel** posts to the existing `finance.invoices.cancel` route (shown only
  while `status !== 'cancelled' && paid_amount <= 0`); paid invoices hide it.
- Write actions (record payment, post/reverse journal) are intentionally
  omitted — they remain on the module-specific pages.

## Menu

Both sidebars get an **All Invoices** link to `invoices.index`:

- Sales section: after the sales Invoices link (`institute.blade.php` ~:610).
- Finance section: before the finance Invoices link (~:922).
- Sidebar open-state conditions updated so either group stays open on
  `invoices.*` (`routeIs(..., 'invoices.*')` on both sections). The medical
  sidebar was left untouched.

## Notification deep-links (3 targets re-pointed)

Admin notifications and the payments list previously linked
`finance.invoices.show`; they now link the unified route (a finance or sales
invoice renders correctly there either way):

1. `app/Services/Accounting/InvoiceService.php` (~:170) — "Invoice created"
   admin notification (`category=finance_invoice`).
2. `app/Services/Accounting/PaymentService.php` (~:169) — "Payment received"
   admin notification (`category=finance_payment`).
3. `resources/views/livewire/payments/list.blade.php` (~:49) — invoice column
   anchor on the payments list.

Student-facing notifications (`students.show` links) were **not** changed.

## Tests

`tests/Feature/Invoices/` — 28 tests (74 assertions), all green:

- `UnifiedInvoiceIndexTest` — auth redirect, 403 without `finance.view`, default
  All listing, source filters, badges, tenant isolation (foreign invoice never
  rendered), search, status filter.
- `UnifiedInvoiceShowTest` — both sources render, sales-order/student/
  progressive/journal/payments sections, cross-tenant **404** (TenantScoped
  binding hides the row before the policy runs — same as
  `finance.invoices.show`, see `InvoicePolicyTest`), 403 for receptionist,
  cancel button present on unpaid + absent on paid.
- `UnifiedInvoiceMenuTest` — "All Invoices" rendered twice (both sections),
  both anchor to the same route.
- `UnifiedInvoiceNotificationLinksTest` — invoice-created and payment-received
  notifications deep-link `/invoices/{id}`; payments list invoice cell links the
  unified show route.

Domain regression at commit `8980b0a2`:

| Suite | Result |
| --- | --- |
| `--filter=UnifiedInvoice` | 28 passed |
| `tests/Feature/Invoices/` | 28 passed |
| `tests/Feature/Accounting/` | 158 passed |
| `tests/Feature/SalesInvoiceTest.php` | 18 passed |
| `tests/Feature/Finance/` | 82 passed, 27 failed — **all failures in `BankFeedImportTest` + `BillableExpenseTest`, confirmed pre-existing baseline** (reproduced on stashed tree) |

Note: `tests/Feature/Sales/` does not exist — Sales invoice coverage is the flat
`tests/Feature/SalesInvoiceTest.php`.

## Known upstream quirks (left as-is, do not confuse with regressions)

- `resources/views/institute/finance/invoices/show.blade.php` (the original
  finance template) still uses `(float) $invoice->paid_amount === 0` for the
  cancel predicate — a strict float-vs-int comparison that is **always false**,
  so the cancel button never renders there either. The unified clone uses
  `<= 0`.
- The same finance template renders `$invoice->student?->name`, but `Student`
  has no `name` attribute (only the `full_name` accessor) — the student name
  shows blank on the old page. The unified clone uses `full_name`.
- MAWA in the test DB has **no CoA rows and no fiscal years**; tests that drive
  `InvoiceService`/`PaymentService` must seed a minimal receivable/cash/income
  CoA + current fiscal year first (see
  `UnifiedInvoiceNotificationLinksTest::seedMinimalCoa()`).

## Out of scope (deliberate)

- Write actions on the unified show page (record payment, post/reverse journal,
  edit, delete) — module pages own them.
- New permission slug, `source` column, migrations, seeder changes.
- Modifying `FinanceInvoiceController`, `SalesInvoiceController`, the existing
  `InvoiceList` component, or any existing invoice URL.
- The remaining 21 invoice-page deep links found during the audit stay on their
  module-specific routes (e.g. fee-collection links stay on
  `finance.invoices.show`) — only the 3 notification targets above were
  re-pointed.
