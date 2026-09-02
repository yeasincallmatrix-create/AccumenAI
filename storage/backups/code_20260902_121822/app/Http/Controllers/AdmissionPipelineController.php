<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\AcademicYear;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\CrmLead;
use App\Models\CrmLeadSource;
use App\Models\CrmLeadStatus;
use App\Models\InstituteCourse;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Services\EducationAdmissionPipelineService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CRM → Education admission pipeline (Step 38).
 *
 * The pipeline board/report is a derived view over the existing source of
 * truth: CRM leads (lead / interested / won / lost stages) and students rows
 * (applicant / admitted / enrolled stages via admission_status). No parallel
 * pipeline entity exists; conversion simply creates/links the students row and
 * delegates CRM mutations to the existing CRM services.
 *
 * Institute/branch identity comes only from the authenticated user; tenant +
 * branch scopes on CrmLead/Student isolate every query and route binding, so a
 * branch-restricted user only ever sees their own branch pipeline.
 */
class AdmissionPipelineController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly EducationAdmissionPipelineService $pipeline) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $filters = $this->filters($request);

        $leads = $this->filteredLeads($filters)->get();
        $students = $this->filteredStudents($filters)->get();

        $linkedLeadIds = Student::query()
            ->whereIn('crm_lead_id', $leads->pluck('id'))
            ->pluck('crm_lead_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $board = [
            'leads' => [],
            'interested' => [],
            'won' => [],
            'lost' => [],
            'applicants' => [],
            'admitted' => [],
            'enrolled' => [],
        ];

        foreach ($leads as $lead) {
            if (in_array((int) $lead->id, $linkedLeadIds, true)) {
                continue; // already an application → shown under its student stage
            }

            $slug = $lead->status?->slug;

            if ($slug === CrmLeadStatus::SLUG_WON) {
                $board['won'][] = $lead;
            } elseif ($slug === CrmLeadStatus::SLUG_LOST) {
                $board['lost'][] = $lead;
            } elseif (in_array($slug, [CrmLeadStatus::SLUG_QUALIFIED, CrmLeadStatus::SLUG_PROPOSAL], true)) {
                $board['interested'][] = $lead;
            } else {
                $board['leads'][] = $lead;
            }
        }

        foreach ($students as $student) {
            if (in_array($student->admission_status, [
                Student::ADMISSION_STATUS_DRAFT,
                Student::ADMISSION_STATUS_SUBMITTED,
                Student::ADMISSION_STATUS_UNDER_REVIEW,
            ], true)) {
                $board['applicants'][] = $student;
            } elseif ($student->admission_status === Student::ADMISSION_STATUS_APPROVED) {
                $board['admitted'][] = $student;
            } elseif ($student->admission_status === Student::ADMISSION_STATUS_ENROLLED) {
                $board['enrolled'][] = $student;
            }
        }

        return view('admissions.pipeline', [
            'institute' => $institute,
            'board' => $board,
            'filters' => $filters,
            'statuses' => CrmLeadStatus::query()->where('status', 'active')->orderBy('display_order')->get(),
            'sources' => CrmLeadSource::query()->where('status', 'active')->orderBy('display_order')->get(),
            'branches' => $this->branches($institute->id),
            'courses' => $this->courses($institute->id),
            'academicYears' => $this->academicYears($institute->id),
            'staff' => $this->staff($institute->id),
            'canManage' => $request->user()->hasPermission('students.manage'),
        ]);
    }

    public function report(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $filters = $this->filters($request);

        $leads = $this->filteredLeads($filters)->get();
        $students = $this->filteredStudents($filters)->get();

        $linkedLeadIds = Student::query()
            ->whereIn('crm_lead_id', $leads->pluck('id'))
            ->pluck('crm_lead_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $funnel = [
            'leads' => 0,
            'interested' => 0,
            'won' => 0,
            'lost' => 0,
            'applicants' => 0,
            'admitted' => 0,
            'enrolled' => 0,
        ];

        foreach ($leads as $lead) {
            if (in_array((int) $lead->id, $linkedLeadIds, true)) {
                continue;
            }

            $slug = $lead->status?->slug;

            if ($slug === CrmLeadStatus::SLUG_WON) {
                $funnel['won']++;
            } elseif ($slug === CrmLeadStatus::SLUG_LOST) {
                $funnel['lost']++;
            } elseif (in_array($slug, [CrmLeadStatus::SLUG_QUALIFIED, CrmLeadStatus::SLUG_PROPOSAL], true)) {
                $funnel['interested']++;
            } else {
                $funnel['leads']++;
            }
        }

        foreach ($students as $student) {
            if (in_array($student->admission_status, [
                Student::ADMISSION_STATUS_DRAFT,
                Student::ADMISSION_STATUS_SUBMITTED,
                Student::ADMISSION_STATUS_UNDER_REVIEW,
            ], true)) {
                $funnel['applicants']++;
            } elseif ($student->admission_status === Student::ADMISSION_STATUS_APPROVED) {
                $funnel['admitted']++;
            } elseif ($student->admission_status === Student::ADMISSION_STATUS_ENROLLED) {
                $funnel['enrolled']++;
            }
        }

        $byCourse = $students
            ->filter(fn (Student $student) => in_array($student->admission_status, [
                Student::ADMISSION_STATUS_SUBMITTED,
                Student::ADMISSION_STATUS_UNDER_REVIEW,
                Student::ADMISSION_STATUS_APPROVED,
                Student::ADMISSION_STATUS_ENROLLED,
            ], true))
            ->groupBy('applied_course_id')
            ->map(fn (Collection $group) => [
                'course' => $group->first()->appliedCourse?->name ?? '—',
                'applicants' => $group->filter(fn ($s) => $s->admission_status !== Student::ADMISSION_STATUS_APPROVED && $s->admission_status !== Student::ADMISSION_STATUS_ENROLLED)->count(),
                'admitted' => $group->filter(fn ($s) => $s->admission_status === Student::ADMISSION_STATUS_APPROVED)->count(),
                'enrolled' => $group->filter(fn ($s) => $s->admission_status === Student::ADMISSION_STATUS_ENROLLED)->count(),
            ])
            ->sortByDesc(fn ($row) => $row['applicants'] + $row['admitted'] + $row['enrolled']);

        return view('admissions.pipeline_report', [
            'institute' => $institute,
            'funnel' => $funnel,
            'byCourse' => $byCourse,
            'filters' => $filters,
            'sources' => CrmLeadSource::query()->where('status', 'active')->orderBy('display_order')->get(),
            'branches' => $this->branches($institute->id),
            'courses' => $this->courses($institute->id),
            'academicYears' => $this->academicYears($institute->id),
            'staff' => $this->staff($institute->id),
        ]);
    }

    public function convert(Request $request, CrmLead $lead): View
    {
        $institute = $this->requireInstitute($request);
        $lead->load(['status', 'source', 'assignedUser']);

        return view('admissions.convert', [
            'institute' => $institute,
            'lead' => $lead,
            'existing' => $this->pipeline->applicationForLead($institute->id, (int) $lead->id),
            'branches' => $this->branches($institute->id),
            'courses' => $this->courses($institute->id),
            'academicYears' => $this->academicYears($institute->id),
            'batches' => $this->batches($institute->id, $lead->branch_id),
            'staff' => $this->staff($institute->id),
            'countries' => config('countries'),
            'sources' => CrmLeadSource::query()->where('status', 'active')->orderBy('display_order')->get(),
        ]);
    }

    public function store(Request $request, CrmLead $lead): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'gender' => ['nullable', 'in:male,female,other'],
            'dob' => ['nullable', 'date', 'before_or_equal:today'],
            'phone' => ['nullable', 'string', 'regex:/^\+?\d{4,20}$/'],
            'guardian_phone' => ['nullable', 'string', 'regex:/^\+?\d{4,20}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('institute_id', $institute->id)],
            'country' => ['nullable', 'string', 'max:80', Rule::in(array_keys(config('countries')))],
            'present_zip_code' => ['nullable', 'string', 'max:10'],
            'application_date' => ['required', 'date'],
            'admission_source' => ['nullable', 'string', 'max:60'],
            'applied_course_id' => ['required', 'integer', Rule::exists('institute_courses', 'course_id')->where('institute_id', $institute->id)],
            'applied_academic_year_id' => ['nullable', 'integer', Rule::exists('academic_years', 'id')->where('institute_id', $institute->id)],
            'preferred_batch_id' => ['nullable', 'integer', Rule::exists('batches', 'id')->where('institute_id', $institute->id)],
            'admission_assigned_user_id' => ['nullable', 'integer', Rule::exists('institute_users', 'id')->where('institute_id', $institute->id)],
        ]);

        $student = $this->pipeline->convertLeadToApplication(
            $lead,
            $data,
            $institute->id,
            (int) $this->actorId($request),
            $request->user()->hasPermission('crm.update'),
        );

        return redirect()
            ->route('admissions.show', $student)
            ->with('status', 'Application '.$student->application_number.' created from lead '.$lead->displayName().'.');
    }

    public function searchStudents(Request $request): JsonResponse
    {
        $institute = $this->requireInstitute($request);

        $q = trim((string) $request->query('q'));
        if ($q === '') {
            return response()->json([]);
        }

        $students = Student::query()
            ->with(['appliedCourse'])
            ->whereNull('crm_lead_id')
            ->search($q)
            ->orderByDesc('id')
            ->limit(15)
            ->get(['id', 'first_name', 'last_name', 'phone', 'email', 'student_id_number', 'application_number', 'admission_status', 'applied_course_id']);

        return response()->json($students->map(fn (Student $student) => [
            'id' => $student->id,
            'full_name' => $student->full_name,
            'phone' => $student->phone,
            'email' => $student->email,
            'student_id_number' => $student->student_id_number,
            'application_number' => $student->application_number,
            'admission_status' => $student->admission_status,
            'course' => $student->appliedCourse?->name,
        ]));
    }

    public function link(Request $request, CrmLead $lead): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $request->validate([
            'student_id' => ['required', 'integer'],
        ]);

        $student = Student::query()->findOrFail((int) $data['student_id']);

        $student = $this->pipeline->linkExistingStudent(
            $lead,
            $student,
            $institute->id,
            (int) $this->actorId($request),
            $request->user()->hasPermission('crm.update'),
        );

        return redirect()
            ->route('admissions.show', $student)
            ->with('status', 'Lead '.$lead->displayName().' linked to existing student '.$student->application_number.'.');
    }

    // ------------------------------------------------------------- Filters

    private function filteredLeads(array $filters): Builder
    {
        return CrmLead::query()
            ->with(['status', 'source', 'branch', 'assignedUser'])
            ->when($filters['q'] !== '', fn ($query, $term) => $query->where(function ($builder) use ($term) {
                $builder->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%");
            }))
            ->when($filters['branch_id'], fn ($query, $id) => $query->where('branch_id', (int) $id))
            ->when($filters['source_id'], fn ($query, $id) => $query->where('source_id', (int) $id))
            ->when($filters['assigned_user_id'], fn ($query, $id) => $query->where('assigned_user_id', (int) $id))
            ->orderByDesc('id');
    }

    private function filteredStudents(array $filters): Builder
    {
        return Student::query()
            ->with(['branch', 'appliedCourse', 'appliedAcademicYear', 'crmLead', 'preferredBatch', 'admissionAssignedUser'])
            ->when($filters['q'] !== '', fn ($query, $term) => $query->search($term))
            ->when($filters['branch_id'], fn ($query, $id) => $query->where('branch_id', (int) $id))
            ->when($filters['course_id'], fn ($query, $id) => $query->where('applied_course_id', (int) $id))
            ->when($filters['academic_year_id'], fn ($query, $id) => $query->where('applied_academic_year_id', (int) $id))
            ->when($filters['assigned_user_id'], fn ($query, $id) => $query->where('admission_assigned_user_id', (int) $id))
            ->orderByDesc('id');
    }

    /**
     * @return array{q: string, branch_id: int|null, course_id: int|null, academic_year_id: int|null, source_id: int|null, assigned_user_id: int|null}
     */
    private function filters(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q')),
            'branch_id' => $request->filled('branch_id') ? (int) $request->query('branch_id') : null,
            'course_id' => $request->filled('course_id') ? (int) $request->query('course_id') : null,
            'academic_year_id' => $request->filled('academic_year_id') ? (int) $request->query('academic_year_id') : null,
            'source_id' => $request->filled('source_id') ? (int) $request->query('source_id') : null,
            'assigned_user_id' => $request->filled('assigned_user_id') ? (int) $request->query('assigned_user_id') : null,
        ];
    }

    private function branches(int $instituteId): Collection
    {
        return Branch::query()->where('institute_id', $instituteId)->orderBy('name')->get();
    }

    private function courses(int $instituteId): Collection
    {
        return InstituteCourse::query()
            ->where('institute_id', $instituteId)
            ->with('course')
            ->get()
            ->map(fn (InstituteCourse $assignment) => $assignment->course)
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    private function academicYears(int $instituteId): Collection
    {
        return AcademicYear::query()
            ->where('institute_id', $instituteId)
            ->orderByDesc('code')
            ->get();
    }

    private function batches(int $instituteId, ?int $branchId): Collection
    {
        return Batch::query()
            ->where('institute_id', $instituteId)
            ->with('course')
            ->when($branchId, fn ($query, $id) => $query->where('branch_id', $id))
            ->orderByDesc('id')
            ->get();
    }

    private function staff(int $instituteId): Collection
    {
        return InstituteUser::query()
            ->where('institute_id', $instituteId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
