<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\AcademicGroup;
use App\Models\AcademicYear;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ClassGrade;
use App\Models\Course;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\Subject;
use App\Models\TeacherAcademicAssignment;
use App\Models\TeacherProfile;
use App\Services\ProfileImageService;
use App\Services\TeacherProfileService;
use App\Services\TeacherWorkloadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Teacher / instructor management (Step 36).
 *
 * Security model mirrors the CRM/academic controllers:
 *   - institute_id / branch_id never come from request input (resolved from the
 *     authenticated user / workspace; branch managers are pinned to their branch);
 *   - tenant + branch visibility is enforced by the InstituteUser / assignment
 *     global scopes (implicit binding 404s cross-tenant / cross-branch records);
 *   - route group gated behind teacher.* permissions; the self-service route
 *     (teachers.me) only ever shows the authenticated user's own profile.
 */
class TeacherController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly TeacherProfileService $profileService,
        private readonly TeacherWorkloadService $workload,
        private readonly ProfileImageService $profileImage,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $query = InstituteUser::query()
            ->where('role_id', $this->teacherRoleId())
            ->with(['branch', 'teacherProfile']);

        if (filled($q = trim((string) $request->query('q')))) {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('employee_id', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        if (filled($request->query('status'))) {
            $query->where('status', $request->query('status'));
        }

        if (filled($request->query('branch_id'))) {
            $query->where('branch_id', (int) $request->query('branch_id'));
        }

        if (filled($request->query('designation'))) {
            $query->where('designation', 'like', "%{$request->query('designation')}%");
        }

        $query->when(filled($request->query('employment_status')), function ($builder) {
            $builder->whereHas('teacherProfile', fn ($q) => $q->where('employment_status', request()->query('employment_status')));
        })->when(filled($request->query('qualification')), function ($builder) {
            $builder->where('qualification', 'like', '%'.request()->query('qualification').'%');
        });

        $teachers = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('institute.teachers.index', [
            'institute' => $institute,
            'teachers' => $teachers,
            'filters' => $request->query(),
            'branches' => $this->branchOptions($institute->id),
            'summary' => $this->summary($institute->id),
            'designations' => $this->designations($institute->id),
            'qualifications' => $this->qualifications($institute->id),
            'employmentStatuses' => TeacherProfile::EMPLOYMENT_STATUSES,
            'canCreate' => $request->user()->hasPermission('teacher.create'),
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.teachers.form', [
            'institute' => $institute,
            'teacher' => null,
            'profile' => null,
            'branches' => $this->branchOptions($institute->id),
            'employmentTypes' => TeacherProfile::EMPLOYMENT_TYPES,
            'employmentStatuses' => TeacherProfile::EMPLOYMENT_STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $this->validatedIdentity($request, null);
        $data = $this->mergeProfileFields($request, $data);

        $data['photo'] = $request->hasFile('photo')
            ? $this->profileImage->processAndStore($request->file('photo'), 'teachers')
            : null;

        $teacher = $this->profileService->create(
            $data,
            $institute->id,
            $this->resolveBranchId($request, $data['branch_id'] ?? null),
            (int) $this->actorId($request)
        );

        return redirect()
            ->route('teachers.show', $teacher)
            ->with('status', 'Teacher "'.$teacher->name.'" created.');
    }

    public function show(Request $request, InstituteUser $teacher): View
    {
        $institute = $this->requireInstitute($request);
        $this->ensureTeacher($teacher);

        $teacher->load(['branch', 'teacherProfile']);

        return $this->renderShow($request, $institute, $teacher);
    }

    public function me(Request $request): View
    {
        abort_unless($request->user() instanceof InstituteUser, 404);

        $institute = $this->requireInstitute($request);

        $teacher = InstituteUser::query()->whereKey($request->user()->getKey())->first();
        abort_if($teacher === null, 404);

        $this->ensureTeacher($teacher);
        $teacher->load(['branch', 'teacherProfile']);

        return $this->renderShow($request, $institute, $teacher);
    }

    public function edit(Request $request, InstituteUser $teacher): View
    {
        $institute = $this->requireInstitute($request);
        $this->ensureTeacher($teacher);

        $teacher->load('teacherProfile');

        return view('institute.teachers.form', [
            'institute' => $institute,
            'teacher' => $teacher,
            'profile' => $teacher->teacherProfile,
            'branches' => $this->branchOptions($institute->id),
            'employmentTypes' => TeacherProfile::EMPLOYMENT_TYPES,
            'employmentStatuses' => TeacherProfile::EMPLOYMENT_STATUSES,
        ]);
    }

    public function update(Request $request, InstituteUser $teacher): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $this->ensureTeacher($teacher);

        $data = $this->validatedIdentity($request, $teacher->id);
        $data = $this->mergeProfileFields($request, $data);

        if ($request->hasFile('photo')) {
            $data['photo'] = $this->profileImage->processAndStore($request->file('photo'), 'teachers');
        } elseif ($request->boolean('remove_photo')) {
            $data['photo'] = null;
        } else {
            $data['photo'] = $teacher->photo;
        }

        $this->profileService->update(
            $teacher,
            $data,
            $institute->id,
            $this->resolveBranchId($request, $data['branch_id'] ?? null),
            (int) $this->actorId($request)
        );

        return redirect()
            ->route('teachers.show', $teacher)
            ->with('status', 'Teacher updated.');
    }

    public function status(Request $request, InstituteUser $teacher): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $this->ensureTeacher($teacher);

        $data = $request->validate([
            'employment_status' => ['required', Rule::in(TeacherProfile::EMPLOYMENT_STATUSES)],
        ]);

        $this->profileService->setStatus($teacher, $data['employment_status'], $institute->id, (int) $this->actorId($request));

        return back()->with('status', 'Teacher status updated to '.str_replace('_', ' ', $data['employment_status']).'.');
    }

    public function assign(Request $request, InstituteUser $teacher): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $this->ensureTeacher($teacher);

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'academic_year_id' => ['required', 'integer'],
            'course_id' => ['nullable', 'integer'],
            'subject_id' => ['nullable', 'integer'],
            'batch_id' => ['nullable', 'integer'],
            'class_grade_id' => ['nullable', 'integer'],
            'academic_group_id' => ['nullable', 'integer'],
            'responsibility' => ['required', Rule::in(TeacherAcademicAssignment::RESPONSIBILITIES)],
            'assigned_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['institute_user_id'] = $teacher->id;
        $data['branch_id'] = $this->resolveBranchId($request, $data['branch_id'] ?? null);

        $this->profileService->assign($data, $institute->id, (int) $this->actorId($request), $this->actingBranchId($request));

        return back()->with('status', 'Teaching assignment added.');
    }

    public function complete(Request $request, TeacherAcademicAssignment $assignment): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $this->profileService->complete($assignment, $institute->id, (int) $this->actorId($request));

        return back()->with('status', 'Assignment marked as completed.');
    }

    public function remove(Request $request, TeacherAcademicAssignment $assignment): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $this->profileService->remove($assignment, $institute->id, (int) $this->actorId($request));

        return back()->with('status', 'Assignment removed.');
    }

    // ------------------------------------------------------------- Internals

    private function renderShow(Request $request, $institute, InstituteUser $teacher): View
    {
        $assignments = TeacherAcademicAssignment::query()
            ->where('institute_user_id', $teacher->id)
            ->with(['academicYear', 'course', 'subject', 'batch', 'classGrade', 'academicGroup', 'branch', 'creator'])
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('institute.teachers.show', [
            'institute' => $institute,
            'teacher' => $teacher,
            'profile' => $teacher->teacherProfile,
            'workload' => $this->workload->summary($teacher),
            'assignments' => $assignments,
            'isSelf' => $request->user()->getKey() === $teacher->id,
            'canManage' => $request->user()->hasPermission('teacher.update'),
            'canDelete' => $request->user()->hasPermission('teacher.delete'),
            'employmentStatuses' => TeacherProfile::EMPLOYMENT_STATUSES,
            'branches' => $this->branchOptions($institute->id),
            'academicYears' => AcademicYear::query()->where('status', true)->orderByDesc('start_date')->get(['id', 'name', 'code']),
            'courses' => Course::query()
                ->where('status', 'active')
                ->where(fn ($q) => $q->where('institute_id', $institute->id)->orWhereNull('institute_id'))
                ->orderBy('name')
                ->get(['id', 'name', 'course_code']),
            'subjects' => Subject::query()
                ->where('status', 'active')
                ->where(fn ($q) => $q->where('institute_id', $institute->id)->orWhereNull('institute_id'))
                ->orderBy('name')
                ->get(['id', 'name', 'subject_code']),
            'batches' => Batch::query()->orderBy('name')->get(['id', 'name', 'batch_code']),
            'classGrades' => ClassGrade::query()->where('status', true)->orderBy('display_order')->get(['id', 'name']),
            'academicGroups' => AcademicGroup::query()->where('status', true)->orderBy('display_order')->get(['id', 'name']),
            'responsibilities' => TeacherAcademicAssignment::RESPONSIBILITIES,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedIdentity(Request $request, ?int $ignoreId): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'string', 'email', 'max:150', Rule::unique('institute_users', 'email')->ignore($ignoreId)],
            'phone' => ['required', 'string', 'regex:/^\+?\d{4,20}$/', Rule::unique('institute_users', 'phone')->ignore($ignoreId)],
            'password' => \App\Support\PasswordPolicy::nullableRules(),
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'designation' => ['nullable', 'string', 'max:80'],
            'department' => ['nullable', 'string', 'max:80'],
            'qualification' => ['nullable', 'string', 'max:150'],
            'joining_date' => ['nullable', 'date'],
            'branch_id' => ['nullable', 'integer'],
            'specialization' => ['nullable', 'string', 'max:150'],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:70'],
            'employment_type' => ['nullable', Rule::in(TeacherProfile::EMPLOYMENT_TYPES)],
            'employment_status' => ['nullable', Rule::in(TeacherProfile::EMPLOYMENT_STATUSES)],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'regex:/^\+?\d{4,20}$/'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function mergeProfileFields(Request $request, array $data): array
    {
        $data['skills'] = $this->csv($request, 'skills');
        $data['languages'] = $this->csv($request, 'languages');

        return $data;
    }

    private function csv(Request $request, string $key): ?array
    {
        $raw = $request->input($key);

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $values = array_values(array_filter(array_map('trim', explode(',', $raw)), fn ($value) => $value !== ''));

        return $values === [] ? null : array_slice($values, 0, 20);
    }

    private function ensureTeacher(InstituteUser $teacher): void
    {
        abort_if($teacher->role?->slug !== 'teacher', 404, 'Teacher not found.');
    }

    private function teacherRoleId(): int
    {
        return (int) Role::where('slug', 'teacher')->value('id');
    }

    private function resolveBranchId(Request $request, ?int $validatedBranchId): ?int
    {
        $acting = $this->actingBranchId($request);

        return $acting ?? $validatedBranchId;
    }

    /**
     * Branch options for forms — branch managers only ever see their own branch.
     */
    private function branchOptions(int $instituteId): Collection
    {
        $acting = $this->actingBranchId(request());

        return Branch::query()
            ->where('institute_id', $instituteId)
            ->where('status', 'active')
            ->when($acting !== null, fn ($q) => $q->whereKey($acting))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Aggregate dashboard summary (no N+1; scoped for branch managers by the
     * InstituteUser / assignment global scopes).
     *
     * @return array<string, mixed>
     */
    private function summary(int $instituteId): array
    {
        $base = fn () => InstituteUser::query()->where('role_id', $this->teacherRoleId());

        $total = (clone $base())->count();
        $active = (clone $base())->where('status', 'active')->count();
        $inactive = (clone $base())->where('status', '!=', 'active')->count();
        $assigned = (clone $base())->whereHas('academicAssignments', fn ($q) => $q->where('status', 'active'))->count();

        $byBranch = (clone $base())
            ->select('branch_id')
            ->whereNotNull('branch_id')
            ->groupBy('branch_id')
            ->pluck('branch_id')
            ->mapWithKeys(fn ($branchId) => [$branchId => (clone $base())->where('branch_id', $branchId)->count()])
            ->all();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'assigned' => $assigned,
            'unassigned' => max(0, $total - $assigned),
            'by_branch' => $byBranch,
        ];
    }

    private function designations(int $instituteId): Collection
    {
        return InstituteUser::query()
            ->where('role_id', $this->teacherRoleId())
            ->whereNotNull('designation')
            ->distinct()
            ->pluck('designation')
            ->sort()
            ->values();
    }

    private function qualifications(int $instituteId): Collection
    {
        return InstituteUser::query()
            ->where('role_id', $this->teacherRoleId())
            ->whereNotNull('qualification')
            ->distinct()
            ->pluck('qualification')
            ->sort()
            ->values();
    }
}
