<?php

namespace Database\Seeders;

use App\Models\ModuleRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TrainingCenterSubModuleSeeder extends Seeder
{
    public function run(): void
    {
        ModuleRegistry::updateOrCreate(
            ['key' => 'training_center'],
            ['icon' => 'bi-easel', 'parent_key' => null, 'coming_soon' => false, 'sort_order' => 22]
        );

        $subModules = [
            ['key' => 'training_center.courses',      'name' => 'Courses',      'sort_order' => 23, 'icon' => 'bi-book',           'index_route' => 'training.courses.index'],
            ['key' => 'training_center.batches',      'name' => 'Batches',      'sort_order' => 24, 'icon' => 'bi-calendar-week',  'index_route' => 'training.batches.index'],
            ['key' => 'training_center.students',     'name' => 'Students',     'sort_order' => 25, 'icon' => 'bi-person-badge',   'index_route' => 'training.students.index'],
            ['key' => 'training_center.classes',      'name' => 'Classes',      'sort_order' => 26, 'icon' => 'bi-collection',     'index_route' => 'training.classes.index'],
            ['key' => 'training_center.attendance',   'name' => 'Attendance',   'sort_order' => 27, 'icon' => 'bi-calendar-check', 'index_route' => 'training.attendance.index'],
            ['key' => 'training_center.exams',        'name' => 'Exams',        'sort_order' => 28, 'icon' => 'bi-pencil-square',  'index_route' => 'training.exams.index'],
            ['key' => 'training_center.certificates', 'name' => 'Certificates', 'sort_order' => 29, 'icon' => 'bi-award',          'index_route' => 'training.certificates.index'],
            ['key' => 'training_center.fees',         'name' => 'Fees',         'sort_order' => 30, 'icon' => 'bi-cash-coin',      'index_route' => 'training.fees.index'],
            ['key' => 'training_center.reports',      'name' => 'Reports',      'sort_order' => 31, 'icon' => 'bi-graph-up',       'index_route' => 'training.reports.index'],
        ];

        foreach ($subModules as $sub) {
            DB::table('module_registry')->updateOrInsert(
                ['key' => $sub['key']],
                array_merge($sub, [
                    'parent_key' => 'training_center',
                    'type' => 'industry',
                    'description' => $sub['name'] . ' management',
                    'dependencies' => null,
                    'coming_soon' => 0,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        foreach (DB::table('subscription_packages')->whereIn('slug', ['advanced', 'premium'])->get() as $pkg) {
            foreach ($subModules as $sub) {
                DB::table('package_modules')->updateOrInsert(
                    ['package_id' => $pkg->id, 'module_key' => $sub['key']],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        if ($this->command) {
            $this->command->info('Training Center sub-modules seeded: 9');
        }
    }
}
