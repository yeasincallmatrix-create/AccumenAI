<?php

namespace Database\Seeders;

use App\Models\ModuleRegistry;
use App\Models\PackageModule;
use App\Models\SubscriptionPackage;
use Illuminate\Database\Seeder;

class ModuleRegistrySeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            ['key' => 'crm', 'name' => 'CRM', 'type' => 'core', 'description' => 'Customer relationship management', 'sort_order' => 1],
            ['key' => 'accounting', 'name' => 'Accounting', 'type' => 'core', 'description' => 'Financial accounting & ledger', 'sort_order' => 2],
            ['key' => 'finance', 'name' => 'Finance', 'type' => 'core', 'description' => 'Finance management & invoicing', 'sort_order' => 3],
            ['key' => 'inventory', 'name' => 'Inventory', 'type' => 'core', 'description' => 'Inventory & stock management', 'sort_order' => 4],
            ['key' => 'hr', 'name' => 'HR', 'type' => 'core', 'description' => 'Human resources management', 'sort_order' => 5],
            ['key' => 'sales', 'name' => 'Sales', 'type' => 'core', 'description' => 'Sales pipeline & quotes', 'sort_order' => 6],
            ['key' => 'purchase', 'name' => 'Purchase', 'type' => 'core', 'description' => 'Purchase orders & procurement', 'sort_order' => 7],
            ['key' => 'reports', 'name' => 'Reports', 'type' => 'core', 'description' => 'Analytics & reporting', 'sort_order' => 8],
            ['key' => 'notifications', 'name' => 'Notifications', 'type' => 'core', 'description' => 'In-app & push notifications', 'sort_order' => 9],
            ['key' => 'ai', 'name' => 'AI', 'type' => 'core', 'description' => 'AI assistant & tools', 'sort_order' => 10],
            ['key' => 'vat', 'name' => 'VAT / Tax', 'type' => 'core', 'description' => 'VAT & tax configuration, returns and compliance', 'sort_order' => 11],
            ['key' => 'education', 'name' => 'Education', 'type' => 'industry', 'description' => 'Education management (students, exams, results, certificates)', 'sort_order' => 20],
            ['key' => 'training_center', 'name' => 'Training Center', 'type' => 'industry', 'description' => 'Training center management', 'sort_order' => 22],
            ['key' => 'medical', 'name' => 'Medical / Hospital Management', 'type' => 'industry', 'description' => 'Complete Hospital Management System (OPD, IPD, Pharmacy, Lab, Billing)', 'sort_order' => 50],
        ];

        foreach ($modules as $mod) {
            ModuleRegistry::updateOrCreate(['key' => $mod['key']], $mod);
        }

        $packages = [
            ['name' => 'FREE', 'slug' => 'free', 'price_monthly' => 0, 'price_yearly' => 0, 'is_default' => 1],
            ['name' => 'BASIC', 'slug' => 'basic', 'price_monthly' => 1500, 'price_yearly' => 15000, 'max_students' => 300, 'max_teachers' => 10, 'max_courses' => 15, 'max_branches' => 2, 'storage_limit_mb' => 2000, 'sms_limit_monthly' => 200],
            ['name' => 'ADVANCED', 'slug' => 'advanced', 'price_monthly' => 4000, 'price_yearly' => 40000, 'max_students' => 1500, 'max_teachers' => 40, 'max_courses' => 60, 'max_branches' => 5, 'storage_limit_mb' => 10000, 'sms_limit_monthly' => 1000],
            ['name' => 'PREMIUM', 'slug' => 'premium', 'price_monthly' => 9000, 'price_yearly' => 90000],
        ];

        foreach ($packages as $pkg) {
            SubscriptionPackage::updateOrCreate(['slug' => $pkg['slug']], $pkg);
        }

        $packageModules = [
            'free'       => ['notifications'],
            'basic'      => ['finance', 'reports', 'notifications', 'education'],
            'advanced'   => ['finance', 'accounting', 'reports', 'notifications', 'ai', 'education', 'sales', 'medical', 'training_center'],
            'premium'    => ['finance', 'accounting', 'inventory', 'hr', 'reports', 'notifications', 'ai', 'education', 'sales', 'purchase', 'medical', 'training_center'],
        ];

        foreach ($packageModules as $slug => $keys) {
            $pkg = SubscriptionPackage::where('slug', $slug)->first();
            if (!$pkg) continue;
            foreach ($keys as $key) {
                PackageModule::updateOrCreate(
                    ['package_id' => $pkg->id, 'module_key' => $key],
                    ['enabled' => true]
                );
            }
        }
    }
}
