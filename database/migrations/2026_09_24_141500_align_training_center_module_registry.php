<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SUB_MODULES = [
        ['key' => 'training_center.courses',      'name' => 'Courses',      'sort_order' => 23, 'icon' => 'bi-book',           'index_route' => 'courses.manage.index'],
        ['key' => 'training_center.batches',      'name' => 'Batches',      'sort_order' => 24, 'icon' => 'bi-calendar-week',  'index_route' => 'training.batches.index'],
        ['key' => 'training_center.students',     'name' => 'Students',     'sort_order' => 25, 'icon' => 'bi-person-badge',   'index_route' => 'training.students.index'],
        ['key' => 'training_center.classes',      'name' => 'Classes',      'sort_order' => 26, 'icon' => 'bi-collection',     'index_route' => 'training.classes.index'],
        ['key' => 'training_center.attendance',   'name' => 'Attendance',   'sort_order' => 27, 'icon' => 'bi-calendar-check', 'index_route' => 'training.attendance.index'],
        ['key' => 'training_center.exams',        'name' => 'Exams',        'sort_order' => 28, 'icon' => 'bi-pencil-square',  'index_route' => 'training.exams.index'],
        ['key' => 'training_center.certificates', 'name' => 'Certificates', 'sort_order' => 29, 'icon' => 'bi-award',          'index_route' => 'training.certificates.index'],
        ['key' => 'training_center.fees',         'name' => 'Fees',         'sort_order' => 30, 'icon' => 'bi-cash-coin',      'index_route' => 'training.fees.index'],
        ['key' => 'training_center.reports',      'name' => 'Reports',      'sort_order' => 31, 'icon' => 'bi-graph-up',       'index_route' => 'training.reports.index'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('module_registry')) {
            return;
        }

        DB::table('module_registry')->updateOrInsert(
            ['key' => 'training_center'],
            [
                'name' => 'Training Center',
                'type' => 'industry',
                'description' => 'Training center management',
                'icon' => 'bi-easel',
                'parent_key' => null,
                'sort_order' => 22,
                'status' => 'active',
                'coming_soon' => 0,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        foreach (self::SUB_MODULES as $sub) {
            DB::table('module_registry')->updateOrInsert(
                ['key' => $sub['key']],
                array_merge($sub, [
                    'parent_key' => 'training_center',
                    'type' => 'industry',
                    'description' => $sub['name'] . ' management',
                    'dependencies' => null,
                    'coming_soon' => 0,
                    'status' => 'active',
                    'updated_at' => now(),
                    'created_at' => now(),
                ])
            );
        }

        DB::table('module_registry')
            ->where('key', 'training_center.batches')
            ->update(['index_route' => 'training.batches.index']);

        if (Schema::hasTable('package_modules') && Schema::hasTable('subscription_packages')) {
            foreach (DB::table('subscription_packages')->whereIn('slug', ['advanced', 'premium'])->pluck('id') as $pkgId) {
                foreach (self::SUB_MODULES as $sub) {
                    DB::table('package_modules')->updateOrInsert(
                        ['package_id' => $pkgId, 'module_key' => $sub['key']],
                        ['enabled' => 1, 'created_at' => now(), 'updated_at' => now()]
                    );
                }
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('module_registry')) {
            return;
        }

        DB::table('module_registry')->where('key', 'training_center.students')->delete();
        DB::table('module_registry')->where('key', 'training_center.classes')->delete();
        DB::table('module_registry')->where('key', 'training_center.batches')->update(['index_route' => 'batches.index']);
    }
};
