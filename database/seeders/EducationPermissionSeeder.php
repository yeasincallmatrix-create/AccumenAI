<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class EducationPermissionSeeder extends Seeder
{
    public function run(): void
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

        // Backward-compatible flat slugs — existing routes reference these
        $flatSlugs = [
            'students.view'       => 'View Students',
            'students.manage'     => 'Manage Students',
            'courses.view'        => 'View Courses',
            'courses.manage'      => 'Manage Courses',
            'batches.view'        => 'View Batches',
            'batches.manage'      => 'Manage Batches',
            'attendance.view'     => 'View Attendance',
            'attendance.manage'   => 'Manage Attendance',
            'exams.view'          => 'View Exams',
            'exams.manage'        => 'Manage Exams',
            'certificates.view'   => 'View Certificates',
            'education.manage'    => 'Manage Education',
            'curriculum.view'     => 'View Curriculum',
            'curriculum.manage'   => 'Manage Curriculum',
            'admission.approve'   => 'Approve Admissions',
            'promotion.manage'    => 'Manage Promotions',
        ];

        foreach ($flatSlugs as $slug => $label) {
            Permission::firstOrCreate(
                ['slug' => $slug],
                ['module' => 'education', 'name' => $label]
            );
        }
    }
}
