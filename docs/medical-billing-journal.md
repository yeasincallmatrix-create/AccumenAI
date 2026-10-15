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
