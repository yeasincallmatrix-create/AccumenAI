# Global COA — Structure (post-Phase C)

> Regenerated 2026-10-08 from live DB after Phase C (C1–C4).

## Counts (live)

| Metric | Value |
|---|---|
| Global template accounts | **114** |
| Global parents/roots (`parent_id IS NULL`, all `is_header`) | **34** (5 category roots + 29 anchors; 28 anchors carry children, `3200` is a bare anchor) |
| Global leaves (`is_postable = 1`, dotted codes) | **80** |
| **Anchors postable** | **0** — anchors are headers (`is_header = 1`, `is_postable = 0`); only leaves are postable |
| Tenant tree (example institute 1022) | **5 roots + 25 anchors + 76 leaves = 106** (1023 = 106; 192/1024 = 104) |
| Tenant rows total (all institutes) | 932 |

## Design Principle

- Three-level tree: **category root (1–5) → anchor (1000s multiple) → leaf (dotted code)**
- Anchors are **headers, never postable**; leaves (`1000.1`, `1000.2`, …) are the only postable rows
- Tenants get a full private clone of the template, then hang custom leaves under anchors
- Dotted leaf numbering gives ~99 slots per anchor (`1000.1`–`1000.99`)
- Global template rows: `institute_id = NULL AND is_system = 1` (shared, read-only)

## Global Template (114 accounts)

Anchors with their leaves; bare headers marked *(no children)*.

### Assets (type `asset`)

| Code | Name | Leaves |
|------|------|--------|
| 1 | Assets | *(category root, no children)* |
| 1000 | Cash & Cash Equivalents | 1000.1 Cash in Hand, 1000.2 Petty Cash |
| 1100 | Bank Accounts | 1100.1 Primary Bank Account |
| 1200 | Accounts Receivable | 1200.1 Trade Receivable, 1200.2 Input VAT Receivable, 1200.3 TDS Receivable |
| 1300 | Inventory | 1300.1 Raw Materials, 1300.2 Finished Goods |
| 1400 | Fixed Assets | 1400.1 Land & Building, 1400.2 Machinery & Equipment, 1400.3 Furniture & Fixtures, 1400.4 Vehicles, 1400.5 Accumulated Depreciation |
| 1500 | Other Assets | 1500.1 Prepaid Expenses, 1500.2 Security Deposits |
| 1600 | Investments | 1600.1 Short-term Investment |

### Liabilities (type `liability`)

| Code | Name | Leaves |
|------|------|--------|
| 2 | Liabilities | *(category root, no children)* |
| 2000 | Accounts Payable | 2000.1 Trade Payables, 2000.2 Accrued Expenses, 2000.3 Salary Payable |
| 2100 | Tax Payable | 2100.1 VAT Output Payable, 2100.2 TDS Payable (WHT), 2100.3 Income Tax Payable, 2100.4 Tax Clearing |
| 2200 | Loans | 2200.1 Bank Loan - Short Term, 2200.2 Bank Loan - Long Term, 2200.3 Director's Loan |
| 2300 | Provisions | 2300.1 Provision for Tax |
| 2400 | Other Liabilities | 2400.1 Dividend Payable, 2400.2 Interest Payable |

### Equity (type `equity`)

| Code | Name | Leaves |
|------|------|--------|
| 3 | Equity | *(category root, no children)* |
| 3100 | Owner's Capital (Sole) | 3100.1 Owner's Capital, 3100.2 Owner's Drawings |
| 3200 | Partners' Capital (Partnership) | *(bare anchor, no children)* |
| 3300 | Share Capital (Pvt Ltd) | 3300.1 Authorized Capital, 3300.2 Issued Capital, 3300.3 Paid-up Capital, 3300.4 Share Premium |
| 3400 | Retained Earnings | 3400.1 Retained Earnings, 3400.2 Dividend Declared |

### Income (type `income`)

| Code | Name | Leaves |
|------|------|--------|
| 4 | Income | *(category root, no children)* |
| 4000 | Operating Revenue | 4000.1 Product Sales, 4000.2 Service Revenue, 4000.3 Consultation Fees, 4000.4 Discount Received |
| 4100 | Education Income | 4100.1 Tuition Fees, 4100.2 Admission Fees, 4100.3 Exam Fees, 4100.4 Certificate Fees |
| 4200 | Training Income | 4200.1 Course Fees, 4200.2 Registration Fees |
| 4300 | Medical Income | 4300.1 Consultation Fees, 4300.2 Diagnostic Fees, 4300.3 Pharmacy Sales |
| 4400 | Retail Income | 4400.1 Merchandise Sales |
| 4900 | Other Income | 4900.1 Interest Income, 4900.2 Rental Income, 4900.3 Gain on Disposal, 4900.4 Miscellaneous Income |

### Expenses (type `expense`)

| Code | Name | Leaves |
|------|------|--------|
| 5 | Expenses | *(category root, no children)* |
| 5000 | Cost of Goods Sold | 5000.1 Raw Material Purchase, 5000.2 Direct Labor, 5000.3 Manufacturing Overhead, 5000.4 Freight & Carriage, 5000.5 Cost of Goods Sold |
| 5100 | Employee Benefits | 5100.1 Basic Salary, 5100.2 House Rent Allowance, 5100.3 Medical Allowance, 5100.4 Bonus & Incentives, 5100.5 Provident Fund, 5100.6 Gratuity |
| 5300 | Financial Expenses | 5300.1 Interest Expense, 5300.2 Bank Charges |
| 5400 | Depreciation | 5400.1 Depreciation Expense |
| 5500 | Taxes & Licenses | 5500.1 Income Tax Expense, 5500.2 Trade License Fees |
| 5900 | Other Expenses | 5900.1 Miscellaneous Expenses |
| 6000 | Operating Expenses | 6000.1 Rent, 6000.2 Utilities, 6000.3 Internet & Telephone, 6000.4 Office Supplies, 6000.5 Marketing & Advertising, 6000.6 Travel & Conveyance, 6000.7 Repairs & Maintenance, 6000.8 Legal & Professional |

## Tenant Trees (live)

- Onboarding clones the template per institute: **5 category roots (1–5) + anchors + leaves**
  (institute 1022: 5 + 25 + 76 = 106 rows; totals vary slightly by industry install)
- Tenant rows are `institute_id = X`, fully editable (`is_system = 0`)
- Custom tenant leaves hang under anchors as dotted codes — ~99 slots per anchor
- Depth is enforced: leaves may not nest below the leaf level

## Tenancy Semantics

- **Global rows**: `institute_id = NULL AND is_system = 1` (shared, read-only)
- **Tenant rows**: `institute_id = X` (private, editable)
- Scopes: `globalOnly()` / `tenantOnly($id)` / `visibleTo($id)` (see `app/Models/ChartOfAccount.php`)
- Global scope name: `'institute'` — `withoutGlobalScope('institute')`, never `'tenant'`

## Migration History

- v1 (Phase C): 38 accounts, mixed design (all `parent_id` NULL, anchors by code convention only)
- v2 (Phase H, 2026-09-20): +5 anchors (1000, 2000, 3000, 4000, 5000), 31 children linked → 43 accounts
- v3 (Anchor-Only, 2026-09-20): deleted 1001/1002, added 1100 Bank + 1400 Prepaid, shifted AR/Inventory/Fixed Assets codes → 43 accounts, 14 parents (migrations `2026_09_20_143959_*`, `2026_09_20_171311_*`)
- Phase C1–C4 (2026-10-08): tenant CoA re-anchored into self-contained 3-level trees; template grew to 114 accounts / 34 parents; anchors converted to headers (0 postable); soft-deleted codes reusable via `uq_*_code_v3` + `alive` (F-004)
