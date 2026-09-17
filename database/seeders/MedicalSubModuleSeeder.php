<?php

namespace Database\Seeders;

use App\Models\ModuleRegistry;
use App\Models\PackageModule;
use App\Models\SubscriptionPackage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MedicalSubModuleSeeder extends Seeder
{
    public function run(): void
    {
        // Update parent medical module
        ModuleRegistry::updateOrCreate(
            ['key' => 'medical'],
            ['icon' => 'bi-hospital', 'parent_key' => null, 'coming_soon' => false, 'sort_order' => 50]
        );

        $activeSubModules = [
            ['key' => 'medical.opd',        'name' => 'OPD',        'icon' => 'bi-person-walking',  'sort' => 51, 'route' => 'medical.opd.appointments.index'],
            ['key' => 'medical.ipd',        'name' => 'IPD',        'icon' => 'bi-hospital-fill',   'sort' => 52, 'route' => 'medical.ipd.admissions.index'],
            ['key' => 'medical.pharmacy',   'name' => 'Pharmacy',   'icon' => 'bi-capsule',         'sort' => 53, 'route' => 'medical.pharmacy.medicines.index'],
            ['key' => 'medical.laboratory', 'name' => 'Laboratory', 'icon' => 'bi-eyedropper',      'sort' => 54, 'route' => 'medical.laboratory.orders.index'],
            ['key' => 'medical.billing',    'name' => 'Billing',    'icon' => 'bi-receipt',         'sort' => 55, 'route' => 'medical.billing.invoices.index'],
            ['key' => 'medical.emergency',  'name' => 'Emergency',  'icon' => 'bi-heart-pulse',     'sort' => 56, 'route' => 'medical.emergency.index'],
            ['key' => 'medical.radiology',  'name' => 'Radiology',  'icon' => 'bi-radioactive',     'sort' => 57, 'route' => 'medical.radiology.orders.index'],
            ['key' => 'medical.bloodbank',   'name' => 'Blood Bank',   'icon' => 'bi-droplet-fill',   'sort' => 58, 'route' => 'medical.blood-bank.dashboard'],
            ['key' => 'medical.physiotherapy', 'name' => 'Physiotherapy', 'icon' => 'bi-activity',     'sort' => 59, 'route' => 'medical.physiotherapy.dashboard'],
            ['key' => 'medical.dental',        'name' => 'Dental',          'icon' => 'bi-emoji-smile', 'sort' => 60, 'route' => 'medical.dental.dashboard'],
            ['key' => 'medical.vaccination',   'name' => 'Vaccination',     'icon' => 'bi-shield-plus', 'sort' => 61, 'route' => 'medical.vaccination.dashboard'],
        ];

        $comingSoonSubModules = [
            ['key' => 'medical.ambulance',     'name' => 'Ambulance',       'icon' => 'bi-truck',          'sort' => 62],
            ['key' => 'medical.diet',          'name' => 'Diet & Nutrition','icon' => 'bi-egg-fried',      'sort' => 63],
            ['key' => 'medical.records',       'name' => 'Medical Records', 'icon' => 'bi-folder2-open',   'sort' => 64],
        ];

        foreach ($activeSubModules as $sub) {
            ModuleRegistry::updateOrCreate(
                ['key' => $sub['key']],
                [
                    'name'        => $sub['name'],
                    'parent_key'  => 'medical',
                    'icon'        => $sub['icon'],
                    'sort_order'  => $sub['sort'],
                    'type'        => 'industry',
                    'coming_soon' => false,
                    'index_route' => $sub['route'] ?? null,
                    'status'      => 'active',
                ]
            );
        }

        foreach ($comingSoonSubModules as $sub) {
            ModuleRegistry::updateOrCreate(
                ['key' => $sub['key']],
                [
                    'name'        => $sub['name'],
                    'parent_key'  => 'medical',
                    'icon'        => $sub['icon'],
                    'sort_order'  => $sub['sort'],
                    'type'        => 'industry',
                    'coming_soon' => true,
                    'status'      => 'active',
                ]
            );
        }

        // Add 5 active sub-modules to advanced + premium packages
        $packageSlugs = ['advanced', 'premium'];
        foreach ($packageSlugs as $slug) {
            $pkg = SubscriptionPackage::where('slug', $slug)->first();
            if (! $pkg) continue;
            foreach ($activeSubModules as $sub) {
                PackageModule::updateOrCreate(
                    ['package_id' => $pkg->id, 'module_key' => $sub['key']],
                    ['enabled' => true]
                );
            }
        }

        // Backfill: enable 5 active sub-modules for every institute that has medical enabled
        $medicalEnabledInstitutes = DB::table('institute_module_overrides')
            ->where('module_key', 'medical')
            ->where('enabled', true)
            ->pluck('institute_id');

        // Also check package-level entitlements
        $packageMedicalInstitutes = DB::table('institute_module_entitlements')
            ->where('module_key', 'medical')
            ->where('status', 'active')
            ->where('is_grant', true)
            ->pluck('institute_id');

        $allInstitutes = $medicalEnabledInstitutes->merge($packageMedicalInstitutes)->unique();

        // If no overrides/entitlements exist, check institutes table directly
        if ($allInstitutes->isEmpty()) {
            $allInstitutes = DB::table('institutes')->pluck('id');
        }

        foreach ($allInstitutes as $instituteId) {
            foreach ($activeSubModules as $sub) {
                DB::table('institute_module_overrides')->updateOrInsert(
                    ['institute_id' => $instituteId, 'module_key' => $sub['key']],
                    ['enabled' => true, 'updated_at' => now()]
                );
            }
        }
    }
}
