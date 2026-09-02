<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrReportController extends Controller
{
    use ResolvesInstitute;

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        return view('hr.reports.index', ['institute' => $institute]);
    }

    public function employee(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        return view('hr.reports.employee', ['institute' => $institute]);
    }

    public function employeeExport(Request $request): StreamedResponse
    {
        $this->requireInstitute($request);
        return response()->streamDownload(function () { echo "employee,code\n"; }, 'employee.csv');
    }

    public function workforce(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        return view('hr.reports.workforce', ['institute' => $institute]);
    }

    public function workforceExport(Request $request): StreamedResponse
    {
        $this->requireInstitute($request);
        return response()->streamDownload(function () { echo "workforce\n"; }, 'workforce.csv');
    }

    public function attendance(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        return view('hr.reports.attendance', ['institute' => $institute]);
    }

    public function attendanceExport(Request $request): StreamedResponse
    {
        $this->requireInstitute($request);
        return response()->streamDownload(function () { echo "attendance\n"; }, 'attendance.csv');
    }

    public function leave(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        return view('hr.reports.leave', ['institute' => $institute]);
    }

    public function leaveExport(Request $request): StreamedResponse
    {
        $this->requireInstitute($request);
        return response()->streamDownload(function () { echo "leave\n"; }, 'leave.csv');
    }

    public function payroll(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        return view('hr.reports.payroll', ['institute' => $institute]);
    }

    public function payrollExport(Request $request): StreamedResponse
    {
        $this->requireInstitute($request);
        return response()->streamDownload(function () { echo "payroll\n"; }, 'payroll.csv');
    }

    public function recruitment(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        return view('hr.reports.recruitment', ['institute' => $institute]);
    }

    public function recruitmentExport(Request $request): StreamedResponse
    {
        $this->requireInstitute($request);
        return response()->streamDownload(function () { echo "recruitment\n"; }, 'recruitment.csv');
    }

    public function performance(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        return view('hr.reports.performance', ['institute' => $institute]);
    }

    public function performanceExport(Request $request): StreamedResponse
    {
        $this->requireInstitute($request);
        return response()->streamDownload(function () { echo "performance\n"; }, 'performance.csv');
    }

    public function training(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        return view('hr.reports.training', ['institute' => $institute]);
    }

    public function trainingExport(Request $request): StreamedResponse
    {
        $this->requireInstitute($request);
        return response()->streamDownload(function () { echo "training\n"; }, 'training.csv');
    }
}
