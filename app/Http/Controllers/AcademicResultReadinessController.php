<?php

namespace App\Http\Controllers;

use App\Models\AcademicAssessment;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\User;
use App\Services\AcademicResultReadinessService;
use App\Support\CsvStream;
use App\Support\Workspace;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Academic result-readiness / assessment-completion (Step 27).
 *
 * Read-only: shows whether an assessment's marks are sufficiently complete
 * for final-result processing and answers nothing else. It never modifies
 * marks, assessments, placements, promotion decisions or final-result
 * snapshots.
 *
 * Security mirrors AcademicAssessmentController:
 *   - Institute identity comes ONLY from the authenticated user / workspace
 *     (resolveInstitute); institute_id/branch_id are never read from input.
 *   - The {assessment} route binding resolves through the tenant + branch
 *     scoped AcademicAssessment model, so cross-tenant and cross-branch
 *     records already 404 before any logic runs.
 *   - The whole route group is gated behind permission:education.manage.
 */
class AcademicResultReadinessController extends Controller
{
    public function __construct(
        private readonly AcademicResultReadinessService $readiness
    ) {}

    public function show(Request $request, AcademicAssessment $assessment): View
    {
        $institute = $this->requireInstitute($request);

        $assessment->load([
            'academicYear',
            'classGrade',
            'academicGroup',
            'assessmentType',
            'branch',
        ]);

        return view('institute.academic-assessments.readiness', [
            'institute' => $institute,
            'assessment' => $assessment,
            'readiness' => $this->readiness->forAssessment($assessment),
        ]);
    }

    /**
     * CSV download of the readiness exceptions only (same read-only matrix).
     */
    public function export(Request $request, AcademicAssessment $assessment)
    {
        $this->requireInstitute($request);

        $export = $this->readiness->export($assessment);

        return CsvStream::download($export['filename'], $export['headers'], $export['rows']);
    }

    // ------------------------------------------------------------- Internals

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
