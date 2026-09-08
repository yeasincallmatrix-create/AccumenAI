<?php

namespace Database\Seeders;

use App\Models\Institute;
use App\Models\Medical\Department;
use App\Models\Medical\Specialty;
use Illuminate\Database\Seeder;

class MedicalDepartmentSpecialtySeeder extends Seeder
{
    /**
     * Seed departments + specialties from the JSON data file.
     *
     * Data lives in database/seeders/data/departments_specialties.json so it
     * can be edited without touching code. Idempotent via firstOrCreate —
     * safe to re-run through MedicalDatabaseSeeder.
     */
    public function run(): void
    {
        // Central Hospital first (by uid), then any healthcare institute.
        $instituteId = Institute::where('uid', '9TZFSA0573')->first()?->id
            ?? Institute::where('industry', 'healthcare')->first()?->id
            ?? 1;

        $path = database_path('seeders/data/departments_specialties.json');

        if (! is_file($path)) {
            $this->command->error("Data file missing: {$path}");
            return;
        }

        $departmentsData = json_decode(file_get_contents($path), true);

        if (! is_array($departmentsData)) {
            $this->command->error('Invalid JSON in departments_specialties.json');
            return;
        }

        foreach ($departmentsData as $deptData) {
            if (empty($deptData['name'])) {
                continue;
            }

            $department = Department::firstOrCreate(
                ['institute_id' => $instituteId, 'name' => $deptData['name']],
                ['is_active' => true]
            );

            foreach ((array) ($deptData['specialties'] ?? []) as $specialtyName) {
                Specialty::firstOrCreate(
                    [
                        'institute_id' => $instituteId,
                        'department_id' => $department->id,
                        'name' => $specialtyName,
                    ],
                    ['is_active' => true]
                );
            }
        }

        // Guarantee at least one specialty per department so the cascade
        // dropdown never renders empty: departments without sub-specialties
        // get a "General {department}" entry (doctors may still register at
        // pure department level with specialty left null).
        $departments = Department::where('institute_id', $instituteId)->get();
        foreach ($departments as $dept) {
            if ($dept->specialties()->count() === 0) {
                Specialty::firstOrCreate(
                    [
                        'institute_id' => $instituteId,
                        'department_id' => $dept->id,
                        'name' => 'General ' . $dept->name,
                    ],
                    ['is_active' => true]
                );
            }
        }

        $this->command->info('Departments and Specialties seeded successfully!');
    }
}
