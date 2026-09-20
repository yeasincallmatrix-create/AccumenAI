# Global COA — 100s-Multiple Pattern

## Design Principle
- Parent anchors at 100s multiples: 1000, 1100, 1200, …
- Children in 1001–1099, 1101–1199, …
- ~99 slots per parent for tenant custom accounts
- Total capacity: ~990+ accounts per major category

## Global Template (43 accounts — live in prod since 2026-09-20)

### Assets
| Code | Name | Role | Children |
|------|------|------|----------|
| 1000 | Cash | parent | 1001, 1002 |
| 1100 | Accounts Receivable | parent (bare) | — |
| 1200 | Inventory Asset | parent | 1201 |
| 1300 | Fixed Assets | parent | 1301 |

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
