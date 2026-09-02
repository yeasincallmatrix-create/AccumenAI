<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\AcademicAssessment;
use App\Models\AcademicFinalResult;
use App\Models\AcademicStudentMark;
use App\Models\Guardian;
use App\Services\GuardianAuditService;
use App\Services\GuardianService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Step 47 — Guardian results page.
 *
 * Read-only: only PUBLISHED final-result snapshots are shown (never review /
 * approved / locked drafts), and assessment marks are only shown for
 * assessments that are LOCKED and whose academic year already has a published
 * final result for the student.
 */
class GuardianResultController extends Controller
{
    public function __construct(
        private readonly GuardianService $guardians,
        private readonly GuardianAuditService $audit,
    ) {}

    public function show(Request $request, int $student)
    {
        /** @var Guardian $guardian */
        $guardian = auth('guardian')->user();

        $student = $this->guardians->requireStudent($guardian, $student);

        $placementIds = $student->academicPlacements()->pluck('id');

        $published = AcademicFinalResult::query()
            ->where('status', AcademicFinalResult::STATUS_PUBLISHED)
            ->whereHas('students', fn ($q) => $q->whereIn('placement_id', $placementIds))
            ->with([
                'scheme.academicYear',
                'scheme.classGrade',
                'students' => fn ($q) => $q->whereIn('placement_id', $placementIds)->with('placement.academicYear', 'placement.classGrade'),
                'rows' => fn ($q) => $q->whereIn('placement_id', $placementIds)->with('subject'),
            ])
            ->orderByDesc('published_at')
            ->get();

        $publishedYearIds = $published
            ->flatMap(fn (AcademicFinalResult $result) => $result->students)
            ->pluck('placement.academic_year_id')
            ->merge($published->pluck('scheme.academic_year_id'))
            ->filter()
            ->unique()
            ->values();

        $marks = $publishedYearIds->isNotEmpty()
            ? AcademicStudentMark::query()
                ->where('student_id', $student->id)
                ->whereHas('assessment', fn ($q) => $q
                    ->whereNotNull('locked_at')
                    ->whereNotIn('status', [AcademicAssessment::STATUS_DRAFT, AcademicAssessment::STATUS_CANCELLED])
                    ->whereIn('academic_year_id', $publishedYearIds))
                ->with([
                    'assessment.academicYear',
                    'assessment.classGrade',
                    'assessmentSubject.subject',
                    'component',
                ])
                ->get()
            : collect();

        $assessments = $this->groupMarksByAssessment($marks);

        $this->audit->record((int) $student->institute_id, (int) $guardian->getKey(), 'guardian_viewed_results', (int) $student->id);

        return view('guardian.results', [
            'guardian' => $guardian,
            'student' => $student,
            'published' => $published,
            'assessments' => $assessments,
        ]);
    }

    /**
     * Group locked-assessment marks into per-assessment, per-subject rows.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function groupMarksByAssessment(Collection $marks): Collection
    {
        return $marks
            ->groupBy(fn (AcademicStudentMark $mark) => (int) $mark->academic_assessment_id)
            ->map(function (Collection $group) {
                $assessment = $group->first()->assessment;

                $subjects = $group
                    ->groupBy('assessment_subject_id')
                    ->map(function (Collection $subjectGroup) {
                        $assessmentSubject = $subjectGroup->first()->assessmentSubject;
                        $obtained = round($subjectGroup->sum('obtained_mark'), 2);
                        $full = $assessmentSubject->totalFullMark();

                        return [
                            'subject' => $assessmentSubject->subject?->name ?? 'Subject',
                            'obtained' => $obtained,
                            'full' => $full,
                            'passed' => $full > 0 && $obtained >= $full / 2,
                        ];
                    })
                    ->values();

                return [
                    'assessment' => $assessment,
                    'subjects' => $subjects,
                    'total_obtained' => round($subjects->sum('obtained'), 2),
                    'total_full' => round($subjects->sum('full'), 2),
                ];
            })
            ->values()
            ->sortByDesc(fn (array $row) => $row['assessment']->exam_date?->toDateString() ?? '')
            ->values();
    }
}
