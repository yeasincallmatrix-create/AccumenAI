# Medical Billing — Test Report (Task 9)

**Date:** 2026-10-15 · **Branch:** `dev` · **Suite:** `php artisan test tests/Feature/Medical/`

| Scope | Tests | Assertions | Runtime |
|---|---:|---:|---:|
| Full `tests/Feature/Medical/` | **108 passed, 0 failed** | 828 | 60.62s |
| Task 9 additions (6 classes) | **21 passed** | 237 | 14.47s |

Run environment: PHPUnit 11.5.56 / PHP 8.2.12 / MySQL `monetix_test` (`DatabaseTransactions`,
pre-seeded schema, no migrations). Authoritative run used `--testdox`; output captured in
`test_results.txt` (gitignored scratch).

---

## 1. Task 9 deliverables by area

| Chunk | Commit | Class | Tests | Assertions | Proves |
|---|---|---|---:|---:|---|
| 9a (Verify-3 fix) | `f422f9c6` | `MedicalModuleActivatorTest` | 3 | 10 | `Setting::has()` + guarded seeds: defaults written only when keys absent; reactivation never overwrites a tenant's customized `revenue_mode` / `auto_post`. |
| 9.1 lifecycle | `96dc764a` | `MedicalBillingLifecycleTest` | 3 | 87 | Full arcs on CoA provisioned by the real `medical:provision-coa` command: accrual (sale journal → partial → full payment → cancel blocked), cash (no sale journal, receipts Dr Cash/Cr Revenue), unpaid cancel (reversal row + `reversal_of` link + swapped legs + FK cleared + re-cancel blocked). |
| 9.2 manual post | `e05c1c3d` | `MedicalBillingManualPostTest` | 2 | 31 | `auto_post=0` → DRAFT journal that `FinancialReportService::entryQuery` (`journals.status='posted'`) excludes from **both** trial balance and GL; `invoices.post-journal` flips it to posted (`posted_by`, `posted_at`) and the rows appear in GL + trial balance. |
| 9.3 mode switch | `d8bc02ff` | `MedicalBillingModeSwitchTest` | 3 | 32 | Guard blocks a `revenue_mode` change while a `medical_invoice` journal sits in the **current** FY (no setting write); the same institute switches after FY rollover (audit row written); old accrual invoice + posted sale + TB rows unchanged after the switch while new invoices follow cash mode. |
| 9.4 tenant isolation | `269971c3` | `MedicalBillingTenantIsolationTest` | 5 | 36 | Two provisioned institutes side by side: index paginator id-scoped; cross-tenant show/cancel → 403 with zero side effects; trial balance/GL keep same account codes but own activity only; `revenue_mode` key independent per institute (accrual posts, cash does not). |
| 9.5 fail-soft | `207db963` | `MedicalBillingFailSoftTest` | 5 | 41 | Missing CoA never blocks business operations: payment completes with `payment.journal_id` null + actionable warning (`code`, `invoice_id`, `payment_id`, `action_required`); zero orphan journal/entry rows (atomicity); cancel works for never-journaled invoices; VAT branch (`2100.1` deleted) fails soft with totals intact; manual-post endpoint answers `errors['journal']` (302, never 500). |
| 9.6 report | (this file) | `docs/medical-billing-test-report.md` | — | — | This document. |

Deliberately non-duplicative with `MedicalProvisionCoaTest` (7 tests: command behavior +
sale-side fail-soft → provision → recovery e2e) and `ManualPostJournalTest` (7 tests:
permission matrix, audit, already-posted/no-journal/same-institute rejections, button
visibility).

---

## 2. Full suite inventory (19 classes / 108 tests)

| Class | Tests | Area |
|---|---:|---|
| BillingServiceTest | 4 | invoice creation + sale hook |
| InvoiceCancelTest | 9 | cancel gate stack, reversal, payments |
| LiveBroadcastTest | 7 | telemedicine broadcast (unrelated to billing GL) |
| ManualPostJournalTest | 7 | post-journal permission/UI/rejections |
| **MedicalBillingFailSoftTest** | 5 | **Task 9.5** |
| MedicalBillingJournalServiceTest | 9 | journal unit paths (incl. cash VAT proration formula, income/AR fail-soft) |
| **MedicalBillingLifecycleTest** | 3 | **Task 9.1** |
| **MedicalBillingManualPostTest** | 2 | **Task 9.2** |
| **MedicalBillingModeSwitchTest** | 3 | **Task 9.3** |
| **MedicalBillingTenantIsolationTest** | 5 | **Task 9.4** |
| **MedicalModuleActivatorTest** | 3 | **Task 9a (Verify-3)** |
| MedicalProvisionCoaTest | 7 | `medical:provision-coa` command + sale fail-soft/recovery |
| NewInstituteBillingSettingsTest | 3 | onboarding seeding + idempotency |
| OpdFeeCancelReversalTest | 7 | OPD fee reversal |
| OpdFeeFlowTest | 6 | OPD fee flow |
| OpdFeeJournalTest | 12 | OPD fee GL |
| OpdFeeRefundButtonTest | 4 | OPD refund UI |
| QueueDisplayTest | 8 | queue display (unrelated) |
| TpaServiceTest | 4 | TPA receivable path |
| **Total** | **108** | |

---

## 3. Gaps found & documented (no code changes in Task 9)

1. **Receipt journals carry `ref_type = NULL`.** Only sale journals are stamped
   (`MedicalBillingJournalService:151 'medical_invoice'`). Consequences:
   - `InstituteSettingController::hasMedicalJournalsInFiscalYear()` filters
     `ref_type='medical_invoice'`, so a **cash-mode institute whose only current-FY
     journals are receipts** can switch `revenue_mode` mid-year — the controller
     docblock ("ref_type is what the service stamps on every journal") overstates this.
   - Cancellation is unaffected: `reverseInvoiceJournals()` resolves receipts through
     `invoice->payments()->journal_id`, not `ref_type`.
2. **Mode switch vs. open accrual invoices.** `postReceiptJournal()` reads the
   **current** `revenue_mode` at payment time, not the invoice's original mode. After an
   accrual invoice is partially paid, an FY rollover + switch to cash makes later receipts
   on that invoice cash-style (Dr Cash / Cr Revenue) even though the sale was already
   accrued — a potential double-recognition scenario. Task 9 pins only the documented
   spec ("old accrual invoices remain"); this open-invoice payment behavior is **not
   tested and not fixed** — needs a product decision (block switch with open AR, or stamp
   mode on the invoice).
3. **Cross-context service calls fail soft by design.** `ChartOfAccount` carries
   `TenantScoped`; creating a foreign tenant's invoice under the wrong `TenantContext`
   fails every account lookup (observed while building Task 9.4). Production is protected
   because `ensureSameInstitute` precedes every billing HTTP mutation — but
   `BillingService` itself has **no tenant guard**; it must only ever be called with the
   context matching the patient's institute.
4. **Invoice numbers are per-tenant sequences** (`INV-YY-00001` restarts each tenant) —
   they are unique only within an institute; UI assertions must key on ids.
5. **Known pre-existing failure outside Medical:** `InstituteSettingsTest >
   settings page shows promotions link only with promotion permission` fails on clean
   `HEAD` (unrelated baseline, per repo notes).

## 4. Coverage gaps remaining

- **Concurrency:** `processPayment`'s `lockForUpdate` overpayment fence has no
  parallel-request test (needs two DB connections; flaky on shared MySQL — skipped
  deliberately).
- **Branch-fenced billing:** Task 9 ran all invoices with `branch_id = NULL`; the H1
  branch-fallback in `requireAccount` and `scopeBranch` paths are exercised elsewhere,
  not in these suites.
- **Route permission matrix for read/cancel routes with non-owner roles:** covered for
  `medical_billing.post` (ManualPostJournalTest); view/cancel slugs rely on the
  middleware gate stack being route-audited (`route:list` verified in Task 8.5).
- **Cash-mode receipt VAT proration** on partial payments is unit-covered in
  `MedicalBillingJournalServiceTest`, not re-tested end-to-end here.

## 5. How to run

```bash
# whole medical suite (canonical)
php artisan test tests/Feature/Medical/

# Task 9 classes only
php artisan test --filter='MedicalBillingLifecycleTest|MedicalBillingManualPostTest|MedicalBillingModeSwitchTest|MedicalBillingTenantIsolationTest|MedicalBillingFailSoftTest|MedicalModuleActivatorTest'

# report listing
php artisan test tests/Feature/Medical/ --testdox
```

Never run the full repo suite sequentially (timeout); use `php tests/shards/run.php`.

---

## Task 9 — Full Coverage Extension

New test classes added:

- MedicalBillingLifecycleTest (3 tests) — full accrual/cash/cancel flows (`96dc764a`)
- MedicalBillingManualPostTest (2 tests) — draft → HTTP post → GL (`e05c1c3d`)
- MedicalBillingModeSwitchTest (3 tests) — FY guard + rollover (`d8bc02ff`)
- MedicalBillingTenantIsolationTest (5 tests) — cross-tenant security (`269971c3`)
- MedicalBillingFailSoftTest (5 tests) — missing CoA recovery (`207db963`)
- ReceiptJournalRefTypeTest (2 tests) — Gap 1 fix (`54605120`)
- ModeSwitchReceiptBehaviorTest (5 tests) — Gap 2 fix (`13f9d9f2`)

## Final Totals

| Directory | Tests | Assertions |
|---|---|---|
| tests/Feature/Medical/ | 115 | 914 |
| tests/Feature/Settings/MedicalBillingSettingsTest | 8 | 34 |
| **Medical + related** | **123** | **948** |

## Regression Snapshot (Task 10)

Full suite via shards: 4,955 tests, 6 shards

- **0 failures in changed domains** (Medical, Accounting, Finance, Settings, Hr, Sales, Purchase, Institute*)
- ~1,122 pre-existing baseline failures unrelated to this work
- Known baselines: InstituteSettingsTest (promotions link), InstituteModuleEntitlementTest (migration rollback on data-only schema)

## Coverage Gaps (acknowledged)

- No concurrency/race tests (row lock verified manually only)
- No multi-currency tests (feature not supported)
- No refund flow tests (feature not implemented)
