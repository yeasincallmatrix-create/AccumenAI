<?php

namespace Database\Seeders;

use App\Models\ModuleRegistry;
use App\Models\PackageModule;
use App\Models\SubscriptionPackage;
use Illuminate\Database\Seeder;

class EducationSubModuleSeeder extends Seeder
{
    public function run(): void
    {
        ModuleRegistry::updateOrCreate(
            ['key' => 'education'],
            ['icon' => 'bi-mortarboard', 'parent_key' => null, 'coming_soon' => false, 'sort_order' => 20]
        );

        $subModules = [
            ['key' => 'education.students',   'name' => 'Students',   'icon' => 'bi-people',           'sort' => 21, 'route' => 'students.index'],
            ['key' => 'education.classes',    'name' => 'Classes',    'icon' => 'bi-journal-bookmark', 'sort' => 22, 'route' => 'classes.index'],
            ['key' => 'education.exams',      'name' => 'Exams',      'icon' => 'bi-pencil-square',    'sort' => 23, 'route' => 'exams.index'],
            ['key' => 'education.attendance', 'name' => 'Attendance', 'icon' => 'bi-calendar-check',   'sort' => 24, 'route' => 'academic-attendance.mark.index'],
            ['key' => 'education.fees',       'name' => 'Fees',       'icon' => 'bi-cash-coin',        'sort' => 25, 'route' => 'finance.education.fee-structures.index'],
            ['key' => 'education.guardians',  'name' => 'Guardians',  'icon' => 'bi-person-badge',     'sort' => 26, 'route' => 'guardian.dashboard'],
            ['key' => 'education.analytics',  'name' => 'Analytics',  'icon' => 'bi-graph-up',         'sort' => 27, 'route' => 'academic.analytics.index'],
        ];

        foreach ($subModules as $sub) {
            ModuleRegistry::updateOrCreate(
                ['key' => $sub['key']],
                [
                    'name'        => $sub['name'],
                    'parent_key'  => 'education',
                    'icon'        => $sub['icon'],
                    'sort_order'  => $sub['sort'],
                    'type'        => 'industry',
                    'coming_soon' => false,
                    'index_route' => $sub['route'] ?? null,
                    'status'      => 'active',
                ]
            );
        }

        $packageSlugs = ['basic', 'advanced', 'premium'];
        foreach ($packageSlugs as $slug) {
            $pkg = SubscriptionPackage::where('slug', $slug)->first();
            if (! $pkg) continue;
            foreach ($subModules as $sub) {
                PackageModule::updateOrCreate(
                    ['package_id' => $pkg->id, 'module_key' => $sub['key']],
                    ['enabled' => true]
                );
            }
        }
    }
}
