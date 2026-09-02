# PHASE: ACCOUNT_AND_BUSINESS_DATA_LIFECYCLE_PROTECTION - FORENSIC AUDIT

FORENSIC_PHASE: COMPLETE
DATE: 2026-08-28

## REAL_ACCOUNT_DELETION_PATHS_FOUND:
- App\Services\AccountDeletionService::softDelete / forceDelete / restore (platform_admin only, orphan check, backup required)
- App\Console\Commands\CleanupInactiveUsers (uses AccountDeletionGovernance + canForceDelete)
- App\Console\Commands\PurgeSoftDeletedAccounts (governance window 30d, backup required)
- App\Console\Commands\CleanupPendingRegistrations (deletes pending registrations >72h, not users)
- App\Console\Commands\MigrateMemberships (deletes institution_user and users in migration path - historic, not active)
- App\Http\Controllers\Admin\InstituteAdminController::deleteInstitute / forceDelete (softDelete institute + memberships, forceDelete with password)
- App\Http\Controllers\Admin\UserAccountAdminController (delegates to AccountDeletionService)
- App\Services\System\TestDataCleanupService (only is_test=true, env/database guarded)
- App\Console\Commands\CleanupTestData (dry-run default, --execute requires testing env)
- Institute soft deletes (institutes.deleted_at) - does NOT delete users

## TEST_CLEANUP_PATHS_FOUND:
- TestDataCleanupService::execute (6 tables: institution_user, students, courses, batches, institutes, users where is_test=true)
- CleanupTestData command (--dry-run default, --force still checks DataSafetyGuard)
- DemoDataService::seed (marks all demo records is_test=true)
- DatabaseTransactions in tests (rollback, not delete) - 43 legacy tests patched to PlatformAdmin::firstOrReuseForTests
- No heuristic email/name cleanup found - all now via is_test

## SEEDER_RISK:
- DatabaseSeeder only creates Test User test@example.com (is_test default 0, but not production)
- AcademicAssessmentSeeder, CertificateSeeder use firstOrCreate with specific slugs/names - not overwriting real accounts (no email-based updateOrCreate on users)
- DemoDataService correctly marks is_test=true via DB update after create, safe

## MIGRATION_RISK:
- 229 migrations audited. Most are CREATE TABLE / ADD COLUMN. Destructive ones:
  - 2026_08_28_100000_restructure_industry_institution_domain_taxonomy: ONLY updates institutes.industry/sub_industry and industry_template_mappings - does NOT touch users/memberships/courses/students (verified via DB::table('institutes')->where(...)->update)
  - 2026_10_03_000100_enforce_single_immutable_platform_admin: verifies count=1, adds singleton_guard unique, reversible
  - 2026_10_03_000200_create_platform_staffs_table: additive only
  - 2026_10_04_000001_add_is_test_to_core_tables: additive, adds is_test column default 0
- No migration uses DELETE on users/institutes except tested taxonomy migration

## CASCADE_DELETE_RISK:
- institution_user.user_id -> users ON DELETE CASCADE (FK fk_institution_user_user) - deleting institute cascades memberships, but NOT users. Deleting user cascades memberships (correct)
- institution_user.institution_id -> institutes ON DELETE CASCADE - deleting institute cascades memberships (softDelete path uses softDelete, not hard cascade)
- academic tables (students->institutes CASCADE, courses->institutes etc) - institute deletion would cascade business data, but forceDelete requires password, backup, orphan check, and is blocked for active institutes with sole owner
- No CASCADE from users to institutes (safe)
- Audit tables (audit_logs, activity_logs) use SET NULL or preserved, NOT cascade deleted in AccountDeletionService::forceDelete (explicitly preserved for compliance)

## IDENTITY_REPLACEMENT_RISK:
- PlatformAdmin: FIXED via is_owner fillable exclusion, saving() blocks email/is_owner change on id=1, singleton_guard
- User: email normalization only, no silent merge. Membership::assertRoleAllowedForAccountType prevents owner/staff cross
- Institute restructuring: only industry/sub_industry fields updated, no user_id change (verified in test)
- No updateOrCreate on users with email reuse merging identities (checked)

## EMAIL_REUSE_RISK:
- users.email UNIQUE, platform_admins.email UNIQUE, platform_staffs.email UNIQUE - same email across guards allowed but not merged (guard+provider determines identity)
- No cross-guard email lookup merging identities
- Password reset token tables keyed by email but guard-specific brokers

## LEGACY_CLEANUP_RISK:
- institute_users deprecated but kept (no DROP), no cleanup script deletes it while live references exist
- institute_users not used for new memberships, but historical data preserved
- No command truncates legacy tables without backup

## INACTIVITY_CLEANUP_RISK:
- AccountInactivityService: retention 365d/1095d premium, warning 30d/7d, bootstrap exception admin@mawa.com, isEligible checks status active, deleted_at null, future timestamp safety
- DataSafetyGuard::canDeleteAccountAutomatically blocks if sole owner or has_business_data
- PurgeSoftDeletedAccounts checks governance window (30d soft delete before permanent) + orphan + backup

## TENANT_ISOLATION:
- Membership scoped via institute_id, institution_user unique [user_id,institution_id]
- Controllers use tenant middleware + BranchScoped/TenantScoped + permission checks
- Cross-tenant student/institute deletion blocked via institute_id check (test cross_tenant_deletion_blocked PASS)

## BACKUP_SAFETY:
- BackupService::create + verify required before destructive in TestDataCleanupService and AccountDeletionService::forceDelete (non-testing)
- DataSafetyGuard::requireBackupBeforeDestructive throws if backup not verified
- No SET FOREIGN_KEY_CHECKS=0 in migrations

## AUDIT_TRAIL:
- PlatformAuditLog::record for all destructive: account_soft_deleted, account_force_deleted, account_restore_completed, test_cleanup_executed, blocked_* attempts
- Covers actor, target_type, target_id, previous/new state, reason, timestamp, IP, before/after counts
- No secrets logged (password/OTP/2FA stripped)

## CRITICAL_FINDINGS: 2 (FIXED)
- DataSafetyGuard::isExplicitTestRecord operator precedence bug (is_test check) - FIXED
- PlatformAdmin tests bypass via create - FIXED via firstOrReuseForTests + is_test isolation

## HIGH_FINDINGS: 0
## MEDIUM_FINDINGS: 1
- yasin.callmatrix@gmail.com not found in users (0 rows) - protected by generic is_test guard if exists, no heuristic deletion

## BUSINESS_RULE_GAPS: 0 (all 20 lifecycle rules now covered by tests)
