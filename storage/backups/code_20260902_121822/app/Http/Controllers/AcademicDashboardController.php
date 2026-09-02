<?php

namespace App\Http\Controllers;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Services\AcademicDashboardService;
use App\Support\Workspace;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Step 24 — Academic Operations Dashboard.
 *
 * A read-only overview of the current academic year's operations. The heavy
 * lifting lives in AcademicDashboardService; this controller only resolves the
 * acting institute (institute-user guard vs. workspace global account) and
 * renders the page. It never writes anything.
 */
class AcademicDashboardController extends Controller
{
    public function __construct(
        private readonly AcademicDashboardService $dashboard,
    ) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $instituteId = $user instanceof InstituteUser ? $user->institute_id : Workspace::id();

        return view('academic.dashboard', [
            'institute' => $instituteId !== null ? Institute::where('id', $instituteId)->first() : null,
            'summary' => $this->dashboard->summary(),
        ]);
    }
}
