<?php

namespace App\Http\Controllers;

use App\Models\AcademicResultAggregationScheme;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\User;
use App\Services\AcademicFinalResultPreflightService;
use App\Support\Workspace;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Final-result generation pre-flight (Step 29).
 *
 * Read-only: shows whether the existing generation pipeline has every input it
 * needs for this scheme (scope, policy, assessments, weights, subjects,
 * grading, eligible students) before it is asked to calculate / preview a
 * result. It never creates a policy, never generates / locks / publishes a
 * final result, and never touches marks, snapshots or promotion decisions.
 *
 * Security mirrors AcademicFinalResultReadinessController:
 *   - Institute identity comes ONLY from the authenticated user / workspace
 *     (resolveInstitute); institute_id/branch_id are never read from input.
 *   - The {scheme} route binding resolves through the tenant + branch scoped
 *     AcademicResultAggregationScheme model, so cross-tenant and cross-branch
 *     records already 404 before any logic runs.
 *   - The whole route group is gated behind permission:education.manage.
 */
class AcademicFinalResultPreflightController extends Controller
{
    public function __construct(
        private readonly AcademicFinalResultPreflightService $preflight
    ) {}

    public function show(Request $request, AcademicResultAggregationScheme $scheme): View
    {
        $this->requireInstitute($request);

        return view('institute.academic-final-results.preflight', [
            'report' => $this->preflight->preflight($scheme),
        ]);
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
