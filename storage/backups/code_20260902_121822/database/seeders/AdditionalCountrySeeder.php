<?php

namespace Database\Seeders;

use App\Models\AcademicGroup;
use App\Models\AcademicLevel;
use App\Models\ClassGrade;
use App\Models\Country;
use App\Models\EducationSystem;
use App\Models\GradeScale;
use App\Models\GradeScaleRow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed 20 additional countries with:
 *  - Country row (if missing)
 *  - One National Education System + 4 levels + 16 classes + groups
 *  - One country-default GradeScale
 *
 * Idempotent via updateOrCreate / row reconciliation.
 * Does NOT touch existing BD/US/UK data (different country_id).
 */
class AdditionalCountrySeeder extends Seeder
{
    /**
     * @var array<int, array{name:string,iso2:string,iso3:string,phone?:string}>
     */
    private array $countries = [
        ['name' => 'India', 'iso2' => 'IN', 'iso3' => 'IND', 'phone' => '91'],
        ['name' => 'Singapore', 'iso2' => 'SG', 'iso3' => 'SGP', 'phone' => '65'],
        ['name' => 'Malaysia', 'iso2' => 'MY', 'iso3' => 'MYS', 'phone' => '60'],
        ['name' => 'Kuwait', 'iso2' => 'KW', 'iso3' => 'KWT', 'phone' => '965'],
        ['name' => 'Qatar', 'iso2' => 'QA', 'iso3' => 'QAT', 'phone' => '974'],
        ['name' => 'Saudi Arabia', 'iso2' => 'SA', 'iso3' => 'SAU', 'phone' => '966'],
        ['name' => 'Italy', 'iso2' => 'IT', 'iso3' => 'ITA', 'phone' => '39'],
        ['name' => 'Spain', 'iso2' => 'ES', 'iso3' => 'ESP', 'phone' => '34'],
        ['name' => 'France', 'iso2' => 'FR', 'iso3' => 'FRA', 'phone' => '33'],
        ['name' => 'Germany', 'iso2' => 'DE', 'iso3' => 'DEU', 'phone' => '49'],
        ['name' => 'Portugal', 'iso2' => 'PT', 'iso3' => 'PRT', 'phone' => '351'],
        ['name' => 'Pakistan', 'iso2' => 'PK', 'iso3' => 'PAK', 'phone' => '92'],
        ['name' => 'Australia', 'iso2' => 'AU', 'iso3' => 'AUS', 'phone' => '61'],
        ['name' => 'Canada', 'iso2' => 'CA', 'iso3' => 'CAN', 'phone' => '1'],
        ['name' => 'New Zealand', 'iso2' => 'NZ', 'iso3' => 'NZL', 'phone' => '64'],
        ['name' => 'Myanmar', 'iso2' => 'MM', 'iso3' => 'MMR', 'phone' => '95'],
        ['name' => 'Vietnam', 'iso2' => 'VN', 'iso3' => 'VNM', 'phone' => '84'],
        ['name' => 'Laos', 'iso2' => 'LA', 'iso3' => 'LAO', 'phone' => '856'],
        ['name' => 'Cambodia', 'iso2' => 'KH', 'iso3' => 'KHM', 'phone' => '855'],
        ['name' => 'Maldives', 'iso2' => 'MV', 'iso3' => 'MDV', 'phone' => '960'],
    ];

    public function run(): void
    {
        foreach ($this->countries as $def) {
            $country = $this->ensureCountry($def);
            $this->seedAcademicStructure($country);
            $this->seedGradeScale($country);
        }
    }

    private function ensureCountry(array $def): Country
    {
        // Match by iso2 first, then name
        $country = Country::where('iso2', $def['iso2'])->first();
        if ($country === null) {
            $country = Country::where('name', $def['name'])->first();
        }

        if ($country !== null) {
            // Ensure iso codes are filled
            $country->forceFill([
                'iso2' => $def['iso2'],
                'iso3' => $def['iso3'],
                'phone_code' => $def['phone'] ?? $country->phone_code,
                'status' => true,
            ])->save();

            return $country->refresh();
        }

        return Country::create([
            'name' => $def['name'],
            'iso2' => $def['iso2'],
            'iso3' => $def['iso3'],
            'phone_code' => $def['phone'] ?? null,
            'academic_unit_label' => null,
            'status' => true,
        ]);
    }

    private function seedAcademicStructure(Country $country): void
    {
        $system = EducationSystem::updateOrCreate(
            ['country_id' => $country->id, 'code' => 'national'],
            ['name' => $country->name . ' National Education System', 'display_order' => 1, 'status' => true]
        );

        $primary = AcademicLevel::updateOrCreate(
            ['education_system_id' => $system->id, 'code' => 'primary'],
            ['country_id' => $country->id, 'name' => 'Primary', 'display_order' => 1, 'status' => true]
        );
        $secondary = AcademicLevel::updateOrCreate(
            ['education_system_id' => $system->id, 'code' => 'secondary'],
            ['country_id' => $country->id, 'name' => 'Secondary', 'display_order' => 2, 'status' => true]
        );
        $higherSecondary = AcademicLevel::updateOrCreate(
            ['education_system_id' => $system->id, 'code' => 'higher_secondary'],
            ['country_id' => $country->id, 'name' => 'Higher Secondary', 'display_order' => 3, 'status' => true]
        );
        $tertiary = AcademicLevel::updateOrCreate(
            ['education_system_id' => $system->id, 'code' => 'tertiary'],
            ['country_id' => $country->id, 'name' => 'Tertiary', 'display_order' => 4, 'status' => true]
        );

        // Primary: 1-5
        foreach (range(1, 5) as $n) {
            $this->classGrade($country, $system, $primary, (string) $n, "Class {$n}", $n);
        }
        // Secondary: 6-10 with groups
        foreach (range(6, 10) as $n) {
            $cg = $this->classGrade($country, $system, $secondary, (string) $n, "Class {$n}", $n);
            $this->groupsForClass($country, $system, $secondary, $cg);
        }
        // Higher Secondary: 11-12 with groups
        foreach ([11, 12] as $n) {
            $cg = $this->classGrade($country, $system, $higherSecondary, (string) $n, "Class {$n}", $n);
            $this->groupsForClass($country, $system, $higherSecondary, $cg);
        }
        // Tertiary: 1-4
        foreach (range(1, 4) as $n) {
            $this->classGrade($country, $system, $tertiary, (string) $n, "Year {$n}", $n);
        }
    }

    private function classGrade(Country $country, EducationSystem $system, AcademicLevel $level, string $code, string $name, int $order): ClassGrade
    {
        return ClassGrade::updateOrCreate(
            ['academic_level_id' => $level->id, 'code' => $code],
            [
                'country_id' => $country->id,
                'education_system_id' => $system->id,
                'name' => $name,
                'sequence' => is_numeric($code) ? (int) $code : null,
                'display_order' => $order,
                'status' => true,
            ]
        );
    }

    private function groupsForClass(Country $country, EducationSystem $system, AcademicLevel $level, ClassGrade $classGrade): void
    {
        $groups = [
            ['science', 'Science', 1],
            ['humanities', 'Humanities', 2],
            ['business', 'Business Studies', 3],
        ];
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

    private function seedGradeScale(Country $country): void
    {
        $definition = $this->gradeDefinitionFor($country->name);
        $this->upsertScale(
            [
                'institute_id' => null,
                'country_id' => $country->id,
                'education_system_id' => null,
                'academic_level_id' => null,
            ],
            $definition
        );
    }

    private function gradeDefinitionFor(string $countryName): array
    {
        $common = [
            'gpa_mode' => GradeScale::GPA_MODE_EQUAL_WEIGHT,
            'optional_subject_gpa' => GradeScale::OPTIONAL_SUBJECT_GPA_INCLUDED,
            'optional_subject_bonus_threshold' => 2.00,
            'optional_subject_bonus_enabled' => true,
            'multiple_optional_policy' => GradeScale::MULTIPLE_OPTIONAL_BEST,
            'marks_decimal_places' => 2,
            'percentage_decimal_places' => 2,
            'gpa_decimal_places' => 2,
            'cgpa_decimal_places' => 2,
            'rounding_mode' => GradeScale::ROUNDING_HALF_UP,
            'display_order' => 0,
            'status' => true,
        ];

        return match ($countryName) {
            'India' => array_merge($common, [
                'name' => 'India National Grade Scale',
                'max_gpa' => 10.00,
                'rows' => [
                    ['grade' => 'A1', 'min_score' => 91, 'max_score' => 100, 'grade_point' => 10.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 1, 'status' => true],
                    ['grade' => 'A2', 'min_score' => 81, 'max_score' => 90, 'grade_point' => 9.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 2, 'status' => true],
                    ['grade' => 'B1', 'min_score' => 71, 'max_score' => 80, 'grade_point' => 8.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 3, 'status' => true],
                    ['grade' => 'B2', 'min_score' => 61, 'max_score' => 70, 'grade_point' => 7.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 4, 'status' => true],
                    ['grade' => 'C1', 'min_score' => 51, 'max_score' => 60, 'grade_point' => 6.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 5, 'status' => true],
                    ['grade' => 'C2', 'min_score' => 41, 'max_score' => 50, 'grade_point' => 5.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 6, 'status' => true],
                    ['grade' => 'D', 'min_score' => 33, 'max_score' => 40, 'grade_point' => 4.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 7, 'status' => true],
                    ['grade' => 'E', 'min_score' => 0, 'max_score' => 32, 'grade_point' => 0.00, 'is_pass' => false, 'gpa_included' => true, 'display_order' => 8, 'status' => true],
                ],
            ]),
            'Singapore' => array_merge($common, [
                'name' => 'Singapore National Grade Scale',
                'max_gpa' => 5.00,
                'rows' => [
                    ['grade' => 'A', 'min_score' => 80, 'max_score' => 100, 'grade_point' => 5.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 1, 'status' => true],
                    ['grade' => 'B', 'min_score' => 70, 'max_score' => 79, 'grade_point' => 4.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 2, 'status' => true],
                    ['grade' => 'C', 'min_score' => 60, 'max_score' => 69, 'grade_point' => 3.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 3, 'status' => true],
                    ['grade' => 'D', 'min_score' => 50, 'max_score' => 59, 'grade_point' => 2.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 4, 'status' => true],
                    ['grade' => 'F', 'min_score' => 0, 'max_score' => 49, 'grade_point' => 0.00, 'is_pass' => false, 'gpa_included' => true, 'display_order' => 5, 'status' => true],
                ],
            ]),
            'Malaysia' => array_merge($common, [
                'name' => 'Malaysia National Grade Scale',
                'max_gpa' => 4.00,
                'rows' => [
                    ['grade' => 'A', 'min_score' => 80, 'max_score' => 100, 'grade_point' => 4.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 1, 'status' => true],
                    ['grade' => 'B', 'min_score' => 65, 'max_score' => 79, 'grade_point' => 3.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 2, 'status' => true],
                    ['grade' => 'C', 'min_score' => 50, 'max_score' => 64, 'grade_point' => 2.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 3, 'status' => true],
                    ['grade' => 'D', 'min_score' => 40, 'max_score' => 49, 'grade_point' => 1.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 4, 'status' => true],
                    ['grade' => 'F', 'min_score' => 0, 'max_score' => 39, 'grade_point' => 0.00, 'is_pass' => false, 'gpa_included' => true, 'display_order' => 5, 'status' => true],
                ],
            ]),
            'Kuwait', 'Qatar', 'Saudi Arabia' => array_merge($common, [
                'name' => $countryName . ' National Grade Scale',
                'max_gpa' => 4.00,
                'rows' => [
                    ['grade' => 'A', 'min_score' => 90, 'max_score' => 100, 'grade_point' => 4.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 1, 'status' => true],
                    ['grade' => 'B', 'min_score' => 80, 'max_score' => 89, 'grade_point' => 3.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 2, 'status' => true],
                    ['grade' => 'C', 'min_score' => 70, 'max_score' => 79, 'grade_point' => 2.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 3, 'status' => true],
                    ['grade' => 'D', 'min_score' => 60, 'max_score' => 69, 'grade_point' => 1.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 4, 'status' => true],
                    ['grade' => 'F', 'min_score' => 0, 'max_score' => 59, 'grade_point' => 0.00, 'is_pass' => false, 'gpa_included' => true, 'display_order' => 5, 'status' => true],
                ],
            ]),
            'Italy' => array_merge($common, [
                'name' => 'Italy National Grade Scale',
                'max_gpa' => 10.00,
                'rows' => [
                    ['grade' => 'A', 'min_score' => 90, 'max_score' => 100, 'grade_point' => 10.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 1, 'status' => true],
                    ['grade' => 'B', 'min_score' => 80, 'max_score' => 89, 'grade_point' => 9.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 2, 'status' => true],
                    ['grade' => 'C', 'min_score' => 70, 'max_score' => 79, 'grade_point' => 8.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 3, 'status' => true],
                    ['grade' => 'D', 'min_score' => 60, 'max_score' => 69, 'grade_point' => 7.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 4, 'status' => true],
                    ['grade' => 'F', 'min_score' => 0, 'max_score' => 59, 'grade_point' => 0.00, 'is_pass' => false, 'gpa_included' => true, 'display_order' => 5, 'status' => true],
                ],
            ]),
            'Spain' => array_merge($common, [
                'name' => 'Spain National Grade Scale',
                'max_gpa' => 10.00,
                'rows' => [
                    ['grade' => 'A', 'min_score' => 90, 'max_score' => 100, 'grade_point' => 10.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 1, 'status' => true],
                    ['grade' => 'B', 'min_score' => 80, 'max_score' => 89, 'grade_point' => 9.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 2, 'status' => true],
                    ['grade' => 'C', 'min_score' => 70, 'max_score' => 79, 'grade_point' => 8.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 3, 'status' => true],
                    ['grade' => 'D', 'min_score' => 60, 'max_score' => 69, 'grade_point' => 7.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 4, 'status' => true],
                    ['grade' => 'E', 'min_score' => 50, 'max_score' => 59, 'grade_point' => 6.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 5, 'status' => true],
                    ['grade' => 'F', 'min_score' => 0, 'max_score' => 49, 'grade_point' => 0.00, 'is_pass' => false, 'gpa_included' => true, 'display_order' => 6, 'status' => true],
                ],
            ]),
            'France' => array_merge($common, [
                'name' => 'France National Grade Scale',
                'max_gpa' => 20.00,
                'rows' => [
                    ['grade' => 'A', 'min_score' => 16, 'max_score' => 20, 'grade_point' => 20.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 1, 'status' => true],
                    ['grade' => 'B', 'min_score' => 14, 'max_score' => 15, 'grade_point' => 18.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 2, 'status' => true],
                    ['grade' => 'C', 'min_score' => 12, 'max_score' => 13, 'grade_point' => 16.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 3, 'status' => true],
                    ['grade' => 'D', 'min_score' => 10, 'max_score' => 11, 'grade_point' => 14.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 4, 'status' => true],
                    ['grade' => 'F', 'min_score' => 0, 'max_score' => 9, 'grade_point' => 0.00, 'is_pass' => false, 'gpa_included' => true, 'display_order' => 5, 'status' => true],
                ],
            ]),
            'Germany' => array_merge($common, [
                'name' => 'Germany National Grade Scale',
                'max_gpa' => 5.00,
                'rows' => [
                    ['grade' => '1', 'min_score' => 90, 'max_score' => 100, 'grade_point' => 4.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 1, 'status' => true],
                    ['grade' => '2', 'min_score' => 80, 'max_score' => 89, 'grade_point' => 3.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 2, 'status' => true],
                    ['grade' => '3', 'min_score' => 70, 'max_score' => 79, 'grade_point' => 2.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 3, 'status' => true],
                    ['grade' => '4', 'min_score' => 60, 'max_score' => 69, 'grade_point' => 1.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 4, 'status' => true],
                    ['grade' => '5', 'min_score' => 0, 'max_score' => 59, 'grade_point' => 0.00, 'is_pass' => false, 'gpa_included' => true, 'display_order' => 5, 'status' => true],
                ],
            ]),
            'Portugal' => array_merge($common, [
                'name' => 'Portugal National Grade Scale',
                'max_gpa' => 20.00,
                'rows' => [
                    ['grade' => 'A', 'min_score' => 90, 'max_score' => 100, 'grade_point' => 20.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 1, 'status' => true],
                    ['grade' => 'B', 'min_score' => 80, 'max_score' => 89, 'grade_point' => 18.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 2, 'status' => true],
                    ['grade' => 'C', 'min_score' => 70, 'max_score' => 79, 'grade_point' => 16.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 3, 'status' => true],
                    ['grade' => 'D', 'min_score' => 60, 'max_score' => 69, 'grade_point' => 14.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 4, 'status' => true],
                    ['grade' => 'E', 'min_score' => 50, 'max_score' => 59, 'grade_point' => 12.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 5, 'status' => true],
                    ['grade' => 'F', 'min_score' => 0, 'max_score' => 49, 'grade_point' => 0.00, 'is_pass' => false, 'gpa_included' => true, 'display_order' => 6, 'status' => true],
                ],
            ]),
            'Pakistan' => array_merge($common, [
                'name' => 'Pakistan National Grade Scale',
                'max_gpa' => 4.00,
                'rows' => [
                    ['grade' => 'A', 'min_score' => 80, 'max_score' => 100, 'grade_point' => 4.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 1, 'status' => true],
                    ['grade' => 'B', 'min_score' => 65, 'max_score' => 79, 'grade_point' => 3.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 2, 'status' => true],
                    ['grade' => 'C', 'min_score' => 50, 'max_score' => 64, 'grade_point' => 2.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 3, 'status' => true],
                    ['grade' => 'D', 'min_score' => 40, 'max_score' => 49, 'grade_point' => 1.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 4, 'status' => true],
                    ['grade' => 'F', 'min_score' => 0, 'max_score' => 39, 'grade_point' => 0.00, 'is_pass' => false, 'gpa_included' => true, 'display_order' => 5, 'status' => true],
                ],
            ]),
            'Australia' => array_merge($common, [
                'name' => 'Australia National Grade Scale',
                'max_gpa' => 7.00,
                'rows' => [
                    ['grade' => 'A', 'min_score' => 85, 'max_score' => 100, 'grade_point' => 7.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 1, 'status' => true],
                    ['grade' => 'B', 'min_score' => 75, 'max_score' => 84, 'grade_point' => 6.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 2, 'status' => true],
                    ['grade' => 'C', 'min_score' => 65, 'max_score' => 74, 'grade_point' => 5.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 3, 'status' => true],
                    ['grade' => 'D', 'min_score' => 55, 'max_score' => 64, 'grade_point' => 4.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 4, 'status' => true],
                    ['grade' => 'E', 'min_score' => 50, 'max_score' => 54, 'grade_point' => 3.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 5, 'status' => true],
                    ['grade' => 'F', 'min_score' => 0, 'max_score' => 49, 'grade_point' => 0.00, 'is_pass' => false, 'gpa_included' => true, 'display_order' => 6, 'status' => true],
                ],
            ]),
            'Canada' => array_merge($common, [
                'name' => 'Canada National Grade Scale',
                'max_gpa' => 4.00,
                'rows' => [
                    ['grade' => 'A', 'min_score' => 80, 'max_score' => 100, 'grade_point' => 4.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 1, 'status' => true],
                    ['grade' => 'B', 'min_score' => 70, 'max_score' => 79, 'grade_point' => 3.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 2, 'status' => true],
                    ['grade' => 'C', 'min_score' => 60, 'max_score' => 69, 'grade_point' => 2.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 3, 'status' => true],
                    ['grade' => 'D', 'min_score' => 50, 'max_score' => 59, 'grade_point' => 1.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 4, 'status' => true],
                    ['grade' => 'F', 'min_score' => 0, 'max_score' => 49, 'grade_point' => 0.00, 'is_pass' => false, 'gpa_included' => true, 'display_order' => 5, 'status' => true],
                ],
            ]),
            'New Zealand' => array_merge($common, [
                'name' => 'New Zealand National Grade Scale',
                'max_gpa' => 9.00,
                'rows' => [
                    ['grade' => 'A', 'min_score' => 85, 'max_score' => 100, 'grade_point' => 9.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 1, 'status' => true],
                    ['grade' => 'B', 'min_score' => 75, 'max_score' => 84, 'grade_point' => 8.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 2, 'status' => true],
                    ['grade' => 'C', 'min_score' => 65, 'max_score' => 74, 'grade_point' => 7.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 3, 'status' => true],
                    ['grade' => 'D', 'min_score' => 55, 'max_score' => 64, 'grade_point' => 6.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 4, 'status' => true],
                    ['grade' => 'E', 'min_score' => 50, 'max_score' => 54, 'grade_point' => 5.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 5, 'status' => true],
                    ['grade' => 'F', 'min_score' => 0, 'max_score' => 49, 'grade_point' => 0.00, 'is_pass' => false, 'gpa_included' => true, 'display_order' => 6, 'status' => true],
                ],
            ]),
            'Myanmar' => array_merge($common, [
                'name' => 'Myanmar National Grade Scale',
                'max_gpa' => 5.00,
                'rows' => [
                    ['grade' => 'A', 'min_score' => 80, 'max_score' => 100, 'grade_point' => 5.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 1, 'status' => true],
                    ['grade' => 'B', 'min_score' => 70, 'max_score' => 79, 'grade_point' => 4.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 2, 'status' => true],
                    ['grade' => 'C', 'min_score' => 60, 'max_score' => 69, 'grade_point' => 3.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 3, 'status' => true],
                    ['grade' => 'D', 'min_score' => 50, 'max_score' => 59, 'grade_point' => 2.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 4, 'status' => true],
                    ['grade' => 'E', 'min_score' => 40, 'max_score' => 49, 'grade_point' => 1.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 5, 'status' => true],
                    ['grade' => 'F', 'min_score' => 0, 'max_score' => 39, 'grade_point' => 0.00, 'is_pass' => false, 'gpa_included' => true, 'display_order' => 6, 'status' => true],
                ],
            ]),
            'Vietnam' => array_merge($common, [
                'name' => 'Vietnam National Grade Scale',
                'max_gpa' => 10.00,
                'rows' => [
                    ['grade' => 'A', 'min_score' => 90, 'max_score' => 100, 'grade_point' => 10.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 1, 'status' => true],
                    ['grade' => 'B', 'min_score' => 80, 'max_score' => 89, 'grade_point' => 9.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 2, 'status' => true],
                    ['grade' => 'C', 'min_score' => 70, 'max_score' => 79, 'grade_point' => 8.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 3, 'status' => true],
                    ['grade' => 'D', 'min_score' => 60, 'max_score' => 69, 'grade_point' => 7.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 4, 'status' => true],
                    ['grade' => 'E', 'min_score' => 50, 'max_score' => 59, 'grade_point' => 6.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 5, 'status' => true],
                    ['grade' => 'F', 'min_score' => 0, 'max_score' => 49, 'grade_point' => 0.00, 'is_pass' => false, 'gpa_included' => true, 'display_order' => 6, 'status' => true],
                ],
            ]),
            'Laos' => array_merge($common, [
                'name' => 'Laos National Grade Scale',
                'max_gpa' => 10.00,
                'rows' => [
                    ['grade' => 'A', 'min_score' => 90, 'max_score' => 100, 'grade_point' => 10.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 1, 'status' => true],
                    ['grade' => 'B', 'min_score' => 80, 'max_score' => 89, 'grade_point' => 9.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 2, 'status' => true],
                    ['grade' => 'C', 'min_score' => 70, 'max_score' => 79, 'grade_point' => 8.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 3, 'status' => true],
                    ['grade' => 'D', 'min_score' => 60, 'max_score' => 69, 'grade_point' => 7.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 4, 'status' => true],
                    ['grade' => 'E', 'min_score' => 50, 'max_score' => 59, 'grade_point' => 6.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 5, 'status' => true],
                    ['grade' => 'F', 'min_score' => 0, 'max_score' => 49, 'grade_point' => 0.00, 'is_pass' => false, 'gpa_included' => true, 'display_order' => 6, 'status' => true],
                ],
            ]),
            'Cambodia' => array_merge($common, [
                'name' => 'Cambodia National Grade Scale',
                'max_gpa' => 5.00,
                'rows' => [
                    ['grade' => 'A', 'min_score' => 80, 'max_score' => 100, 'grade_point' => 5.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 1, 'status' => true],
                    ['grade' => 'B', 'min_score' => 70, 'max_score' => 79, 'grade_point' => 4.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 2, 'status' => true],
                    ['grade' => 'C', 'min_score' => 60, 'max_score' => 69, 'grade_point' => 3.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 3, 'status' => true],
                    ['grade' => 'D', 'min_score' => 50, 'max_score' => 59, 'grade_point' => 2.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 4, 'status' => true],
                    ['grade' => 'F', 'min_score' => 0, 'max_score' => 49, 'grade_point' => 0.00, 'is_pass' => false, 'gpa_included' => true, 'display_order' => 5, 'status' => true],
                ],
            ]),
            'Maldives' => array_merge($common, [
                'name' => 'Maldives National Grade Scale',
                'max_gpa' => 5.00,
                'rows' => [
                    ['grade' => 'A', 'min_score' => 80, 'max_score' => 100, 'grade_point' => 5.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 1, 'status' => true],
                    ['grade' => 'B', 'min_score' => 70, 'max_score' => 79, 'grade_point' => 4.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 2, 'status' => true],
                    ['grade' => 'C', 'min_score' => 60, 'max_score' => 69, 'grade_point' => 3.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 3, 'status' => true],
                    ['grade' => 'D', 'min_score' => 50, 'max_score' => 59, 'grade_point' => 2.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 4, 'status' => true],
                    ['grade' => 'F', 'min_score' => 0, 'max_score' => 49, 'grade_point' => 0.00, 'is_pass' => false, 'gpa_included' => true, 'display_order' => 5, 'status' => true],
                ],
            ]),
            default => array_merge($common, [
                'name' => $countryName . ' National Grade Scale',
                'max_gpa' => 5.00,
                'rows' => [
                    ['grade' => 'A', 'min_score' => 80, 'max_score' => 100, 'grade_point' => 5.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 1, 'status' => true],
                    ['grade' => 'B', 'min_score' => 70, 'max_score' => 79, 'grade_point' => 4.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 2, 'status' => true],
                    ['grade' => 'C', 'min_score' => 60, 'max_score' => 69, 'grade_point' => 3.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 3, 'status' => true],
                    ['grade' => 'D', 'min_score' => 50, 'max_score' => 59, 'grade_point' => 2.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 4, 'status' => true],
                    ['grade' => 'F', 'min_score' => 0, 'max_score' => 49, 'grade_point' => 0.00, 'is_pass' => false, 'gpa_included' => true, 'display_order' => 5, 'status' => true],
                ],
            ]),
        };
    }

    private function upsertScale(array $scope, array $definition): void
    {
        $rows = $definition['rows'];
        unset($definition['rows']);

        DB::transaction(function () use ($scope, $definition, $rows) {
            $query = GradeScale::query();
            foreach ($scope as $col => $val) {
                if ($val === null) {
                    $query->whereNull($col);
                } else {
                    $query->where($col, $val);
                }
            }
            $scale = $query->first();

            if ($scale === null) {
                $scale = GradeScale::create(array_merge($scope, $definition));
            } else {
                $scale->forceFill($definition)->save();
                $scale->rows()->delete();
            }

            foreach ($rows as $row) {
                GradeScaleRow::create(array_merge(['grade_scale_id' => $scale->id], $row));
            }
        });
    }
}
