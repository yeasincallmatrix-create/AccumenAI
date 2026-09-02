<?php

namespace App\Services;

use App\Models\AcademicCumulativeResult;
use App\Models\AcademicCumulativeResultEntry;
use App\Models\AcademicFinalResult;
use App\Models\AcademicFinalResultStudent;
use App\Models\AcademicLevel;
use App\Models\GradeScale;
use App\Models\Institute;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Computes and persists cumulative GPA (CGPA) from published final-result
 * snapshots for a student across all academic years at a given level.
 *
 * The CGPA is always derived from frozen snapshot data (published
 * AcademicFinalResultStudent rows), never from mutable live marks. Each
 * published result is represented by a single entry in the CGPA log, so
 * adding, updating or re-publishing a result naturally feeds into the
 * cumulative total.
 *
 * Precision and rounding mode are read from the effective grade scale's
 * new configuration columns.
 */
class AcademicCumulativeService
{
    public function __construct(
        private readonly AcademicGradingService $grading
    ) {}

    /**
     * Compute the current CGPA for a student in a given academic level context.
     *
     * If the level is null, all published results for the student are included.
     * Returns null when no qualifying published results exist.
     */
    public function compute(Student $student, ?int $academicLevelId = null): ?array
    {
        $entries = $this->publishedEntrySnapshots($student, $academicLevelId);

        if ($entries->isEmpty()) {
            return null;
        }

        // P3-3 — Per-level scale resolution: resolve GradeScale for each level explicitly
        // using AcademicGradingService::resolveScaleForClass. Each period's GPA mode,
        // rounding and credits are evaluated with its own scale instead of the first entry's scale.
        $totalWeightedPoints = 0.0;
        $totalWeight = 0.0;
        $totalCredits = 0.0;
        $totalGradePoints = 0.0;
        $periodsCompleted = 0;
        $includedEntries = [];
        $perLevelScales = [];

        foreach ($entries as $snapshot) {
            $gpaValue = $snapshot->gpa;

            if ($gpaValue === null) {
                continue;
            }

            // Resolve scale per level explicitly
            $classGrade = $snapshot->placement?->classGrade ?? $snapshot->result?->scheme?->classGrade ?? null;
            $scaleForEntry = null;
            if ($classGrade !== null) {
                try {
                    $scaleForEntry = $this->grading->resolveScaleForClass($student->institute, $classGrade);
                } catch (\Throwable $e) {
                    $scaleForEntry = null;
                }
            }
            if ($scaleForEntry === null) {
                // Fallback to legacy ladder resolution for this single entry
                $scaleForEntry = $this->resolveScaleForEntries(collect([$snapshot]), $student);
            }
            $perLevelScales[] = $scaleForEntry;
            $gpaModeForEntry = $scaleForEntry?->gpa_mode ?? GradeScale::GPA_MODE_EQUAL_WEIGHT;

            $rows = $this->publishedResultRows($snapshot);
            $periodCredits = $rows
                ->filter(fn ($r) => (bool) $r->gpa_included && $r->credits !== null)
                ->sum('credits');
            $periodCredits = (float) $periodCredits;

            $periodPassed = 0;
            $periodFailed = 0;
            foreach ($rows as $row) {
                if ($row->subject_status !== null && strtoupper((string) $row->subject_status) === 'FAIL') {
                    $periodFailed++;
                } else {
                    $periodPassed++;
                }
            }

            // Per-level weighted contribution: credit_weighted uses credits, equal_weight uses weight 1
            if ($gpaModeForEntry === GradeScale::GPA_MODE_CREDIT_WEIGHTED) {
                if ($periodCredits <= 0) {
                    continue;
                }
                $weight = $periodCredits;
                $gradePoints = $gpaValue * $periodCredits;
                $totalCredits += $periodCredits;
            } else {
                $weight = 1.0;
                $gradePoints = $gpaValue;
            }

            $totalWeightedPoints += $gpaValue * $weight;
            $totalWeight += $weight;
            $totalGradePoints += $gradePoints;
            $periodsCompleted++;

            $includedEntries[] = [
                'final_result_id' => $snapshot->result_id,
                'gpa' => $gpaValue,
                'grade_points_earned' => $gradePoints,
                'credits_earned' => $periodCredits,
                'subjects_passed' => $periodPassed,
                'subjects_failed' => $periodFailed,
            ];
        }

        if ($periodsCompleted === 0) {
            return null;
        }

        // Determine final rounding/mode from the most recent per-level scale (last entry), fallback to first
        $finalScale = null;
        if (! empty($perLevelScales)) {
            $finalScale = end($perLevelScales) ?: $perLevelScales[0] ?? null;
        }
        if ($finalScale === null) {
            $finalScale = $this->resolveScaleForEntries($entries, $student);
        }
        $gpaMode = $finalScale?->gpa_mode ?? GradeScale::GPA_MODE_EQUAL_WEIGHT;
        $cgpaDecimal = $finalScale?->cgpa_decimal_places ?? 2;
        $roundingMode = $finalScale?->rounding_mode ?? GradeScale::ROUNDING_HALF_UP;

        // Compute CGPA as weighted average of per-level GPAs
        // If any period used credit_weighted, totalWeightedPoints/totalWeight already reflects per-level weighting.
        // For backward compatibility when all periods share same mode, this matches legacy computeValue.
        if ($totalWeight <= 0) {
            return null;
        }
        $cgpa = $this->preciseRound($totalWeightedPoints / $totalWeight, $cgpaDecimal, $roundingMode);

        if ($cgpa === null) {
            return null;
        }

        // For legacy callers expecting total_grade_points/total_credits/mode, preserve weighted totals
        // Use credit_weighted semantics when final mode is credit_weighted, else equal_weight
        $legacyCredits = ($gpaMode === GradeScale::GPA_MODE_CREDIT_WEIGHTED) ? $totalCredits : 0.0;
        $legacyPoints = ($gpaMode === GradeScale::GPA_MODE_CREDIT_WEIGHTED) ? $totalGradePoints : $totalWeightedPoints;

        return [
            'cumulative_gpa' => $cgpa,
            'mode' => $gpaMode,
            'total_grade_points' => round($legacyPoints, 4),
            'total_credits' => round($legacyCredits, 4),
            'periods_completed' => $periodsCompleted,
            'entries' => $includedEntries,
        ];
    }

    /**
     * Compute + persist the CGPA record, creating or updating as needed.
     * Returns the AcademicCumulativeResult model (refreshed).
     */
    public function store(Student $student, ?int $academicLevelId = null): AcademicCumulativeResult
    {
        $computed = $this->compute($student, $academicLevelId);
        $instituteId = $student->institute_id;

        if ($computed === null) {
            return $this->upsert($instituteId, $student->id, $academicLevelId, [
                'cumulative_gpa' => null,
                'gpa_mode' => null,
                'total_grade_points' => 0,
                'total_credits' => 0,
                'periods_completed' => 0,
                'status' => 'empty',
            ], []);
        }

        return $this->upsert(
            $instituteId,
            $student->id,
            $academicLevelId,
            [
                'cumulative_gpa' => $computed['cumulative_gpa'],
                'gpa_mode' => $computed['mode'],
                'total_grade_points' => $computed['total_grade_points'],
                'total_credits' => $computed['total_credits'],
                'periods_completed' => $computed['periods_completed'],
                'status' => 'active',
            ],
            $computed['entries']
        );
    }

    /**
     * Convenience: store for every unique academic level across all the
     * student's published results.
     *
     * @return Collection<int, AcademicCumulativeResult>
     */
    public function storeAllLevels(Student $student): Collection
    {
        $levelIds = $this->publishedLevelIds($student);

        // Null level covers results with no associated academic_level_id
        if ($levelIds->contains(null)) {
            $results = collect([$this->store($student, null)]);
            $levelIds = $levelIds->filter()->values();
        } else {
            $results = collect();
        }

        foreach ($levelIds as $levelId) {
            $results->push($this->store($student, (int) $levelId));
        }

        return $results;
    }

    /**
     * Retrieve the stored CGPA record for a student/level context.
     */
    public function find(Student $student, ?int $academicLevelId = null): ?AcademicCumulativeResult
    {
        return AcademicCumulativeResult::query()
            ->where('student_id', $student->id)
            ->where('academic_level_id', $academicLevelId)
            ->first();
    }

    // ------------------------------------------------------------- Round-up trigger

    /**
     * Recompute CGPA after a published final result changes status.
     * Called from AcademicFinalResultLifecycleService when a result is
     * published or un-published.
     */
    public function recomputeAfterPublish(StudentAcademicPlacement $placement): void
    {
        $levelId = $placement->classGrade?->academic_level_id;
        $this->store($placement->student, $levelId);
    }

    // ------------------------------------------------------------- CGPA formula

    private function computeValue(
        float $totalGradePoints,
        float $totalCredits,
        int $periodsCompleted,
        string $gpaMode,
        int $decimalPlaces,
        string $roundingMode,
    ): ?float {
        if ($gpaMode === GradeScale::GPA_MODE_CREDIT_WEIGHTED) {
            if ($totalCredits <= 0) {
                return null;
            }

            return $this->preciseRound($totalGradePoints / $totalCredits, $decimalPlaces, $roundingMode);
        }

        // Equal-weight CGPA: sum of per-period GPAs divided by periods completed.
        if ($periodsCompleted <= 0) {
            return null;
        }

        return $this->preciseRound($totalGradePoints / $periodsCompleted, $decimalPlaces, $roundingMode);
    }

    private function preciseRound(float $value, int $precision, string $mode): float
    {
        return match ($mode) {
            GradeScale::ROUNDING_FLOOR => floor($value * (10 ** $precision)) / (10 ** $precision),
            GradeScale::ROUNDING_CEIL => ceil($value * (10 ** $precision)) / (10 ** $precision),
            GradeScale::ROUNDING_HALF_DOWN => round($value, $precision, PHP_ROUND_HALF_DOWN),
            default => round($value, $precision, PHP_ROUND_HALF_UP),
        };
    }

    // ------------------------------------------------------------- Data access

    private function publishedEntrySnapshots(Student $student, ?int $academicLevelId = null): Collection
    {
        return AcademicFinalResultStudent::query()
            ->whereHas('placement', function (Builder $query) use ($student) {
                $query->withTrashed()->where('student_id', $student->id);
            })
            ->whereHas('result', function (Builder $query) {
                $query->where('status', AcademicFinalResult::STATUS_PUBLISHED);
            })
            ->when($academicLevelId !== null, function (Builder $query) use ($academicLevelId) {
                $query->whereHas('result.scheme.classGrade', function (Builder $q) use ($academicLevelId) {
                    $q->where('academic_level_id', $academicLevelId);
                });
            })
            ->with(['result.scheme.classGrade', 'placement' => fn ($q) => $q->withTrashed(), 'placement.classGrade'])
            ->get();
    }

    private function publishedResultRows(AcademicFinalResultStudent $snapshot): Collection
    {
        return \App\Models\AcademicFinalResultRow::query()
            ->where('result_id', $snapshot->result_id)
            ->where('placement_id', $snapshot->placement_id)
            ->get();
    }

    private function publishedLevelIds(Student $student): Collection
    {
        return AcademicFinalResultStudent::query()
            ->whereHas('placement', function (Builder $query) use ($student) {
                $query->withTrashed()->where('student_id', $student->id);
            })
            ->whereHas('result', function (Builder $query) {
                $query->where('status', AcademicFinalResult::STATUS_PUBLISHED);
            })
            ->with('result.scheme.classGrade')
            ->get()
            ->map(fn (AcademicFinalResultStudent $s) => $s->result?->scheme?->classGrade?->academic_level_id)
            ->unique()
            ->values();
    }

    private function resolveScaleForEntries(Collection $entries, Student $student): ?GradeScale
    {
        foreach ($entries as $snapshot) {
            $scheme = $snapshot->result?->scheme;

            if ($scheme === null) {
                continue;
            }

            $levelId = $scheme->classGrade?->academic_level_id;

            if ($levelId !== null) {
                $level = AcademicLevel::find($levelId);

                if ($level !== null) {
                    $scale = $this->grading->resolveScale(
                        $student->institute,
                        $level->country_id,
                        $level->education_system_id,
                        $level->id
                    );

                    if ($scale !== null) {
                        return $scale;
                    }
                }
            }
        }

        return null;
    }

    // ------------------------------------------------------------- Persistence

    private function upsert(
        int $instituteId,
        int $studentId,
        ?int $academicLevelId,
        array $attributes,
        array $entries
    ): AcademicCumulativeResult {
        return DB::transaction(function () use ($instituteId, $studentId, $academicLevelId, $attributes, $entries) {
            $existing = AcademicCumulativeResult::query()
                ->where('institute_id', $instituteId)
                ->where('student_id', $studentId)
                ->where('academic_level_id', $academicLevelId)
                ->first();

            if ($existing !== null) {
                $existing->fill($attributes);
                $existing->save();

                $existing->entries()->delete();
            } else {
                $existing = AcademicCumulativeResult::create(array_merge($attributes, [
                    'institute_id' => $instituteId,
                    'student_id' => $studentId,
                    'academic_level_id' => $academicLevelId,
                ]));
            }

            foreach ($entries as $entry) {
                AcademicCumulativeResultEntry::create(array_merge($entry, [
                    'cumulative_result_id' => $existing->id,
                ]));
            }

            return $existing->refresh();
        });
    }
}
