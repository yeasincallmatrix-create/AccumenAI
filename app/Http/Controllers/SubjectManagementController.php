<?php

namespace App\Http\Controllers;

use App\Models\CourseCategory;
use App\Models\Institute;
use App\Models\InstituteSubject;
use App\Models\Subject;
use App\Services\SubjectDeletionService;
use App\Support\InstituteDomain;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SubjectManagementController extends Controller
{
    public const SUBJECTS_COLUMNS = ['serial', 'name', 'code', 'type', 'category', 'status', 'usage', 'created_at'];

    public function __construct(
        private readonly SubjectDeletionService $deletionService,
    ) {}

    public function index(Request $request): View
    {
        $instituteId = $request->user()->institute_id;
        $institute = Institute::find($instituteId);
        $derivedType = InstituteDomain::subjectTypeFor($institute);

        $q = trim((string) $request->query('q'));
        $categoryId = $request->query('category_id');
        $rawSubjectType = $request->query('subject_type');
        // Server derives domain; do not trust browser subject_type. Clamp to derived.
        $subjectType = null;
        if (is_string($rawSubjectType) && $rawSubjectType !== '' && in_array($rawSubjectType, ['academic','professional'], true)) {
            $subjectType = $rawSubjectType === $derivedType ? $rawSubjectType : null;
            // If browser tries to request opposite domain type, force empty result set by ignoring filter and clamping counts
            if ($rawSubjectType !== $derivedType && $request->query('subject_type') !== null) {
                // keep subjectType null but we will enforce derived filter below
            }
        }
        $status = $request->query('status');
        $trashed = $request->boolean('trashed', false);

        $query = $this->subjectQuery($instituteId, $derivedType)
            ->with(['category:id,name,subject_type'])
            ->when($trashed, fn ($q) => $q->onlyTrashed())
            ->when(! $trashed, fn ($q) => $q->whereNull('deleted_at'))
            ->when($q !== '', function ($query) use ($q) {
                return $query->where(function (Builder $w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                        ->orWhere('subject_code', 'like', "%{$q}%")
                        ->orWhere('short_name', 'like', "%{$q}%");
                });
            })
            ->when($categoryId, function ($query) use ($categoryId) {
                return $query->where('category_id', $categoryId);
            })
            ->when($subjectType, function ($query) use ($subjectType) {
                return $query->where('subject_type', $subjectType);
            })
            ->when($status && ! $trashed, function ($query) use ($status) {
                return $query->where('status', $status);
            })
            ->orderBy('subject_type')
            ->orderBy('name');

        $perPage = (int) $request->query('per_page', 20);
        $perPage = in_array($perPage, [15, 25, 50, 75, 100, 200], true) ? $perPage : 20;

        $subjects = (clone $query)->paginate($perPage)->withQueryString();

        $visibleColumns = $request->user()->preference('columns_subject_management', self::SUBJECTS_COLUMNS);
        $visibleColumns = array_values(array_intersect(self::SUBJECTS_COLUMNS, (array) $visibleColumns));

        $filterCategories = $this->filterCategories($instituteId);
        // Only expose derived type in UI filter; never show opposite domain as selectable
        $allSubjectTypes = [$derivedType];

        $stats = [
            'total' => (clone $query)->whereNull('deleted_at')->count(),
            'academic' => $derivedType === 'academic' ? (clone $query)->whereNull('deleted_at')->where('subject_type', 'academic')->count() : 0,
            'professional' => $derivedType === 'professional' ? (clone $query)->whereNull('deleted_at')->where('subject_type', 'professional')->count() : 0,
            'trashed' => Subject::query()->where('institute_id', $instituteId)->onlyTrashed()->count(),
        ];

        return view('institute.course-master.subjects', [
            'subjects' => $subjects,
            'q' => $q,
            'categoryId' => $categoryId,
            'subjectType' => $subjectType,
            'status' => $status,
            'trashed' => $trashed,
            'filterCategories' => $filterCategories,
            'allSubjectTypes' => $allSubjectTypes,
            'visibleColumns' => $visibleColumns,
            'stats' => $stats,
        ]);
    }

    public function create(): View
    {
        $instituteId = request()->user()->institute_id;
        $institute = Institute::find($instituteId);
        $derived = InstituteDomain::subjectTypeFor($institute);
        $domain = InstituteDomain::fromInstitute($institute);

        return view('institute.course-master.subject-form', [
            'subject' => null,
            'categories' => $this->categories($instituteId, $derived),
            'subjectTypes' => ['academic' => 'Academic', 'professional' => 'Professional'],
            'derivedSubjectType' => $derived,
            'domain' => $domain,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $instituteId = $user->institute_id;
        $institute = Institute::find($instituteId);
        $derivedType = InstituteDomain::subjectTypeFor($institute);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:100'],
            'subject_code' => ['nullable', 'string', 'max:50', Rule::unique('subjects', 'subject_code')->whereNull('deleted_at')->where('institute_id', $instituteId)],
            // subject_type is NOT trusted — server derives
            'category_id' => ['required', 'integer', Rule::exists('course_categories', 'id')->where('institute_id', $instituteId)->where('subject_type', $derivedType)],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $slug = $this->uniqueSlug($data['name'], $instituteId);

        $subject = Subject::create([
            'institute_id' => $instituteId,
            'category_id' => $data['category_id'],
            'subject_type' => $derivedType,
            'name' => $data['name'],
            'slug' => $slug,
            'short_name' => $data['short_name'],
            'subject_code' => $data['subject_code'],
            'description' => $data['description'],
            'status' => $data['status'],
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => mawa_lang('subjects.created'),
                'data' => ['id' => $subject->id],
            ]);
        }

        return redirect()->route('courses.manage.subjects.edit', $subject)->with('status', mawa_lang('subjects.created'));
    }

    public function edit(Request $request, Subject $subject): View
    {
        $this->assertAccessible($request, $subject);
        $instituteId = $request->user()->institute_id;
        $institute = Institute::find($instituteId);
        $derived = InstituteDomain::subjectTypeFor($institute);

        return view('institute.course-master.subject-form', [
            'subject' => $subject,
            'categories' => $this->categories($instituteId, $derived),
            'subjectTypes' => ['academic' => 'Academic', 'professional' => 'Professional'],
            'derivedSubjectType' => $derived,
            'domain' => InstituteDomain::fromInstitute($institute),
        ]);
    }

    public function update(Request $request, Subject $subject): RedirectResponse|JsonResponse
    {
        $this->assertAccessible($request, $subject);

        $instituteId = $request->user()->institute_id;
        $institute = Institute::find($instituteId);
        $derivedType = InstituteDomain::subjectTypeFor($institute);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:100'],
            'subject_code' => ['nullable', 'string', 'max:50', Rule::unique('subjects', 'subject_code')->whereNull('deleted_at')->where('institute_id', $instituteId)->ignore($subject->id)],
            'category_id' => ['required', 'integer', Rule::exists('course_categories', 'id')->where('institute_id', $instituteId)->where('subject_type', $derivedType)],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
        $data['subject_type'] = $derivedType;

        // Regenerate slug when name changes, keep unique per institute (including soft-deleted)
        $slug = $subject->slug;
        if (isset($data['name']) && $data['name'] !== $subject->name) {
            $candidate = Str::slug($data['name']);
            if ($candidate === '') {
                $candidate = $subject->slug;
            }
            if ($candidate !== $subject->slug) {
                $slug = $this->uniqueSlug($data['name'], $instituteId, $subject->id);
            }
        }
        $data['slug'] = $slug;

        $subject->update($data);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => mawa_lang('subjects.updated'),
                'data' => ['id' => $subject->id],
            ]);
        }

        return redirect()->route('courses.manage.subjects.edit', $subject)->with('status', mawa_lang('subjects.updated'));
    }

    public function destroy(Request $request, Subject $subject): RedirectResponse|JsonResponse
    {
        $this->assertAccessible($request, $subject);

        $user = $request->user();

        try {
            $this->deletionService->softDelete($subject, $user->id);
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->errors()['subject'][0] ?? 'Deletion blocked.'], 422);
            }
            return back()->withErrors($e->errors());
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => mawa_lang('subjects.deleted'), 'data' => ['id' => $subject->id]]);
        }

        return redirect()->route('courses.manage.subjects.index')->with('status', mawa_lang('subjects.deleted'));
    }

    public function restore(Request $request, Subject $subject): RedirectResponse|JsonResponse
    {
        $this->assertAccessible($request, $subject);

        $user = $request->user();

        try {
            $this->deletionService->restore($subject, $user->id);
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->errors()['subject'][0] ?? 'Restore blocked.'], 422);
            }
            return back()->withErrors($e->errors());
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => mawa_lang('subjects.restored'), 'data' => ['id' => $subject->id]]);
        }

        return redirect()->route('courses.manage.subjects.index')->with('status', mawa_lang('subjects.restored'));
    }

    public function dependencies(Request $request, Subject $subject): View|JsonResponse
    {
        $this->assertAccessible($request, $subject);

        $classification = $this->deletionService->classify($subject);

        $details = $this->getDependencyDetails($subject);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'classification' => $classification,
                    'details' => $details,
                ],
            ]);
        }

        return view('institute.course-master.subject-dependencies', [
            'subject' => $subject,
            'classification' => $classification,
            'details' => $details,
        ]);
    }

    private function subjectQuery(int $instituteId, ?string $domainFilter = null): EloquentBuilder
    {
        // Canonical listing: institute-owned only, domain-filtered. Globals (null institute_id) are NOT listed here to guarantee tenant isolation;
        // shared catalog subjects are handled via explicit InstituteSubject assignment, not implicit global visibility.
        $q = Subject::query()
            ->where('institute_id', $instituteId);
        if ($domainFilter !== null) {
            $q->where('subject_type', $domainFilter);
        }
        return $q;
    }

    private function filterCategories(int $instituteId)
    {
        $institute = Institute::find($instituteId);
        $derived = InstituteDomain::subjectTypeFor($institute);
        return CourseCategory::query()
            ->where('institute_id', $instituteId)
            ->where('subject_type', $derived)
            ->orderBy('name')
            ->get(['id', 'name', 'subject_type']);
    }

    private function categories(?int $instituteId = null, ?string $derived = null)
    {
        if ($instituteId === null) $instituteId = (int) request()->user()->institute_id;
        if ($derived === null) {
            $institute = Institute::find($instituteId);
            $derived = InstituteDomain::subjectTypeFor($institute);
        }
        return CourseCategory::query()
            ->where('institute_id', $instituteId)
            ->where('subject_type', $derived)
            ->orderBy('name')
            ->get();
    }

    private function assertAccessible(Request $request, Subject $subject): void
    {
        $instituteId = $request->user()->institute_id;
        $isOwned = (int) $subject->institute_id === (int) $instituteId;

        if (! $isOwned) {
            abort(403, mawa_lang('subjects.not_accessible'));
        }
        // Domain check: subject_type must match institute domain
        $institute = Institute::find($instituteId);
        $derived = InstituteDomain::subjectTypeFor($institute);
        if ($subject->subject_type !== $derived) {
            abort(403, mawa_lang('subjects.not_accessible'));
        }
    }

    private function uniqueSlug(string $name, int $instituteId, ?int $ignoreId = null): string
    {
        $slug = Str::slug($name);
        if ($slug === '') {
            $slug = 'subject-'.Str::random(6);
        }
        // varchar(180) limit
        $slug = Str::limit($slug, 170, '');
        $base = $slug;
        $suffix = 1;
        while (Subject::withTrashed()->where('institute_id', $instituteId)->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $suffix++;
            $slug = Str::limit($base, 170 - strlen((string) $suffix) - 1, '').'-'.$suffix;
        }

        return $slug;
    }

    private function getDependencyDetails(Subject $subject): array
    {
        $id = (int) $subject->id;

        return [
            'course_subjects' => DB::table('course_subjects')->where('subject_id', $id)->count(),
            'institute_subjects' => DB::table('institute_subjects')->where('subject_id', $id)->count(),
            'subject_academic_assignments' => DB::table('subject_academic_assignments')->where('subject_id', $id)->count(),
            'student_subject_selections' => DB::table('student_subject_selections')->where('subject_id', $id)->count(),
            'assessment_subjects' => DB::table('assessment_subjects')->where('subject_id', $id)->count(),
            'exam_subjects' => DB::table('exam_subjects')->where('subject_id', $id)->count(),
            'exam_results' => DB::table('exam_results')->where('subject_id', $id)->count(),
            'academic_final_result_rows' => DB::table('academic_final_result_rows')->where('subject_id', $id)->count(),
            'teacher_academic_assignments' => DB::table('teacher_academic_assignments')->where('subject_id', $id)->count(),
            'calendar_events' => DB::table('calendar_events')->where('subject_id', $id)->count(),
        ];
    }
}