<?php

namespace Database\Seeders;

use App\Models\Institute;
use App\Models\Medical\Bed;
use App\Models\Medical\LabTest;
use App\Models\Medical\Medicine;
use App\Models\Medical\Ward;
use Illuminate\Database\Seeder;

class MedicalDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Every medical table is multi-tenant (institute_id NOT NULL), so the
        // seeder targets a healthcare institute when one exists and otherwise
        // falls back to the first institute. Aborts cleanly when empty.
        $institute = Institute::withoutGlobalScopes()
            ->where('industry', 'healthcare')
            ->orderBy('id')
            ->first()
            ?? Institute::withoutGlobalScopes()->orderBy('id')->first();

        if (! $institute) {
            $this->command->warn('⚠️  MedicalDemoSeeder skipped: no institute found.');

            return;
        }

        $instituteId = $institute->id;

        // Create Demo Wards (idempotent on institute + name)
        $wards = [
            ['name' => 'General Ward - Floor 1', 'type' => 'general', 'total_beds' => 20, 'available_beds' => 20, 'daily_rate' => 500],
            ['name' => 'General Ward - Floor 2', 'type' => 'general', 'total_beds' => 25, 'available_beds' => 25, 'daily_rate' => 600],
            ['name' => 'ICU Ward', 'type' => 'icu', 'total_beds' => 10, 'available_beds' => 10, 'daily_rate' => 2000],
            ['name' => 'CCU Ward', 'type' => 'ccu', 'total_beds' => 8, 'available_beds' => 8, 'daily_rate' => 1800],
            ['name' => 'Private Cabin', 'type' => 'cabin', 'total_beds' => 15, 'available_beds' => 15, 'daily_rate' => 1500],
            ['name' => 'NICU', 'type' => 'nicu', 'total_beds' => 5, 'available_beds' => 5, 'daily_rate' => 2500],
        ];

        foreach ($wards as $ward) {
            Ward::firstOrCreate(
                ['institute_id' => $instituteId, 'name' => $ward['name']],
                $ward + ['institute_id' => $instituteId, 'is_active' => true]
            );
        }

        // Create Beds for each ward of this institute
        foreach (Ward::where('institute_id', $instituteId)->get() as $ward) {
            for ($i = 1; $i <= $ward->total_beds; $i++) {
                Bed::firstOrCreate(
                    [
                        'ward_id' => $ward->id,
                        'bed_number' => 'Bed-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                    ],
                    [
                        'institute_id' => $instituteId,
                        'status' => 'available',
                    ]
                );
            }
        }

        // Create Demo Medicines (idempotent on code)
        $medicines = [
            ['code' => 'MED-001', 'generic_name' => 'Paracetamol', 'brand_name' => 'Napa 500mg', 'dosage_form' => 'Tablet', 'unit' => 'Strip', 'pack_size' => 10, 'selling_price' => 3.75, 'reorder_level' => 50],
            ['code' => 'MED-002', 'generic_name' => 'Amoxicillin', 'brand_name' => 'Amoxil 500mg', 'dosage_form' => 'Capsule', 'unit' => 'Strip', 'pack_size' => 10, 'selling_price' => 8.00, 'reorder_level' => 30],
            ['code' => 'MED-003', 'generic_name' => 'Metformin', 'brand_name' => 'Metformin 500mg', 'dosage_form' => 'Tablet', 'unit' => 'Strip', 'pack_size' => 10, 'selling_price' => 4.50, 'reorder_level' => 40],
            ['code' => 'MED-004', 'generic_name' => 'Omeprazole', 'brand_name' => 'Omeprazole 20mg', 'dosage_form' => 'Capsule', 'unit' => 'Strip', 'pack_size' => 10, 'selling_price' => 5.00, 'reorder_level' => 30],
            ['code' => 'MED-005', 'generic_name' => 'Multivitamin Syrup', 'brand_name' => 'Multivit Syrup', 'dosage_form' => 'Syrup', 'unit' => 'Bottle', 'pack_size' => 1, 'selling_price' => 150.00, 'reorder_level' => 20],
        ];

        foreach ($medicines as $medicine) {
            Medicine::firstOrCreate(
                ['code' => $medicine['code']],
                $medicine + ['institute_id' => $instituteId, 'is_active' => true]
            );
        }

        // Create Demo Lab Tests (idempotent on code)
        $labTests = [
            ['code' => 'LAB-001', 'name' => 'Complete Blood Count', 'category' => 'Hematology', 'normal_range' => 'WBC: 4-11 x10^3/uL, RBC: 4.5-5.5 x10^6/uL', 'unit' => 'Various', 'price' => 500],
            ['code' => 'LAB-002', 'name' => 'Blood Glucose Fasting', 'category' => 'Biochemistry', 'normal_range' => '70-100 mg/dL', 'unit' => 'mg/dL', 'price' => 300],
            ['code' => 'LAB-003', 'name' => 'HbA1c', 'category' => 'Biochemistry', 'normal_range' => '< 5.7%', 'unit' => '%', 'price' => 600],
            ['code' => 'LAB-004', 'name' => 'Lipid Profile', 'category' => 'Biochemistry', 'normal_range' => 'Total Chol: <200 mg/dL', 'unit' => 'mg/dL', 'price' => 800],
            ['code' => 'LAB-005', 'name' => 'Urine R/E', 'category' => 'Urinalysis', 'normal_range' => 'PH: 4.5-8.0', 'unit' => 'Various', 'price' => 250],
            ['code' => 'LAB-006', 'name' => 'ECG', 'category' => 'Cardiology', 'normal_range' => 'Normal Sinus Rhythm', 'unit' => 'N/A', 'price' => 400],
            ['code' => 'LAB-007', 'name' => 'Chest X-Ray', 'category' => 'Radiology', 'normal_range' => 'Clear Lungs', 'unit' => 'N/A', 'price' => 600],
            ['code' => 'LAB-008', 'name' => 'COVID-19 RT-PCR', 'category' => 'Molecular', 'normal_range' => 'Negative', 'unit' => 'N/A', 'price' => 500],
        ];

        foreach ($labTests as $test) {
            LabTest::firstOrCreate(
                ['code' => $test['code']],
                $test + ['institute_id' => $instituteId, 'is_active' => true]
            );
        }

        $this->command->info("✅ Medical Demo Data Seeded Successfully! (institute_id: {$instituteId})");
    }
}
