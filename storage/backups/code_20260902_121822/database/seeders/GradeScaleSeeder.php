<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\GradeScale;
use App\Models\GradeScaleRow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed country-default grade scales for the Academic Engine ladder:
 *
 *   GLOBAL DEFAULT  → COUNTRY DEFAULT  → SYSTEM DEFAULT  → LEVEL DEFAULT  → INSTITUTE OVERRIDE
 *
 * This seeder populates:
 *   1. Global Default (all scope columns NULL)
 *   2. Bangladesh National Grade Scale (country_id = Bangladesh)
 *   3. USA Standard Grade Scale (country_id = United States)
 *   4. UK Degree Classification (country_id = United Kingdom)
 *
 * Idempotent: safe to run multiple times via updateOrCreate + row reconciliation.
 * No existing institute overrides are touched.
 */
class GradeScaleSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedGlobalDefault();
        $this->seedCountry('Bangladesh', $this->bangladeshScale());
        $this->seedCountry('United States', $this->usaScale());
        $this->seedCountry('United Kingdom', $this->ukScale());
    }

    // ------------------------------------------------------------------ Country

    private function seedCountry(string $countryName, array $definition): void
    {
        $country = Country::where('name', $countryName)->first();

        if ($country === null) {
            $this->command?->warn("GradeScaleSeeder: country '{$countryName}' not found — skipping.");

            return;
        }

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

    private function seedGlobalDefault(): void
    {
        $this->upsertScale(
            [
                'institute_id' => null,
                'country_id' => null,
                'education_system_id' => null,
                'academic_level_id' => null,
            ],
            $this->globalScale()
        );
    }

    // ------------------------------------------------------------------ Definitions

    private function bangladeshScale(): array
    {
        return [
            'name' => 'Bangladesh National Grade Scale',
            'gpa_mode' => GradeScale::GPA_MODE_EQUAL_WEIGHT,
            'optional_subject_gpa' => GradeScale::OPTIONAL_SUBJECT_GPA_INCLUDED,
            'optional_subject_bonus_threshold' => 2.00,
            'optional_subject_bonus_enabled' => true,
            'multiple_optional_policy' => GradeScale::MULTIPLE_OPTIONAL_BEST,
            'max_gpa' => 5.00,
            'marks_decimal_places' => 2,
            'percentage_decimal_places' => 2,
            'gpa_decimal_places' => 2,
            'cgpa_decimal_places' => 2,
            'rounding_mode' => GradeScale::ROUNDING_HALF_UP,
            'display_order' => 0,
            'status' => true,
            'rows' => [
                ['grade' => 'A+', 'min_score' => 80, 'max_score' => 100, 'grade_point' => 5.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 1, 'status' => true],
                ['grade' => 'A', 'min_score' => 70, 'max_score' => 79, 'grade_point' => 4.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 2, 'status' => true],
                ['grade' => 'A-', 'min_score' => 60, 'max_score' => 69, 'grade_point' => 3.50, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 3, 'status' => true],
                ['grade' => 'B', 'min_score' => 50, 'max_score' => 59, 'grade_point' => 3.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 4, 'status' => true],
                ['grade' => 'C', 'min_score' => 40, 'max_score' => 49, 'grade_point' => 2.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 5, 'status' => true],
                ['grade' => 'D', 'min_score' => 33, 'max_score' => 39, 'grade_point' => 1.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 6, 'status' => true],
                ['grade' => 'F', 'min_score' => 0, 'max_score' => 32, 'grade_point' => 0.00, 'is_pass' => false, 'gpa_included' => true, 'display_order' => 7, 'status' => true],
            ],
        ];
    }

    private function usaScale(): array
    {
        return [
            'name' => 'USA Standard Grade Scale',
            'gpa_mode' => GradeScale::GPA_MODE_EQUAL_WEIGHT,
            'optional_subject_gpa' => GradeScale::OPTIONAL_SUBJECT_GPA_EXCLUDED,
            'optional_subject_bonus_threshold' => 2.00,
            'optional_subject_bonus_enabled' => false,
            'multiple_optional_policy' => GradeScale::MULTIPLE_OPTIONAL_SINGLE,
            'max_gpa' => 4.00,
            'marks_decimal_places' => 2,
            'percentage_decimal_places' => 2,
            'gpa_decimal_places' => 2,
            'cgpa_decimal_places' => 2,
            'rounding_mode' => GradeScale::ROUNDING_HALF_UP,
            'display_order' => 0,
            'status' => true,
            'rows' => [
                ['grade' => 'A', 'min_score' => 90, 'max_score' => 100, 'grade_point' => 4.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 1, 'status' => true],
                ['grade' => 'B', 'min_score' => 80, 'max_score' => 89, 'grade_point' => 3.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 2, 'status' => true],
                ['grade' => 'C', 'min_score' => 70, 'max_score' => 79, 'grade_point' => 2.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 3, 'status' => true],
                ['grade' => 'D', 'min_score' => 60, 'max_score' => 69, 'grade_point' => 1.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 4, 'status' => true],
                ['grade' => 'F', 'min_score' => 0, 'max_score' => 59, 'grade_point' => 0.00, 'is_pass' => false, 'gpa_included' => true, 'display_order' => 5, 'status' => true],
            ],
        ];
    }

    private function ukScale(): array
    {
        return [
            'name' => 'UK Degree Classification',
            'gpa_mode' => GradeScale::GPA_MODE_EQUAL_WEIGHT,
            'optional_subject_gpa' => GradeScale::OPTIONAL_SUBJECT_GPA_EXCLUDED,
            'optional_subject_bonus_threshold' => 2.00,
            'optional_subject_bonus_enabled' => false,
            'multiple_optional_policy' => GradeScale::MULTIPLE_OPTIONAL_SINGLE,
            'max_gpa' => 4.00,
            'marks_decimal_places' => 2,
            'percentage_decimal_places' => 2,
            'gpa_decimal_places' => 2,
            'cgpa_decimal_places' => 2,
            'rounding_mode' => GradeScale::ROUNDING_HALF_UP,
            'display_order' => 0,
            'status' => true,
            'rows' => [
                ['grade' => 'First Class', 'min_score' => 70, 'max_score' => 100, 'grade_point' => 4.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 1, 'status' => true],
                ['grade' => 'Upper Second (2:1)', 'min_score' => 60, 'max_score' => 69, 'grade_point' => 3.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 2, 'status' => true],
                ['grade' => 'Lower Second (2:2)', 'min_score' => 50, 'max_score' => 59, 'grade_point' => 2.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 3, 'status' => true],
                ['grade' => 'Third Class', 'min_score' => 40, 'max_score' => 49, 'grade_point' => 1.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 4, 'status' => true],
                ['grade' => 'Fail', 'min_score' => 0, 'max_score' => 39, 'grade_point' => 0.00, 'is_pass' => false, 'gpa_included' => true, 'display_order' => 5, 'status' => true],
            ],
        ];
    }

    private function globalScale(): array
    {
        return [
            'name' => 'Global Default Grade Scale',
            'gpa_mode' => GradeScale::GPA_MODE_EQUAL_WEIGHT,
            'optional_subject_gpa' => GradeScale::OPTIONAL_SUBJECT_GPA_INCLUDED,
            'optional_subject_bonus_threshold' => 2.00,
            'optional_subject_bonus_enabled' => true,
            'multiple_optional_policy' => GradeScale::MULTIPLE_OPTIONAL_BEST,
            'max_gpa' => 5.00,
            'marks_decimal_places' => 2,
            'percentage_decimal_places' => 2,
            'gpa_decimal_places' => 2,
            'cgpa_decimal_places' => 2,
            'rounding_mode' => GradeScale::ROUNDING_HALF_UP,
            'display_order' => 0,
            'status' => true,
            'rows' => [
                ['grade' => 'A', 'min_score' => 80, 'max_score' => 100, 'grade_point' => 4.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 1, 'status' => true],
                ['grade' => 'B', 'min_score' => 60, 'max_score' => 79, 'grade_point' => 3.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 2, 'status' => true],
                ['grade' => 'C', 'min_score' => 40, 'max_score' => 59, 'grade_point' => 2.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 3, 'status' => true],
                ['grade' => 'D', 'min_score' => 33, 'max_score' => 39, 'grade_point' => 1.00, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 4, 'status' => true],
                ['grade' => 'F', 'min_score' => 0, 'max_score' => 32, 'grade_point' => 0.00, 'is_pass' => false, 'gpa_included' => true, 'display_order' => 5, 'status' => true],
            ],
        ];
    }

    // ------------------------------------------------------------------ Upsert

    /**
     * @param  array<string, mixed>  $scope  institute_id, country_id, education_system_id, academic_level_id
     * @param  array<string, mixed>  $definition  name, gpa_mode, rows, etc.
     */
    private function upsertScale(array $scope, array $definition): void
    {
        $rows = $definition['rows'];
        unset($definition['rows']);

        DB::transaction(function () use ($scope, $definition, $rows) {
            // scope_key is virtual; uniqueness is enforced via the four nullable FKs.
            // Use explicit NULL-aware lookup: whereNull vs where for each scope column.
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
                // Reconcile rows: delete and recreate to avoid stale bands.
                $scale->rows()->delete();
            }

            foreach ($rows as $row) {
                GradeScaleRow::create(array_merge(['grade_scale_id' => $scale->id], $row));
            }
        });
    }
}
