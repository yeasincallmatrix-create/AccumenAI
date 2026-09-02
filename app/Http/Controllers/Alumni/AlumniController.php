<?php

namespace App\Http\Controllers\Alumni;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Alumni;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\InstituteCourse;
use App\Models\Student;
use App\Services\Alumni\AlumniService;
use App\Support\CsvStream;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule as ValidationRule;
use Illuminate\View\View;
use LogicException;

class AlumniController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly AlumniService $alumni,
    ) {}

    /**
     * Alumni dashboard: headline stats + recent profiles. Tenant + branch
     * isolation comes from the scoped Alumni query (branch via the student).
     */
    public function index(Request $request): View
    {
        $instituteId = $this->resolveInstitute($request)?->id;

        $totals = Alumni::query()->inScope()
            ->selectRaw('count(*) as total, sum(case when status = ? then 1 else 0 end) as active, sum(case when status = ? then 1 else 0 end) as inactive', [Alumni::STATUS_ACTIVE, Alumni::STATUS_INACTIVE])
            ->first();

        $employed = Alumni::query()->inScope()->whereNotNull('employer')->count();
        $recent = Alumni::query()->inScope()
            ->with(['student', 'completedCourse', 'completedBatch', 'completionAcademicYear'])
            ->latest('id')
            ->limit(8)
            ->get();

        return view('alumni.index', [
            'instituteId' => $instituteId,
            'totals' => [
                'total' => (int) ($totals->total ?? 0),
                'active' => (int) ($totals->active ?? 0),
                'inactive' => (int) ($totals->inactive ?? 0),
            ],
            'employed' => $employed,
            'recent' => $recent,
        ]);
    }

    /**
     * Searchable / filterable alumni directory.
     */
    public function directory(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $filters = $request->only([
            'search',
            'status',
            'completion_academic_year_id',
            'completed_course_id',
            'completed_batch_id',
            'current_occupation',
            'employer',
            'graduation_year',
        ]);

        $alumni = $this->alumni->directoryQuery($filters)
            ->paginate(15)
            ->withQueryString();

        return view('alumni.directory', [
            'alumni' => $alumni,
            'filters' => $filters,
            'branches' => Branch::where('institute_id', $institute->id)->orderBy('name')->get(),
            'academicYears' => AcademicYear::query()->where('institute_id', $institute->id)->orderByDesc('code')->get(),
            'courses' => InstituteCourse::query()->where('institute_id', $institute->id)->with('course')->get()
                ->map(fn (InstituteCourse $assignment) => $assignment->course)
                ->filter()->unique('id')->sortBy('name')->values(),
            'batches' => Batch::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * "Add Alumni" form: search a student and activate their alumni profile.
     */
    public function create(Request $request): View
    {
        return view('alumni.create', [
            'recent' => $this->alumni->searchEligibleStudents('', 10),
        ]);
    }

    /**
     * JSON student search for the add-alumni flow (eligible + not yet added).
     */
    public function searchStudents(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));

        if ($q === '') {
            return response()->json(['results' => []]);
        }

        $results = $this->alumni->searchEligibleStudents($q, 20);

        return response()->json([
            'results' => $results->map(fn (array $row) => [
                'id' => $row['student']->id,
                'name' => $row['student']->full_name ?: trim($row['student']->first_name.' '.$row['student']->last_name),
                'reg_no' => $row['student']->reg_no,
                'student_id_number' => $row['student']->student_id_number,
                'eligible' => $row['eligibility']['eligible'],
                'outcome' => $row['eligibility']['outcome'],
                'graduation_date' => $row['eligibility']['graduationDate'],
            ]),
        ]);
    }

    /**
     * Activate (create) the alumni profile. Eligibility is enforced
     * server-side; the student is resolved through the scoped model
     * (cross-tenant / cross-branch → 404). Idempotent: re-adding returns the
     * existing profile.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'alumni_reference_number' => ['nullable', 'string', 'max:40'],
            'graduation_date' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $student = Student::query()->find((int) $data['student_id']);

        abort_unless($student instanceof Student, 404);

        try {
            $alumni = $this->alumni->createForStudent($student, (int) $request->user()->id, $data);
        } catch (LogicException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('alumni.show', $alumni)
            ->with('status', "Alumni profile activated for {$student->full_name}.");
    }

    /**
     * Alumni profile: career info + read-only academic history from the
     * existing source-of-truth records.
     */
    public function show(Alumni $alumni): View
    {
        $alumni->load([
            'student',
            'completionAcademicYear',
            'completedCourse',
            'completedBatch',
            'crmContact',
            'createdBy',
            'updatedBy',
        ]);

        $certificates = $alumni->student->certificates()
            ->with('course', 'batch')
            ->latest('id')
            ->limit(20)
            ->get();

        return view('alumni.show', [
            'alumni' => $alumni,
            'certificates' => $certificates,
        ]);
    }

    public function edit(Alumni $alumni): View
    {
        return view('alumni.edit', ['alumni' => $alumni]);
    }

    /**
     * Update only alumni/career fields; academic provenance is read-only.
     */
    public function update(Request $request, Alumni $alumni): RedirectResponse
    {
        $data = $request->validate([
            'alumni_reference_number' => ['nullable', 'string', 'max:40'],
            'graduation_date' => ['nullable', 'date', 'before_or_equal:today'],
            'current_occupation' => ['nullable', 'string', 'max:150'],
            'job_title' => ['nullable', 'string', 'max:150'],
            'employer' => ['nullable', 'string', 'max:150'],
            'employment_sector' => ['nullable', 'string', 'max:150'],
            'higher_education' => ['nullable', 'string', 'max:2000'],
            'career_notes' => ['nullable', 'string', 'max:4000'],
            'current_city' => ['nullable', 'string', 'max:120'],
            'current_country' => ['nullable', 'string', 'max:120'],
            'public_contact_preference' => ['required', ValidationRule::in([Alumni::CONTACT_PREFERENCE_PRIVATE, Alumni::CONTACT_PREFERENCE_EMAIL, Alumni::CONTACT_PREFERENCE_PHONE, Alumni::CONTACT_PREFERENCE_BOTH])],
            'profile_visibility' => ['required', ValidationRule::in([Alumni::PROFILE_VISIBILITY_PRIVATE, Alumni::PROFILE_VISIBILITY_PUBLIC])],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $this->alumni->updateProfile($alumni, $data, (int) $request->user()->id);

        return redirect()
            ->route('alumni.show', $alumni)
            ->with('status', "Alumni profile for {$alumni->student->full_name} updated.");
    }

    /**
     * Activate / deactivate an alumni profile (soft visibility toggle; the
     * record and its academic provenance stay intact).
     */
    public function status(Request $request, Alumni $alumni): RedirectResponse
    {
        $status = $request->validate(['status' => ['required', ValidationRule::in(Alumni::STATUSES)]])['status'];

        $this->alumni->setStatus($alumni, $status, (int) $request->user()->id);

        return back()->with('status', $status === Alumni::STATUS_ACTIVE
            ? "Alumni profile for {$alumni->student->full_name} reactivated."
            : "Alumni profile for {$alumni->student->full_name} deactivated.");
    }

    public function destroy(Request $request, Alumni $alumni): RedirectResponse
    {
        $this->alumni->delete($alumni, (int) $request->user()->id);

        return redirect()
            ->route('alumni.directory')
            ->with('status', 'Alumni profile removed.');
    }

    /**
     * Alumni reports: totals + breakdowns by course / batch / academic year /
     * graduation year / occupation / employer. Branch filter is validated
     * against the acting branch context.
     */
    public function reports(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $instituteId = (int) $institute->id;

        $branchId = $request->query('branch_id');
        $branchId = $branchId !== null && $branchId !== '' ? (int) $branchId : null;

        if ($branchId !== null) {
            $branch = Branch::query()->find($branchId);
            abort_unless($branch !== null && $branch->institute_id === $instituteId, 404);
        }

        $report = $this->alumni->reportAggregates($instituteId, $branchId);

        return view('alumni.reports', [
            'report' => $report,
            'branchId' => $branchId,
            'branches' => Branch::where('institute_id', $instituteId)->orderBy('name')->get(),
        ]);
    }

    /**
     * CSV export of the current directory filter. Streamed, never loaded in
     * memory (CsvStream).
     */
    public function export(Request $request)
    {
        $filters = $request->only([
            'search',
            'status',
            'completion_academic_year_id',
            'completed_course_id',
            'completed_batch_id',
            'current_occupation',
            'employer',
            'graduation_year',
        ]);

        $rows = $this->alumni->directoryQuery($filters)
            ->with(['student', 'completionAcademicYear', 'completedCourse', 'completedBatch'])
            ->get()
            ->map(fn (Alumni $alumni) => [
                $alumni->alumni_reference_number,
                $alumni->student->full_name ?: trim($alumni->student->first_name.' '.$alumni->student->last_name),
                $alumni->student->reg_no,
                $alumni->student->student_id_number,
                $alumni->completedCourse?->name,
                $alumni->completedBatch?->name,
                $alumni->completionAcademicYear?->name,
                $alumni->graduation_date?->toDateString(),
                $alumni->current_occupation,
                $alumni->job_title,
                $alumni->employer,
                $alumni->employment_sector,
                $alumni->current_city,
                $alumni->current_country,
                $alumni->status,
                $alumni->profile_visibility,
                $alumni->public_contact_preference,
                $alumni->higher_education,
            ]);

        return CsvStream::download('alumni-directory.csv', [
            'Reference', 'Name', 'Registration', 'Student ID', 'Course', 'Batch',
            'Completion Year', 'Graduation Date', 'Occupation', 'Job Title',
            'Employer', 'Sector', 'City', 'Country', 'Status', 'Visibility',
            'Contact Preference', 'Higher Education',
        ], $rows);
    }
}
