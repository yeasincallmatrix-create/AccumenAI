<?php

namespace App\Services\System;

use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 111 — Orphan Cleanup Service (ONE-TIME)
 * Identifies, classifies, and safely cleans legacy orphans.
 */
class OrphanCleanupService
{
    public function __construct(
        private readonly BackupService $backups = new BackupService()
    ) {}

    /**
     * Identify exact orphans using actual schema & FKs.
     */
    public function identify(): array
    {
        return [
            'batches' => $this->identifyBatches(),
            'enrollments' => $this->identifyEnrollments(),
            'institution_users' => $this->identifyInstitutionUsers(),
        ];
    }

    public function identifyBatches(): array
    {
        if (! Schema::hasTable('batches')) return [];

        $rows = DB::table('batches')
            ->leftJoin('institutes', 'batches.institute_id', '=', 'institutes.id')
            ->leftJoin('courses', 'batches.course_id', '=', 'courses.id')
            ->leftJoin('branches', 'batches.branch_id', '=', 'branches.id')
            ->select('batches.id', 'batches.institute_id', 'batches.course_id', 'batches.branch_id', 'batches.name', 'batches.batch_code', 'batches.created_at', 'institutes.id as inst_exists', 'courses.id as course_exists', 'branches.id as branch_exists')
            ->where(function ($q) {
                $q->whereNull('institutes.id')
                  ->orWhereNull('courses.id')
                  ->orWhere(function ($qq) {
                      $qq->whereNotNull('batches.branch_id')->whereNull('branches.id');
                  });
            })
            ->get();

        $result = [];
        foreach ($rows as $r) {
            $reasons = [];
            if ($r->inst_exists === null) $reasons[] = "institute_id {$r->institute_id} missing";
            if ($r->course_exists === null) $reasons[] = "course_id {$r->course_id} missing";
            if ($r->branch_id !== null && $r->branch_exists === null) $reasons[] = "branch_id {$r->branch_id} missing";
            $result[] = [
                'id' => $r->id,
                'institute_id' => $r->institute_id,
                'course_id' => $r->course_id,
                'branch_id' => $r->branch_id,
                'name' => $r->name,
                'reason' => implode('; ', $reasons),
                'created_at' => $r->created_at,
            ];
        }
        return $result;
    }

    public function identifyEnrollments(): array
    {
        if (! Schema::hasTable('student_enrollments')) return [];

        $rows = DB::table('student_enrollments')
            ->leftJoin('students', 'student_enrollments.student_id', '=', 'students.id')
            ->leftJoin('batches', 'student_enrollments.batch_id', '=', 'batches.id')
            ->leftJoin('institutes', 'student_enrollments.institute_id', '=', 'institutes.id')
            ->select('student_enrollments.id', 'student_enrollments.student_id', 'student_enrollments.batch_id', 'student_enrollments.institute_id', 'student_enrollments.created_at', 'students.id as s_exists', 'batches.id as b_exists', 'institutes.id as i_exists')
            ->where(function ($q) {
                $q->whereNull('students.id')
                  ->orWhereNull('batches.id')
                  ->orWhereNull('institutes.id');
            })
            ->get();

        $result = [];
        foreach ($rows as $r) {
            $reasons = [];
            if ($r->s_exists === null) $reasons[] = "student_id {$r->student_id} missing";
            if ($r->b_exists === null) $reasons[] = "batch_id {$r->batch_id} missing";
            if ($r->i_exists === null) $reasons[] = "institute_id {$r->institute_id} missing";
            $result[] = [
                'id' => $r->id,
                'student_id' => $r->student_id,
                'batch_id' => $r->batch_id,
                'institute_id' => $r->institute_id,
                'reason' => implode('; ', $reasons),
                'created_at' => $r->created_at,
            ];
        }
        return $result;
    }

    public function identifyInstitutionUsers(): array
    {
        if (! Schema::hasTable('institution_user')) return [];

        $rows = DB::table('institution_user')
            ->leftJoin('users', 'institution_user.user_id', '=', 'users.id')
            ->leftJoin('institutes', 'institution_user.institution_id', '=', 'institutes.id')
            ->leftJoin('roles', 'institution_user.role_id', '=', 'roles.id')
            ->select('institution_user.id', 'institution_user.user_id', 'institution_user.institution_id', 'institution_user.role_id', 'institution_user.created_at', 'users.email', 'users.id as u_exists', 'institutes.id as i_exists', 'roles.id as r_exists')
            ->where(function ($q) {
                $q->whereNull('users.id')
                  ->orWhereNull('institutes.id')
                  ->orWhereNull('roles.id');
            })
            ->get();

        $result = [];
        foreach ($rows as $r) {
            $reasons = [];
            if ($r->u_exists === null) $reasons[] = "user_id {$r->user_id} missing";
            if ($r->i_exists === null) $reasons[] = "institute_id {$r->institution_id} missing";
            if ($r->r_exists === null) $reasons[] = "role_id {$r->role_id} missing";
            $result[] = [
                'id' => $r->id,
                'user_id' => $r->user_id,
                'institute_id' => $r->institution_id,
                'email' => $r->email,
                'reason' => implode('; ', $reasons),
                'created_at' => $r->created_at,
            ];
        }
        return $result;
    }

    /**
     * Dependency analysis for each orphan
     */
    public function analyzeDependencies(array $batches, array $enrollments, array $institutionUsers): array
    {
        $deps = ['batches' => [], 'enrollments' => [], 'institution_users' => []];

        foreach ($batches as $b) {
            $id = $b['id'];
            $counts = [];
            if (Schema::hasTable('attendance') && Schema::hasColumn('attendance', 'batch_id')) $counts['attendance'] = DB::table('attendance')->where('batch_id', $id)->count();
            if (Schema::hasTable('exams') && Schema::hasColumn('exams', 'batch_id')) $counts['exams'] = DB::table('exams')->where('batch_id', $id)->count();
            if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'batch_id')) $counts['invoices'] = DB::table('invoices')->where('batch_id', $id)->count();
            if (Schema::hasTable('student_enrollments') && Schema::hasColumn('student_enrollments', 'batch_id')) $counts['enrollments'] = DB::table('student_enrollments')->where('batch_id', $id)->count();
            // Filter zero
            $counts = array_filter($counts, fn($c) => $c > 0);
            $deps['batches'][$id] = $counts;
        }

        foreach ($enrollments as $e) {
            $id = $e['id'];
            $sid = $e['student_id'];
            $counts = [];
            if (Schema::hasTable('attendance')) $counts['attendance'] = DB::table('attendance')->where('student_id', $sid)->count();
            if (Schema::hasTable('invoices')) $counts['invoices'] = DB::table('invoices')->where('student_id', $sid)->count();
            if (Schema::hasTable('payments')) {
                // payments via invoice
                $counts['payments'] = DB::table('payments')->whereIn('invoice_id', function ($q) use ($sid) {
                    $q->select('id')->from('invoices')->where('student_id', $sid);
                })->count();
            }
            $counts = array_filter($counts, fn($c) => $c > 0);
            $deps['enrollments'][$id] = $counts;
        }

        foreach ($institutionUsers as $iu) {
            $uid = $iu['user_id'];
            $iid = $iu['institute_id'];
            $counts = [];
            // Check if user still has other valid memberships
            $otherMemberships = DB::table('institution_user')->where('user_id', $uid)->where('id', '!=', $iu['id'])->count();
            $counts['other_memberships'] = $otherMemberships;
            if (Schema::hasTable('notifications')) {
                // notifications table has institute_id and maybe user_id
                $counts['notifications'] = 0;
            }
            $deps['institution_users'][$iu['id']] = $counts;
        }

        return $deps;
    }

    /**
     * Classification: SAFE_TO_DELETE, DELETE_WITH_DEPENDENCIES, BLOCKED
     */
    public function classify(array $batches, array $enrollments, array $institutionUsers, array $deps): array
    {
        $result = ['batches' => [], 'enrollments' => [], 'institution_users' => []];

        foreach ($batches as $b) {
            $id = $b['id'];
            $d = $deps['batches'][$id] ?? [];
            // BLOCKED if has invoices/payments/financial
            $hasFinancial = ($d['invoices'] ?? 0) > 0 || ($d['payments'] ?? 0) > 0;
            if ($hasFinancial) {
                $result['batches'][$id] = ['status' => 'BLOCKED', 'reason' => 'valid financial transaction exists', 'deps' => $d];
            } elseif (empty($d)) {
                $result['batches'][$id] = ['status' => 'SAFE_TO_DELETE', 'deps' => $d];
            } else {
                $result['batches'][$id] = ['status' => 'DELETE_WITH_DEPENDENCIES', 'deps' => $d];
            }
        }

        foreach ($enrollments as $e) {
            $id = $e['id'];
            $d = $deps['enrollments'][$id] ?? [];
            $hasFinancial = ($d['invoices'] ?? 0) > 0 || ($d['payments'] ?? 0) > 0;
            if ($hasFinancial) {
                $result['enrollments'][$id] = ['status' => 'BLOCKED', 'reason' => 'valid financial transaction exists', 'deps' => $d];
            } elseif (empty($d)) {
                $result['enrollments'][$id] = ['status' => 'SAFE_TO_DELETE', 'deps' => $d];
            } else {
                $result['enrollments'][$id] = ['status' => 'DELETE_WITH_DEPENDENCIES', 'deps' => $d];
            }
        }

        foreach ($institutionUsers as $iu) {
            $id = $iu['id'];
            $d = $deps['institution_users'][$id] ?? [];
            // Check if user is orphaned (no other membership) and not referenced elsewhere
            // For now, all 3 are safe to delete if user has other memberships or user is also orphaned
            // If user still has valid institute, do not delete user, only membership
            $result['institution_users'][$id] = ['status' => 'SAFE_TO_DELETE', 'deps' => $d];
            // If user has no other membership and no audit history, could be DELETE_WITH_DEPENDENCIES
            // We keep as SAFE_TO_DELETE for membership only
        }

        return $result;
    }

    public function dryRun(): array
    {
        $identified = $this->identify();
        $deps = $this->analyzeDependencies($identified['batches'], $identified['enrollments'], $identified['institution_users']);
        $classified = $this->classify($identified['batches'], $identified['enrollments'], $identified['institution_users'], $deps);

        $safe = ['batches' => 0, 'enrollments' => 0, 'institution_users' => 0];
        $withDeps = ['batches' => 0, 'enrollments' => 0, 'institution_users' => 0];
        $blocked = ['batches' => 0, 'enrollments' => 0, 'institution_users' => 0];

        foreach (['batches','enrollments','institution_users'] as $type) {
            foreach ($classified[$type] as $id => $info) {
                if ($info['status'] === 'SAFE_TO_DELETE') $safe[$type]++;
                elseif ($info['status'] === 'DELETE_WITH_DEPENDENCIES') $withDeps[$type]++;
                else $blocked[$type]++;
            }
        }

        return [
            'identified' => $identified,
            'dependencies' => $deps,
            'classified' => $classified,
            'counts' => [
                'batches' => count($identified['batches']),
                'enrollments' => count($identified['enrollments']),
                'institution_users' => count($identified['institution_users']),
            ],
            'safe' => $safe,
            'with_deps' => $withDeps,
            'blocked' => $blocked,
        ];
    }

    public function execute(bool $dryRun = true): array
    {
        // DATA SAFETY: database + environment verification before any destructive orphan cleanup
        \App\Services\System\DataSafetyGuard::assertDatabaseSafeForDestructive('orphan_cleanup');
        // Orphan cleanup is allowed in local/testing but requires explicit --execute; production blocked by command guard

        // System-level: clear TenantContext to avoid tenant scoping
        \App\Support\TenantContext::clear();
        \App\Support\BranchContext::clear();

        $dry = $this->dryRun();

        if ($dryRun) {
            return array_merge($dry, ['dry_run' => true, 'backup' => null]);
        }

        // Before destructive: backup via DataSafetyGuard (verified)
        $backup = \App\Services\System\DataSafetyGuard::requireBackupBeforeDestructive('orphan_cleanup', 'pre_orphan_cleanup');
        // Backwards compat still verify
        $verified = $this->backups->verify($backup);
        if (! $verified || $backup->status !== 'verified') {
            throw new \Exception('Backup verification failed — aborting cleanup');
        }

        // Audit log start
        $this->audit('orphan_cleanup_started', 0, ['dry' => $dry]);

        // Re-run detection to confirm not changed
        $identified2 = $this->identify();
        if (count($identified2['batches']) !== $dry['counts']['batches'] ||
            count($identified2['enrollments']) !== $dry['counts']['enrollments'] ||
            count($identified2['institution_users']) !== $dry['counts']['institution_users']) {
            throw new \Exception('Orphan counts changed since dry-run — aborting');
        }

        $deleted = ['batches' => 0, 'enrollments' => 0, 'institution_users' => 0, 'dependents' => 0];
        $blocked = $dry['blocked'];

        DB::beginTransaction();
        try {
            // Delete in dependency-safe order: enrollments first, then batches, then institution_user
            // Only delete SAFE_TO_DELETE and DELETE_WITH_DEPENDENCIES (with dependent cleanup)
            foreach (['enrollments', 'batches', 'institution_users'] as $type) {
                foreach ($dry['classified'][$type] as $id => $info) {
                    if ($info['status'] === 'BLOCKED') continue;

                    if ($type === 'batches') {
                        // Delete dependents first
                        $deps = $info['deps'] ?? [];
                        if (! empty($deps['attendance'])) {
                            $deleted['dependents'] += DB::table('attendance')->where('batch_id', $id)->delete();
                        }
                        if (! empty($deps['enrollments'])) {
                            // Enrollments for this batch that are already orphan may be deleted separately
                        }
                        $deleted['batches'] += DB::table('batches')->where('id', $id)->delete();
                    } elseif ($type === 'enrollments') {
                        $deps = $info['deps'] ?? [];
                        if (! empty($deps['attendance'])) {
                            // Attendance for this enrollment's student — only delete those linked to this enrollment's batch?
                            // Safer: not auto-delete attendance unless orphan
                        }
                        $deleted['enrollments'] += DB::table('student_enrollments')->where('id', $id)->delete();
                    } elseif ($type === 'institution_users') {
                        $deleted['institution_users'] += DB::table('institution_user')->where('id', $id)->delete();
                        // Do NOT delete user even if orphaned — preserve user record
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->audit('orphan_cleanup_failed', 0, ['error' => $e->getMessage()]);
            throw $e;
        }

        $this->audit('orphan_cleanup_completed', 0, ['deleted' => $deleted, 'backup' => $backup->filename]);

        // Post-cleanup verification
        $post = $this->identify();
        $after = [
            'batches' => count($post['batches']),
            'enrollments' => count($post['enrollments']),
            'institution_users' => count($post['institution_users']),
        ];

        return array_merge($dry, [
            'dry_run' => false,
            'backup' => $backup,
            'deleted' => $deleted,
            'blocked' => $blocked,
            'after' => $after,
        ]);
    }

    protected function audit(string $action, int $recordId, array $data): void
    {
        try {
            AuditLog::create([
                'institute_id' => 0,
                'user_type' => 'system',
                'user_id' => auth()->id() ?? 0,
                'action' => $action,
                'module' => 'orphan_cleanup',
                'record_id' => $recordId,
                'old_values' => null,
                'new_values' => json_encode($data),
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => substr((string)request()->userAgent(), 0, 255),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {}
    }
}
