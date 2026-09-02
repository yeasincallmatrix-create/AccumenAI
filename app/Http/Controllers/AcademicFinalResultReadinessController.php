<?php

namespace App\Http\Controllers;

use App\Models\AcademicResultAggregationScheme;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\User;
use App\Services\AcademicFinalResultReadinessService;
use App\Support\CsvStream;
use App\Support\Workspace;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Final-result readiness gate (Step 28).
 *
 * Read-only: shows whether a scheme's scope (year + class/grade + group +
 * required assessments with weights) is ready for final-result generation /
 * locking. It never modifies marks, assessments, placements, policies, final
 * results or snapshots, never aggregates or grades, and does not lazily create
 * a missing policy.
 *
 * Security mirrors AcademicResultReadinessController:
 *   - Institute identity comes ONLY from the authenticated user / workspace
 *     (resolveInstitute); institute_id/branch_id are never read from input.
 *   - The {scheme} route binding resolves through the tenant + branch scoped
 *     AcademicResultAggregationScheme model, so cross-tenant and cross-branch
 *     records already 404 before any logic runs.
 *   - The whole route group is gated behind permission:education.manage.
 */
class AcademicFinalResultReadinessController extends Controller
{
    public function __construct(
        private readonly AcademicFinalResultReadinessService $readiness
    ) {}

    public function show(Request $request, AcademicResultAggregationScheme $scheme): View
    {
        $this->requireInstitute($request);

        return view('institute.academic-final-results.readiness', [
            'readiness' => $this->readiness->forScheme($scheme),
        ]);
    }

    /**
     * CSV download of the readiness exceptions (same read-only report).
     */
    public function export(Request $request, AcademicResultAggregationScheme $scheme)
    {
        $this->requireInstitute($request);

        $export = $this->readiness->export($scheme);

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
