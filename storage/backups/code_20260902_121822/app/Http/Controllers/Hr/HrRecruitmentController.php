<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\CrmLead;
use App\Models\CrmLeadSource;
use App\Models\CrmLeadStatus;
use App\Models\HrApplication;
use App\Models\HrInterview;
use App\Models\HrOffer;
use App\Models\HrRequisition;
use App\Models\HrVacancy;
use App\Services\HrRecruitmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HrRecruitmentController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly HrRecruitmentService $service) {}

    private function can(Request $request, array $perms): bool
    {
        foreach ($perms as $p) if ($request->user()->hasPermission($p)) return true;
        return false;
    }

    private function ensureInstitute($model, int $instituteId, ?int $branchId): void
    {
        abort_if((int)$model->institute_id !== (int)$instituteId, 404);
        if ($branchId!==null && $model->branch_id!==null && (int)$model->branch_id!==(int)$branchId) abort(404);
    }

    // ---------------- Dashboard & Reports

    public function dashboard(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $baseReq = HrRequisition::where('institute_id',$institute->id)->when($branchId, fn($q)=>$q->where('branch_id',$branchId));
        $baseVac = HrVacancy::where('institute_id',$institute->id)->when($branchId, fn($q)=>$q->where('branch_id',$branchId));
        $baseApp = HrApplication::where('institute_id',$institute->id)->when($branchId, fn($q)=>$q->where('branch_id',$branchId));

        $stats = [
            'open_requisitions' => (clone $baseReq)->whereIn('status',['pending_approval','approved'])->count(),
            'open_vacancies' => (clone $baseVac)->whereIn('status',['published','approved'])->count(),
            'total_applications' => (clone $baseApp)->count(),
            'by_stage' => (clone $baseApp)->selectRaw('current_stage, COUNT(*) as c')->groupBy('current_stage')->pluck('c','current_stage')->all(),
            'by_source' => (clone $baseApp)->selectRaw('source_id, COUNT(*) as c')->groupBy('source_id')->pluck('c','source_id')->all(),
            'interviews' => \App\Models\HrInterview::where('institute_id',$institute->id)->when($branchId, fn($q)=>$q->where('branch_id',$branchId))->count(),
            'selected' => (clone $baseApp)->where('current_stage','selected')->count(),
            'rejected' => (clone $baseApp)->where('current_stage','rejected')->count(),
            'hired' => (clone $baseApp)->where('current_stage','hired')->count(),
        ];

        // time-to-hire avg days from application_date to hired stage
        $hiredApps = (clone $baseApp)->where('current_stage','hired')->with('histories')->get();
        $totalDays = 0; $hiredCount = 0;
        foreach ($hiredApps as $app) {
            $hiredHist = $app->histories->where('to_stage','hired')->first();
            if ($hiredHist) {
                $days = \Carbon\Carbon::parse($app->application_date)->diffInDays($hiredHist->created_at);
                $totalDays += $days; $hiredCount++;
            }
        }
        $stats['avg_time_to_hire'] = $hiredCount ? round($totalDays/$hiredCount,1) : 0;
        $stats['by_department'] = HrVacancy::where('institute_id',$institute->id)->when($branchId, fn($q)=>$q->where('branch_id',$branchId))->selectRaw('department_id, COUNT(*) as c')->groupBy('department_id')->pluck('c','department_id')->all();
        $stats['by_branch'] = HrVacancy::where('institute_id',$institute->id)->selectRaw('branch_id, COUNT(*) as c')->groupBy('branch_id')->pluck('c','branch_id')->all();

        $recentApps = (clone $baseApp)->with(['candidateLead','vacancy'])->latest('id')->limit(10)->get();
        $openVacancies = (clone $baseVac)->whereIn('status',['published','approved'])->latest('id')->limit(5)->get();

        return view('hr.recruitment.dashboard', compact('institute','stats','recentApps','openVacancies'));
    }

    // ---------------- Requisitions

    public function requisitions(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $reqs = HrRequisition::where('institute_id',$institute->id)->when($branchId, fn($q)=>$q->where('branch_id',$branchId))->with(['department','designation'])->orderByDesc('id')->paginate(20);
        return view('hr.recruitment.requisitions', [
            'institute'=>$institute,
            'requisitions'=>$reqs,
            'canManage'=>$this->can($request,['hr.requisition.manage','hr.recruitment.manage','hr.manage']),
            'canApprove'=>$this->can($request,['hr.requisition.approve','hr.manage']),
        ]);
    }

    public function storeRequisition(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate([
            'title'=>['required','string','max:150'],
            'description'=>['nullable','string','max:5000'],
            'department_id'=>['nullable','integer','exists:hr_departments,id'],
            'designation_id'=>['nullable','integer','exists:hr_designations,id'],
            'branch_id'=>['nullable','integer','exists:branches,id'],
            'openings'=>['nullable','integer','min:1','max:1000'],
            'employment_type'=>['nullable', Rule::in(['full_time','part_time','contractual','permanent','temporary','intern','probation'])],
            'required_skills'=>['nullable','string','max:2000'],
            'experience'=>['nullable','string','max:500'],
            'education'=>['nullable','string','max:255'],
            'salary_min'=>['nullable','numeric','min:0'],
            'salary_max'=>['nullable','numeric','min:0','gte:salary_min'],
        ]);
        $branchId = $this->actingBranchId($request) ?? ($data['branch_id']??null);
        $req = $this->service->createRequisition($data,$institute->id,$branchId,$this->actorId($request));
        return redirect()->route('hr.recruitment.requisitions')->with('status','Requisition created');
    }

    public function submitRequisition(Request $request, HrRequisition $hrRequisition)
    {
        $institute = $this->requireInstitute($request);
        $this->ensureInstitute($hrRequisition,$institute->id,$this->actingBranchId($request));
        $this->service->submitRequisition($hrRequisition,$this->actorId($request));
        return back()->with('status','Requisition submitted for approval');
    }

    public function decideRequisition(Request $request, HrRequisition $hrRequisition)
    {
        $institute = $this->requireInstitute($request);
        $this->ensureInstitute($hrRequisition,$institute->id,$this->actingBranchId($request));
        $data = $request->validate(['decision'=>['required',Rule::in(['approved','rejected'])],'reason'=>['nullable','string','max:2000']]);
        $this->service->approveRequisition($hrRequisition,$data['decision'],$this->actorId($request),$data['reason']??null);
        return back()->with('status','Requisition '.$data['decision']);
    }

    // ---------------- Vacancies

    public function vacancies(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $vacs = HrVacancy::where('institute_id',$institute->id)->when($branchId, fn($q)=>$q->where('branch_id',$branchId))->with(['department','designation','requisition'])->orderByDesc('id')->paginate(20);
        return view('hr.recruitment.vacancies', ['institute'=>$institute,'vacancies'=>$vacs,'canManage'=>$this->can($request,['hr.vacancy.manage','hr.recruitment.manage','hr.manage'])]);
    }

    public function storeVacancy(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate([
            'title'=>['required','string','max:150'],
            'description'=>['nullable','string','max:5000'],
            'requisition_id'=>['nullable','integer','exists:hr_requisitions,id'],
            'department_id'=>['nullable','integer','exists:hr_departments,id'],
            'designation_id'=>['nullable','integer','exists:hr_designations,id'],
            'openings'=>['nullable','integer','min:1'],
            'employment_type'=>['nullable', Rule::in(['full_time','part_time','contractual','permanent','temporary','intern','probation'])],
            'salary_min'=>['nullable','numeric','min:0'],
            'salary_max'=>['nullable','numeric','min:0','gte:salary_min'],
            'branch_id'=>['nullable','integer','exists:branches,id'],
        ]);
        $branchId = $this->actingBranchId($request) ?? ($data['branch_id'] ?? null);
        $vac = $this->service->createVacancy($data,$institute->id,$branchId,$this->actorId($request));
        return back()->with('status','Vacancy created');
    }

    public function updateVacancyStatus(Request $request, HrVacancy $hrVacancy)
    {
        $institute = $this->requireInstitute($request);
        $this->ensureInstitute($hrVacancy,$institute->id,$this->actingBranchId($request));
        $data = $request->validate(['status'=>['required',Rule::in(['draft','pending_approval','approved','published','closed','cancelled'])]]);
        $this->service->updateVacancyStatus($hrVacancy,$data['status'],$this->actorId($request));
        return back()->with('status','Vacancy status updated');
    }

    // ---------------- Candidates & Applications

    public function candidates(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $leads = CrmLead::where('institute_id',$institute->id)->when($branchId, fn($q)=>$q->where('branch_id',$branchId))->orderByDesc('id')->paginate(20);
        return view('hr.recruitment.candidates', ['institute'=>$institute,'candidates'=>$leads]);
    }

    public function storeCandidate(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate([
            'first_name'=>['required','string','max:120'],
            'last_name'=>['nullable','string','max:120'],
            'email'=>['nullable','email','max:191'],
            'phone'=>['nullable','string','max:30'],
            'source_id'=>['nullable','integer','exists:crm_lead_sources,id'],
            'skills'=>['nullable','string','max:2000'],
            'experience'=>['nullable','string','max:2000'],
            'education'=>['nullable','string','max:2000'],
            'notes'=>['nullable','string','max:5000'],
        ]);
        $lead = $this->service->createCandidate($data,$institute->id,$this->actingBranchId($request),$this->actorId($request));
        return back()->with('status','Candidate created: '.$lead->first_name);
    }

    public function applications(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $apps = HrApplication::where('institute_id',$institute->id)->when($branchId, fn($q)=>$q->where('branch_id',$branchId))
            ->with(['candidateLead','vacancy','recruiter'])->orderByDesc('id')->paginate(20);
        $vacancies = HrVacancy::where('institute_id',$institute->id)->when($branchId, fn($q)=>$q->where('branch_id',$branchId))->whereIn('status',['published','approved'])->get();
        $leads = CrmLead::where('institute_id',$institute->id)->when($branchId, fn($q)=>$q->where('branch_id',$branchId))->limit(100)->get();
        $sources = CrmLeadSource::orderBy('display_order')->get();
        return view('hr.recruitment.applications', compact('institute','apps','vacancies','leads','sources'));
    }

    public function storeApplication(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate([
            'vacancy_id'=>['nullable','integer','exists:hr_vacancies,id'],
            'candidate_lead_id'=>['required','integer','exists:crm_leads,id'],
            'assigned_recruiter_id'=>['nullable','integer','exists:institute_users,id'],
            'application_date'=>['nullable','date'],
            'source_id'=>['nullable','integer','exists:crm_lead_sources,id'],
            'notes'=>['nullable','string','max:5000'],
        ]);
        $app = $this->service->apply($data,$institute->id,$this->actingBranchId($request),$this->actorId($request));
        return back()->with('status','Application created');
    }

    public function showApplication(Request $request, HrApplication $hrApplication)
    {
        $institute = $this->requireInstitute($request);
        $this->ensureInstitute($hrApplication,$institute->id,$this->actingBranchId($request));
        $hrApplication->load(['candidateLead','vacancy','recruiter','histories.changer','interviews','offer']);
        return view('hr.recruitment.application-show', ['institute'=>$institute,'application'=>$hrApplication,'canManage'=>$this->can($request,['hr.application.manage','hr.recruitment.manage'])]);
    }

    public function transitionStage(Request $request, HrApplication $hrApplication)
    {
        $institute = $this->requireInstitute($request);
        $this->ensureInstitute($hrApplication,$institute->id,$this->actingBranchId($request));
        $data = $request->validate(['to_stage'=>['required',Rule::in(HrApplication::STAGES)],'notes'=>['nullable','string','max:2000']]);
        $this->service->transitionStage($hrApplication,$data['to_stage'],$this->actorId($request),$data['notes']??null);
        return back()->with('status','Stage moved to '.$data['to_stage']);
    }

    // ---------------- Interviews

    public function interviews(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $interviews = HrInterview::where('institute_id',$institute->id)->when($branchId, fn($q)=>$q->where('branch_id',$branchId))->with(['application.candidateLead','interviewer'])->orderByDesc('scheduled_at')->paginate(20);
        return view('hr.recruitment.interviews', compact('institute','interviews'));
    }

    public function storeInterview(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate([
            'application_id'=>['required','integer','exists:hr_applications,id'],
            'interviewer_id'=>['nullable','integer','exists:institute_users,id'],
            'interview_type'=>['nullable',Rule::in(['onsite','online','phone','panel'])],
            'scheduled_at'=>['required','date'],
            'location'=>['nullable','string','max:255'],
            'score'=>['nullable','numeric','min:0','max:100'],
            'feedback'=>['nullable','string','max:5000'],
            'recommendation'=>['nullable',Rule::in(['hire','reject','hold','pending'])],
        ]);
        $interview = $this->service->scheduleInterview($data,$institute->id,$this->actorId($request));
        return back()->with('status','Interview scheduled');
    }

    public function updateInterview(Request $request, HrInterview $hrInterview)
    {
        $institute = $this->requireInstitute($request);
        $this->ensureInstitute($hrInterview,$institute->id,$this->actingBranchId($request));
        $data = $request->validate([
            'score'=>['nullable','numeric','min:0','max:100'],
            'feedback'=>['nullable','string','max:5000'],
            'recommendation'=>['nullable',Rule::in(['hire','reject','hold','pending'])],
            'status'=>['nullable',Rule::in(['scheduled','completed','cancelled','no_show'])],
        ]);
        $this->service->updateInterview($hrInterview,$data,$this->actorId($request));
        return back()->with('status','Interview updated');
    }

    // ---------------- Offers

    public function offers(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $offers = HrOffer::where('institute_id',$institute->id)->when($branchId, fn($q)=>$q->where('branch_id',$branchId))->with(['application.candidateLead'])->orderByDesc('id')->paginate(20);
        return view('hr.recruitment.offers', compact('institute','offers'));
    }

    public function storeOffer(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate([
            'application_id'=>['required','integer','exists:hr_applications,id'],
            'proposed_designation_id'=>['nullable','integer','exists:hr_designations,id'],
            'proposed_department_id'=>['nullable','integer','exists:hr_departments,id'],
            'proposed_branch_id'=>['nullable','integer','exists:branches,id'],
            'salary_reference'=>['nullable','string','max:100'],
            'offered_salary'=>['nullable','numeric','min:0'],
            'joining_date'=>['nullable','date'],
            'offer_date'=>['nullable','date'],
            'expiry_date'=>['nullable','date','after_or_equal:offer_date'],
            'status'=>['nullable',Rule::in(['draft','sent','accepted','rejected','withdrawn','expired'])],
        ]);
        $offer = $this->service->createOffer($data,$institute->id,$this->actorId($request));
        return back()->with('status','Offer created');
    }

    public function updateOfferStatus(Request $request, HrOffer $hrOffer)
    {
        $institute = $this->requireInstitute($request);
        $this->ensureInstitute($hrOffer,$institute->id,$this->actingBranchId($request));
        $data = $request->validate(['status'=>['required',Rule::in(['draft','sent','accepted','rejected','withdrawn','expired'])]]);
        $this->service->updateOfferStatus($hrOffer,$data['status'],$this->actorId($request));
        return back()->with('status','Offer status '.$data['status']);
    }

    // ---------------- Hiring

    public function hire(Request $request, HrApplication $hrApplication)
    {
        $institute = $this->requireInstitute($request);
        $this->ensureInstitute($hrApplication,$institute->id,$this->actingBranchId($request));
        $data = $request->validate([
            'branch_id'=>['nullable','integer','exists:branches,id'],
            'department_id'=>['nullable','integer','exists:hr_departments,id'],
            'designation_id'=>['nullable','integer','exists:hr_designations,id'],
            'employment_type'=>['nullable',Rule::in(['full_time','part_time','contractual','permanent','temporary','intern','probation'])],
            'joining_date'=>['nullable','date'],
        ]);
        $employee = $this->service->hire($hrApplication,$data,$institute->id,$this->actorId($request));
        return redirect()->route('hr.employees.show',$employee)->with('status','Candidate hired as '.$employee->employee_code);
    }
}
