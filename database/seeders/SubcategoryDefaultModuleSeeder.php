<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubcategoryDefaultModuleSeeder extends Seeder
{
    public function run(): void
    {
        // Only module_registry keys are seeded — resolveEnabled() iterates the
        // registry, so any other key would be dead data.
        //
        // Deviations from the draft mapping (verified against module_registry):
        //   medical.inventory      → inventory            (registry has no medical.inventory)
        //   training_center.labs   → training_center.classes (ACTION REQUIRED rename)
        //   sales.invoice          → sales
        //   purchase.bill          → purchase.invoices
        //   sales.add_customer     → sales.customers
        //   purchase.add_vendor    → purchase (root already mandatory elsewhere → dropped here)
        //   sales.sales_receipt    → dropped (no equivalent)
        //   sales.credit_memo      → sales.returns
        //   purchase.vendor_credit → purchase.returns
        //   education.admission/library/transport/hostel/departments/research/batches/activities
        //                         → dropped (no equivalent registry keys)
        $mapping = [
            // ═══ HEALTHCARE ═══
            'healthcare.pharmacy' => [
                ['module' => 'medical.pharmacy', 'category' => 'mandatory'],
                ['module' => 'medical.billing', 'category' => 'mandatory'],
                ['module' => 'medical.records', 'category' => 'default'],
                ['module' => 'inventory', 'category' => 'default'],
                ['module' => 'medical.laboratory', 'category' => 'optional'],
            ],
            'healthcare.hospital' => [
                ['module' => 'medical.opd', 'category' => 'mandatory'],
                ['module' => 'medical.billing', 'category' => 'mandatory'],
                ['module' => 'medical.ipd', 'category' => 'default'],
                ['module' => 'medical.pharmacy', 'category' => 'default'],
                ['module' => 'medical.laboratory', 'category' => 'default'],
                ['module' => 'medical.records', 'category' => 'default'],
                ['module' => 'medical.emergency', 'category' => 'optional'],
                ['module' => 'medical.radiology', 'category' => 'optional'],
                ['module' => 'medical.bloodbank', 'category' => 'optional'],
                ['module' => 'medical.physiotherapy', 'category' => 'optional'],
                ['module' => 'medical.dental', 'category' => 'optional'],
                ['module' => 'medical.vaccination', 'category' => 'optional'],
                ['module' => 'medical.ambulance', 'category' => 'optional'],
                ['module' => 'medical.diet', 'category' => 'optional'],
            ],
            'healthcare.clinic' => [
                ['module' => 'medical.opd', 'category' => 'mandatory'],
                ['module' => 'medical.billing', 'category' => 'mandatory'],
                ['module' => 'medical.pharmacy', 'category' => 'default'],
                ['module' => 'medical.laboratory', 'category' => 'optional'],
                ['module' => 'medical.records', 'category' => 'optional'],
            ],
            'healthcare.diagnostic' => [
                ['module' => 'medical.laboratory', 'category' => 'mandatory'],
                ['module' => 'medical.billing', 'category' => 'mandatory'],
                ['module' => 'medical.records', 'category' => 'default'],
                ['module' => 'medical.radiology', 'category' => 'optional'],
                ['module' => 'medical.pharmacy', 'category' => 'optional'],
            ],
            'healthcare.dental' => [
                ['module' => 'medical.dental', 'category' => 'mandatory'],
                ['module' => 'medical.billing', 'category' => 'mandatory'],
                ['module' => 'medical.opd', 'category' => 'default'],
                ['module' => 'medical.records', 'category' => 'default'],
                ['module' => 'medical.pharmacy', 'category' => 'optional'],
                ['module' => 'medical.radiology', 'category' => 'optional'],
            ],
            'healthcare.physiotherapy' => [
                ['module' => 'medical.physiotherapy', 'category' => 'mandatory'],
                ['module' => 'medical.billing', 'category' => 'mandatory'],
                ['module' => 'medical.opd', 'category' => 'default'],
                ['module' => 'medical.records', 'category' => 'default'],
                ['module' => 'medical.pharmacy', 'category' => 'optional'],
            ],
            'healthcare.veterinary' => [
                ['module' => 'medical.opd', 'category' => 'mandatory'],
                ['module' => 'medical.billing', 'category' => 'mandatory'],
                ['module' => 'medical.pharmacy', 'category' => 'default'],
                ['module' => 'medical.laboratory', 'category' => 'optional'],
                ['module' => 'medical.records', 'category' => 'optional'],
            ],

            // ═══ EDUCATION ═══
            'education.school' => [
                ['module' => 'education.fees', 'category' => 'mandatory'],
                ['module' => 'education.students', 'category' => 'default'],
                ['module' => 'education.classes', 'category' => 'default'],
                ['module' => 'education.exams', 'category' => 'default'],
            ],
            'education.college' => [
                ['module' => 'education.fees', 'category' => 'mandatory'],
                ['module' => 'education.students', 'category' => 'default'],
                ['module' => 'education.classes', 'category' => 'default'],
                ['module' => 'education.exams', 'category' => 'default'],
            ],
            'education.university' => [
                ['module' => 'education.fees', 'category' => 'mandatory'],
                ['module' => 'education.students', 'category' => 'default'],
                ['module' => 'education.classes', 'category' => 'default'],
                ['module' => 'education.exams', 'category' => 'default'],
            ],
            'education.coaching' => [
                ['module' => 'education.fees', 'category' => 'mandatory'],
                ['module' => 'education.students', 'category' => 'default'],
                ['module' => 'education.exams', 'category' => 'optional'],
            ],
            'education.kindergarten' => [
                ['module' => 'education.fees', 'category' => 'mandatory'],
                ['module' => 'education.classes', 'category' => 'default'],
            ],

            // ═══ TRAINING CENTER ═══
            'training_center.vocational' => [
                ['module' => 'training_center.courses', 'category' => 'mandatory'],
                ['module' => 'training_center.batches', 'category' => 'mandatory'],
                ['module' => 'training_center.students', 'category' => 'default'],
                ['module' => 'training_center.certificates', 'category' => 'default'],
                ['module' => 'training_center.exams', 'category' => 'optional'],
                ['module' => 'training_center.attendance', 'category' => 'optional'],
            ],
            'training_center.it_training' => [
                ['module' => 'training_center.courses', 'category' => 'mandatory'],
                ['module' => 'training_center.batches', 'category' => 'mandatory'],
                ['module' => 'training_center.students', 'category' => 'default'],
                ['module' => 'training_center.certificates', 'category' => 'default'],
                ['module' => 'training_center.classes', 'category' => 'default'],
                ['module' => 'training_center.exams', 'category' => 'optional'],
            ],
            'training_center.language_center' => [
                ['module' => 'training_center.courses', 'category' => 'mandatory'],
                ['module' => 'training_center.batches', 'category' => 'mandatory'],
                ['module' => 'training_center.students', 'category' => 'default'],
                ['module' => 'training_center.certificates', 'category' => 'default'],
                ['module' => 'training_center.exams', 'category' => 'optional'],
                ['module' => 'training_center.attendance', 'category' => 'optional'],
            ],

            // ═══ RETAIL ═══
            'retail.grocery' => [
                ['module' => 'sales', 'category' => 'mandatory'],
                ['module' => 'purchase.invoices', 'category' => 'mandatory'],
                ['module' => 'sales.customers', 'category' => 'default'],
                ['module' => 'inventory', 'category' => 'default'],
            ],
            'retail.electronics' => [
                ['module' => 'sales', 'category' => 'mandatory'],
                ['module' => 'purchase.invoices', 'category' => 'mandatory'],
                ['module' => 'sales.customers', 'category' => 'default'],
                ['module' => 'inventory', 'category' => 'default'],
                ['module' => 'sales.returns', 'category' => 'optional'],
                ['module' => 'purchase.returns', 'category' => 'optional'],
            ],
            'retail.clothing' => [
                ['module' => 'sales', 'category' => 'mandatory'],
                ['module' => 'purchase.invoices', 'category' => 'mandatory'],
                ['module' => 'sales.customers', 'category' => 'default'],
                ['module' => 'inventory', 'category' => 'default'],
                ['module' => 'sales.returns', 'category' => 'optional'],
            ],
            'retail.restaurant' => [
                ['module' => 'sales', 'category' => 'mandatory'],
                ['module' => 'purchase.invoices', 'category' => 'mandatory'],
                ['module' => 'sales.customers', 'category' => 'default'],
                ['module' => 'inventory', 'category' => 'default'],
                ['module' => 'pos', 'category' => 'optional'],
            ],

            // ═══ MANUFACTURING ═══
            'manufacturing.general' => [
                ['module' => 'sales', 'category' => 'mandatory'],
                ['module' => 'purchase.invoices', 'category' => 'mandatory'],
                ['module' => 'sales.customers', 'category' => 'default'],
                ['module' => 'inventory', 'category' => 'default'],
                ['module' => 'manufacturing', 'category' => 'default'],
                ['module' => 'manufacturing.bom', 'category' => 'default'],
                ['module' => 'manufacturing.routing', 'category' => 'default'],
                ['module' => 'manufacturing.work_centers', 'category' => 'default'],
                ['module' => 'manufacturing.production_orders', 'category' => 'default'],
                ['module' => 'manufacturing.costing', 'category' => 'default'],
                ['module' => 'manufacturing.reports', 'category' => 'default'],
                ['module' => 'manufacturing.quality_control', 'category' => 'optional'],
                ['module' => 'manufacturing.quality_lab', 'category' => 'optional'],
                ['module' => 'manufacturing.sample_management', 'category' => 'optional'],
                ['module' => 'manufacturing.regulatory_compliance', 'category' => 'optional'],
                ['module' => 'manufacturing.batch_tracking', 'category' => 'optional'],
                ['module' => 'manufacturing.expiry_tracking', 'category' => 'optional'],
                ['module' => 'manufacturing.serial_number', 'category' => 'optional'],
                ['module' => 'manufacturing.assembly_line', 'category' => 'optional'],
                ['module' => 'manufacturing.mold_management', 'category' => 'optional'],
                ['module' => 'manufacturing.recipe', 'category' => 'optional'],
                ['module' => 'manufacturing.cutting', 'category' => 'optional'],
                ['module' => 'manufacturing.welding', 'category' => 'optional'],
                ['module' => 'manufacturing.finishing', 'category' => 'optional'],
                ['module' => 'manufacturing.printing', 'category' => 'optional'],
                ['module' => 'manufacturing.packaging', 'category' => 'optional'],
                ['module' => 'manufacturing.warranty', 'category' => 'optional'],
                ['module' => 'purchase.returns', 'category' => 'optional'],
            ],
            'manufacturing.food_processing' => [
                ['module' => 'sales', 'category' => 'mandatory'],
                ['module' => 'purchase.invoices', 'category' => 'mandatory'],
                ['module' => 'sales.customers', 'category' => 'default'],
                ['module' => 'inventory', 'category' => 'default'],
                ['module' => 'manufacturing', 'category' => 'default'],
                ['module' => 'manufacturing.bom', 'category' => 'default'],
                ['module' => 'manufacturing.routing', 'category' => 'default'],
                ['module' => 'manufacturing.work_centers', 'category' => 'default'],
                ['module' => 'manufacturing.production_orders', 'category' => 'default'],
                ['module' => 'manufacturing.costing', 'category' => 'default'],
                ['module' => 'manufacturing.reports', 'category' => 'default'],
                ['module' => 'manufacturing.quality_control', 'category' => 'optional'],
                ['module' => 'manufacturing.quality_lab', 'category' => 'optional'],
                ['module' => 'manufacturing.sample_management', 'category' => 'optional'],
                ['module' => 'manufacturing.regulatory_compliance', 'category' => 'optional'],
                ['module' => 'manufacturing.batch_tracking', 'category' => 'optional'],
                ['module' => 'manufacturing.expiry_tracking', 'category' => 'optional'],
                ['module' => 'manufacturing.serial_number', 'category' => 'optional'],
                ['module' => 'manufacturing.assembly_line', 'category' => 'optional'],
                ['module' => 'manufacturing.mold_management', 'category' => 'optional'],
                ['module' => 'manufacturing.recipe', 'category' => 'optional'],
                ['module' => 'manufacturing.cutting', 'category' => 'optional'],
                ['module' => 'manufacturing.welding', 'category' => 'optional'],
                ['module' => 'manufacturing.finishing', 'category' => 'optional'],
                ['module' => 'manufacturing.printing', 'category' => 'optional'],
                ['module' => 'manufacturing.packaging', 'category' => 'optional'],
                ['module' => 'manufacturing.warranty', 'category' => 'optional'],
                ['module' => 'purchase.returns', 'category' => 'optional'],
            ],

            // ═══ REAL ESTATE ═══
            'real_estate.property' => [
                ['module' => 'sales', 'category' => 'default'],
                ['module' => 'purchase', 'category' => 'default'],
                ['module' => 'inventory', 'category' => 'optional'],
            ],
            'real_estate.rental' => [
                ['module' => 'real_estate.properties', 'category' => 'mandatory'],
                ['module' => 'real_estate.leases', 'category' => 'mandatory'],
                ['module' => 'real_estate.tenants', 'category' => 'mandatory'],
                ['module' => 'real_estate.rent_invoices', 'category' => 'default'],
                ['module' => 'real_estate.rent_collection', 'category' => 'default'],
                ['module' => 'real_estate.security_deposits', 'category' => 'optional'],
                ['module' => 'real_estate.lease_renewals', 'category' => 'optional'],
                ['module' => 'real_estate.utility_billing', 'category' => 'optional'],
            ],
        ];

        $registryKeys = DB::table('module_registry')->pluck('key')->flip();

        $count = 0;
        $skipped = [];
        foreach ($mapping as $key => $modules) {
            [$industry, $subcategory] = explode('.', $key, 2);

            $sub = DB::table('industry_subcategories')
                ->where('industry_key', $industry)
                ->where('subcategory_key', $subcategory)
                ->first();

            if (! $sub) {
                $this->command->warn("Sub-category not found: {$key}");

                continue;
            }

            foreach ($modules as $m) {
                if (! isset($registryKeys[$m['module']])) {
                    $skipped[] = "{$key} → {$m['module']}";

                    continue;
                }

                DB::table('subcategory_default_modules')->updateOrInsert(
                    ['subcategory_id' => $sub->id, 'module_key' => $m['module']],
                    ['category' => $m['category'], 'created_at' => now(), 'updated_at' => now()]
                );
                $count++;
            }
        }

        if ($skipped) {
            $this->command->warn('Skipped keys not in module_registry: '.implode(', ', $skipped));
        }

        $this->command->info('Sub-category default modules seeded: '.$count);
    }
}
