<?php

namespace App\Services;

use App\Models\Institute;
use App\Models\InstituteModuleOverride;
use App\Models\ModuleRegistry;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EducationModuleActivator
{
    public function activateForEducation(Institute $institute): void
    {
        if (($institute->industry ?? '') !== 'education') {
            return;
        }

        $this->ensureModulesRegistered();

        InstituteModuleOverride::updateOrCreate(
            ['institute_id' => $institute->id, 'module_key' => 'education'],
            ['enabled' => true]
        );

        $this->seedPermissions();
        $this->assignToAdminRole($institute);

        try {
            app(ModuleAccessService::class)->flushCache($institute->id);
        } catch (\Throwable $e) {
            Log::warning('EducationModuleActivator: cache flush failed', [
                'institute_id' => $institute->id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('Education module activated for tenant', [
            'tenant_id' => $institute->id,
            'identifier' => $institute->identifier,
        ]);
    }

    private function ensureModulesRegistered(): void
    {
        ModuleRegistry::updateOrCreate(
            ['key' => 'education'],
            [
                'name' => 'Education',
                'type' => 'industry',
                'description' => 'Education management (students, exams, results, certificates)',
                'status' => 'active',
                'sort_order' => 20,
            ]
        );

        $subModules = [
            ['key' => 'education.students',   'name' => 'Students',   'icon' => 'bi-people',           'sort' => 21],
            ['key' => 'education.classes',    'name' => 'Classes',    'icon' => 'bi-journal-bookmark', 'sort' => 22],
            ['key' => 'education.exams',      'name' => 'Exams',      'icon' => 'bi-pencil-square',    'sort' => 23],
            ['key' => 'education.attendance', 'name' => 'Attendance', 'icon' => 'bi-calendar-check',   'sort' => 24],
            ['key' => 'education.fees',       'name' => 'Fees',       'icon' => 'bi-cash-coin',        'sort' => 25],
            ['key' => 'education.guardians',  'name' => 'Guardians',  'icon' => 'bi-person-badge',     'sort' => 26],
            ['key' => 'education.analytics',  'name' => 'Analytics',  'icon' => 'bi-graph-up',         'sort' => 27],
        ];

        foreach ($subModules as $sub) {
            ModuleRegistry::updateOrCreate(
                ['key' => $sub['key']],
                [
                    'name'       => $sub['name'],
                    'parent_key' => 'education',
                    'icon'       => $sub['icon'],
                    'sort_order' => $sub['sort'],
                    'type'       => 'industry',
                    'status'     => 'active',
                ]
            );
        }
    }

    private function seedPermissions(): void
    {
        $permissions = [
            'education_students' => [
                'view'   => 'View Students',
                'create' => 'Create Students',
                'edit'   => 'Edit Students',
                'delete' => 'Delete Students',
                'enroll' => 'Enroll Students',
            ],
            'education_classes' => [
                'view'   => 'View Classes',
                'manage' => 'Manage Classes',
            ],
            'education_exams' => [
                'view'   => 'View Exams',
                'manage' => 'Manage Exams',
            ],
            'education_attendance' => [
                'view'   => 'View Attendance',
                'manage' => 'Manage Attendance',
            ],
            'education_fees' => [
                'view'   => 'View Fees',
                'manage' => 'Manage Fees',
            ],
            'education_guardians' => [
                'view'   => 'View Guardians',
                'manage' => 'Manage Guardians',
            ],
            'education_analytics' => [
                'view' => 'View Analytics',
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
    }

    private function assignToAdminRole(Institute $institute): void
    {
        $adminRole = Role::where('institute_id', $institute->id)
            ->whereIn('slug', ['institute-owner', 'admin'])
            ->first();

        if (! $adminRole) {
            Log::warning('EducationModuleActivator: no admin role found for institute', [
                'institute_id' => $institute->id,
            ]);
            return;
        }

        $eduPermissions = Permission::where('module', 'like', 'education_%')->pluck('id')->toArray();

        if (! empty($eduPermissions)) {
            $existing = DB::table('role_permissions')
                ->where('role_id', $adminRole->id)
                ->whereIn('permission_id', $eduPermissions)
                ->pluck('permission_id')
                ->toArray();

            $toAttach = array_diff($eduPermissions, $existing);

            foreach ($toAttach as $permId) {
                DB::table('role_permissions')->insert([
                    'role_id' => $adminRole->id,
                    'permission_id' => $permId,
                ]);
            }
        }
    }
}
