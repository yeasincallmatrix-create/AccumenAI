<?php

use App\Models\GradeScale;

/*
|--------------------------------------------------------------------------
| Grade Scale Bands per Country
|--------------------------------------------------------------------------
|
| Predefined grade bands keyed by ISO-3166 alpha-2. The batch service
| `CountryBatchService::assignGradeScales()` uses these definitions to
| create a country-default GradeScale when one is missing.
|
| `global` is the fallback when a country has no dedicated entry.
| The shape mirrors Database\Seeders\GradeScaleSeeder definitions.
|
*/

return [

    'global' => [
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
    ],

    'BD' => [
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
    ],

    'US' => [
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
    ],

    'GB' => [
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
    ],

    'IN' => [
        'name' => 'India Standard Grade Scale',
        'gpa_mode' => GradeScale::GPA_MODE_EQUAL_WEIGHT,
        'optional_subject_gpa' => GradeScale::OPTIONAL_SUBJECT_GPA_INCLUDED,
        'optional_subject_bonus_threshold' => 2.00,
        'optional_subject_bonus_enabled' => true,
        'multiple_optional_policy' => GradeScale::MULTIPLE_OPTIONAL_BEST,
        'max_gpa' => 10.00,
        'marks_decimal_places' => 2,
        'percentage_decimal_places' => 2,
        'gpa_decimal_places' => 2,
        'cgpa_decimal_places' => 2,
        'rounding_mode' => GradeScale::ROUNDING_HALF_UP,
        'display_order' => 0,
        'status' => true,
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
    ],
];
