<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\HrEmployee;
use App\Models\HrEmployeeSkill;
use App\Models\HrTraining;
use App\Models\HrTrainingEnrollment;
use App\Services\HrTrainingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HrTrainingController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly HrTrainingService $trainingService) {}

    public function dashboard(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $totalTrainings = HrTraining::where('institute_id', $institute->id)->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->count();
        $enrollments = HrTrainingEnrollment::where('institute_id', $institute->id)->when($branchId, fn ($q) => $q->whereHas('training', fn ($qq) => $qq->where('branch_id', $branchId)))->count();
        $completed = HrTrainingEnrollment::where('institute_id', $institute->id)->where('status', 'completed')->when($branchId, fn ($q) => $q->whereHas('employee', fn ($qq) => $qq->where('branch_id', $branchId)))->count();
        $cost = HrTraining::where('institute_id', $institute->id)->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->sum('cost');

        return view('hr.training.dashboard', ['institute' => $institute, 'stats' => compact('totalTrainings', 'enrollments', 'completed', 'cost')]);
    }

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $query = HrTraining::withCount('enrollments')->where('institute_id', $institute->id)->when($this->actingBranchId($request), fn ($q) => $q->where('branch_id', $this->actingBranchId($request)));
        if (filled($request->query('status'))) {
            $query->where('status', $request->query('status'));
        }
        $trainings = $query->orderByDesc('start_date')->paginate(20)->withQueryString();

        return view('hr.training.index', ['institute' => $institute, 'trainings' => $trainings, 'branches' => $this->branchOptions($institute->id), 'canManage' => $request->user()->hasPermission('hr.training.manage')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'provider' => ['nullable', 'string', 'max:150'],
            'trainer' => ['nullable', 'string', 'max:150'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'location' => ['nullable', 'string', 'max:200'],
            'is_online' => ['nullable', 'boolean'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['draft', 'planned', 'ongoing', 'completed', 'cancelled'])],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);
        $branchId = $this->actingBranchId($request) ?? $data['branch_id'] ?? null;
        $this->trainingService->createTraining($data, $institute->id, $branchId, $this->actorId($request));

        return back()->with('status', 'Training created.');
    }

    public function update(Request $request, HrTraining $training): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'provider' => ['nullable', 'string', 'max:150'],
            'trainer' => ['nullable', 'string', 'max:150'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'location' => ['nullable', 'string', 'max:200'],
            'is_online' => ['nullable', 'boolean'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['draft', 'planned', 'ongoing', 'completed', 'cancelled'])],
        ]);
        $this->trainingService->updateTraining($training, $data, $institute->id, $this->actorId($request));

        return back()->with('status', 'Training updated.');
    }

    public function show(Request $request, HrTraining $training): View
    {
        $institute = $this->requireInstitute($request);
        abort_if((int) $training->institute_id !== (int) $institute->id, 404);
        if ($this->actingBranchId($request) && $training->branch_id && (int) $training->branch_id !== (int) $this->actingBranchId($request)) {
            abort(404);
        }
        $training->load(['enrollments.employee']);

        return view('hr.training.show', [
            'institute' => $institute, 'training' => $training,
            'employees' => $this->employeeOptions($institute->id),
            'canEnroll' => $request->user()->hasPermission('hr.training.enroll'),
            'canManage' => $request->user()->hasPermission('hr.training.manage'),
        ]);
    }

    public function enroll(Request $request, HrTraining $training): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:hr_employees,id'],
            'status' => ['nullable', Rule::in(['enrolled', 'attending', 'completed', 'dropped', 'cancelled'])],
        ]);
        $this->trainingService->enroll(['training_id' => $training->id, 'employee_id' => $data['employee_id'], 'status' => $data['status'] ?? 'enrolled'], $institute->id, $this->actingBranchId($request), $this->actorId($request));

        return back()->with('status', 'Employee enrolled.');
    }

    public function updateEnrollment(Request $request, HrTrainingEnrollment $enrollment): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['enrolled', 'attending', 'completed', 'dropped', 'cancelled'])],
            'attendance_status' => ['nullable', Rule::in(['present', 'absent', 'partial'])],
            'result' => ['nullable', Rule::in(['pass', 'fail', 'pending'])],
            'score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'completion_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'certificate' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
        ]);
        $payload = collect($data)->except('certificate')->toArray();
        if ($request->hasFile('certificate')) {
            $payload['certificate_file'] = $request->file('certificate');
        }
        $this->trainingService->updateEnrollment($enrollment, $payload, $institute->id, $this->actorId($request));

        return back()->with('status', 'Enrollment updated.');
    }

    public function skills(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $query = HrEmployeeSkill::with(['employee', 'verifier'])->where('institute_id', $institute->id)->when($this->actingBranchId($request), fn ($q) => $q->whereHas('employee', fn ($qq) => $qq->where('branch_id', $this->actingBranchId($request))));
        if (filled($request->query('employee_id'))) {
            $query->where('employee_id', (int) $request->query('employee_id'));
        }
        $skills = $query->orderByDesc('acquired_date')->paginate(20)->withQueryString();

        return view('hr.training.skills', [
            'institute' => $institute, 'skills' => $skills,
            'employees' => $this->employeeOptions($institute->id),
            'proficiencies' => HrEmployeeSkill::PROFICIENCY,
        ]);
    }

    public function storeSkill(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:hr_employees,id'],
            'skill_name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'proficiency_level' => ['required', Rule::in(HrEmployeeSkill::PROFICIENCY)],
            'acquired_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->trainingService->createSkill($data, $institute->id, $this->actingBranchId($request), $this->actorId($request));

        return back()->with('status', 'Skill added.');
    }

    public function verifySkill(Request $request, HrEmployeeSkill $skill): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate(['status' => ['required', Rule::in(['verified', 'rejected'])]]);
        $this->trainingService->verifySkill($skill, $institute->id, $this->actorId($request), $data['status']);

        return back()->with('status', 'Skill '.$data['status'].'.');
    }

    private function branchOptions(int $instituteId)
    {
        $acting = $this->actingBranchId(request());

        return Branch::query()->where('institute_id', $instituteId)->where('status', 'active')->when($acting !== null, fn ($q) => $q->whereKey($acting))->orderBy('name')->get(['id', 'name']);
    }

    private function employeeOptions(int $instituteId)
    {
        return HrEmployee::query()->where('institute_id', $instituteId)->when($this->actingBranchId(request()), fn ($q) => $q->where('branch_id', $this->actingBranchId(request())))->orderBy('display_name')->limit(200)->get(['id', 'display_name', 'employee_code']);
    }
}
