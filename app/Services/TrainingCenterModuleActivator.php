<?php

namespace App\Services;

use App\Models\Institute;
use App\Models\InstituteModuleOverride;
use App\Models\ModuleRegistry;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TrainingCenterModuleActivator
{
    public function activateForTrainingCenter(Institute $institute): void
    {
        if (($institute->industry ?? '') !== 'training_center') {
            return;
        }

        $this->ensureModulesRegistered();

        InstituteModuleOverride::updateOrCreate(
            ['institute_id' => $institute->id, 'module_key' => 'training_center'],
            ['enabled' => true]
        );

        $this->enableSubModules($institute);
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

    private function ensureModulesRegistered(): void
    {
        ModuleRegistry::updateOrCreate(
            ['key' => 'training_center'],
            [
                'name' => 'Training Center',
                'type' => 'industry',
                'description' => 'Training center management: courses, batches, enrollments, attendance, exams, results, certificates, and fees',
                'status' => 'active',
                'sort_order' => 22,
            ]
        );

        $subModules = [
            ['key' => 'training_center.courses',      'name' => 'Courses',      'icon' => 'bi-book',           'sort' => 23, 'route' => 'courses.manage.index'],
            ['key' => 'training_center.batches',      'name' => 'Batches',      'icon' => 'bi-calendar-week',  'sort' => 24, 'route' => 'batches.index'],
            ['key' => 'training_center.students',     'name' => 'Students',     'icon' => 'bi-person-badge',   'sort' => 25, 'route' => 'training.students.index'],
            ['key' => 'training_center.classes',      'name' => 'Classes',      'icon' => 'bi-collection',     'sort' => 26, 'route' => 'training.classes.index'],
            ['key' => 'training_center.attendance',   'name' => 'Attendance',   'icon' => 'bi-calendar-check', 'sort' => 27, 'route' => 'training.attendance.index'],
            ['key' => 'training_center.exams',        'name' => 'Exams',        'icon' => 'bi-pencil-square',  'sort' => 28, 'route' => 'training.exams.index'],
            ['key' => 'training_center.certificates', 'name' => 'Certificates', 'icon' => 'bi-award',          'sort' => 29, 'route' => 'training.certificates.index'],
            ['key' => 'training_center.fees',         'name' => 'Fees',         'icon' => 'bi-cash-coin',      'sort' => 30, 'route' => 'training.fees.index'],
            ['key' => 'training_center.reports',      'name' => 'Reports',      'icon' => 'bi-graph-up',       'sort' => 31, 'route' => 'training.reports.index'],
        ];

        foreach ($subModules as $sub) {
            ModuleRegistry::updateOrCreate(
                ['key' => $sub['key']],
                [
                    'name'       => $sub['name'],
                    'parent_key' => 'training_center',
                    'icon'       => $sub['icon'],
                    'sort_order' => $sub['sort'],
                    'type'       => 'industry',
                    'status'     => 'active',
                    'index_route' => $sub['route'],
                ]
            );
        }
    }

    private function enableSubModules(Institute $institute): void
    {
        $subKeys = [
            'training_center.courses', 'training_center.batches', 'training_center.students',
            'training_center.classes', 'training_center.attendance', 'training_center.exams',
            'training_center.certificates', 'training_center.fees', 'training_center.reports',
        ];

        foreach ($subKeys as $key) {
            InstituteModuleOverride::updateOrCreate(
                ['institute_id' => $institute->id, 'module_key' => $key],
                ['enabled' => true]
            );
        }
    }

    private function seedPermissions(): void
    {
        $permissions = [
            'training_students' => [
                'view' => 'View Students', 'create' => 'Create Students',
                'edit' => 'Edit Students', 'delete' => 'Delete Students',
                'manage' => 'Manage Students',
            ],
            'training_classes' => [
                'view' => 'View Classes', 'create' => 'Create Classes',
                'edit' => 'Edit Classes', 'delete' => 'Delete Classes',
                'manage' => 'Manage Classes',
            ],
            'training_batches' => [
                'view' => 'View Batches', 'create' => 'Create Batches',
                'edit' => 'Edit Batches', 'delete' => 'Delete Batches',
                'manage' => 'Manage Batches',
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

        foreach ($permissions as $module => $actions) {
            foreach ($actions as $action => $label) {
                Permission::firstOrCreate(
                    ['slug' => $module.'.'.$action],
                    ['module' => $module, 'name' => $label]
                );
            }
        }

        $flatSlugs = [
            'training.view' => 'View Training',
            'training.manage' => 'Manage Training',
            'students.view' => 'View Students',
            'students.manage' => 'Manage Students',
            'classes.view' => 'View Classes',
            'classes.manage' => 'Manage Classes',
            'trainees.view' => 'View Trainees',
            'trainees.manage' => 'Manage Trainees',
            'enrollments.view' => 'View Enrollments',
            'enrollments.manage' => 'Manage Enrollments',
            'marks.view' => 'View Marks',
            'marks.manage' => 'Manage Marks',
            'results.view' => 'View Results',
            'results.publish' => 'Publish Results',
        ];

        foreach ($flatSlugs as $slug => $label) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                ['module' => 'training', 'name' => $label, 'created_at' => now()]
            );
        }
    }

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

        $trainingPermissions = Permission::where('module', 'like', 'training_%')
            ->orWhere('module', 'training')
            ->pluck('id')->toArray();

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
