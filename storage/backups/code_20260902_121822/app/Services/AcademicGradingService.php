<?php

namespace App\Services;

use App\Models\AcademicLevel;
use App\Models\ClassGrade;
use App\Models\Country;
use App\Models\EducationSystem;
use App\Models\GradeScale;
use App\Models\GradeScaleRow;
use App\Models\Institute;
use App\Models\InstituteSubject;
use App\Models\SubjectAcademicAssignment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Configurable grading configuration + resolution — pure configuration, never
 * writes to marks, aggregates or assessments.
 *
 * Resolution ladder (most specific wins):
 *
 *   1. INSTITUTE OVERRIDE at academic level
 *   2. INSTITUTE OVERRIDE (whole institute)
 *   3. ACADEMIC LEVEL default    (institute_id NULL)
 *   4. EDUCATION SYSTEM default  (institute_id NULL)
 *   5. COUNTRY default           (institute_id NULL)
 *   6. GLOBAL DEFAULT            (all scope columns NULL)
 *
 * Grade bands are CLOSED ranges [min,max] with strict no-overlap validation,
 * so any score (compared at 2-dp display precision) resolves to at most one
 * band. Nothing here fabricates grades: a missing scale or a score outside
 * every band simply yields NO grade.
 */
class AcademicGradingService
{
    public const DISPLAY_PRECISION = 2;

    /**
     * Resolve the effective grade scale for a country/system/level context.
     */
    public function resolveScale(
        Institute $institute,
        int|Country $countryId,
        int|EducationSystem|null $systemId = null,
        int|AcademicLevel|null $levelId = null
    ): ?GradeScale {
        $country = $countryId instanceof Country ? (int) $countryId->id : (int) $countryId;
        $system = $systemId instanceof EducationSystem ? (int) $systemId->id : ($systemId !== null ? (int) $systemId : null);
        $level = $levelId instanceof AcademicLevel ? (int) $levelId->id : ($levelId !== null ? (int) $levelId : null);

        // 1. Institute override at the academic level.
        if ($level !== null) {
            $scale = GradeScale::query()
                ->where('institute_id', $institute->id)
                ->where('academic_level_id', $level)
                ->where('status', true)
                ->with('rows')
                ->first();
            if ($scale !== null) {
                return $scale;
            }
        }

        // 2. Whole-institute override.
        $scale = GradeScale::query()
            ->where('institute_id', $institute->id)
            ->whereNull('academic_level_id')
            ->where('status', true)
            ->with('rows')
            ->first();
        if ($scale !== null) {
            return $scale;
        }

        // 3. Academic level default.
        if ($level !== null) {
            $scale = GradeScale::query()
                ->whereNull('institute_id')
                ->where('academic_level_id', $level)
                ->where('status', true)
                ->with('rows')
                ->first();
            if ($scale !== null) {
                return $scale;
            }
        }

        // 4. Education system default.
        if ($system !== null) {
            $scale = GradeScale::query()
                ->whereNull('institute_id')
                ->where('education_system_id', $system)
                ->whereNull('academic_level_id')
                ->where('status', true)
                ->with('rows')
                ->first();
            if ($scale !== null) {
                return $scale;
            }
        }

        // 5. Country default.
        $scale = GradeScale::query()
            ->whereNull('institute_id')
            ->where('country_id', $country)
            ->whereNull('education_system_id')
            ->whereNull('academic_level_id')
            ->where('status', true)
            ->with('rows')
            ->first();
        if ($scale !== null) {
            return $scale;
        }

        // 6. Global default. All scope columns NULL.
        return GradeScale::query()
            ->whereNull('institute_id')
            ->whereNull('country_id')
            ->whereNull('education_system_id')
            ->whereNull('academic_level_id')
            ->where('status', true)
            ->with('rows')
            ->first();
    }

    /**
     * Convenience: resolve the effective scale from a placement's class/grade
     * (the class carries country_id / education_system_id / academic_level_id).
     */
    public function resolveScaleForClass(Institute $institute, ClassGrade $classGrade): ?GradeScale
    {
        return $this->resolveScale(
            $institute,
            $classGrade->country_id,
            $classGrade->education_system_id,
            $classGrade->academic_level_id
        );
    }

    // ------------------------------------------------------------- Grade lookup

    /**
     * Resolve the active band covering a percentage score (2-dp comparison).
     * Closed range [min,max]; no-overlap validation guarantees a single match.
     * Returns null when no band covers the score (e.g. a score in a gap).
     */
    public function bandForScore(GradeScale $scale, float $score): ?GradeScaleRow
    {
        $score = round($score, self::DISPLAY_PRECISION);

        return $scale->rows
            ->filter(fn (GradeScaleRow $row) => (bool) $row->status)
            ->first(fn (GradeScaleRow $row) => $score >= $row->min_score && $score <= $row->max_score);
    }

    /**
     * Convenience wrapper for the final-result service: resolve the effective
     * scale for a context and return the band for a score in one step.
     */
    public function resolveBand(
        Institute $institute,
        int|Country $countryId,
        int|EducationSystem|null $systemId,
        int|AcademicLevel|null $levelId,
        float $score
    ): ?GradeScaleRow {
        $scale = $this->resolveScale($institute, $countryId, $systemId, $levelId);

        return $scale !== null ? $this->bandForScore($scale, $score) : null;
    }

    /**
     * Effective credit hours for a subject in an institute/class context.
     * Institute override (institute_subjects) wins over the global assignment.
     * Returns null when unset — the GPA resolver must NOT invent a credit.
     */
    public function effectiveCreditHours(Institute $institute, int $classGradeId, int $subjectId): ?float
    {
        $assignment = SubjectAcademicAssignment::query()
            ->where('class_grade_id', $classGradeId)
            ->where('subject_id', $subjectId)
            ->where('status', 'active')
            ->first();

        $override = InstituteSubject::query()
            ->where('institute_id', $institute->id)
            ->where('subject_id', $subjectId)
            ->first();

        if ($override !== null && $override->credit_hours !== null) {
            return (float) $override->credit_hours;
        }

        return $assignment?->credit_hours !== null ? (float) $assignment->credit_hours : null;
    }

    /**
     * Whether the subject level config allows its grade to enter GPA.
     * Effective = institute override (gpa_included) else global assignment
     * default (true). The band-level gpa_included flag is applied separately
     * at aggregation time. Returns null when no config row exists (caller
     * decides — inherited assignments with no institute row default to true).
     */
    public function effectiveSubjectGpaIncluded(Institute $institute, int $classGradeId, int $subjectId): bool
    {
        $assignment = SubjectAcademicAssignment::query()
            ->where('class_grade_id', $classGradeId)
            ->where('subject_id', $subjectId)
            ->where('status', 'active')
            ->first();

        $override = InstituteSubject::query()
            ->where('institute_id', $institute->id)
            ->where('subject_id', $subjectId)
            ->first();

        return $override?->gpa_included ?? ($assignment?->gpa_included ?? true);
    }

    // ------------------------------------------------------------- Row validation

    /**
     * Validate candidate bands: non-empty, closed ranges, min <= max, bounds
     * within 0..100, non-negative grade point, and no overlapping ranges
     * (given closed ranges, overlap exists when a later min <= an earlier max).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>> normalized rows
     *
     * @throws ValidationException
     */
    public function validateRows(array $rows): array
    {
        if ($rows === []) {
            throw ValidationException::withMessages(['rows' => 'Add at least one grade band.']);
        }

        $normalized = [];
        $sorted = [];

        foreach ($rows as $index => $row) {
            $grade = trim((string) ($row['grade'] ?? ''));
            if ($grade === '') {
                throw ValidationException::withMessages(["rows.$index.grade" => 'Grade label is required.']);
            }

            $min = (float) ($row['min_score'] ?? 0);
            $max = (float) ($row['max_score'] ?? 0);
            $point = (float) ($row['grade_point'] ?? 0);

            if ($min < 0 || $max > 100 || $min > $max) {
                throw ValidationException::withMessages([
                    "rows.$index.min_score" => 'Range must satisfy 0 <= min <= max <= 100.',
                ]);
            }

            if ($point < 0) {
                throw ValidationException::withMessages([
                    "rows.$index.grade_point" => 'Grade point cannot be negative.',
                ]);
            }

            $normalized[] = [
                'grade' => $grade,
                'min_score' => round($min, self::DISPLAY_PRECISION),
                'max_score' => round($max, self::DISPLAY_PRECISION),
                'grade_point' => round($point, self::DISPLAY_PRECISION),
                'is_pass' => (bool) ($row['is_pass'] ?? true),
                'gpa_included' => (bool) ($row['gpa_included'] ?? true),
                'display_order' => (int) ($row['display_order'] ?? $index + 1),
                'status' => (bool) ($row['status'] ?? true),
            ];
            $sorted[] = ['min' => $normalized[count($normalized) - 1]['min_score'], 'max' => $normalized[count($normalized) - 1]['max_score']];
        }

        usort($sorted, fn ($a, $b) => $a['min'] <=> $b['min']);

        for ($i = 1; $i < count($sorted); $i++) {
            if ($sorted[$i]['min'] <= $sorted[$i - 1]['max']) {
                throw ValidationException::withMessages([
                    'rows' => 'Grade bands must not overlap. Every score must map to at most one band.',
                ]);
            }
        }

        return $normalized;
    }

    // ------------------------------------------------------------- Persistence

    // ------------------------------------------------------------- Precision validation

    /**
     * Validate that configurable decimal-place columns are within the
     * allowed range (0..6). Only checks columns that are present in $data.
     *
     * @throws ValidationException
     */
    private function validatePrecision(array $data): void
    {
        $precisionColumns = [
            'marks_decimal_places',
            'percentage_decimal_places',
            'gpa_decimal_places',
            'cgpa_decimal_places',
        ];

        foreach ($precisionColumns as $column) {
            if (! array_key_exists($column, $data)) {
                continue;
            }

            $value = $data[$column];

            if ($value === null || $value === '') {
                continue;
            }

            $int = (int) $value;

            if ($int < 0 || $int > 6) {
                throw ValidationException::withMessages([
                    $column => 'Decimal places must be between 0 and 6.',
                ]);
            }
        }

        if (array_key_exists('rounding_mode', $data) && filled($data['rounding_mode'])) {
            $mode = (string) $data['rounding_mode'];

            if (! in_array($mode, GradeScale::ROUNDING_MODES, true)) {
                throw ValidationException::withMessages([
                    'rounding_mode' => 'Invalid rounding mode.',
                ]);
            }
        }
    }

    /**
     * Create a grade scale (default or institute override) with its bands.
     *
     * Defaults (super admin) pass scope columns with institute_id absent; the
     * scale is a GLOBAL/COUNTRY/SYSTEM/LEVEL default. Institute overrides pass
     * institute_id + optional academic_level_id; the institute is then taken
     * from the institute_id column itself (never read from input by this
     * service — the controller is the tenant authority).
     *
     * @param  array<string, mixed>  $data  name, gpa_mode, optional_subject_gpa, status, display_order + scope columns
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function store(array $data, array $rows): GradeScale
    {
        $rows = $this->validateRows($rows);
        $this->validatePrecision($data);

        return DB::transaction(function () use ($data, $rows) {
            $scale = GradeScale::create([
                'institute_id' => $this->scopeColumn($data, 'institute_id'),
                'country_id' => $this->scopeColumn($data, 'country_id'),
                'education_system_id' => $this->scopeColumn($data, 'education_system_id'),
                'academic_level_id' => $this->scopeColumn($data, 'academic_level_id'),
                'name' => trim($data['name']),
                'gpa_mode' => $data['gpa_mode'] ?? GradeScale::GPA_MODE_EQUAL_WEIGHT,
                'optional_subject_gpa' => $data['optional_subject_gpa'] ?? GradeScale::OPTIONAL_SUBJECT_GPA_INCLUDED,
                'display_order' => (int) ($data['display_order'] ?? 0),
                'status' => (bool) ($data['status'] ?? true),
            ]);

            $this->syncRows($scale, $rows);

            return $scale->refresh();
        });
    }

    /**
     * Update a scale's config + bands. Scope columns are immutable here —
     * changing scope means creating a new scale (historical records stay
     * stable; the new ladder position is an explicit new configuration).
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function update(GradeScale $scale, array $data, array $rows): GradeScale
    {
        $rows = $this->validateRows($rows);
        $this->validatePrecision($data);

        DB::transaction(function () use ($scale, $data, $rows) {
            $scale->forceFill([
                'name' => trim($data['name']),
                'gpa_mode' => $data['gpa_mode'] ?? $scale->gpa_mode,
                'optional_subject_gpa' => $data['optional_subject_gpa'] ?? $scale->optional_subject_gpa,
                'display_order' => (int) ($data['display_order'] ?? $scale->display_order),
                'status' => (bool) ($data['status'] ?? $scale->status),
            ])->save();

            $scale->rows()->delete();
            $this->syncRows($scale, $rows);
        });

        return $scale->refresh();
    }

    public function destroy(GradeScale $scale): void
    {
        $scale->delete();
    }

    /**
     * Scope cascading options for the admin scale editor.
     */
    public function scopeOptions(?int $countryId): array
    {
        return [
            'countries' => Country::query()->where('status', true)->orderBy('name')->get(['id', 'name']),
            'systems' => $countryId !== null
                ? EducationSystem::query()->where('country_id', $countryId)->where('status', true)->orderBy('display_order')->orderBy('id')->get(['id', 'name'])
                : collect(),
            'levels' => collect(),
        ];
    }

    public function scopeLevels(?int $systemId): Collection
    {
        return $systemId !== null
            ? AcademicLevel::query()->where('education_system_id', $systemId)->where('status', true)->orderBy('display_order')->orderBy('id')->get(['id', 'name'])
            : collect();
    }

    // ------------------------------------------------------------- Internals

    private function syncRows(GradeScale $scale, array $rows): void
    {
        foreach ($rows as $index => $row) {
            GradeScaleRow::create([
                'grade_scale_id' => $scale->id,
                'grade' => $row['grade'],
                'min_score' => $row['min_score'],
                'max_score' => $row['max_score'],
                'grade_point' => $row['grade_point'],
                'is_pass' => $row['is_pass'],
                'gpa_included' => $row['gpa_included'],
                'display_order' => $row['display_order'] ?: $index + 1,
                'status' => $row['status'],
            ]);
        }
    }

    /**
     * Map a scope column to a real FK value when present, else null.
     */
    private function scopeColumn(array $data, string $column): ?int
    {
        return isset($data[$column]) && filled($data[$column]) ? (int) $data[$column] : null;
    }

    // ------------------------------------------------------------- Precision / rounding

    /**
     * Effective marks decimal places from the resolved grade scale, or
     * the hardcoded default (2) when no scale is resolved.
     */
    public function marksDecimal(?GradeScale $scale): int
    {
        return $scale?->marks_decimal_places ?? self::DISPLAY_PRECISION;
    }

    /**
     * Effective percentage decimal places from the resolved grade scale.
     */
    public function percentageDecimal(?GradeScale $scale): int
    {
        return $scale?->percentage_decimal_places ?? self::DISPLAY_PRECISION;
    }

    /**
     * Effective GPA decimal places from the resolved grade scale.
     */
    public function gpaDecimal(?GradeScale $scale): int
    {
        return $scale?->gpa_decimal_places ?? self::DISPLAY_PRECISION;
    }

    /**
     * Effective CGPA decimal places from the resolved grade scale.
     */
    public function cgpaDecimal(?GradeScale $scale): int
    {
        return $scale?->cgpa_decimal_places ?? self::DISPLAY_PRECISION;
    }

    /**
     * Effective rounding mode from the resolved grade scale.
     */
    public function roundingMode(?GradeScale $scale): string
    {
        return $scale?->rounding_mode ?? GradeScale::ROUNDING_HALF_UP;
    }

    /**
     * Round a value using the configured precision and rounding mode.
     */
    public function preciseRound(float $value, int $precision, string $mode): float
    {
        return match ($mode) {
            GradeScale::ROUNDING_FLOOR => floor($value * (10 ** $precision)) / (10 ** $precision),
            GradeScale::ROUNDING_CEIL => ceil($value * (10 ** $precision)) / (10 ** $precision),
            GradeScale::ROUNDING_HALF_DOWN => round($value, $precision, PHP_ROUND_HALF_DOWN),
            default => round($value, $precision, PHP_ROUND_HALF_UP),
        };
    }
}
