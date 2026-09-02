<?php

namespace Database\Seeders;

use App\Models\AcademicGroup;
use App\Models\AcademicLevel;
use App\Models\ClassGrade;
use App\Models\Country;
use App\Models\EducationSystem;
use Illuminate\Database\Seeder;

/**
 * Seed country-scoped academic structure masters:
 *
 *   Country → EducationSystem → AcademicLevel → ClassGrade → AcademicGroup
 *
 * Covers:
 *   - Bangladesh: General / Madrasa / Technical
 *   - United States: Public / Private / Homeschool
 *   - United Kingdom: National Curriculum / Private School / IB
 *
 * Idempotent via updateOrCreate on natural keys:
 *   EducationSystem: [country_id, code]
 *   AcademicLevel:   [education_system_id, code]
 *   ClassGrade:      [academic_level_id, code]
 *   AcademicGroup:   [class_grade_id, code]
 *
 * All display_order / status / names are reconciled on re-run.
 */
class AcademicStructureSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedBangladesh();
        $this->seedUnitedStates();
        $this->seedUnitedKingdom();
    }

    // ------------------------------------------------------------------ Bangladesh

    private function seedBangladesh(): void
    {
        $country = Country::where('name', 'Bangladesh')->first();
        if ($country === null) {
            $this->command?->warn('AcademicStructureSeeder: Bangladesh not found — skipping.');

            return;
        }

        // Systems: General, Madrasa, Technical
        $general = $this->system($country, 'general', 'General Education', 1);
        $madrasa = $this->system($country, 'madrasa', 'Madrasa Education', 2);
        $technical = $this->system($country, 'technical', 'Technical Education', 3);

        // ---------- General Education ----------
        $primary = $this->level($country, $general, 'primary', 'Primary', 1);
        $secondary = $this->level($country, $general, 'secondary', 'Secondary', 2);
        $higherSecondary = $this->level($country, $general, 'higher_secondary', 'Higher Secondary', 3);
        $tertiary = $this->level($country, $general, 'tertiary', 'Tertiary', 4);

        // Primary: Classes 1-5 (no groups)
        foreach (range(1, 5) as $n) {
            $this->classGrade($country, $general, $primary, (string) $n, "Class {$n}", $n);
        }

        // Secondary: Classes 6-10 with groups
        foreach (range(6, 10) as $n) {
            $cg = $this->classGrade($country, $general, $secondary, (string) $n, "Class {$n}", $n);
            $this->groupsForClass($country, $general, $secondary, $cg, [
                ['science', 'Science', 1],
                ['humanities', 'Humanities', 2],
                ['business', 'Business Studies', 3],
            ]);
        }

        // Higher Secondary: Classes 11-12 with groups
        foreach ([11, 12] as $n) {
            $cg = $this->classGrade($country, $general, $higherSecondary, (string) $n, "Class {$n}", $n);
            $this->groupsForClass($country, $general, $higherSecondary, $cg, [
                ['science', 'Science', 1],
                ['humanities', 'Humanities', 2],
                ['business', 'Business Studies', 3],
            ]);
        }

        // Tertiary: University level — placeholder classes (Year 1-4) no groups
        foreach (range(1, 4) as $n) {
            $this->classGrade($country, $general, $tertiary, (string) $n, "Year {$n}", $n);
        }

        // ---------- Madrasa Education ----------
        $ebtedayi = $this->level($country, $madrasa, 'ebtedayi', 'Ebtedayi', 1);
        $dakhil = $this->level($country, $madrasa, 'dakhil', 'Dakhil', 2);
        $alim = $this->level($country, $madrasa, 'alim', 'Alim', 3);
        $fazil = $this->level($country, $madrasa, 'fazil', 'Fazil', 4);
        $kamil = $this->level($country, $madrasa, 'kamil', 'Kamil', 5);

        foreach (range(1, 5) as $n) {
            $this->classGrade($country, $madrasa, $ebtedayi, (string) $n, "Class {$n}", $n);
        }
        foreach (range(6, 10) as $n) {
            $cg = $this->classGrade($country, $madrasa, $dakhil, (string) $n, "Class {$n}", $n);
            $this->groupsForClass($country, $madrasa, $dakhil, $cg, [
                ['science', 'Science', 1],
                ['humanities', 'Humanities', 2],
                ['business', 'Business Studies', 3],
            ]);
        }
        foreach ([11, 12] as $n) {
            $cg = $this->classGrade($country, $madrasa, $alim, (string) $n, "Class {$n}", $n);
            $this->groupsForClass($country, $madrasa, $alim, $cg, [
                ['science', 'Science', 1],
                ['humanities', 'Humanities', 2],
                ['business', 'Business Studies', 3],
            ]);
        }
        // Fazil (Degree) — Year 1-3
        foreach (range(1, 3) as $n) {
            $this->classGrade($country, $madrasa, $fazil, (string) $n, "Year {$n}", $n);
        }
        // Kamil (Masters) — Year 1-2
        foreach (range(1, 2) as $n) {
            $this->classGrade($country, $madrasa, $kamil, (string) $n, "Year {$n}", $n);
        }

        // ---------- Technical Education ----------
        $vocational = $this->level($country, $technical, 'vocational', 'Vocational', 1);
        $diploma = $this->level($country, $technical, 'diploma', 'Diploma', 2);
        $degree = $this->level($country, $technical, 'degree', 'Degree', 3);

        // Vocational: SSC Vocational (Class 9-10)
        foreach ([9, 10] as $n) {
            $this->classGrade($country, $technical, $vocational, (string) $n, "Class {$n}", $n);
        }
        // Diploma: Semester 1-8
        foreach (range(1, 8) as $n) {
            $this->classGrade($country, $technical, $diploma, (string) $n, "Semester {$n}", $n);
        }
        // Degree: Semester 1-8
        foreach (range(1, 8) as $n) {
            $this->classGrade($country, $technical, $degree, (string) $n, "Semester {$n}", $n);
        }
    }

    // ------------------------------------------------------------------ United States

    private function seedUnitedStates(): void
    {
        $country = Country::where('name', 'United States')->first();
        if ($country === null) {
            $this->command?->warn('AcademicStructureSeeder: United States not found — skipping.');

            return;
        }

        $systems = [
            ['code' => 'public', 'name' => 'Public Education', 'order' => 1],
            ['code' => 'private', 'name' => 'Private Education', 'order' => 2],
            ['code' => 'homeschool', 'name' => 'Homeschool', 'order' => 3],
        ];

        // Level definitions shared across US systems
        $levelDefs = [
            ['code' => 'elementary', 'name' => 'Elementary School', 'order' => 1, 'classes' => [
                ['code' => 'k', 'name' => 'Kindergarten', 'order' => 0, 'seq' => 0],
                ['code' => '1', 'name' => 'Grade 1', 'order' => 1, 'seq' => 1],
                ['code' => '2', 'name' => 'Grade 2', 'order' => 2, 'seq' => 2],
                ['code' => '3', 'name' => 'Grade 3', 'order' => 3, 'seq' => 3],
                ['code' => '4', 'name' => 'Grade 4', 'order' => 4, 'seq' => 4],
                ['code' => '5', 'name' => 'Grade 5', 'order' => 5, 'seq' => 5],
            ], 'groups' => []],
            ['code' => 'middle', 'name' => 'Middle School', 'order' => 2, 'classes' => [
                ['code' => '6', 'name' => 'Grade 6', 'order' => 1, 'seq' => 6],
                ['code' => '7', 'name' => 'Grade 7', 'order' => 2, 'seq' => 7],
                ['code' => '8', 'name' => 'Grade 8', 'order' => 3, 'seq' => 8],
            ], 'groups' => []],
            ['code' => 'high', 'name' => 'High School', 'order' => 3, 'classes' => [
                ['code' => '9', 'name' => 'Grade 9', 'order' => 1, 'seq' => 9],
                ['code' => '10', 'name' => 'Grade 10', 'order' => 2, 'seq' => 10],
                ['code' => '11', 'name' => 'Grade 11', 'order' => 3, 'seq' => 11],
                ['code' => '12', 'name' => 'Grade 12', 'order' => 4, 'seq' => 12],
            ], 'groups' => [
                ['science', 'Science', 1],
                ['arts', 'Arts', 2],
                ['business', 'Business', 3],
                ['vocational', 'Vocational', 4],
            ]],
            ['code' => 'college', 'name' => 'College', 'order' => 4, 'classes' => [
                ['code' => '1', 'name' => 'Year 1', 'order' => 1, 'seq' => 13],
                ['code' => '2', 'name' => 'Year 2', 'order' => 2, 'seq' => 14],
                ['code' => '3', 'name' => 'Year 3', 'order' => 3, 'seq' => 15],
                ['code' => '4', 'name' => 'Year 4', 'order' => 4, 'seq' => 16],
            ], 'groups' => []],
            ['code' => 'university', 'name' => 'University', 'order' => 5, 'classes' => [
                ['code' => '1', 'name' => 'Year 1', 'order' => 1, 'seq' => 17],
                ['code' => '2', 'name' => 'Year 2', 'order' => 2, 'seq' => 18],
            ], 'groups' => []],
        ];

        foreach ($systems as $sysDef) {
            $system = $this->system($country, $sysDef['code'], $sysDef['name'], $sysDef['order']);

            foreach ($levelDefs as $lvlDef) {
                $level = $this->level($country, $system, $lvlDef['code'], $lvlDef['name'], $lvlDef['order']);

                foreach ($lvlDef['classes'] as $cls) {
                    $cg = $this->classGrade($country, $system, $level, $cls['code'], $cls['name'], $cls['order'], $cls['seq']);
                    if ($lvlDef['groups'] !== []) {
                        $this->groupsForClass($country, $system, $level, $cg, $lvlDef['groups']);
                    }
                }
            }
        }
    }

    // ------------------------------------------------------------------ United Kingdom

    private function seedUnitedKingdom(): void
    {
        $country = Country::where('name', 'United Kingdom')->first();
        if ($country === null) {
            $this->command?->warn('AcademicStructureSeeder: United Kingdom not found — skipping.');

            return;
        }

        $systems = [
            ['code' => 'national', 'name' => 'National Curriculum', 'order' => 1],
            ['code' => 'private', 'name' => 'Private School', 'order' => 2],
            ['code' => 'ib', 'name' => 'International Baccalaureate', 'order' => 3],
        ];

        $levelDefs = [
            ['code' => 'primary', 'name' => 'Primary', 'order' => 1, 'classes' => [
                ['code' => '1', 'name' => 'Year 1', 'order' => 1, 'seq' => 1],
                ['code' => '2', 'name' => 'Year 2', 'order' => 2, 'seq' => 2],
                ['code' => '3', 'name' => 'Year 3', 'order' => 3, 'seq' => 3],
                ['code' => '4', 'name' => 'Year 4', 'order' => 4, 'seq' => 4],
                ['code' => '5', 'name' => 'Year 5', 'order' => 5, 'seq' => 5],
                ['code' => '6', 'name' => 'Year 6', 'order' => 6, 'seq' => 6],
            ], 'groups' => []],
            ['code' => 'secondary', 'name' => 'Secondary', 'order' => 2, 'classes' => [
                ['code' => '7', 'name' => 'Year 7', 'order' => 1, 'seq' => 7],
                ['code' => '8', 'name' => 'Year 8', 'order' => 2, 'seq' => 8],
                ['code' => '9', 'name' => 'Year 9', 'order' => 3, 'seq' => 9],
                ['code' => '10', 'name' => 'Year 10', 'order' => 4, 'seq' => 10],
                ['code' => '11', 'name' => 'Year 11', 'order' => 5, 'seq' => 11],
            ], 'groups' => [
                ['science', 'Science', 1],
                ['humanities', 'Humanities', 2],
                ['arts', 'Arts', 3],
                ['vocational', 'Vocational', 4],
            ]],
            ['code' => 'sixth_form', 'name' => 'Sixth Form', 'order' => 3, 'classes' => [
                ['code' => '12', 'name' => 'Year 12', 'order' => 1, 'seq' => 12],
                ['code' => '13', 'name' => 'Year 13', 'order' => 2, 'seq' => 13],
            ], 'groups' => []],
            ['code' => 'university', 'name' => 'University', 'order' => 4, 'classes' => [
                ['code' => '1', 'name' => 'Year 1', 'order' => 1, 'seq' => 14],
                ['code' => '2', 'name' => 'Year 2', 'order' => 2, 'seq' => 15],
                ['code' => '3', 'name' => 'Year 3', 'order' => 3, 'seq' => 16],
            ], 'groups' => []],
        ];

        foreach ($systems as $sysDef) {
            $system = $this->system($country, $sysDef['code'], $sysDef['name'], $sysDef['order']);

            foreach ($levelDefs as $lvlDef) {
                $level = $this->level($country, $system, $lvlDef['code'], $lvlDef['name'], $lvlDef['order']);

                foreach ($lvlDef['classes'] as $cls) {
                    $cg = $this->classGrade($country, $system, $level, $cls['code'], $cls['name'], $cls['order'], $cls['seq']);
                    if ($lvlDef['groups'] !== []) {
                        $this->groupsForClass($country, $system, $level, $cg, $lvlDef['groups']);
                    }
                }
            }
        }
    }

    // ------------------------------------------------------------------ Helpers

    private function system(Country $country, string $code, string $name, int $order): EducationSystem
    {
        return EducationSystem::updateOrCreate(
            ['country_id' => $country->id, 'code' => $code],
            ['name' => $name, 'display_order' => $order, 'status' => true]
        );
    }

    private function level(Country $country, EducationSystem $system, string $code, string $name, int $order): AcademicLevel
    {
        return AcademicLevel::updateOrCreate(
            ['education_system_id' => $system->id, 'code' => $code],
            ['country_id' => $country->id, 'name' => $name, 'display_order' => $order, 'status' => true]
        );
    }

    private function classGrade(Country $country, EducationSystem $system, AcademicLevel $level, string $code, string $name, int $order, ?int $sequence = null): ClassGrade
    {
        return ClassGrade::updateOrCreate(
            ['academic_level_id' => $level->id, 'code' => $code],
            [
                'country_id' => $country->id,
                'education_system_id' => $system->id,
                'name' => $name,
                'sequence' => $sequence ?? (is_numeric($code) ? (int) $code : null),
                'display_order' => $order,
                'status' => true,
            ]
        );
    }

    /**
     * @param  array<int, array{0:string,1:string,2:int}>  $groups  [code, name, order]
     */
    private function groupsForClass(Country $country, EducationSystem $system, AcademicLevel $level, ClassGrade $classGrade, array $groups): void
    {
        foreach ($groups as [$code, $name, $order]) {
            AcademicGroup::updateOrCreate(
                ['class_grade_id' => $classGrade->id, 'code' => $code],
                [
                    'country_id' => $country->id,
                    'education_system_id' => $system->id,
                    'academic_level_id' => $level->id,
                    'name' => $name,
                    'display_order' => $order,
                    'status' => true,
                ]
            );
        }
    }
}
