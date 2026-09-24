<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Training\TrainingCourseCategory;
use App\Models\Training\TrainingSubject;
use App\Services\Training\TrainingSubjectDeletionService;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TrainingSubjectController extends Controller
{
    public const SUBJECTS_COLUMNS = ['serial', 'name', 'code', 'type', 'category', 'status', 'usage', 'created_at'];

    public function __construct(
        private readonly TrainingSubjectDeletionService $deletionService,
    ) {}

    public function index(Request $request): View
    {
        $instituteId = (int) $request->user()->institute_id;

        $q = trim((string) $request->query('q'));
        $categoryId = $request->query('category_id');
        $status = $request->query('status');
        $trashed = $request->boolean('trashed', false);

        $query = $this->subjectQuery($instituteId)
            ->with(['category:id,name,subject_type'])
            ->when($trashed, fn ($qq) => $qq->onlyTrashed())
            ->when(! $trashed, fn ($qq) => $qq->whereNull('deleted_at'))
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
            ->when($status && ! $trashed, function ($query) use ($status) {
                return $query->where('status', $status);
            })
            ->orderBy('name');

        $perPage = (int) $request->query('per_page', 20);
        $perPage = in_array($perPage, [15, 25, 50, 75, 100, 200], true) ? $perPage : 20;

        $subjects = (clone $query)->paginate($perPage)->withQueryString();

        $visibleColumns = $request->user()->preference('columns_training_subject_management', self::SUBJECTS_COLUMNS);
        $visibleColumns = array_values(array_intersect(self::SUBJECTS_COLUMNS, (array) $visibleColumns));

        $filterCategories = $this->filterCategories($instituteId);

        $stats = [
            'total' => (clone $query)->whereNull('deleted_at')->count(),
            'academic' => 0,
            'professional' => (clone $query)->whereNull('deleted_at')->count(),
            'trashed' => TrainingSubject::query()->where('institute_id', $instituteId)->onlyTrashed()->count(),
        ];

        return view('training.courses.subjects', [
            'subjects' => $subjects,
            'q' => $q,
            'categoryId' => $categoryId,
            'subjectType' => null,
            'status' => $status,
            'trashed' => $trashed,
            'filterCategories' => $filterCategories,
            'allSubjectTypes' => ['professional'],
            'visibleColumns' => $visibleColumns,
            'stats' => $stats,
            'institute' => \App\Models\Institute::find($instituteId),
        ]);
    }

    public function create(): View
    {
        $instituteId = (int) request()->user()->institute_id;

        return view('training.courses.subject-form', [
            'subject' => null,
            'categories' => $this->categories($instituteId),
            'subjectTypes' => ['professional' => 'Professional'],
            'derivedSubjectType' => 'professional',
            'domain' => 'professional',
            'institute' => \App\Models\Institute::find($instituteId),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $instituteId = (int) $user->institute_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:100'],
            'subject_code' => ['nullable', 'string', 'max:50', Rule::unique('training_subjects', 'subject_code')->whereNull('deleted_at')->where('institute_id', $instituteId)],
            'category_id' => ['required', 'integer', Rule::exists('training_course_categories', 'id')->where('institute_id', $instituteId)->where('subject_type', 'professional')],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $slug = $this->uniqueSlug($data['name'], $instituteId);

        $subject = TrainingSubject::create([
            'institute_id' => $instituteId,
            'category_id' => $data['category_id'],
            'subject_type' => 'professional',
            'name' => $data['name'],
            'slug' => $slug,
            'short_name' => $data['short_name'] ?? null,
            'subject_code' => $data['subject_code'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Subject created.',
                'data' => ['id' => $subject->id],
            ]);
        }

        return redirect()->route('training.courses.subjects.edit', $subject)->with('status', 'Subject created.');
    }

    public function edit(Request $request, TrainingSubject $subject): View
    {
        $this->assertAccessible($request, $subject);
        $instituteId = (int) $request->user()->institute_id;

        return view('training.courses.subject-form', [
            'subject' => $subject,
            'categories' => $this->categories($instituteId),
            'subjectTypes' => ['professional' => 'Professional'],
            'derivedSubjectType' => 'professional',
            'domain' => 'professional',
            'institute' => \App\Models\Institute::find($instituteId),
        ]);
    }

    public function update(Request $request, TrainingSubject $subject): RedirectResponse|JsonResponse
    {
        $this->assertAccessible($request, $subject);

        $instituteId = (int) $request->user()->institute_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:100'],
            'subject_code' => ['nullable', 'string', 'max:50', Rule::unique('training_subjects', 'subject_code')->whereNull('deleted_at')->where('institute_id', $instituteId)->ignore($subject->id)],
            'category_id' => ['required', 'integer', Rule::exists('training_course_categories', 'id')->where('institute_id', $instituteId)->where('subject_type', 'professional')],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
        $data['subject_type'] = 'professional';

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
                'message' => 'Subject updated.',
                'data' => ['id' => $subject->id],
            ]);
        }

        return redirect()->route('training.courses.subjects.edit', $subject)->with('status', 'Subject updated.');
    }

    public function destroy(Request $request, TrainingSubject $subject): RedirectResponse|JsonResponse
    {
        $this->assertAccessible($request, $subject);

        try {
            $this->deletionService->softDelete($subject);
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->errors()['subject'][0] ?? 'Deletion blocked.'], 422);
            }

            return back()->withErrors($e->errors());
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Subject deleted.', 'data' => ['id' => $subject->id]]);
        }

        return redirect()->route('training.courses.subjects.index')->with('status', 'Subject deleted.');
    }

    public function restore(Request $request, TrainingSubject $subject): RedirectResponse|JsonResponse
    {
        $this->assertAccessible($request, $subject);

        try {
            $this->deletionService->restore($subject);
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->errors()['subject'][0] ?? 'Restore blocked.'], 422);
            }

            return back()->withErrors($e->errors());
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Subject restored.', 'data' => ['id' => $subject->id]]);
        }

        return redirect()->route('training.courses.subjects.index')->with('status', 'Subject restored.');
    }

    public function dependencies(Request $request, TrainingSubject $subject): View|JsonResponse
    {
        $this->assertAccessible($request, $subject);

        $classification = $this->deletionService->classify($subject);
        $details = [
            'training_course_subjects' => $classification['counts']['training_course_subjects'] ?? 0,
            'training_exam_results' => $classification['counts']['training_exam_results'] ?? 0,
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'classification' => $classification,
                    'details' => $details,
                ],
            ]);
        }

        return view('training.courses.subject-dependencies', [
            'subject' => $subject,
            'classification' => $classification,
            'details' => $details,
        ]);
    }

    private function subjectQuery(int $instituteId): Builder
    {
        return TrainingSubject::query()->where('institute_id', $instituteId);
    }

    private function filterCategories(int $instituteId)
    {
        return TrainingCourseCategory::query()
            ->where('institute_id', $instituteId)
            ->where('subject_type', 'professional')
            ->orderBy('name')
            ->get(['id', 'name', 'subject_type']);
    }

    private function categories(int $instituteId)
    {
        return TrainingCourseCategory::query()
            ->where('institute_id', $instituteId)
            ->where('subject_type', 'professional')
            ->orderBy('name')
            ->get();
    }

    private function assertAccessible(Request $request, TrainingSubject $subject): void
    {
        $instituteId = (int) $request->user()->institute_id;

        if ((int) $subject->institute_id !== $instituteId) {
            abort(403, 'Subject not accessible.');
        }
        if ($subject->subject_type !== null && $subject->subject_type !== 'professional') {
            abort(403, 'Subject not accessible.');
        }
    }

    private function uniqueSlug(string $name, int $instituteId, ?int $ignoreId = null): string
    {
        $slug = Str::slug($name);
        if ($slug === '') {
            $slug = 'subject-'.Str::random(6);
        }
        $slug = Str::limit($slug, 170, '');
        $base = $slug;
        $suffix = 1;
        while (TrainingSubject::withTrashed()->where('institute_id', $instituteId)->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $suffix++;
            $slug = Str::limit($base, 170 - strlen((string) $suffix) - 1, '').'-'.$suffix;
        }

        return $slug;
    }
}
