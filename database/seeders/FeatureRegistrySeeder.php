<?php

namespace Database\Seeders;

use App\Models\FeatureRegistry;
use Illuminate\Database\Seeder;

class FeatureRegistrySeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            'medical.pharmacy' => [
                'name' => 'Pharmacy',
                'description' => 'Medicine inventory, dispensing, and stock management',
                'sort_order' => 53,
            ],
            'medical.laboratory' => [
                'name' => 'Laboratory',
                'description' => 'Lab test orders, results, and diagnostics',
                'sort_order' => 54,
            ],
            'medical.billing' => [
                'name' => 'Billing',
                'description' => 'Invoice generation, payment processing, and TPA billing',
                'sort_order' => 55,
            ],
            'medical.emergency' => [
                'name' => 'Emergency',
                'description' => 'Emergency visits, triage, and ER discharge management',
                'sort_order' => 56,
            ],
            'medical.radiology' => [
                'name' => 'Radiology',
                'description' => 'Imaging orders, results, and radiology reporting',
                'sort_order' => 57,
            ],
            'medical.bloodbank' => [
                'name' => 'Blood Bank',
                'description' => 'Blood stock management, cross-matching, and issue tracking',
                'sort_order' => 58,
            ],
            'medical.physiotherapy' => [
                'name' => 'Physiotherapy',
                'description' => 'Exercise plans, sessions, and rehabilitation tracking',
                'sort_order' => 59,
            ],
            'medical.dental' => [
                'name' => 'Dental',
                'description' => 'Dental charting, procedures, and treatment planning',
                'sort_order' => 60,
            ],
            'medical.vaccination' => [
                'name' => 'Vaccination',
                'description' => 'Vaccine scheduling, administration, and stock management',
                'sort_order' => 61,
            ],
            'medical.ambulance' => [
                'name' => 'Ambulance Services',
                'description' => 'Fleet management, dispatch, and trip tracking',
                'sort_order' => 62,
            ],
            'medical.diet' => [
                'name' => 'Diet & Nutrition',
                'description' => 'Diet plans, meal templates, and nutritional management',
                'sort_order' => 63,
            ],
            'medical.records' => [
                'name' => 'Medical Records',
                'description' => 'Patient records, discharge summaries, and document management',
                'sort_order' => 64,
            ],
        ];

        $created = 0;
        $updated = 0;

        foreach ($features as $key => $attrs) {
            $row = FeatureRegistry::updateOrCreate(
                ['feature_key' => $key],
                array_merge($attrs, ['module_key' => 'medical', 'status' => 'active'])
            );
            $row->wasRecentlyCreated ? $created++ : $updated++;
        }

        if ($this->command) {
            $this->command->info("Medical features: {$created} created, {$updated} updated.");
        }
    }
}
