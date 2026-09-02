<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\HrAttendance;
use App\Models\HrAttendanceCorrection;
use App\Models\HrLeaveApplication;
use App\Models\HrPerformanceReview;
use App\Models\HrTrainingEnrollment;
use App\Services\HrSelfService;
use Illuminate\Http\Request;

class HrManagerController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly HrSelfService $selfService) {}

    private function managerEmployee(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $emp = $this->selfService->resolveEmployee($institute->id,$this->actorId($request));
        if (!$emp) abort(403,'No employee record for manager');
        return $emp;
    }

    public function dashboard(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $manager = $this->managerEmployee($request);
        $team = $this->selfService->teamEmployees($manager);
        $teamIds = $team->pluck('id')->all();

        $pendingLeaves = $this->selfService->teamPendingLeaves($manager);
        $exceptions = $this->selfService->teamAttendanceExceptions($manager);
        $pendingCorrections = HrAttendanceCorrection::where('institute_id',$institute->id)->whereIn('employee_id',$teamIds)->where('status','pending')->with(['employee'])->get();
        $pendingReviews = HrPerformanceReview::where('institute_id',$institute->id)->whereIn('employee_id',$teamIds)->whereIn('status',['pending','manager_review'])->with(['employee','period'])->get();
        $trainingTasks = HrTrainingEnrollment::where('institute_id',$institute->id)->whereIn('employee_id',$teamIds)->where('status','enrolled')->with(['training','employee'])->get();
        $teamAttendance = HrAttendance::where('institute_id',$institute->id)->whereIn('employee_id',$teamIds)->where('attendance_date', today()->toDateString())->with(['employee'])->get();

        return view('hr.manager.dashboard', compact('institute','manager','team','pendingLeaves','exceptions','pendingCorrections','pendingReviews','trainingTasks','teamAttendance'));
    }
}
