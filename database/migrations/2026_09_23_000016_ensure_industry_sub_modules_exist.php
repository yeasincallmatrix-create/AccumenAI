<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        // Parent industry modules (idempotent).
        $parentModules = [
            ['key' => 'education', 'name' => 'Education', 'type' => 'industry', 'icon' => 'bi-mortarboard', 'sort_order' => 20, 'status' => 'active', 'coming_soon' => 0],
            ['key' => 'training_center', 'name' => 'Training Center', 'type' => 'industry', 'icon' => 'bi-easel', 'sort_order' => 22, 'status' => 'active', 'coming_soon' => 0],
            ['key' => 'medical', 'name' => 'Medical / Hospital Management', 'type' => 'industry', 'icon' => 'bi-hospital', 'sort_order' => 50, 'status' => 'active', 'coming_soon' => 0],
        ];

        foreach ($parentModules as $mod) {
            DB::table('module_registry')->updateOrInsert(
                ['key' => $mod['key']],
                array_merge($mod, ['parent_key' => null, 'description' => $mod['name'] . ' management', 'updated_at' => now(), 'created_at' => now()])
            );
        }

        $subModules = [
            // Education
            ['key' => 'education.students',   'name' => 'Students',   'parent_key' => 'education', 'icon' => 'bi-people',           'sort_order' => 21, 'index_route' => 'students.index'],
            ['key' => 'education.classes',    'name' => 'Classes',    'parent_key' => 'education', 'icon' => 'bi-journal-bookmark', 'sort_order' => 22, 'index_route' => 'classes.index'],
            ['key' => 'education.exams',      'name' => 'Exams',      'parent_key' => 'education', 'icon' => 'bi-pencil-square',    'sort_order' => 23, 'index_route' => 'exams.index'],
            ['key' => 'education.attendance', 'name' => 'Attendance', 'parent_key' => 'education', 'icon' => 'bi-calendar-check',   'sort_order' => 24, 'index_route' => 'academic-attendance.mark.index'],
            ['key' => 'education.fees',       'name' => 'Fees',       'parent_key' => 'education', 'icon' => 'bi-cash-coin',        'sort_order' => 25, 'index_route' => 'finance.education.fee-structures.index'],
            ['key' => 'education.guardians',  'name' => 'Guardians',  'parent_key' => 'education', 'icon' => 'bi-person-badge',     'sort_order' => 26, 'index_route' => 'guardian.dashboard'],
            ['key' => 'education.analytics',  'name' => 'Analytics',  'parent_key' => 'education', 'icon' => 'bi-graph-up',         'sort_order' => 27, 'index_route' => 'academic.analytics.index'],

            // Training Center
            ['key' => 'training_center.courses',      'name' => 'Courses',      'parent_key' => 'training_center', 'icon' => 'bi-book',           'sort_order' => 23, 'index_route' => 'courses.manage.index'],
            ['key' => 'training_center.batches',      'name' => 'Batches',      'parent_key' => 'training_center', 'icon' => 'bi-calendar-week',  'sort_order' => 24, 'index_route' => 'batches.index'],
            ['key' => 'training_center.trainees',     'name' => 'Trainees',     'parent_key' => 'training_center', 'icon' => 'bi-person-badge',   'sort_order' => 25, 'index_route' => 'students.index'],
            ['key' => 'training_center.attendance',   'name' => 'Attendance',   'parent_key' => 'training_center', 'icon' => 'bi-calendar-check', 'sort_order' => 26, 'index_route' => 'training.attendance.index'],
            ['key' => 'training_center.exams',        'name' => 'Exams',        'parent_key' => 'training_center', 'icon' => 'bi-pencil-square',  'sort_order' => 27, 'index_route' => 'training.exams.index'],
            ['key' => 'training_center.certificates', 'name' => 'Certificates', 'parent_key' => 'training_center', 'icon' => 'bi-award',          'sort_order' => 28, 'index_route' => 'training.certificates.index'],
            ['key' => 'training_center.fees',         'name' => 'Fees',         'parent_key' => 'training_center', 'icon' => 'bi-cash-coin',      'sort_order' => 29, 'index_route' => 'training.fees.index'],
            ['key' => 'training_center.reports',      'name' => 'Reports',      'parent_key' => 'training_center', 'icon' => 'bi-graph-up',       'sort_order' => 30, 'index_route' => 'training.reports.index'],

            // Medical
            ['key' => 'medical.opd',            'name' => 'OPD',             'parent_key' => 'medical', 'icon' => 'bi-person-walking',  'sort_order' => 51, 'index_route' => 'medical.opd.appointments.index'],
            ['key' => 'medical.ipd',            'name' => 'IPD',             'parent_key' => 'medical', 'icon' => 'bi-hospital-fill',   'sort_order' => 52, 'index_route' => 'medical.ipd.admissions.index'],
            ['key' => 'medical.pharmacy',       'name' => 'Pharmacy',        'parent_key' => 'medical', 'icon' => 'bi-capsule',         'sort_order' => 53, 'index_route' => 'medical.pharmacy.medicines.index'],
            ['key' => 'medical.laboratory',     'name' => 'Laboratory',      'parent_key' => 'medical', 'icon' => 'bi-eyedropper',      'sort_order' => 54, 'index_route' => 'medical.laboratory.orders.index'],
            ['key' => 'medical.billing',        'name' => 'Billing',         'parent_key' => 'medical', 'icon' => 'bi-receipt',         'sort_order' => 55, 'index_route' => 'medical.billing.invoices.index'],
            ['key' => 'medical.emergency',      'name' => 'Emergency',       'parent_key' => 'medical', 'icon' => 'bi-heart-pulse',     'sort_order' => 56, 'index_route' => 'medical.emergency.index'],
            ['key' => 'medical.radiology',      'name' => 'Radiology',       'parent_key' => 'medical', 'icon' => 'bi-radioactive',     'sort_order' => 57, 'index_route' => 'medical.radiology.orders.index'],
            ['key' => 'medical.bloodbank',      'name' => 'Blood Bank',      'parent_key' => 'medical', 'icon' => 'bi-droplet-fill',    'sort_order' => 58, 'index_route' => 'medical.blood-bank.dashboard'],
            ['key' => 'medical.physiotherapy',  'name' => 'Physiotherapy',   'parent_key' => 'medical', 'icon' => 'bi-activity',        'sort_order' => 59, 'index_route' => 'medical.physiotherapy.dashboard'],
            ['key' => 'medical.dental',         'name' => 'Dental',          'parent_key' => 'medical', 'icon' => 'bi-emoji-smile',     'sort_order' => 60, 'index_route' => 'medical.dental.dashboard'],
            ['key' => 'medical.vaccination',    'name' => 'Vaccination',     'parent_key' => 'medical', 'icon' => 'bi-shield-plus',     'sort_order' => 61, 'index_route' => 'medical.vaccination.dashboard'],
            ['key' => 'medical.ambulance',      'name' => 'Ambulance',       'parent_key' => 'medical', 'icon' => 'bi-truck',           'sort_order' => 62, 'index_route' => 'medical.ambulance.dashboard'],
            ['key' => 'medical.diet',           'name' => 'Diet & Nutrition','parent_key' => 'medical', 'icon' => 'bi-egg-fried',       'sort_order' => 63, 'index_route' => 'medical.diet.dashboard'],
            ['key' => 'medical.records',        'name' => 'Medical Records', 'parent_key' => 'medical', 'icon' => 'bi-folder2-open',    'sort_order' => 64, 'index_route' => 'medical.records.dashboard'],
        ];

        foreach ($subModules as $sub) {
            DB::table('module_registry')->updateOrInsert(
                ['key' => $sub['key']],
                array_merge($sub, [
                    'type'        => 'industry',
                    'description' => $sub['name'] . ' management',
                    'status'      => 'active',
                    'coming_soon' => 0,
                    'dependencies' => null,
                    'updated_at'  => now(),
                    'created_at'  => now(),
                ])
            );
        }

        // Parent package_modules for packages that include each industry.
        $packageModuleMap = [
            'free'     => [],
            'basic'    => ['education'],
            'advanced' => ['education', 'training_center', 'medical'],
            'premium'  => ['education', 'training_center', 'medical'],
        ];

        foreach ($packageModuleMap as $slug => $parentKeys) {
            if (empty($parentKeys)) {
                continue;
            }

            $pkg = DB::table('subscription_packages')->where('slug', $slug)->first();
            if (! $pkg) {
                continue;
            }

            foreach ($parentKeys as $moduleKey) {
                DB::table('package_modules')->updateOrInsert(
                    ['package_id' => $pkg->id, 'module_key' => $moduleKey],
                    ['enabled' => true, 'created_at' => now(), 'updated_at' => now()]
                );
            }

            // Sub-module keys under those parents (only if submodules exist).
            $subs = DB::table('module_registry')
                ->whereNotNull('parent_key')
                ->whereIn('parent_key', $parentKeys)
                ->pluck('key')
                ->toArray();

            foreach ($subs as $moduleKey) {
                DB::table('package_modules')->updateOrInsert(
                    ['package_id' => $pkg->id, 'module_key' => $moduleKey],
                    ['enabled' => true, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        // Data backfill only — intentionally a no-op.
    }
};
