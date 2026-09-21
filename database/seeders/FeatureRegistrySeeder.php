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

        $educationFeatures = [
            'education.students' => [
                'name' => 'Students',
                'description' => 'Student registration, enrollment, profiles, and academic records',
                'sort_order' => 21,
            ],
            'education.classes' => [
                'name' => 'Classes',
                'description' => 'Classes, sections, subjects, timetable, and curriculum',
                'sort_order' => 22,
            ],
            'education.exams' => [
                'name' => 'Exams & Results',
                'description' => 'Exam scheduling, marks entry, grading, and result publishing',
                'sort_order' => 23,
            ],
            'education.attendance' => [
                'name' => 'Attendance',
                'description' => 'Student and teacher attendance tracking and reports',
                'sort_order' => 24,
            ],
            'education.fees' => [
                'name' => 'Fees',
                'description' => 'Fee heads, structures, collection, waivers, and Outstanding tracking',
                'sort_order' => 25,
            ],
            'education.guardians' => [
                'name' => 'Guardians',
                'description' => 'Guardian portal, parent-student linking, and communication',
                'sort_order' => 26,
            ],
            'education.analytics' => [
                'name' => 'Academic Analytics',
                'description' => 'Performance dashboards, analytics, and reports',
                'sort_order' => 27,
            ],
        ];

        foreach ($educationFeatures as $key => $attrs) {
            $row = FeatureRegistry::updateOrCreate(
                ['feature_key' => $key],
                array_merge($attrs, ['module_key' => 'education', 'status' => 'active'])
            );
            $row->wasRecentlyCreated ? $created++ : $updated++;
        }

        $trainingFeatures = [
            'training_center.courses' => [
                'name' => 'Courses',
                'description' => 'Course catalog, curriculum, subjects, and materials',
                'sort_order' => 101,
            ],
            'training_center.batches' => [
                'name' => 'Batches',
                'description' => 'Batch management, schedules, and capacity',
                'sort_order' => 102,
            ],
            'training_center.trainees' => [
                'name' => 'Trainees',
                'description' => 'Trainee registration, enrollment, and profiles',
                'sort_order' => 103,
            ],
            'training_center.attendance' => [
                'name' => 'Attendance',
                'description' => 'Training attendance tracking and reports',
                'sort_order' => 104,
            ],
            'training_center.exams' => [
                'name' => 'Exams',
                'description' => 'Training exams, marks, results, and publishing',
                'sort_order' => 105,
            ],
            'training_center.certificates' => [
                'name' => 'Certificates',
                'description' => 'Certificate generation, templates, and downloads',
                'sort_order' => 106,
            ],
            'training_center.fees' => [
                'name' => 'Fees',
                'description' => 'Training fees, collection, and receipts',
                'sort_order' => 107,
            ],
            'training_center.reports' => [
                'name' => 'Reports',
                'description' => 'Training analytics and reports',
                'sort_order' => 108,
            ],
        ];

        foreach ($trainingFeatures as $key => $attrs) {
            $row = FeatureRegistry::updateOrCreate(
                ['feature_key' => $key],
                array_merge($attrs, ['module_key' => 'training_center', 'status' => 'active'])
            );
            $row->wasRecentlyCreated ? $created++ : $updated++;
        }

        if ($this->command) {
            $this->command->info("Medical features: {$created} created, {$updated} updated.");
        }
    }
}
