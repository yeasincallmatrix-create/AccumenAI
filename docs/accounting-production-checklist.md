# Accounting Production Checklist — accumenAI / monetix

## 1. Database

- [ ] MariaDB 10.4.32+ (InnoDB, utf8mb4)
- [ ] Run `php artisan migrate --force` on `monetix` (never `migrate:fresh` in prod)
- [ ] Verify `SHOW TABLES` includes: journals, journal_entries, fiscal_years, accounting_periods, chart_of_accounts, account_groups, parties, currencies, exchange_rates, tax_groups, opening_balances, fx_revaluations, budgets, fixed_assets, tax_rates etc.
- [ ] Verify hardening indexes: `php artisan migrate:status` shows `2026_08_31_000100` applied
- [ ] Run `php artisan accounting:health-check` — must return 0
- [ ] Run `php artisan accounting:health-check --institute=<id>` for each tenant after onboarding

## 2. Migrations

- [ ] All accounting migrations idempotent (guarded with `hasTable`/`hasColumn`)
- [ ] No destructive migrations (no `migrate:fresh`, `db:wipe`, `TRUNCATE`)
- [ ] Backup before each `migrate --force`

## 3. Permissions

- [ ] `permissions` + `roles` + `role_permissions` seeded via migrations
- [ ] Verify: `SELECT COUNT(*) FROM permissions WHERE module='accounting'` >= 8
- [ ] Verify: `fx.rates.manage`, `budget.*`, `asset.*`, `tax.*` granted to correct roles
- [ ] Test: receptionist cannot `journals.post`, branch-manager cannot `asset.dispose`

## 4. Accounting Settings

- [ ] `accounting_settings` per institute: `base_currency`, `fiscal_year_start`, `invoice_auto_post`
- [ ] `fx_unrealized_gain_account_code` (4901) / `fx_unrealized_loss_account_code` (5901) configured

## 5. Base Currency & Currencies

- [ ] `currencies` seeded (BDT, USD, INR, PKR, EUR) with `is_base` on one
- [ ] `SELECT * FROM currencies WHERE is_base=1` returns 1 row

## 6. Exchange Rates

- [ ] At least one rate per active foreign currency pair for current date
- [ ] `php artisan accounting:health-check` warns if no rates for foreign currencies

## 7. Fiscal Years & Periods

- [ ] At least one `fiscal_years` with `status=open` and `is_current=1` per institute/branch
- [ ] Monthly `accounting_periods` with `status=open` covering today
- [ ] No overlapping fiscal years (validated by service)

## 8. Chart of Accounts

- [ ] 32+ template accounts installed via `AccountingSetupService::setupForInstitute()`
- [ ] Required codes exist: 1001 Cash, 1002 Bank, 1100 AR, 1200 Inventory, 1300 Fixed Assets, 2001 AP, 2100 VAT Payable, 3002 Retained Earnings, 4901 FX Gain, 5901 FX Loss

## 9. Opening Balances

- [ ] `opening_balances` balanced per fiscal year (debit = credit)
- [ ] Carried forward from prior year via `closeFiscalYear`

## 10. AR/AP

- [ ] `parties` with `type=customer|supplier|both` and correct `branch_id`
- [ ] `ReceivablesPayablesService::totals()` matches GL (`is_receivable`/`is_payable`)

## 11. Inventory

- [ ] `inventory_items` valuation matches GL 1200
- [ ] Stock engine uses `DB::transaction` and `lockForUpdate` for valuation

## 12. Tax/VAT

- [ ] `tax_groups` or `tax_rates` configured per jurisdiction
- [ ] `tax_jurisdictions` hierarchy if multi-region
- [ ] Check `TaxComplianceService::countryConfig('BD')` returns 15% VAT

## 13. Depreciation

- [ ] `asset_categories` with CoA mappings
- [ ] `DepreciationRunJob` scheduled daily 02:00, idempotent per (institute,branch,period_start)

## 14. Budgeting

- [ ] `budgets` workflow: draft → submitted → approved → locked (all transactional)
- [ ] `BudgetCalculationService` actuals from posted journals

## 15. FX

- [ ] `FxRevaluationJob` scheduled daily 03:00, idempotent per (institute,branch,fiscal_year,period,currency,as_of_date)
- [ ] Closing rates resolvable via `ExchangeRateService::findEffective`

## 16. Reports

- [ ] `FinancialReportService::trialBalance` balanced
- [ ] `AccountingReconciliationService::all($instituteId)` all pass

## 17. Audit

- [ ] `accounting_audit_trails` append-only, contains who/what/when/tenant/branch
- [ ] `tax_audit_logs`, `asset_audit_logs`, `fx_revaluations` audited

## 18. Backups

- [ ] Daily `mysqldump` of `monetix` (full), retain 30 days
- [ ] Before `closePeriod` / `closeFiscalYear` take snapshot
- [ ] Store dumps offsite, test restore quarterly
- [ ] Backup `storage/`, `.env`

## 19. Queue

- [ ] `.env` `QUEUE_CONNECTION=database` (not `sync`) in production
- [ ] Run `php artisan queue:work --queue=notifications,default --tries=3 --sleep=3` via supervisor/systemd
- [ ] Run `php artisan schedule:run` every minute via cron: `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1`

## 20. Scheduler

- [ ] Verify `php artisan schedule:list` shows: DepreciationRunJob 02:00, FxRevaluationJob 03:00, auth:audit-hashes 03:00, notifications:retry every 5m, accounting:health-check 04:00

## 21. Cron

- [ ] Single cron entry for scheduler (see above)
- [ ] Queue worker supervised (systemd/supervisor), auto-restart

## 22. Logs

- [ ] `storage/logs/laravel.log` captures failed postings, reconciliation mismatches, period-close failures
- [ ] No sensitive data (passwords, SQL credentials) in logs
- [ ] Log rotation configured

## 23. Security

- [ ] `SetTenantContext` + `CheckPermission` middleware on all `/finance/*` routes
- [ ] No direct `Model::create($request->all())` — validated via services
- [ ] Tenant isolation verified: `php artisan accounting:health-check` checks `je.institute_id != j.institute_id`
- [ ] Branch isolation: `BranchScopedOrShared` on CoA, Party, Journal

## 24. Verification

- [ ] Run `php artisan accounting:health-check --institute=<id>` per tenant
- [ ] Run `php artisan test --filter=Accounting` — all green
- [ ] Run full suite, classify failures (pre-existing vs new)
