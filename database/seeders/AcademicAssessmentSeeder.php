<?php

namespace Database\Seeders;

use App\Models\AssessmentType;
use App\Models\Component;
use Illuminate\Database\Seeder;

/**
 * Seeds the GLOBAL default academic assessment types and components.
 *
 * These rows have institute_id = NULL, so every institute inherits them (the
 * AssessmentType/Component `availableFor` scope merges global + institute rows).
 * Idempotent via updateOrCreate on the (institute_id, slug) unique key.
 */
class AcademicAssessmentSeeder extends Seeder
{
    public const DEFAULT_TYPES = [
        ['slug' => 'first-term', 'name' => 'First Term', 'display_order' => 1],
        ['slug' => 'second-term', 'name' => 'Second Term', 'display_order' => 2],
        ['slug' => 'mid-term', 'name' => 'Mid Term', 'display_order' => 3],
        ['slug' => 'half-yearly', 'name' => 'Half Yearly', 'display_order' => 4],
        ['slug' => 'final', 'name' => 'Final', 'display_order' => 5],
        ['slug' => 'class-test', 'name' => 'Class Test', 'display_order' => 6],
        ['slug' => 'quiz', 'name' => 'Quiz', 'display_order' => 7],
    ];

    public const DEFAULT_COMPONENTS = [
        ['slug' => 'written', 'name' => 'Written', 'display_order' => 1],
        ['slug' => 'mcq', 'name' => 'MCQ', 'display_order' => 2],
        ['slug' => 'practical', 'name' => 'Practical', 'display_order' => 3],
        ['slug' => 'viva', 'name' => 'Viva', 'display_order' => 4],
        ['slug' => 'attendance', 'name' => 'Attendance', 'display_order' => 5],
        ['slug' => 'assignment', 'name' => 'Assignment', 'display_order' => 6],
        ['slug' => 'project', 'name' => 'Project', 'display_order' => 7],
        ['slug' => 'presentation', 'name' => 'Presentation', 'display_order' => 8],
        ['slug' => 'class-work', 'name' => 'Class Work', 'display_order' => 9],
        ['slug' => 'lab', 'name' => 'Lab', 'display_order' => 10],
        ['slug' => 'portfolio', 'name' => 'Portfolio', 'display_order' => 11],
        ['slug' => 'other', 'name' => 'Other', 'display_order' => 12],
    ];

    public function run(): void
    {
        foreach (self::DEFAULT_TYPES as $type) {
            AssessmentType::updateOrCreate(
                ['institute_id' => null, 'slug' => $type['slug']],
                ['name' => $type['name'], 'display_order' => $type['display_order'], 'status' => true]
            );
        }

        foreach (self::DEFAULT_COMPONENTS as $component) {
            Component::updateOrCreate(
                ['institute_id' => null, 'slug' => $component['slug']],
                ['name' => $component['name'], 'display_order' => $component['display_order'], 'status' => true]
            );
        }
    }
}
