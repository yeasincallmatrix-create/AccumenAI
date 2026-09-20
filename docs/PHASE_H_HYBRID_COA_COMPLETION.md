# Hybrid COA — Phase H Completion

## Date: 2026-09-20
## Status: COMPLETE (functional verification)
## Full-suite numbers: PENDING (coordination-blocked)

## Deliverables
- Migration: institute_id nullable (COA + account_groups)
- Seeders: 5 groups + 38 accounts (prod + test)
- Isolation: 5 layers (scope, service, policy, binding, tests)
- UI: Livewire with global badges + edit gating
- Tests: 9 isolation + 4 resolver priority

## Verification (Verified Read-Only)
### Production State
- Global groups: 5
- Global accounts: 38
- Tenant rows: 0
- Tenant groups: 0
- Integrity: clean

### Test State
- Global groups: 5
- Global accounts: 38
- Tenant rows: 0
- Permissions: 190 (finance.view ✅, crm.view ✅)

### Behavior Verification
- Immutability: Global 1001 (Cash in Hand) — isGlobal=YES, isEditableBy(1)=NO ✅
- Isolation: visibleTo(4)=38, visibleTo(5)=38, no cross-tenant leak ✅
- Scope: TenantScoped hybrid branch verified (lines 29-38)
- Policy: Gate authorization active

### Test Baseline (from Phase G)
- Accounting: 193/193 ✅
- COA isolation: 9/9 ✅
- Resolver priority: 4/4 ✅
- Bank reconciliation: 6/6 ✅
- Approval workflow: 6/6 ✅
- FinanceCore: 30/30 ✅

## Full-Suite Baseline — PENDING
Reason: Parallel lane activity
- 2 foreign processes (Education test: PIDs 17724/20008)
- 16 uncommitted lane files (DatabaseSeeder B95 hunk + LabIntegration/gateway)
- Shared monetix_test contention

To complete:
1. Coordinate lane to commit/stash
2. Get 15-min clean window
3. Run H.2 baseline
4. Update this doc with results

## Coordination Notes
- Parallel lane active throughout Phase D-H
- Test DB baseline shifted (finance.view + crm.view added externally)
- Deadlocks observed: exogenous (shared DB contention)
- Phase G work unaffected

## Production State
- Hybrid COA: LIVE in accumen_ai
- Tenant isolation: ACTIVE (5 layers)
- Ready for tenant onboarding: YES

## Phase 9 Backlog
1. Per-shard test DBs (1-2 days)
2. Seed caching layer (1 day)
3. Coordination protocol (process)
4. DeadlockException standard (4 hrs)
5. CI/CD pipeline (2 days)
6. Production monitoring (3 days)
7. Test suite speed (<5 min)
8. Model canonicalization (2-3 days)
9. Institute_users provisioning (1-2 days)
10. Observer cleanup (1 day)

## Commits
- Phase C (seed/global rows): `cf736660` global account groups + chart of accounts, `e8d61dd7` register global COA seeders in DatabaseSeeder (C.5), `4e47e934` Phase B nullable institute_id + unique key redesign
- Phase D (isolation scope): `da874635` enforce tenant isolation with hybrid scope, `48fc667f` explicit hybrid scopes (additive only)
- Phase E (controller/UI): `26a11b7e` controller with tenant isolation, `884c3c88` UI for global + tenant accounts
- Phase F (policy): `cb019c75` register ChartOfAccountPolicy via Gate
- Phase G (tests): `282f802f` tenant isolation tests

## Recommendation
- Hybrid COA: FUNCTIONALLY COMPLETE
- Formal baseline: run when coordination window available
- Next: Phase 9 infrastructure improvements
