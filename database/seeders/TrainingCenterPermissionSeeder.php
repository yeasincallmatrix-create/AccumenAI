<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TrainingCenterPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // ═══ FLAT slugs (what routes reference or will reference) ═══
            // batches.view and batches.manage already exist from education — skip
            ['slug' => 'training.view',                'name' => 'View Training',                'module' => 'training'],
            ['slug' => 'training.manage',              'name' => 'Manage Training',              'module' => 'training'],
            ['slug' => 'classes.view',                 'name' => 'View Classes',                 'module' => 'training'],
            ['slug' => 'classes.manage',               'name' => 'Manage Classes',               'module' => 'training'],
            ['slug' => 'enrollments.view',             'name' => 'View Enrollments',             'module' => 'training'],
            ['slug' => 'enrollments.manage',           'name' => 'Manage Enrollments',           'module' => 'training'],
            ['slug' => 'marks.view',                   'name' => 'View Marks',                   'module' => 'training'],
            ['slug' => 'marks.manage',                 'name' => 'Manage Marks',                 'module' => 'training'],
            ['slug' => 'results.view',                 'name' => 'View Results',                 'module' => 'training'],
            ['slug' => 'results.publish',              'name' => 'Publish Results',              'module' => 'training'],
            ['slug' => 'training.certificates.view',   'name' => 'View Certificates',            'module' => 'training'],
            ['slug' => 'training.certificates.manage', 'name' => 'Manage Certificates',          'module' => 'training'],
            ['slug' => 'training.settings.manage',     'name' => 'Manage Training Settings',     'module' => 'training'],
            ['slug' => 'training.attendance.view',     'name' => 'View Training Attendance',     'module' => 'training'],
            ['slug' => 'training.attendance.manage',   'name' => 'Manage Training Attendance',   'module' => 'training'],
            ['slug' => 'training.exams.view',          'name' => 'View Training Exams',          'module' => 'training'],
            ['slug' => 'training.exams.manage',        'name' => 'Manage Training Exams',        'module' => 'training'],
            ['slug' => 'training.fees.view',           'name' => 'View Training Fees',           'module' => 'training'],
            ['slug' => 'training.fees.manage',         'name' => 'Manage Training Fees',         'module' => 'training'],
            ['slug' => 'training.reports.view',        'name' => 'View Training Reports',        'module' => 'training'],
            ['slug' => 'training.courses.view',        'name' => 'View Training Courses',        'module' => 'training'],
            ['slug' => 'training.courses.manage',      'name' => 'Manage Training Courses',      'module' => 'training'],

            // ═══ PREFIXED slugs (medical-style) ═══
            ['slug' => 'training_center.courses.view',        'name' => 'View Courses',        'module' => 'training_center.courses'],
            ['slug' => 'training_center.courses.manage',      'name' => 'Manage Courses',      'module' => 'training_center.courses'],
            ['slug' => 'training_batches.view',              'name' => 'View Batches',        'module' => 'training_batches'],
            ['slug' => 'training_batches.manage',            'name' => 'Manage Batches',      'module' => 'training_batches'],
            ['slug' => 'training_students.view',             'name' => 'View Students',       'module' => 'training_students'],
            ['slug' => 'training_students.manage',           'name' => 'Manage Students',     'module' => 'training_students'],
            ['slug' => 'training_classes.view',              'name' => 'View Classes',        'module' => 'training_classes'],
            ['slug' => 'training_classes.manage',            'name' => 'Manage Classes',      'module' => 'training_classes'],
            ['slug' => 'training_center.attendance.view',     'name' => 'View Attendance',     'module' => 'training_center.attendance'],
            ['slug' => 'training_center.attendance.manage',   'name' => 'Manage Attendance',   'module' => 'training_center.attendance'],
            ['slug' => 'training_center.exams.view',          'name' => 'View Exams',          'module' => 'training_center.exams'],
            ['slug' => 'training_center.exams.manage',        'name' => 'Manage Exams',        'module' => 'training_center.exams'],
            ['slug' => 'training_center.certificates.view',   'name' => 'View Certificates',   'module' => 'training_center.certificates'],
            ['slug' => 'training_center.certificates.manage', 'name' => 'Manage Certificates', 'module' => 'training_center.certificates'],
            ['slug' => 'training_center.fees.view',           'name' => 'View Fees',           'module' => 'training_center.fees'],
            ['slug' => 'training_center.fees.manage',         'name' => 'Manage Fees',         'module' => 'training_center.fees'],
            ['slug' => 'training_center.reports.view',        'name' => 'View Reports',        'module' => 'training_center.reports'],
        ];

        foreach ($permissions as $p) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $p['slug']],
                array_merge($p, ['created_at' => now()])
            );
        }

        if ($this->command) {
            $this->command->info('Training Center permissions seeded: ' . count($permissions));
        }
    }
}
