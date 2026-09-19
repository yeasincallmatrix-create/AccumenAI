<?php

namespace Database\Seeders;

use App\Models\DocumentCategory;
use Illuminate\Database\Seeder;

/**
 * Seeds the global document categories tests look up by slug
 * (photo, resume, hr-*, birth-certificate, contract, other).
 * Global rows use institute_id NULL per the model contract.
 * Idempotent via firstOrCreate. Seeded for tests (B82).
 */
class DocumentCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['slug' => 'photo', 'name' => 'Photo', 'sort_order' => 1],
            ['slug' => 'resume', 'name' => 'Resume', 'sort_order' => 2],
            ['slug' => 'birth-certificate', 'name' => 'Birth Certificate', 'sort_order' => 3],
            ['slug' => 'contract', 'name' => 'Contract', 'sort_order' => 4],
            ['slug' => 'other', 'name' => 'Other', 'sort_order' => 5],
            ['slug' => 'hr-other', 'name' => 'HR Other', 'sort_order' => 6],
            ['slug' => 'hr-cv-resume', 'name' => 'HR CV / Resume', 'sort_order' => 7],
            ['slug' => 'hr-nid-passport', 'name' => 'HR NID / Passport', 'sort_order' => 8],
            ['slug' => 'hr-professional-certificate', 'name' => 'HR Professional Certificate', 'sort_order' => 9],
            ['slug' => 'hr-appointment-letter', 'name' => 'HR Appointment Letter', 'sort_order' => 10],
        ] as $row) {
            DocumentCategory::firstOrCreate(
                ['slug' => $row['slug'], 'institute_id' => null],
                [
                    'name' => $row['name'],
                    'is_active' => true,
                    'sort_order' => $row['sort_order'],
                ]
            );
        }
    }
}
