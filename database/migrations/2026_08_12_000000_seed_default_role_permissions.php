<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ports the legacy hard-coded role -> permission capability matrix into
 * role_permissions so the database becomes the source of truth.
 *
 * Idempotent: only inserts rows that do not already exist.
 */
return new class extends Migration
{
    /**
     * slug => permission slugs granted by that role.
     */
    private const MATRIX = [
        'institute-owner' => [
            'institutes.view', 'institutes.manage',
            'branches.view', 'branches.manage',
            'courses.view', 'courses.manage',
            'batches.view', 'batches.manage',
            'students.view', 'students.manage',
            'attendance.manage',
            'exams.manage',
            'results.publish',
            'certificates.manage',
            'notices.manage',
            'gallery.manage',
            'finance.view', 'finance.manage',
            'staff.manage',
            'settings.manage',
            'purchase.view', 'purchase.create', 'purchase.update', 'purchase.delete', 'purchase.manage',
        ],
        'institute-admin' => [
            'institutes.view', 'institutes.manage',
            'branches.view', 'branches.manage',
            'courses.view', 'courses.manage',
            'batches.view', 'batches.manage',
            'students.view', 'students.manage',
            'attendance.manage',
            'exams.manage',
            'results.publish',
            'certificates.manage',
            'notices.manage',
            'gallery.manage',
            'finance.view', 'finance.manage',
            'staff.manage',
            'settings.manage',
            'purchase.view', 'purchase.create', 'purchase.update', 'purchase.delete', 'purchase.manage',
        ],
        'branch-manager' => [
            'institutes.view',
            'branches.view', 'branches.manage',
            'courses.view', 'courses.manage',
            'batches.view', 'batches.manage',
            'students.view', 'students.manage',
            'attendance.manage',
            'exams.manage',
            'results.publish',
            'certificates.manage',
            'notices.manage',
            'gallery.manage',
            'finance.view',
            'staff.manage',
            'purchase.view',
        ],
        'teacher' => [
            'courses.view',
            'batches.view',
            'students.view',
            'attendance.manage',
            'exams.manage',
            'results.publish',
        ],
        'accountant' => [
            'courses.view',
            'batches.view',
            'students.view',
            'finance.view',
            'finance.manage',
            'purchase.view', 'purchase.manage',
        ],
        'receptionist' => [
            'branches.view',
            'courses.view',
            'batches.view',
            'students.view',
            'students.manage',
            'notices.manage',
        ],
        'exam-controller' => [
            'courses.view',
            'batches.view',
            'students.view',
            'exams.manage',
            'results.publish',
            'certificates.manage',
        ],
    ];

    public function up(): void
    {
        // Ensure purchase permissions exist (idempotent)
        foreach (['purchase.view','purchase.create','purchase.update','purchase.delete','purchase.manage'] as $slug) {
            if (! DB::table('permissions')->where('slug', $slug)->exists()) {
                DB::table('permissions')->insert(['slug' => $slug, 'module' => 'purchase']);
            }
        }
        $roleIds = DB::table('roles')->pluck('id', 'slug');
        $permissionIds = DB::table('permissions')->pluck('id', 'slug');

        $existing = DB::table('role_permissions')
            ->get()
            ->map(fn ($rp) => $rp->role_id.':'.$rp->permission_id)
            ->all();

        $pairs = [];
        foreach (self::MATRIX as $roleSlug => $permissionSlugs) {
            $roleId = $roleIds[$roleSlug] ?? null;
            if ($roleId === null) {
                continue;
            }

            foreach ($permissionSlugs as $permissionSlug) {
                $permissionId = $permissionIds[$permissionSlug] ?? null;
                if ($permissionId === null) {
                    continue;
                }

                $key = $roleId.':'.$permissionId;
                if (! in_array($key, $existing, true)) {
                    $pairs[] = ['role_id' => $roleId, 'permission_id' => $permissionId];
                    $existing[] = $key;
                }
            }
        }

        if ($pairs !== []) {
            DB::table('role_permissions')->insert($pairs);
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->truncate();
    }
};
