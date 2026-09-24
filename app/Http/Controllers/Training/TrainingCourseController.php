<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Institute;
use App\Models\Training\TrainingCourse;
use App\Models\Training\TrainingCourseCategory;
use App\Models\Training\TrainingSubject;
use App\Services\BannerImageService;
use App\Services\CourseAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Training Center course master — full table-level separation (Option B).
 * Operates only on training_courses / training_course_* tables.
 */
class TrainingCourseController extends Controller
{
    public function __construct(
        private readonly CourseAuditService $audit,
        private readonly BannerImageService $bannerImage,
    ) {}

    public function index(Request $request): View
    {
        $instituteId = (int) $request->user()->institute_id;

        $perPage = (int) $request->query('per_page', 15);
        $perPage = in_array($perPage, [15, 25, 50, 75, 100, 200], true) ? $perPage : 15;

        $courses = TrainingCourse::query()
            ->where('institute_id', $instituteId)
            ->withCount(['materials', 'subjects', 'batches'])
            ->with('category:id,name,subject_type')
            ->whereHas('category', fn ($q) => $q->where('subject_type', 'professional'))
            ->when($request->query('q'), fn ($query, $search) => $query
                ->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('course_code', 'like', "%{$search}%");
                }))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('category_id'), fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->orderBy('display_order')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        $subjectsCount = TrainingSubject::query()
            ->where('institute_id', $instituteId)
            ->whereNull('deleted_at')
            ->count();

        return view('training.courses.index', [
            'courses' => $courses,
            'q' => $request->query('q'),
            'status' => $request->query('status'),
            'categoryId' => $request->query('category_id'),
            'categories' => $this->categories(),
            'subjectsCount' => $subjectsCount,
        ]);
    }

    public function create(): View
    {
        return view('training.courses.form', [
            'course' => null,
            'categories' => $this->categories(),
            'subCategories' => collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $data = $this->validated($request);

        $course = $this->createCourse((int) $user->institute_id, $data);

        if ($request->hasFile('banner')) {
            try {
                $path = $this->bannerImage->processAndStore($request->file('banner'), (int) $user->institute_id);
                $course->update(['banner' => $path]);
            } catch (\InvalidArgumentException $e) {
                if ($request->expectsJson()) {
                    return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => ['banner' => [$e->getMessage()]]], 422);
                }

                return redirect()->route('training.courses.edit', $course)->withErrors(['banner' => $e->getMessage()])->with('status', 'Course created, but banner: '.$e->getMessage());
            }
        }

        $this->audit->record((int) $user->institute_id, (int) $user->id, 'training_course_created', $course->id, null, $this->snapshot($course), 'training_courses');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Course created successfully.',
                'data' => ['id' => $course->id],
            ]);
        }

        return redirect()->route('training.courses.edit', $course)->with('status', 'Course created successfully.');
    }

    public function edit(Request $request, TrainingCourse $course): View
    {
        $this->assertOwned($request, $course);

        return view('training.courses.form', [
            'course' => $course,
            'categories' => $this->categories(),
            'subCategories' => $course->category?->subCategories ?? collect(),
        ]);
    }

    public function show(Request $request, TrainingCourse $course): View
    {
        $this->assertOwned($request, $course);
        $instituteId = (int) $request->user()->institute_id;

        $course->load([
            'category:id,name,subject_type,slug',
            'subCategory:id,name,category_id',
            'subjects:id,name,short_name,subject_code,status',
            'materials' => fn ($q) => $q->orderBy('display_order')->orderBy('id'),
        ])->loadCount(['materials', 'subjects', 'batches']);

        $availableSubjects = TrainingSubject::query()
            ->where('institute_id', $instituteId)
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->with('category:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'subject_code', 'short_name', 'category_id']);

        $attachedIds = $course->subjects->pluck('id')->map(fn ($id) => (int) $id)->all();

        return view('training.courses.show', [
            'course' => $course,
            'availableSubjects' => $availableSubjects,
            'attachedIds' => $attachedIds,
            'subjectCategories' => $this->categories(),
        ]);
    }

    public function addSubjects(Request $request, TrainingCourse $course): View
    {
        $this->assertOwned($request, $course);
        $instituteId = (int) $request->user()->institute_id;

        $availableSubjects = TrainingSubject::query()
            ->where('institute_id', $instituteId)
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->with('category:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'subject_code', 'short_name', 'category_id']);

        $attachedIds = $course->subjects()->pluck('training_subjects.id')->map(fn ($id) => (int) $id)->all();
        $categories = $this->categories();

        return view('training.courses.subjects-add', [
            'course' => $course,
            'availableSubjects' => $availableSubjects,
            'attachedIds' => $attachedIds,
            'categories' => $categories,
        ]);
    }

    public function attachSubjects(Request $request, TrainingCourse $course): RedirectResponse|JsonResponse
    {
        $this->assertOwned($request, $course);
        $instituteId = (int) $request->user()->institute_id;

        $data = $request->validate([
            'subjects' => ['nullable', 'array'],
            'subjects.*' => ['integer'],
        ]);

        $requested = collect($data['subjects'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        $available = TrainingSubject::query()
            ->where('institute_id', $instituteId)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $allowed = array_values(array_intersect($requested, $available));
        $course->subjects()->sync($allowed);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Course subjects updated.',
                'data' => ['course_id' => $course->id, 'subject_count' => count($allowed)],
            ]);
        }

        return redirect()->route('training.courses.show', $course)
            ->with('status', 'Course subjects updated.');
    }

    public function createAndAttachSubject(Request $request, TrainingCourse $course): RedirectResponse|JsonResponse
    {
        $this->assertOwned($request, $course);
        $instituteId = (int) $request->user()->institute_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:100'],
            'subject_code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('training_subjects', 'subject_code')->whereNull('deleted_at')->where('institute_id', $instituteId),
            ],
            'category_id' => [
                'required', 'integer',
                Rule::exists('training_course_categories', 'id')
                    ->where('institute_id', $instituteId)
                    ->where('subject_type', 'professional'),
            ],
            'description' => ['nullable', 'string'],
        ]);

        $slug = $this->uniqueSubjectSlug($data['name'], $instituteId);

        $subject = TrainingSubject::create([
            'institute_id' => $instituteId,
            'category_id' => $data['category_id'],
            'subject_type' => 'professional',
            'name' => $data['name'],
            'slug' => $slug,
            'short_name' => $data['short_name'] ?? null,
            'subject_code' => $data['subject_code'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => 'active',
        ]);

        $course->subjects()->syncWithoutDetaching([$subject->id]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Subject created and attached to course.',
                'data' => ['id' => $subject->id, 'course_id' => $course->id],
            ]);
        }

        return redirect()->route('training.courses.show', $course)
            ->with('status', 'Subject created and attached to course.');
    }

    public function detachSubject(Request $request, TrainingCourse $course, TrainingSubject $subject): RedirectResponse|JsonResponse
    {
        $this->assertOwned($request, $course);

        if ((int) $subject->institute_id !== (int) $request->user()->institute_id) {
            abort(403, 'Subject not accessible.');
        }

        $course->subjects()->detach($subject->id);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Subject removed from course.',
                'data' => ['course_id' => $course->id, 'subject_id' => $subject->id],
            ]);
        }

        return redirect()->route('training.courses.show', $course)
            ->with('status', 'Subject removed from course.');
    }

    private function uniqueSubjectSlug(string $name, int $instituteId): string
    {
        $slug = Str::slug($name);
        if ($slug === '') {
            $slug = 'subject-'.Str::random(6);
        }
        $slug = Str::limit($slug, 170, '');
        $base = $slug;
        $suffix = 1;
        while (TrainingSubject::withTrashed()
            ->where('institute_id', $instituteId)
            ->where('slug', $slug)
            ->exists()) {
            $suffix++;
            $slug = Str::limit($base, 170 - strlen((string) $suffix) - 1, '').'-'.$suffix;
        }

        return $slug;
    }

    public function update(Request $request, TrainingCourse $course): RedirectResponse|JsonResponse
    {
        $this->assertOwned($request, $course);

        $user = $request->user();
        $data = $this->validated($request, $course);

        $old = $this->snapshot($course);
        $data = $this->normalize($data);

        if (isset($data['name']) && $data['name'] !== $course->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], (int) $user->institute_id, $course->id);
        }

        $course->update($data);

        if ($request->hasFile('banner')) {
            try {
                $oldBanner = $course->banner;
                $path = $this->bannerImage->processAndStore($request->file('banner'), (int) $user->institute_id);
                $course->update(['banner' => $path]);
                if ($oldBanner) {
                    Storage::disk('public')->delete($oldBanner);
                }
            } catch (\InvalidArgumentException $e) {
                if ($request->expectsJson()) {
                    return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => ['banner' => [$e->getMessage()]]], 422);
                }

                return back()->withErrors(['banner' => $e->getMessage()])->withInput();
            }
        }

        if ($request->input('remove_banner')) {
            if ($course->banner) {
                Storage::disk('public')->delete($course->banner);
            }
            $course->update(['banner' => null]);
        }

        $new = $this->snapshot($course);
        if ($old !== $new) {
            $this->audit->record((int) $user->institute_id, (int) $user->id, 'training_course_updated', $course->id, $old, $new, 'training_courses');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Course updated successfully.',
                'data' => ['id' => $course->id],
            ]);
        }

        return redirect()->route('training.courses.edit', $course)->with('status', 'Course updated successfully.');
    }

    public function destroy(Request $request, TrainingCourse $course): RedirectResponse|JsonResponse
    {
        $this->assertOwned($request, $course);

        $user = $request->user();

        try {
            $this->assertDeletable($course);
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->errors()['course'][0] ?? 'Cannot delete this course.'], 422);
            }

            return back()->withErrors($e->errors());
        }

        $old = $this->snapshot($course);
        $course->delete();
        $this->audit->record((int) $user->institute_id, (int) $user->id, 'training_course_deleted', $course->id, $old, null, 'training_courses');

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Course deleted.', 'data' => ['id' => $course->id]]);
        }

        return redirect()->route('training.courses.index')->with('status', 'Course deleted.');
    }

    private function assertOwned(Request $request, TrainingCourse $course): void
    {
        if ($course->institute_id === null || (int) $course->institute_id !== (int) $request->user()->institute_id) {
            abort(403, 'This course does not belong to your institute.');
        }
    }

    private function assertDeletable(TrainingCourse $course): void
    {
        $batches = 0;
        if (Schema::hasTable('training_batches')) {
            $batches = (int) DB::table('training_batches')
                ->where('course_id', $course->id)
                ->whereNull('deleted_at')
                ->count();
        }

        $referenced = $batches > 0
            || $course->materials()->exists()
            || $course->subjects()->exists();

        if ($referenced) {
            throw ValidationException::withMessages([
                'course' => 'This course cannot be deleted because it is already referenced by batches, subjects or materials.',
            ]);
        }
    }

    private function createCourse(int $instituteId, array $data): TrainingCourse
    {
        $institute = Institute::find($instituteId);
        $data = $this->normalize($data);

        return TrainingCourse::create([
            ...$data,
            'institute_id' => $instituteId,
            'course_code' => $this->generateCourseCode($instituteId, $institute),
            'slug' => $this->uniqueSlug($data['name'], $instituteId),
        ]);
    }

    private function generateCourseCode(int $instituteId, ?Institute $institute = null): string
    {
        $source = $institute?->short_name ?: $institute?->institute_code ?: null;
        $prefix = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) Str::slug($source ?? 'INST', '')));
        $prefix = $prefix !== '' ? substr($prefix, 0, 5) : 'INST';

        $seq = 1;
        do {
            $code = $prefix.'-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
            $taken = TrainingCourse::query()
                ->where('institute_id', $instituteId)
                ->where('course_code', $code)
                ->exists();
            $seq++;
        } while ($taken);

        return $code;
    }

    private function uniqueSlug(string $name, int $instituteId, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base !== '' ? $base : 'course';
        $suffix = 1;
        while (TrainingCourse::withTrashed()
            ->where('institute_id', $instituteId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.($suffix++);
        }

        return $slug;
    }

    private function validated(Request $request, ?TrainingCourse $course = null): array
    {
        $instituteId = (int) $request->user()->institute_id;

        $rules = [
            'name' => ['required', 'string', 'max:200'],
            'category_id' => ['required', 'integer', Rule::exists('training_course_categories', 'id')->where('institute_id', $instituteId)->where('subject_type', 'professional')],
            'sub_category_id' => ['nullable', 'integer', Rule::exists('training_course_sub_categories', 'id')->where('institute_id', $instituteId)],
            'short_name' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'level' => ['nullable', Rule::in(['basic', 'intermediate', 'advanced'])],
            'language' => ['nullable', 'string', 'max:30'],
            'duration_type' => ['required', Rule::in(['hours', 'days', 'weeks', 'months', 'years'])],
            'duration_value' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'weekly_classes' => ['nullable', 'integer', 'min:1', 'max:60'],
            'total_classes' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'total_hours' => ['nullable', 'numeric', 'min:0'],
            'mode' => ['nullable', Rule::in(['offline', 'online', 'hybrid'])],
            'batch_capacity_default' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'fee' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'admission_fee' => ['nullable', 'numeric', 'min:0'],
            'exam_fee' => ['nullable', 'numeric', 'min:0'],
            'certificate_fee' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive', 'draft'])],
            'is_featured' => ['nullable', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'requirements' => ['nullable', 'string'],
            'outcomes' => ['nullable', 'string'],
            'prerequisites' => ['nullable', 'string'],
            'intro_video' => ['nullable', 'string', 'max:500'],
            'meta_title' => ['nullable', 'string', 'max:200'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:500'],
            'banner' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];

        if ($course !== null) {
            $rules['remove_banner'] = ['nullable', 'boolean'];
        }

        return $request->validate($rules);
    }

    private function categories(): Collection
    {
        $instituteId = (int) request()->user()->institute_id;

        return TrainingCourseCategory::query()
            ->where('institute_id', $instituteId)
            ->where('subject_type', 'professional')
            ->with('subCategories')
            ->orderBy('name')
            ->get();
    }

    private function normalize(array $data): array
    {
        foreach (['requirements', 'outcomes', 'prerequisites'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->linesToArray($data[$field]);
            }
        }

        if (array_key_exists('display_order', $data)) {
            $data['display_order'] = (int) ($data['display_order'] ?: 0);
        }

        if (array_key_exists('is_featured', $data)) {
            $data['is_featured'] = ! empty($data['is_featured']);
        }

        if (array_key_exists('level', $data)) {
            $v = trim((string) ($data['level'] ?? ''));
            $data['level'] = $v !== '' ? $v : 'basic';
        }

        if (array_key_exists('duration_type', $data)) {
            $v = strtolower(trim((string) ($data['duration_type'] ?? '')));
            $allowed = ['hours', 'days', 'weeks', 'months', 'years'];
            $data['duration_type'] = $v !== '' && in_array($v, $allowed, true) ? $v : 'months';
        }

        if (array_key_exists('mode', $data)) {
            $v = strtolower(trim((string) ($data['mode'] ?? '')));
            $allowed = ['offline', 'online', 'hybrid'];
            $data['mode'] = $v !== '' && in_array($v, $allowed, true) ? $v : 'offline';
        }

        if (array_key_exists('duration_value', $data)) {
            $data['duration_value'] = $data['duration_value'] === null || $data['duration_value'] === '' ? 0 : $data['duration_value'];
        }

        if (array_key_exists('batch_capacity_default', $data)) {
            $data['batch_capacity_default'] = $data['batch_capacity_default'] === null || $data['batch_capacity_default'] === '' ? 30 : (int) $data['batch_capacity_default'];
        }

        foreach (['fee', 'discount', 'admission_fee', 'exam_fee', 'certificate_fee'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $data[$field] === null || $data[$field] === '' ? 0 : $data[$field];
            }
        }

        foreach (['language', 'description', 'short_description', 'short_name', 'intro_video', 'meta_title', 'meta_description', 'meta_keywords'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        return $data;
    }

    private function linesToArray(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            $lines = array_values(array_filter(array_map('trim', $value), fn ($l) => $l !== ''));

            return $lines !== [] ? $lines : null;
        }

        $lines = preg_split('/\r\n|\r|\n/', (string) $value);
        $lines = array_values(array_filter(array_map('trim', $lines), fn ($l) => $l !== ''));

        return $lines !== [] ? $lines : null;
    }

    private function snapshot(TrainingCourse $course): array
    {
        return [
            'name' => $course->name,
            'course_code' => $course->course_code,
            'category_id' => $course->category_id,
            'sub_category_id' => $course->sub_category_id,
            'short_name' => $course->short_name,
            'level' => $course->level,
            'language' => $course->language,
            'short_description' => $course->short_description,
            'duration_type' => $course->duration_type,
            'duration_value' => $course->duration_value,
            'weekly_classes' => $course->weekly_classes,
            'total_classes' => $course->total_classes,
            'total_hours' => $course->total_hours,
            'mode' => $course->mode,
            'fee' => $course->fee,
            'discount' => $course->discount,
            'admission_fee' => $course->admission_fee,
            'exam_fee' => $course->exam_fee,
            'certificate_fee' => $course->certificate_fee,
            'status' => $course->status,
            'is_featured' => $course->is_featured,
            'display_order' => $course->display_order,
        ];
    }
}
