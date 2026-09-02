<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\HrAttendance;
use App\Models\HrAttendanceCorrection;
use App\Models\HrLeaveApplication;
use App\Models\HrLeaveType;
use App\Services\HrAttendanceService;
use App\Services\HrLeaveService;
use App\Services\HrSelfService;
use Illuminate\Http\Request;

class HrSelfServiceController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly HrSelfService $selfService,
        private readonly HrLeaveService $leaveService,
        private readonly HrAttendanceService $attendanceService,
    ) {}

    private function currentEmployee(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $emp = $this->selfService->resolveEmployeeOrFail($institute->id, $this->actorId($request));
        // Branch isolation: ensure employee branch matches actingBranch if set
        $acting = $this->actingBranchId($request);
        if ($acting !== null && $emp->branch_id !== null && (int)$emp->branch_id !== (int)$acting) abort(404);
        return $emp;
    }

    public function dashboard(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $employee = $this->currentEmployee($request);
        $attendance = $this->selfService->attendanceSummary($employee);
        $balances = $this->selfService->leaveBalances($employee);
        $leaveHistory = HrLeaveApplication::where('institute_id',$institute->id)->where('employee_id',$employee->id)->orderByDesc('id')->limit(5)->get();
        $payslips = $this->selfService->payslips($employee)->take(5);
        $documents = $this->selfService->documents($employee)->take(5);
        $performance = \App\Models\HrPerformanceReview::where('institute_id',$institute->id)->where('employee_id',$employee->id)->with(['period'])->latest('id')->limit(3)->get();
        $trainings = \App\Models\HrTrainingEnrollment::where('institute_id',$institute->id)->where('employee_id',$employee->id)->with(['training'])->latest('id')->limit(3)->get();
        $notifications = \App\Models\NotificationLog::where('recipient_type','institute_user')->where('recipient_id',$this->actorId($request))->orderByDesc('id')->limit(5)->get();

        return view('hr.self.dashboard', compact('institute','employee','attendance','balances','leaveHistory','payslips','documents','performance','trainings','notifications'));
    }

    public function profile(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $employee = $this->currentEmployee($request);
        $employee->load(['branch','department','designation','reportingManager']);
        return view('hr.self.profile', compact('institute','employee'));
    }

    public function updateProfile(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $employee = $this->currentEmployee($request);
        $data = $request->validate([
            'first_name'=>['nullable','string','max:60'],
            'middle_name'=>['nullable','string','max:60'],
            'last_name'=>['nullable','string','max:60'],
            'phone'=>['nullable','string','max:20','regex:/^\+?[0-9\s\-]{7,20}$/'],
            'email'=>['nullable','email','max:150'],
            'address'=>['nullable','string','max:2000'],
            'emergency_contact_name'=>['nullable','string','max:120'],
            'emergency_contact_phone'=>['nullable','string','max:20'],
            'gender'=>['nullable',\Illuminate\Validation\Rule::in(['male','female','other'])],
            // sensitive fields are NOT validated here, will be filtered in service
            'employee_code'=>['nullable','string'], // should be ignored
            'department_id'=>['nullable','integer'], // ignored
            'branch_id'=>['nullable','integer'], // ignored
        ]);
        // Filter sensitive: service will ignore them anyway
        $this->selfService->updateProfile($employee, $request->only(HrSelfService::ALLOWED_PROFILE_FIELDS), $this->actorId($request));
        return back()->with('status','Profile updated');
    }

    // Leave
    public function leave(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $employee = $this->currentEmployee($request);
        $balances = $this->selfService->leaveBalances($employee);
        $history = HrLeaveApplication::where('institute_id',$institute->id)->where('employee_id',$employee->id)->with('leaveType')->orderByDesc('id')->paginate(10);
        $types = HrLeaveType::where('institute_id',$institute->id)->where('is_active',true)->orderBy('display_order')->get();
        return view('hr.self.leave', compact('institute','employee','balances','history','types'));
    }

    public function storeLeave(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $employee = $this->currentEmployee($request);
        $data = $request->validate([
            'leave_type_id'=>['required','integer','exists:hr_leave_types,id'],
            'start_date'=>['required','date'],
            'end_date'=>['required','date','after_or_equal:start_date'],
            'reason'=>['nullable','string','max:2000'],
        ]);
        $data['employee_id'] = $employee->id;
        $this->leaveService->apply($data,$institute->id,$this->actorId($request));
        // notify
        try {
            app(\App\Services\NotificationService::class)->send('hr.leave_requested',
                \App\Models\InstituteUser::where('institute_id',$institute->id)->whereHas('role', fn($q)=>$q->whereIn('slug',['institute-owner','institute-admin']))->limit(3)->get(),
                ['employee_name'=>$employee->display_name,'leave_type'=>HrLeaveType::find($data['leave_type_id'])?->name ?? 'Leave','start_date'=>$data['start_date'],'end_date'=>$data['end_date'],'institute_name'=>$institute->name],
                ['institute_id'=>$institute->id,'actor_id'=>$this->actorId($request)]
            );
        } catch (\Throwable $e) {}
        return back()->with('status','Leave request submitted');
    }

    public function cancelLeave(Request $request, HrLeaveApplication $hrLeaveApplication)
    {
        $institute = $this->requireInstitute($request);
        $employee = $this->currentEmployee($request);
        abort_if((int)$hrLeaveApplication->institute_id !== (int)$institute->id,404);
        abort_if((int)$hrLeaveApplication->employee_id !== (int)$employee->id,403);
        $this->leaveService->decide($hrLeaveApplication, 'cancelled', null, $institute->id, $this->actorId($request));
        return back()->with('status','Leave cancelled');
    }

    // Attendance
    public function attendance(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $employee = $this->currentEmployee($request);
        $month = $request->query('month', now()->format('Y-m'));
        $summary = $this->selfService->attendanceSummary($employee,$month);
        $corrections = HrAttendanceCorrection::where('institute_id',$institute->id)->where('employee_id',$employee->id)->orderByDesc('id')->limit(10)->get();
        return view('hr.self.attendance', compact('institute','employee','summary','corrections','month'));
    }

    public function requestCorrection(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $employee = $this->currentEmployee($request);
        $data = $request->validate([
            'correction_date'=>['required','date'],
            'requested_status'=>['required',\Illuminate\Validation\Rule::in(['present','absent','late','early_departure','leave','holiday','weekend','half_day'])],
            'requested_check_in'=>['nullable','date_format:H:i'],
            'requested_check_out'=>['nullable','date_format:H:i'],
            'reason'=>['required','string','max:2000'],
        ]);
        $data['employee_id'] = $employee->id;
        $this->attendanceService->requestCorrection($data,$institute->id,$this->actorId($request));
        try {
            app(\App\Services\NotificationService::class)->send('hr.attendance_correction_requested',
                \App\Models\InstituteUser::where('institute_id',$institute->id)->whereHas('role', fn($q)=>$q->whereIn('slug',['institute-owner','institute-admin','branch-manager']))->limit(3)->get(),
                ['employee_name'=>$employee->display_name,'correction_date'=>$data['correction_date'],'institute_name'=>$institute->name],
                ['institute_id'=>$institute->id,'actor_id'=>$this->actorId($request)]
            );
        } catch (\Throwable $e) {}
        return back()->with('status','Correction requested');
    }

    // Payslips
    public function payslips(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $employee = $this->currentEmployee($request);
        $payslips = $this->selfService->payslips($employee);
        return view('hr.self.payslips', compact('institute','employee','payslips'));
    }

    public function payslipShow(Request $request, \App\Models\HrPayroll $hrPayroll)
    {
        $institute = $this->requireInstitute($request);
        $employee = $this->currentEmployee($request);
        abort_if((int)$hrPayroll->institute_id !== (int)$institute->id,404);
        abort_if((int)$hrPayroll->employee_id !== (int)$employee->id,403);
        $hrPayroll->load(['period','currency','items']);
        return view('hr.self.payslip', compact('institute','employee','hrPayroll'));
    }

    // Documents
    public function documents(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $employee = $this->currentEmployee($request);
        $documents = $this->selfService->documents($employee);
        return view('hr.self.documents', compact('institute','employee','documents'));
    }

    public function uploadDocument(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $employee = $this->currentEmployee($request);
        $request->validate([
            'category_id'=>['required','integer','exists:document_categories,id'],
            'file'=>['required','file','max:10240','mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip,txt,csv,jpg,jpeg,png,gif,webp'],
            'title'=>['nullable','string','max:200'],
        ]);
        $category = \App\Models\DocumentCategory::findOrFail($request->integer('category_id'));
        // Check if category is allowed for hr-employee and is active
        if (!$category->appliesTo('hr-employee')) {
            return back()->withErrors(['category_id'=>'Category not allowed for employee documents']);
        }
        // Optional HR permit check: if category is_required and verification_required, allow upload
        app(\App\Services\DocumentService::class)->upload(
            $institute->id,'hr-employee',$employee->id,$category->id,$request->file('file'),$this->actorId($request),$employee->branch_id,
            $request->string('title')->toString()?:null,
            null
        );
        return back()->with('status','Document uploaded');
    }
}
