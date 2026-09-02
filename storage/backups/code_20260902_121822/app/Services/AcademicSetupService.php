<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\GradeScale;
use App\Models\GradeScaleRow;
use App\Models\Institute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * One-call academic bootstrap for an institute — mirrors
 * App\Services\Accounting\AccountingSetupService.
 *
 * ensureDefaults() is idempotent: safe to call during onboarding and on
 * every install/upgrade. It never duplicates existing records and never
 * overwrites existing academic configuration.
 *
 *  1. AcademicYear — one current year per institute (calendar year)
 *  2. Global GradeScale — one global default scale when none exists
 */
class AcademicSetupService
{
    /**
     * Bangladesh-compatible default grade bands.
     * Mirrors tests/Feature/AcademicGradingTest::bdRows().
     */
    public const DEFAULT_BANDS = [
        ['grade' => 'A+', 'min_score' => 80, 'max_score' => 100, 'grade_point' => 5.0, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 1],
        ['grade' => 'A', 'min_score' => 70, 'max_score' => 79.99, 'grade_point' => 4.0, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 2],
        ['grade' => 'B', 'min_score' => 60, 'max_score' => 69.99, 'grade_point' => 3.0, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 3],
        ['grade' => 'C', 'min_score' => 50, 'max_score' => 59.99, 'grade_point' => 2.0, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 4],
        ['grade' => 'E', 'min_score' => 40, 'max_score' => 49.99, 'grade_point' => 1.0, 'is_pass' => false, 'gpa_included' => false, 'display_order' => 5],
        ['grade' => 'F', 'min_score' => 0, 'max_score' => 39.99, 'grade_point' => 0.0, 'is_pass' => false, 'gpa_included' => false, 'display_order' => 6],
    ];

    public const DEFAULT_GRADE_SCALE_NAME = 'Global Default';
    public const DEFAULT_GRADE_SCALE_GPA_MODE = GradeScale::GPA_MODE_EQUAL_WEIGHT;
    public const DEFAULT_GRADE_SCALE_OPTIONAL = GradeScale::OPTIONAL_SUBJECT_GPA_INCLUDED;

    public function __construct(
        private readonly AcademicGradingService $gradingService,
    ) {}

    /**
     * Ensure academic defaults for the given institute.
     *
     * @return array{academic_year: array{created: bool, id: ?int, code: ?string}, grade_scale: array{created: bool, id: ?int}}
     */
    public function ensureDefaults(Institute $institute): array
    {
        $result = [
            'academic_year' => ['created' => false, 'id' => null, 'code' => null],
            'grade_scale' => ['created' => false, 'id' => null],
        ];

        // Academic only — other domains do not need academic years
        if (! \App\Support\InstituteDomain::isAcademic($institute)) {
            return $result;
        }

        DB::transaction(function () use ($institute, &$result) {
            $yearResult = $this->ensureAcademicYear($institute);
            $result['academic_year'] = $yearResult;

            $scaleResult = $this->ensureGlobalGradeScale();
            $result['grade_scale'] = $scaleResult;
        });

        return $result;
    }

    /**
     * Ensure one current AcademicYear for the institute.
     * Idempotent: if any is_current year exists, reuse it.
     * Otherwise create a calendar-year row for the current year.
     */
    private function ensureAcademicYear(Institute $institute): array
    {
        $existing = AcademicYear::withoutGlobalScope('institute')
            ->where('institute_id', $institute->id)
            ->where('is_current', true)
            ->first();

        if ($existing !== null) {
            return ['created' => false, 'id' => $existing->id, 'code' => $existing->code];
        }

        $year = (int) now()->format('Y');
        $code = (string) $year;
        $name = "Academic Year {$year}";

        // Code must be unique per institute — firstOrCreate by institute+code
        $yearRow = AcademicYear::withoutGlobalScope('institute')->firstOrCreate(
            ['institute_id' => $institute->id, 'code' => $code],
            [
                'name' => $name,
                'start_date' => "{$year}-01-01",
                'end_date' => "{$year}-12-31",
                'is_current' => true,
                'status' => true,
            ]
        );

        // If we reused an existing non-current year by code, promote it to current
        if (! $yearRow->wasRecentlyCreated && ! $yearRow->is_current) {
            // Unset any stale current flag (should be none, but be safe)
            AcademicYear::withoutGlobalScope('institute')
                ->where('institute_id', $institute->id)
                ->where('id', '!=', $yearRow->id)
                ->where('is_current', true)
                ->update(['is_current' => false]);
            $yearRow->update(['is_current' => true]);
        }

        // Ensure only this year is current
        if ($yearRow->wasRecentlyCreated) {
            AcademicYear::withoutGlobalScope('institute')
                ->where('institute_id', $institute->id)
                ->where('id', '!=', $yearRow->id)
                ->where('is_current', true)
                ->update(['is_current' => false]);
        }

        return [
            'created' => (bool) $yearRow->wasRecentlyCreated,
            'id' => $yearRow->id,
            'code' => $yearRow->code,
        ];
    }

    /**
     * Ensure a single global default GradeScale exists when none does.
     * institute_id = NULL means any institute inherits it via AcademicGradingService::resolveScale().
     * This is a GLOBAL singleton — not per-institute.
     */
    private function ensureGlobalGradeScale(): array
    {
        $existing = GradeScale::query()
            ->whereNull('institute_id')
            ->whereNull('country_id')
            ->whereNull('education_system_id')
            ->whereNull('academic_level_id')
            ->first();

        if ($existing !== null) {
            return ['created' => false, 'id' => $existing->id];
        }

        $scale = GradeScale::firstOrCreate(
            [
                'institute_id' => null,
                'country_id' => null,
                'education_system_id' => null,
                'academic_level_id' => null,
            ],
            [
                'name' => self::DEFAULT_GRADE_SCALE_NAME,
                'gpa_mode' => self::DEFAULT_GRADE_SCALE_GPA_MODE,
                'optional_subject_gpa' => self::DEFAULT_GRADE_SCALE_OPTIONAL,
                'display_order' => 0,
                'status' => true,
            ]
        );

        if ($scale->wasRecentlyCreated) {
            foreach (self::DEFAULT_BANDS as $band) {
                GradeScaleRow::create([
                    'grade_scale_id' => $scale->id,
                    'grade' => $band['grade'],
                    'min_score' => $band['min_score'],
                    'max_score' => $band['max_score'],
                    'grade_point' => $band['grade_point'],
                    'is_pass' => $band['is_pass'],
                    'gpa_included' => $band['gpa_included'],
                    'display_order' => $band['display_order'],
                    'status' => true,
                ]);
            }

            Log::info('AcademicSetupService: created global grade scale', ['grade_scale_id' => $scale->id]);

            return ['created' => true, 'id' => $scale->id];
        }

        return ['created' => false, 'id' => $scale->id];
    }
}
