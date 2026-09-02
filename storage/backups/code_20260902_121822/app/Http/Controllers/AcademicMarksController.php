<?php

namespace App\Http\Controllers;

use App\Models\AcademicAssessment;
use App\Models\AssessmentSubject;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\User;
use App\Services\AcademicFinalResultLifecycleService;
use App\Services\AcademicMarksService;
use App\Support\CsvStream;
use App\Support\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Academic marks entry + derived results (Education Engine).
 *
 * Security model mirrors AcademicAssessmentController:
 *   - Institute identity comes ONLY from the authenticated user/workspace.
 *   - The assessment route model is tenant + branch scoped (404s cross-tenant /
 *     cross-branch records); the subject must belong to that assessment
 *     (assessment_subjects.id is globally unique, so ownership is verified
 *     against the scoped assessment rather than relying on subject scoping).
 *   - Entry rows are restricted to placements that match the assessment
 *     context and the acting branch (AcademicMarksService::eligiblePlacements).
 */
class AcademicMarksController extends Controller
{
    public function __construct(
        private readonly AcademicMarksService $marks,
        private readonly AcademicFinalResultLifecycleService $lifecycle
    ) {}

    public function index(Request $request, AcademicAssessment $assessment, AssessmentSubject $assessmentSubject): View
    {
        $this->requireInstitute($request);
        $this->assertSubjectInAssessment($assessment, $assessmentSubject);

        $assessment->load(['academicYear', 'classGrade', 'academicGroup', 'subjects.subject']);
        $subject = $assessment->subjects->firstWhere('id', $assessmentSubject->id);

        return view('institute.academic-assessments.marks', [
            'assessment' => $assessment,
            'studentSubject' => $subject,
            'grid' => $this->marks->grid($assessment, $assessmentSubject),
        ]);
    }

    public function store(Request $request, AcademicAssessment $assessment, AssessmentSubject $assessmentSubject): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $this->assertSubjectInAssessment($assessment, $assessmentSubject);

        // A locked/published final result freezes the marks of every
        // assessment it covers (Step 10).
        $this->lifecycle->assertAssessmentEditable($institute, $assessment);

        $rows = $request->input('rows') ?? [];

        $saved = $this->marks->saveMarks(
            $assessment,
            $assessmentSubject,
            $this->creatorId($request),
            $rows
        );

        return redirect()
            ->route('settings.academic.assessments.marks', [$assessment, $assessmentSubject])
            ->with('status', $saved > 0 ? "$saved mark record(s) saved." : 'No marks were changed.');
    }

    /**
     * Class-wide marks sheet (all subjects × all eligible students) for an
     * assessment, with a derived per-subject result per cell and per-student
     * totals. Reuses the same eligibility and derived-result rules as the
     * per-subject entry grid.
     */
    public function sheet(Request $request, AcademicAssessment $assessment): View
    {
        $institute = $this->requireInstitute($request);

        $assessment->load(['academicYear', 'classGrade', 'academicGroup', 'branch']);

        return view('institute.academic-assessments.marks-sheet', [
            'institute' => $institute,
            'assessment' => $assessment,
            'sheet' => $this->marks->sheet($assessment),
        ]);
    }

    /**
     * CSV download of the class-wide marks sheet for an assessment. Like the
     * sheet, it reads live assessment marks only and never touches frozen
     * final-result snapshots.
     */
    public function export(Request $request, AcademicAssessment $assessment)
    {
        $this->requireInstitute($request);

        abort_if($assessment->subjects()->count() === 0, 404, 'This assessment has no subjects to export.');

        $export = $this->marks->export($assessment);

        return CsvStream::download($export['filename'], $export['headers'], $export['rows']);
    }

    // ------------------------------------------------------------- Internals

    private function assertSubjectInAssessment(AcademicAssessment $assessment, AssessmentSubject $assessmentSubject): void
    {
        abort_if((int) $assessmentSubject->assessment_id !== (int) $assessment->id, 404, 'Subject is not part of this assessment.');
    }

    private function creatorId(Request $request): ?int
    {
        $user = $request->user();

        return $user instanceof InstituteUser ? (int) $user->id : null;
    }

    private function requireInstitute(Request $request): Institute
    {
        $institute = $this->resolveInstitute($request);
        abort_if($institute === null, 404);

        return $institute;
    }

    private function resolveInstitute(Request $request): ?Institute
    {
        $user = $request->user();

        if ($user instanceof InstituteUser) {
            return Institute::query()->find($user->institute_id);
        }

        if ($user instanceof User) {
            $membership = Workspace::membership();

            return $membership !== null ? Institute::query()->find($membership->institution_id) : null;
        }

        return null;
    }
}
