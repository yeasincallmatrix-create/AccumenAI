<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Models\AdministrativeUnit;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Country;
use App\Models\Institute;
use App\Models\Result;
use App\Models\Student;
use App\Models\TrainingBatchResult;
use App\Models\StudentAcademicPlacement;
use App\Services\AdmissionWorkflowService;
use App\Services\Education\BatchLifecycleService;
use App\Services\EducationCrmIntegrationService;
use App\Services\ProfileImageService;
use App\Services\StudentAcademicAttendanceService;
use App\Services\StudentAcademicCertificateRequestService;
use App\Services\StudentAcademicCertificateService;
use App\Services\StudentAcademicExitService;
use App\Services\StudentAcademicHistoryService;
use App\Services\StudentAcademicLifecycleService;
use App\Services\AcademicCumulativeService;
use App\Support\BdGeo;
use App\Support\GeoHierarchy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class StudentController extends Controller
{
    public const STUDENT_COLUMNS = ['serial', 'no', 'roll', 'name', 'phone', 'email', 'reg', 'gender', 'dob', 'age', 'blood', 'religion', 'nationality', 'nid', 'passport', 'branch', 'guardian', 'admission', 'status', 'action'];

    public function __construct(
        private readonly ProfileImageService $profileImage,
        private readonly StudentAcademicHistoryService $academicHistory,
        private readonly StudentAcademicCertificateService $academicCertificates,
        private readonly StudentAcademicCertificateRequestService $academicCertificateRequests,
        private readonly StudentAcademicLifecycleService $academicLifecycle,
        private readonly StudentAcademicExitService $academicExit,
        private readonly StudentAcademicAttendanceService $academicAttendance,
        private readonly EducationCrmIntegrationService $crmIntegration,
        private readonly AdmissionWorkflowService $admissionWorkflow,
        private readonly BatchLifecycleService $batches,
        private readonly AcademicCumulativeService $cumulativeGpa,
    ) {}

    public function index(Request $request): View
    {
        $instituteId = $request->user()->institute_id;

        $sort = $request->query('sort');
        $dir = $request->query('dir');
        $sort = in_array($sort, ['admission', 'age'], true) ? $sort : null;
        $dir = in_array($dir, ['asc', 'desc'], true) ? $dir : null;

        $students = Student::query()
            ->with('branch')
            ->search($request->query('q'))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('gender'), fn ($query, $gender) => $query->where('gender', $gender))
            ->when($request->query('religion'), fn ($query, $religion) => $query->where('religion', $religion))
            ->when($request->query('branch_id'), fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when($sort === 'admission' && $dir, fn ($query) => $query->orderBy('admission_date', $dir))
            ->when($sort === 'age' && $dir, fn ($query) => $query->orderBy('dob', $dir === 'asc' ? 'desc' : 'asc'))
            ->when(! $sort || ! $dir, fn ($query) => $query->latest('id'))
            ->paginate(20)
            ->withQueryString();

        $editingStudent = null;
        $editingId = (int) old('student_id');
        if ($editingId && $request->session()->has('errors')) {
            $editingStudent = Student::find($editingId);
        }

        $visibleColumns = $request->user()->preference('columns_students', self::STUDENT_COLUMNS);
        $visibleColumns = array_values(array_intersect(self::STUDENT_COLUMNS, (array) $visibleColumns));

        $canManage = $request->user()->hasPermission('students.manage');
        $editData = $canManage
            ? $students->mapWithKeys(fn (Student $student) => [$student->id => $this->studentEditData($student)])->all()
            : $students->mapWithKeys(function (Student $student) {
                $data = $this->studentEditData($student);
                unset($data['nid_number'], $data['birth_cert_number'], $data['passport_number']);

                return [$student->id => $data];
            })->all();

        return view('students.index', [
            'students' => $students,
            'q' => $request->query('q'),
            'status' => $request->query('status'),
            'gender' => $request->query('gender'),
            'religion' => $request->query('religion'),
            'branchId' => $request->query('branch_id'),
            'branches' => $this->branches($instituteId),
            'sort' => $sort,
            'dir' => $dir,
            'instituteId' => $instituteId,
            'geo' => $this->geo($instituteId),
            'country' => $this->instituteCountry($instituteId),
            'defaultCountryId' => $this->instituteCountryId($instituteId),
            'editData' => $editData,
            'editingStudent' => $editingStudent,
            'visibleColumns' => $visibleColumns,
        ]);
    }

    public function create(Request $request): View
    {
        $student = new Student(['institute_id' => $request->user()->institute_id, 'status' => 'active']);
        $this->defaultAddressCountry($student, $request->user()->institute_id);

        return view('students.form', [
            'student' => $student,
            'branches' => $this->branches($request->user()->institute_id),
            'nextNumber' => Student::nextStudentNumber($request->user()->institute_id),
            'geo' => $this->geo($request->user()->institute_id),
            'country' => $this->instituteCountry($request->user()->institute_id),
            'countries' => config('countries'),
            'defaultCountryId' => $this->instituteCountryId($request->user()->institute_id),
            'presentAddress' => $this->addressData($student, 'present_', $this->instituteCountryId($request->user()->institute_id)),
            'permanentAddress' => $this->addressData($student, 'permanent_', $this->instituteCountryId($request->user()->institute_id)),
        ]);
    }

    public function store(StoreStudentRequest $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validated();
        $data['institute_id'] = $user->institute_id;
        $data['student_id_number'] = Student::nextStudentNumber($user->institute_id);
        // A directly registered student is an approved admission awaiting
        // batch assignment; the funnel then flips it to enrolled on enrollment.
        $data['admission_status'] = Student::ADMISSION_STATUS_APPROVED;

        if ($request->hasFile('photo')) {
            $data['photo'] = $this->profileImage->processAndStore($request->file('photo'));
        }

        if ($request->hasFile('document')) {
            $data['document'] = $this->storeStudentDocument($data['student_id_number'], $request->file('document'));
        }

        try {
            $student = Student::create($data);
        } catch (QueryException $e) {
            // Very rare: concurrent creation collided on the generated number.
            $student = Student::create(array_merge($data, [
                'student_id_number' => (string) ((int) $data['student_id_number'] + 1),
            ]));
        }

        // Step 34: link the admission to CRM (lead + contact) without ever
        // failing the admission when CRM is unavailable or the actor lacks the
        // crm.create permission.
        try {
            if ($user->hasPermission('crm.create')) {
                $this->crmIntegration->ensureStudentCrmLink($student, $student->branch_id, (int) $user->id);
                $this->crmIntegration->captureAdmissionLead($student, $student->branch_id, (int) $user->id);
            }
        } catch (\Throwable $e) {
            Log::warning('Education→CRM admission integration skipped.', ['student_id' => $student->id, 'error' => $e->getMessage()]);
        }

        return redirect()
            ->route('students.show', $student)
            ->with('status', "Student {$student->full_name} added.");
    }

    public function show(Request $request, Student $student): View
    {
        $student->load(['branch', 'enrollments.batch.course', 'certificates.course', 'certificates.batch']);

        $results = TrainingBatchResult::query()
            ->where('student_id', $student->id)
            ->get()
            ->keyBy('batch_id');

        $batches = Batch::query()
            ->with('course')
            ->where('status', '!=', 'cancelled')
            ->orderBy('name')
            ->get();

        return view('students.show', [
            'student' => $student,
            'results' => $results,
            'batches' => $batches,
            'branches' => $this->branches($request->user()->institute_id),
            'geo' => $this->geo($request->user()->institute_id),
            'country' => $this->instituteCountry($request->user()->institute_id),
            'defaultCountryId' => $this->instituteCountryId($request->user()->institute_id),
            'presentAddress' => $this->addressData($student, 'present_', $this->instituteCountryId($request->user()->institute_id)),
            'permanentAddress' => $this->addressData($student, 'permanent_', $this->instituteCountryId($request->user()->institute_id)),
            'lifecycle' => $this->academicLifecycle->forStudent($student),
        ]);
    }

    public function academicHistory(Request $request, Student $student): View
    {
        $selectedYearId = $request->integer('academic_year_id') ?: null;

        $history = $this->academicHistory->forStudent($student, $selectedYearId);
        $lifecycle = $this->academicLifecycle->forStudent($student);

        return view('students.academic_history', $history + [
            'selectedYearId' => $selectedYearId,
            'lifecycle' => $lifecycle,
            'certificates' => $this->academicCertificates->forStudent($student),
            'attendanceByPlacement' => $this->academicAttendance
                ->summariesForPlacements($history['timeline']->pluck('placement')),
            'canViewAttendanceReport' => $request->user()->hasPermission('attendance.manage'),
            'certificateRequestable' => $request->user()->hasPermission('certificates.manage')
                && ($lifecycle['isCompletion'] || $lifecycle['isGraduation'])
                && ! $this->academicCertificateRequests->hasPendingRequest($student),
        ]);
    }

    /**
     * Academic attendance for a student: per-year summary + recent day records
     * resolved against the academic placement that was active on each date.
     * Read-only — attendance is a separate data source and never touches
     * academic results or published snapshots.
     */
    public function academicAttendance(Request $request, Student $student): View
    {
        $selectedYearId = $request->integer('academic_year_id') ?: null;

        $academicYears = $this->academicHistory->academicYears($student);

        $year = $selectedYearId !== null
            ? $academicYears->firstWhere('id', $selectedYearId)
            : $academicYears->first();

        $records = $year !== null
            ? $this->academicAttendance->recordsForStudentInYear($student, $year)
            : null;

        $summary = $year !== null
            ? $this->academicAttendance->summaryForPlacement(
                $student->academicPlacements()->where('academic_year_id', $year->id)->firstOrFail()
            )
            : null;

        return view('students.academic_attendance', [
            'student' => $student,
            'academicYears' => $academicYears,
            'selectedYearId' => $year?->id,
            'year' => $year,
            'records' => $records,
            'summary' => $summary,
            'lifecycle' => $this->academicLifecycle->forStudent($student),
        ]);
    }

    public function academicTranscript(Request $request, Student $student): View
    {
        $history = $this->academicHistory->forStudent($student);

        $cgpa = $this->cumulativeGpa->compute($student);

        return view('students.academic_transcript', $history + [
            'institute' => Institute::where('id', $student->institute_id)->first(),
            'cgpa' => $cgpa,
        ]);
    }

    /**
     * Officially withdraw the student from the academic program by closing
     * their current active academic placement (`dropped`). Never deletes the
     * student, the placement, marks, results or promotion history.
     *
     * Guarded by permission:students.manage; the student param is resolved
     * through the tenant + branch scoped Student model (cross-tenant /
     * cross-branch → 404).
     */
    public function withdraw(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->academicExit->withdraw($student, $data['reason'] ?? null);
        $this->admissionWorkflow->markWithdrawn($student, $student->institute_id, (int) $request->user()->id);

        return redirect()
            ->route('students.show', $student)
            ->with('status', "{$student->full_name} marked as withdrawn from the academic program.");
    }

    /**
     * Officially mark the student as transferred out of their current active
     * academic placement. An optional in-institute target branch follows the
     * transfer; a cross-institute branch is rejected by validation. The new
     * placement (if the student continues) is created through the existing
     * placement / promotion flows — never duplicated here.
     */
    public function transfer(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'branch_id' => [
                'nullable', 'integer',
                Rule::exists('branches', 'id')->where('institute_id', $student->institute_id),
            ],
        ]);

        $targetBranch = null;
        if (filled($data['branch_id'] ?? null)) {
            $targetBranch = Branch::query()->find((int) $data['branch_id']);
        }

        $this->academicExit->transfer($student, $data['reason'] ?? null, $targetBranch);
        $this->admissionWorkflow->markWithdrawn($student, $student->institute_id, (int) $request->user()->id);

        return redirect()
            ->route('students.show', $student)
            ->with('status', "{$student->full_name} marked as transferred.");
    }

    public function enroll(Request $request, Student $student): RedirectResponse|JsonResponse
    {
        $instituteId = $request->user()->institute_id;

        $current = $student->currentAcademicPlacement();

        if ($current !== null && in_array($current->status, StudentAcademicPlacement::EXITED_STATUSES, true)) {
            $message = "{$student->full_name} is marked as {$current->status} and cannot be enrolled in a new batch.";

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }

            return back()
                ->withInput()
                ->withErrors(['batch_id' => $message]);
        }

        // An application that has not been approved cannot be enrolled.
        if (in_array($student->admission_status, [
            Student::ADMISSION_STATUS_DRAFT,
            Student::ADMISSION_STATUS_SUBMITTED,
            Student::ADMISSION_STATUS_UNDER_REVIEW,
            Student::ADMISSION_STATUS_REJECTED,
            Student::ADMISSION_STATUS_CANCELLED,
        ], true)) {
            $message = "{$student->full_name} is not admitted yet ({$student->admission_status}) and cannot be enrolled.";

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }

            return back()
                ->withInput()
                ->withErrors(['batch_id' => $message]);
        }

        $data = $request->validate([
            'batch_id' => [
                'required',
                'integer',
                Rule::exists('batches', 'id')->where('institute_id', $instituteId),
            ],
            'roll_number' => ['nullable', 'string', 'max:20'],
            'enrollment_date' => ['required', 'date'],
            'fee_payable' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $batch = Batch::findOrFail($data['batch_id']);

        // STEP 41: a batch that has reached its seat capacity cannot accept
        // another active enrollment.
        try {
            $this->batches->assertCanEnroll($batch);
        } catch (ValidationException $e) {
            $message = $e->errors()['batch_id'][0] ?? 'This batch has reached its seat capacity.';

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }

            return back()
                ->withInput()
                ->withErrors(['batch_id' => $message]);
        }

        try {
            DB::transaction(function () use ($student, $batch, $data, $instituteId, $request) {
                $this->batches->enrollStudent($student, $batch, $data, $instituteId, (int) $request->user()->id);
            });
        } catch (QueryException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not assign: '.$e->getMessage(),
                ], 422);
            }

            return back()
                ->withInput()
                ->withErrors(['batch_id' => 'Could not assign: '.($e->getMessage())]);
        }

        $message = "Student {$student->full_name} assigned to {$batch->name}.";

        // Step 36: a successful enrollment flips an approved admission to
        // 'enrolled'.
        $this->admissionWorkflow->markEnrolled($student, $instituteId, (int) $request->user()->id);

        // Step 34: convert the admission lead into a CRM contact once the
        // student enrolls. Never breaks enrollment when CRM is unavailable or
        // the actor lacks the crm.update permission.
        try {
            if ($request->user()->hasPermission('crm.update')) {
                $this->crmIntegration->convertAdmissionLead($student, $instituteId, (int) $request->user()->id);
            }
        } catch (\Throwable $e) {
            Log::warning('Education→CRM enrollment conversion skipped.', ['student_id' => $student->id, 'error' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => ['batch_id' => $batch->id],
            ]);
        }

        return back()->with('status', $message);
    }

    public function edit(Request $request, Student $student): View
    {
        return view('students.form', [
            'student' => $student,
            'branches' => $this->branches($request->user()->institute_id),
            'nextNumber' => null,
            'geo' => $this->geo($request->user()->institute_id),
            'country' => $this->instituteCountry($request->user()->institute_id),
            'countries' => config('countries'),
            'defaultCountryId' => $this->instituteCountryId($request->user()->institute_id),
            'presentAddress' => $this->addressData($student, 'present_', $this->instituteCountryId($request->user()->institute_id)),
            'permanentAddress' => $this->addressData($student, 'permanent_', $this->instituteCountryId($request->user()->institute_id)),
        ]);
    }

    public function update(UpdateStudentRequest $request, Student $student): RedirectResponse|JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('photo')) {
            $data['photo'] = $this->profileImage->processAndStore($request->file('photo'));
            if ($student->photo) {
                Storage::disk('public')->delete($student->photo);
            }
        }

        if ($request->hasFile('document')) {
            if ($student->document) {
                Storage::disk('public')->delete($student->document);
            }
            $data['document'] = $this->storeStudentDocument($student->student_id_number, $request->file('document'));
        }

        $student->update($data);

        if ($request->has('roll_number')) {
            $enrollment = $student->enrollments()->latest('id')->first();
            if ($enrollment) {
                $enrollment->update(['roll_no' => $request->input('roll_number')]);
            }
        }

        $status = "Student {$student->full_name} updated.";

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $status,
                'data' => ['id' => $student->id],
            ]);
        }

        if ($request->boolean('return_to_list')) {
            return redirect()->route('students.index')->with('status', $status);
        }

        return redirect()
            ->route('students.show', $student)
            ->with('status', $status);
    }

    public function uploadPhoto(Request $request, Student $student): RedirectResponse|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'photo' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:100'],
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first('photo'),
                    'errors' => $validator->errors()->toArray(),
                ], 422);
            }

            return back()
                ->withInput()
                ->withErrors($validator)
                ->with('photo_upload_error', true);
        }

        try {
            $path = $this->profileImage->processAndStore($request->file('photo'));
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => ['photo' => [$e->getMessage()]],
                ], 422);
            }

            return back()
                ->withInput()
                ->withErrors(['photo' => $e->getMessage()])
                ->with('photo_upload_error', true);
        }

        if ($student->photo) {
            Storage::disk('public')->delete($student->photo);
        }

        $student->update(['photo' => $path]);

        $message = "Profile photo for {$student->full_name} updated.";

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => ['photo' => Storage::url($student->photo)],
            ]);
        }

        return redirect()
            ->route('students.show', $student)
            ->with('status', $message);
    }

    public function destroy(Request $request, Student $student): RedirectResponse|JsonResponse
    {
        $student->delete();

        $message = "Student {$student->full_name} removed.";

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => ['id' => $student->id],
            ]);
        }

        return redirect()
            ->route('students.index')
            ->with('status', $message);
    }

    private function branches(int $instituteId): Collection
    {
        return Branch::where('institute_id', $instituteId)->orderBy('name')->get();
    }

    private function geo(int $instituteId): array
    {
        return [
            'lang' => mawa_current_lang(),
            'divisions' => BdGeo::divisions(),
            'districts' => BdGeo::districts(),
            'upazilas' => BdGeo::upazilas(),
        ];
    }

    /**
     * Data for the country-neutral <x-address> component: the dynamic level
     * labels (from the per-country config) plus the location options for the
     * student's selected country (administrative_units; BD is already seeded).
     */
    private function addressData(Student $student, string $prefix, ?int $fallbackCountryId = null): array
    {
        $countryId = (int) ($student->getAttribute($prefix.'country_id') ?? $fallbackCountryId) ?: 0;
        $country = $countryId ? Country::find($countryId) : null;

        $levelOptions = [1 => [], 2 => [], 3 => []];

        if ($country) {
            $levels = $country->selectableLevels()->orderBy('level_number')->get();
            foreach ($levels as $level) {
                $query = AdministrativeUnit::query()
                    ->where('country_id', $country->id)
                    ->where('administrative_level_id', $level->id)
                    ->where('status', true);

                if ($level->level_number > 1) {
                    $parentAttr = $prefix.'admin_'.($level->level_number - 1).'_id';
                    $query->where('parent_id', (int) ($student->getAttribute($parentAttr) ?? 0));
                } else {
                    $query->whereNull('parent_id');
                }

                $levelOptions[$level->level_number] = $query
                    ->orderBy('name')
                    ->get()
                    ->pluck('name', 'id')
                    ->all();
            }
        }

        return [
            'country' => $country,
            'level_labels' => $country ? GeoHierarchy::levelLabels($country) : [],
            'level_options' => $levelOptions,
        ];
    }

    private function instituteCountry(int $instituteId): ?string
    {
        return Institute::query()
            ->where('id', $instituteId)
            ->value('country');
    }

    /**
     * Default a brand-new student's address selection to the institute's
     * country so the global address cascades render immediately instead of
     * showing empty selects until a country is picked.
     */
    private function defaultAddressCountry(Student $student, int $instituteId): void
    {
        if ($student->present_country_id || $student->permanent_country_id || $student->exists) {
            return;
        }

        $countryId = $this->instituteCountryId($instituteId);
        $country = $countryId !== null
            ? Country::find($countryId)
            : Country::query()->where('name', $this->instituteCountry($instituteId))->first();

        if (! $country) {
            return;
        }

        $student->present_country_id = $country->id;
        $student->permanent_country_id = $country->id;
    }

    private function instituteCountryId(int $instituteId): ?int
    {
        return Institute::query()
            ->where('id', $instituteId)
            ->value('country_id');
    }

    /**
     * Store a student document (pdf / csv / svg) named after the student's ID.
     */
    private function storeStudentDocument(string $studentIdNumber, $file): string
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($ext, ['pdf', 'csv', 'svg'], true)) {
            $ext = 'pdf';
        }

        return $file->storeAs('students/documents', $studentIdNumber.'.'.$ext, 'public');
    }

    /**
     * Editable fields used by the quick-edit modal on the students list page.
     */
    private function studentEditData(Student $student): array
    {
        return [
            'id' => $student->id,
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'roll_number' => $student->roll_number,
            'reg_no' => $student->reg_no,
            'gender' => $student->gender,
            'dob' => $student->dob?->format('Y-m-d'),
            'admission_date' => $student->admission_date?->format('Y-m-d'),
            'phone' => $student->phone,
            'email' => $student->email,
            'religion' => $student->religion,
            'status' => $student->status,
            'father_name' => $student->father_name,
            'mother_name' => $student->mother_name,
            'guardian_phone' => $student->guardian_phone,
            'nationality' => $student->nationality,
            'nid_number' => $student->nid_number,
            'birth_cert_number' => $student->birth_cert_number,
            'passport_number' => $student->passport_number,
            'blood_group' => $student->blood_group,
            'present_division_id' => $student->present_division_id,
            'present_district_id' => $student->present_district_id,
            'present_upazila_id' => $student->present_upazila_id,
            'present_country_id' => $student->present_country_id,
            'present_admin_1_id' => $student->present_admin_1_id,
            'present_admin_2_id' => $student->present_admin_2_id,
            'present_admin_3_id' => $student->present_admin_3_id,
            'present_post_office' => $student->present_post_office,
            'present_zip_code' => $student->present_zip_code,
            'present_address' => $student->present_address,
            'permanent_division_id' => $student->permanent_division_id,
            'permanent_district_id' => $student->permanent_district_id,
            'permanent_upazila_id' => $student->permanent_upazila_id,
            'permanent_country_id' => $student->permanent_country_id,
            'permanent_admin_1_id' => $student->permanent_admin_1_id,
            'permanent_admin_2_id' => $student->permanent_admin_2_id,
            'permanent_admin_3_id' => $student->permanent_admin_3_id,
            'permanent_post_office' => $student->permanent_post_office,
            'permanent_zip_code' => $student->permanent_zip_code,
            'permanent_address' => $student->permanent_address,
            'emergency_contact_name' => $student->emergency_contact_name,
            'emergency_contact_phone' => $student->emergency_contact_phone,
        ];
    }
}
