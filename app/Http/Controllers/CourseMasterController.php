<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Institute;
use App\Models\Subject;
use App\Services\BannerImageService;
use App\Services\CourseAuditService;
use App\Services\CourseMasterService;
use App\Support\InstituteDomain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Institute-facing Course Master authoring (Step 42).
 *
 * Operates on institute-owned courses only (institute_id = actor's institute).
 * Shared catalog courses and other institutes' courses are never exposed here.
 * Deleting a referenced course is blocked by CourseMasterService.
 */
class CourseMasterController extends Controller
{
    public function __construct(
        private readonly CourseMasterService $master,
        private readonly CourseAuditService $audit,
        private readonly BannerImageService $bannerImage,
    ) {}

    public function index(Request $request): View
    {
        $instituteId = (int) $request->user()->institute_id;

        $perPage = (int) $request->query('per_page', 15);
        $perPage = in_array($perPage, [15,25,50,75,100,200], true) ? $perPage : 15;

        $courses = Course::query()
            ->where('institute_id', $instituteId)
            ->withCount(['batches', 'curricula', 'materials'])
            ->with('category:id,name')
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

        // Subjects count for tabs — tenant isolated, domain-filtered
        $institute = Institute::find($instituteId);
        $derived = InstituteDomain::subjectTypeFor($institute);
        $subjectsCount = Subject::query()
            ->where('institute_id', $instituteId)
            ->where('subject_type', $derived)
            ->whereNull('deleted_at')
            ->count();

        return view('institute.course-master.index', [
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
        return view('institute.course-master.form', [
            'course' => null,
            'categories' => $this->categories(),
            'subCategories' => collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $data = $this->validated($request);

        $course = $this->master->create((int) $user->institute_id, $data, (int) $user->id);

        if ($request->hasFile('banner')) {
            try {
                $path = $this->bannerImage->processAndStore($request->file('banner'), (int) $user->institute_id);
                $course->update(['banner' => $path]);
            } catch (\InvalidArgumentException $e) {
                // Non-destructive: course already created, surface banner error without rolling back course
                // Keep course, but inform user; banner will be empty and can be re-uploaded on edit.
                if ($request->expectsJson()) {
                    return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => ['banner' => [$e->getMessage()]]], 422);
                }
                return redirect()->route('courses.manage.edit', $course)->withErrors(['banner' => $e->getMessage()])->with('status', 'Course created, but banner: '.$e->getMessage());
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Course created successfully.',
                'data' => ['id' => $course->id],
            ]);
        }

        return redirect()->route('courses.manage.edit', $course)->with('status', 'Course created successfully.');
    }

    public function edit(Request $request, Course $course): View
    {
        $this->assertOwned($request, $course);

        return view('institute.course-master.form', [
            'course' => $course,
            'categories' => $this->categories(),
            'subCategories' => $course->category?->subCategories
                ?? collect(),
        ]);
    }

    public function update(Request $request, Course $course): RedirectResponse|JsonResponse
    {
        $this->assertOwned($request, $course);

        $user = $request->user();
        $data = $this->validated($request, $course);

        $course = $this->master->update($course, (int) $user->institute_id, $data, (int) $user->id);

        if ($request->hasFile('banner')) {
            try {
                $old = $course->banner;
                $path = $this->bannerImage->processAndStore($request->file('banner'), (int) $user->institute_id);
                $course->update(['banner' => $path]);
                if ($old) {
                    Storage::disk('public')->delete($old);
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

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Course updated successfully.',
                'data' => ['id' => $course->id],
            ]);
        }

        return redirect()->route('courses.manage.edit', $course)->with('status', 'Course updated successfully.');
    }

    public function destroy(Request $request, Course $course): RedirectResponse|JsonResponse
    {
        $this->assertOwned($request, $course);

        $user = $request->user();

        try {
            $this->master->destroy($course, (int) $user->institute_id, (int) $user->id);
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->errors()['course'][0] ?? 'Cannot delete this course.'], 422);
            }

            return back()->withErrors($e->errors());
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Course deleted.', 'data' => ['id' => $course->id]]);
        }

        return redirect()->route('courses.manage.index')->with('status', 'Course deleted.');
    }

    private function assertOwned(Request $request, Course $course): void
    {
        if ($course->institute_id === null || (int) $course->institute_id !== (int) $request->user()->institute_id) {
            abort(403, 'This course does not belong to your institute.');
        }
    }

    private function validated(Request $request, ?Course $course = null): array
    {
        $instituteId = (int) $request->user()->institute_id;
        $institute = Institute::find($instituteId);
        $domainType = InstituteDomain::subjectTypeFor($institute);
        $rules = [
            'name' => ['required', 'string', 'max:200'],
            'category_id' => ['required', 'integer', Rule::exists('course_categories', 'id')->where('institute_id', $instituteId)->where('subject_type', $domainType)],
            'sub_category_id' => ['nullable', 'integer', Rule::exists('course_sub_categories', 'id')->where('institute_id', $instituteId)],
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
        $institute = Institute::find($instituteId);
        $domainType = InstituteDomain::subjectTypeFor($institute);
        return CourseCategory::query()
            ->where('institute_id', $instituteId)
            ->where('subject_type', $domainType)
            ->with('subCategories')
            ->orderBy('name')
            ->get();
    }
}
