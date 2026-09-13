<?php

namespace App\Services;

use App\Models\Institute;
use App\Models\InstituteModuleOverride;
use App\Models\ModuleRegistry;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Activates the training_center module for professional/training-center tenants.
 *
 * Called automatically when an institute is created or its industry changes
 * to 'training_center'. Ensures:
 *   1. training_center module exists in module_registry
 *   2. training_center permissions are seeded (idempotent)
 *   3. Permissions are assigned to the institute's admin/owner role
 *
 * Module access is controlled via InstituteModuleOverride, not via
 * package_modules (which is shared across all tenants on the same package).
 */
class TrainingCenterModuleActivator
{
    /**
     * Activate training_center module for a training_center institute.
     * No-op for non-training_center industries.
     */
    public function activateForTrainingCenter(Institute $institute): void
    {
        if (($institute->industry ?? '') !== 'training_center') {
            return;
        }

        $this->ensureModuleRegistered();

        InstituteModuleOverride::updateOrCreate(
            ['institute_id' => $institute->id, 'module_key' => 'training_center'],
            ['enabled' => true]
        );

        $this->seedPermissions();
        $this->assignToAdminRole($institute);

        try {
            app(ModuleAccessService::class)->flushCache($institute->id);
        } catch (\Throwable $e) {
            Log::warning('TrainingCenterModuleActivator: cache flush failed', [
                'institute_id' => $institute->id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('Training center module activated for tenant', [
            'tenant_id' => $institute->id,
            'identifier' => $institute->identifier,
        ]);
    }

    /**
     * Ensure the training_center module exists in module_registry.
     */
    public function ensureModuleRegistered(): void
    {
        ModuleRegistry::updateOrCreate(
            ['key' => 'training_center'],
            [
                'name' => 'Training Center',
                'type' => 'industry',
                'description' => 'Training center management: courses, batches, enrollments, attendance, exams, results, certificates, and fees',
                'status' => 'active',
                'sort_order' => 40,
            ]
        );
    }

    /**
     * Seed all training_center permissions (idempotent — uses firstOrCreate).
     */
    private function seedPermissions(): void
    {
        $trainingPermissions = [
            'training_batches' => [
                'view' => 'View Batches', 'create' => 'Create Batches',
                'edit' => 'Edit Batches', 'delete' => 'Delete Batches',
            ],
            'training_enrollments' => [
                'view' => 'View Enrollments', 'create' => 'Enroll Trainees',
                'edit' => 'Edit Enrollments', 'delete' => 'Cancel Enrollments',
            ],
            'training_attendance' => [
                'view' => 'View Attendance', 'manage' => 'Manage Attendance',
            ],
            'training_exams' => [
                'view' => 'View Exams', 'manage' => 'Manage Exams',
            ],
            'training_results' => [
                'view' => 'View Results', 'manage' => 'Manage Results',
                'publish' => 'Publish Results',
            ],
            'training_certificates' => [
                'view' => 'View Certificates', 'manage' => 'Manage Certificates',
            ],
            'training_fees' => [
                'view' => 'View Fees', 'manage' => 'Manage Fees',
            ],
            'training_reports' => [
                'view' => 'View Reports',
            ],
            'training_settings' => [
                'manage' => 'Manage Training Settings',
            ],
        ];

        foreach ($trainingPermissions as $module => $actions) {
            foreach ($actions as $action => $label) {
                Permission::firstOrCreate(
                    ['slug' => $module.'.'.$action],
                    ['module' => $module, 'name' => $label]
                );
            }
        }
    }

    /**
     * Assign all training_center permissions to the institute's admin/owner role.
     */
    private function assignToAdminRole(Institute $institute): void
    {
        $adminRole = Role::where('institute_id', $institute->id)
            ->whereIn('slug', ['institute-owner', 'admin'])
            ->first();

        if (! $adminRole) {
            Log::warning('TrainingCenterModuleActivator: no admin role found for institute', [
                'institute_id' => $institute->id,
            ]);
            return;
        }

        $trainingPermissions = Permission::where('module', 'like', 'training_%')->pluck('id')->toArray();

        if (! empty($trainingPermissions)) {
            $existing = DB::table('role_permissions')
                ->where('role_id', $adminRole->id)
                ->whereIn('permission_id', $trainingPermissions)
                ->pluck('permission_id')
                ->toArray();

            $toAttach = array_diff($trainingPermissions, $existing);

            foreach ($toAttach as $permId) {
                DB::table('role_permissions')->insert([
                    'role_id' => $adminRole->id,
                    'permission_id' => $permId,
                ]);
            }
        }
    }
}
