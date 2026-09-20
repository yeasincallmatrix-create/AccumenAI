# Global COA — 100s-Multiple Pattern (Anchor-Only)

## Design Principle
- Parent anchors at 100s multiples: 1000, 1100, 1200, …
- Anchors are POSTABLE (no leaf-only restriction)
- Tenants add custom sub-accounts (1003–1099, 1101–1199, …)
- ~99 slots per parent for tenant custom accounts
- Total capacity: ~990+ accounts per major category

## Global Template (43 accounts, 14 parents, 29 linked — live in prod since 2026-09-20)

### Assets
| Code | Name | Role | Children |
|------|------|------|----------|
| 1000 | Cash | anchor (posting) | — |
| 1100 | Bank | anchor (posting) | — |
| 1200 | Accounts Receivable | anchor | — |
| 1300 | Inventory Asset | anchor | — |
| 1400 | Prepaid Expenses | anchor | — |
| 1500 | Fixed Assets | anchor | — |

> Note: 1001 (Cash in Hand) and 1002 (Bank Account) deleted; 1100 Bank and
> 1400 Prepaid added. AR shifted 1100→1200, Inventory 1200→1300,
> Fixed Assets 1300→1500. App default fallback: 1000 Cash, 1100 Bank.

### Liabilities
| Code | Name | Role | Children |
|------|------|------|----------|
| 2000 | Payables | parent | 2001, 2002, 2003 |
| 2100 | VAT Payable | parent | 2101, 2102 |

### Equity
| Code | Name | Role | Children |
|------|------|------|----------|
| 3000 | Equity | parent | 3001, 3002 |
| 3100 | Revaluation Surplus | parent (bare) | — |

### Income
| Code | Name | Role | Children |
|------|------|------|----------|
| 4000 | Revenue | parent | 4001–4005, 4010 |
| 4900 | Realized FX Gain | parent | 4901 |

### Expenses
| Code | Name | Role | Children |
|------|------|------|----------|
| 5000 | Operating Expenses | parent | 5001–5012 |
| 5900 | Realized FX Loss | parent | 5901 |

## Tenant Slot Usage
- Tenants hang custom sub-accounts under any top-level anchor (`parent_id` = anchor id)
- Max sub-account depth is 2 levels (enforced by controller) → tenant subs go under anchors, not under global children
- ~99 free slots per family

## Tenancy Semantics
- **Global rows**: `institute_id = NULL AND is_system = 1` (shared, read-only)
- **Tenant rows**: `institute_id = X` (private, editable)
- Scopes: `globalOnly()` / `tenantOnly($id)` / `visibleTo($id)` (see `app/Models/ChartOfAccount.php`)
- Global scope name: `'institute'` — `withoutGlobalScope('institute')`, never `'tenant'`

## Migration History
- v1 (Phase C): 38 accounts, mixed design (all `parent_id` NULL, anchors by code convention only)
- v2 (Phase H, 2026-09-20): +5 anchors (1000, 2000, 3000, 4000, 5000), 31 children linked → 43 accounts, 12 parents, consistent 100s pattern
- Migration: `2026_09_20_143959_complete_global_coa_100s_pattern` (idempotent, reversible, prod 45ms, zero downtime)
- v3 (Anchor-Only, 2026-09-20): deleted 1001/1002, added 1100 Bank + 1400 Prepaid, shifted 1100→1200 / 1200→1300 / 1300→1500 → 43 accounts, 14 parents, 29 linked
- Migration: `2026_09_20_171311_restructure_coa_anchor_only` (idempotent, reversible except 1001/1002 restore via backup, prod 38ms, zero downtime)
