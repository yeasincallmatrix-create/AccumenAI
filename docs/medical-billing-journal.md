# Medical Billing Journal Integration

Medical invoices (`medical_invoices`) post double-entry journals to the GL
through `MedicalBillingJournalService`, which wraps the shared
`JournalPostingService` (post / reverse / void — never a hand-rolled posting
path). This document covers the revenue mode switch; the posting pipeline
lives in `app/Services/Medical/MedicalBillingJournalService.php`.

## Revenue Mode Switching

- Settings → **Medical** → *Medical Billing — Journal Settings*
- Can only switch at the start of a fiscal year
- The system blocks switching if any medical journals exist in the
  current open fiscal year
- Rationale: prevents mixed-mode confusion in the same reporting period

The guard is enforced in `InstituteSettingController::updateMedicalBilling()`:
any journal with `ref_type = 'medical_invoice'` whose `journal_date` falls
between the current fiscal year's `start_date` and `end_date` refuses the
switch with a validation error. The `auto_post` toggle is **not** fenced —
it can be changed at any time without affecting the mode guard.

Settings are stored per institute in the global `settings` table using
tenant-suffixed keys (the table has no `institute_id` column):

| Key | Values | Fallback |
|---|---|---|
| `medical.billing.revenue_mode.{institute_id}` | `accrual` \| `cash` | `config('medical_billing.revenue_mode')` |
| `medical.billing.auto_post.{institute_id}` | `1` \| `0` | `config('medical_billing.auto_post')` |

Both the settings form and the posting pipeline read through
`MedicalBillingJournalService::getRevenueMode()` / `getAutoPost()` so the
fallback chain can never diverge between the two.

### How to switch

1. Wait for current fiscal year to close
2. Open new fiscal year
3. Switch mode before creating any medical invoices
4. Verify with a test invoice

## Symptom: Medical invoice created but no journal

**Symptom:** the invoice saves and appears in billing normally, but its
`journal_id` is NULL and no `journals` row exists (`ref_type =
'medical_invoice'`). GL revenue is under-reported while the invoice exists.

**Why:** `postSaleJournal()` is deliberately fail-soft — the journal pipeline
resolves accounts by **tenant-scoped** code lookup (`accountByCode` never
falls back to global `institute_id NULL` rows), and any missing account makes
it log a warning and return `null` *before* creating the journal. Invoice
creation itself never fails. Typical cause: a healthcare institute that
skipped onboarding (or predates the industry filter fix) owns none of
`1000.1` / `1200.1` / `2100.1` / `4300.x`.

**Diagnosis — grep the log for:**

- `MedicalBillingJournalService: required account missing` — carries
  `institute_id`, `code`, `invoice_id` (or `payment_id`) and an
  `action_required` value.
- `MedicalBillingJournalService: tenant has no active income account` —
  carries `institute_id`, `type`, `invoice_id`, `action_required`.

**Recovery:**

1. Run the remediation the log line suggests:

   ```bash
   php artisan medical:provision-coa --institute={id} --execute
   ```

   Dry-run is the default (omit `--execute` to preview). `--all-healthcare`
   targets every active healthcare institute. Installs the healthcare subtree
   **plus** the billing-core accounts (`1000.1`, `1200.1`, `2100.1`) — a
   subtree-only install still fails the Trade Receivable lookup. Idempotent:
   per-institute `⊘ no changes needed` when everything exists. Exit 0 when
   all targets succeeded, 1 otherwise.

2. Invoices created **before** the fix keep `journal_id` NULL — the pipeline
   runs once at creation. Re-post them with **Post Journal** on the invoice
   page (see *Manual Posting* below); new invoices post automatically.
