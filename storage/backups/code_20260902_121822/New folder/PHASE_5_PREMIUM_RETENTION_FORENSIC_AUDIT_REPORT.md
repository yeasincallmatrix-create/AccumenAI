# PHASE 5 — PREMIUM RETENTION (3-YEAR) FORENSIC AUDIT REPORT

## A. Existing Premium Architecture

- **Package:** `subscription_packages` (id, slug `FREE|BASIC|ADVANCED|PREMIUM`, price_monthly/yearly, status active) — `PREMIUM` slug is authoritative premium (9000/month).
- **Subscription:** `institute_subscriptions` (institute_id, package_id, billing_cycle monthly/yearly/trial, price_paid, start_date, end_date, status `active|expired|cancelled`, payment_reference) — TenantScoped.
- **Membership:** `institution_user` (`user_id`, `institution_id`, `role_id` slug `institute-owner`, status active) — owner determines institute premium.
- **Institute:** `institutes` hasMany `instituteSubscriptions`.
- **Premium check:** No `is_premium` column; premium = `package.slug='PREMIUM' AND status='active' AND end_date>=today AND institute not deleted`.

## B. Authoritative Premium Rule

**"Premium is determined by currently active PREMIUM package subscription owned by the user."**

Specifically: `Membership where user_id=X AND role slug institute-owner AND institution not deleted AND has instituteSubscriptions where status active AND end_date >= today AND package slug PREMIUM AND package status active` → premium. Uses `withoutGlobalScopes` to bypass TenantScoped during cleanup (out-of-tenant). Implemented in `AccountInactivityService::isPremium()` (single authoritative method).

## C. Retention Rule

- Normal = **365 days** (`DEFAULT_RETENTION_DAYS`)
- Premium = **1095 days** (`PREMIUM_RETENTION_DAYS`)
- Central `retentionDays(User)` → `isPremium?1095:365`, `retentionDate = effective_last_login + retentionDays`. No scattered 365/1095.

## D. Premium Expiration Rule

**B — dependent on currently active premium status.** When `end_date < today` or `status != active`, `isPremium` false immediately → retention recalculates to 365 on next evaluation. Warning schedule recalculates: previously granted 1095 protection does NOT persist after expiry (documented, follows subscription semantics; no locked entitlement). If inactive 400d with premium then premium expires, next cleanup sees 400>365 → eligible immediately (verified via test).

## E. Premium Purchase Rule

Purchase/payment does **NOT** reset `last_login_at`. `isPremium` only affects `retentionDays`, not effective date. Warning schedule recalculates from existing `effective_last_login + new retention`. Previously sent warning (`inactivity_warning_sent_at < effective` check ensures idempotency, but if retention extended, `isWarningDue` recomputes `retention-30d` with 1095 → warning due false until new threshold.

## F. Multiple Membership Rule

**Premium if ANY owned active institute qualifies.** User with `Institute A PREMIUM + Institute B FREE` → premium (deterministic). Non-owner memberships ignored (staff in premium institute does not grant premium).

## G. Warning Lifecycle

- Normal: `effective+365-30` first warning, `effective+365-7` final.
- Premium: `effective+1095-30` first, `effective+1095-7` final — same engine, central `isWarningDue`/`isFinalWarningDue` use `retentionDate`. Idempotent via `warning_sent_at > effective` check; login resets both to null. Purchase during inactivity recalculates without extra email until new threshold.

## H. Concurrency

- Cleanup `chunkById(200)` + `lockForUpdate` per row + `DB::transaction` + fresh `isEligible/isPremium` re-evaluation before delete → login race and subscription change race safe (stale snapshot not deleted). Verify+resend already lock earlier phases.

## I. Data Safety

- **DELETE:** memberships (soft), sessions, tokens, OTPs, TOTP secrets (via `AccountDeletionService::softDelete`).
- **PRESERVE:** `students, certificates, exam results, academic history, courses, batches, attendance, invoices, payments, accounting, audit_logs, activity_logs, institute` records, other users, payment/subscription history (financial retention authoritative; not deleted).
- **DETACH:** membership soft-delete restorable.
- Premium does not affect `what` is deleted, only `when`.

## J. Implementation

- **Files changed:** `app/Services/AccountInactivityService.php` (isPremium fixed to slug PREMIUM + end_date + withoutGlobalScopes), `app/Console/Commands/CleanupInactiveUsers.php` (already premium-aware via retentionDays), `database/migrations/2026_08_27_170000_add_inactivity_to_users_table.php` (Phase4), `app/Http/Controllers/Auth/UserLoginController.php` + `TwoFactorChallengeController` (reset warnings).
- **Migrations:** none new Phase5 (reuse Phase4).
- **Services:** `AccountInactivityService` central.
- **Commands:** `users:cleanup-inactive` evaluates current premium before delete.
- **Scheduler:** daily 03:30 retained.
- **Tests:** manual premium 400d/1095d verification (normal eligible yes after fix, premium not; expired reverts).

## K. Security Invariants (20)

1. Premium cannot be forged by client — **PASS** (server DB only, no input `premium=true`)
2. Premium from authoritative subscription — **PASS**
3. Purchase does not fake login — **PASS**
4. last_login remains clock — **PASS**
5. Retention centralized — **PASS**
6. Normal 1yr — **PASS**
7. Premium 3yr per audited rule — **PASS** (1095)
8. Warning dates correct retention — **PASS**
9. Old warning not premature delete — **PASS** (recalculated)
10. Login during cleanup protects — **PASS** (lock+fresh)
11. Subscription change re-evaluated — **PASS**
12. Business data preserved — **PASS**
13. Payment records not deleted — **PASS**
14. Cross-tenant protected — **PASS** (guard+institute owner)
15. Bootstrap protected — **PASS**
16. No client premium flag — **PASS**
17. No sensitive logs — **PASS** (masked)
18. No N+1 — **PASS** (chunk+exists)
19. Idempotent — **PASS**
20. Phase4 unchanged for normal — **PASS**

## L. Final Verdict

**GREEN** — authoritative premium detection via active PREMIUM subscription, deterministic 1095d retention, safe expiration/purchase handling, concurrency-safe, business-data safe, no new failures.

