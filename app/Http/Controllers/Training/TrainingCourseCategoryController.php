<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Training\TrainingCourse;
use App\Models\Training\TrainingCourseCategory;
use App\Models\Training\TrainingCourseSubCategory;
use App\Models\Training\TrainingSubject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TrainingCourseCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $instituteId = (int) $request->user()->institute_id;

        $categories = TrainingCourseCategory::query()
            ->where('institute_id', $instituteId)
            ->where('subject_type', 'professional')
            ->withCount(['subCategories'])
            ->orderBy('name')
            ->get()
            ->map(function (TrainingCourseCategory $cat) use ($instituteId) {
                $coursesCount = TrainingCourse::query()->withoutGlobalScopes()->where('institute_id', $instituteId)->where('category_id', $cat->id)->count();
                $subjectsCount = TrainingSubject::query()->withoutGlobalScopes()->where('institute_id', $instituteId)->where('category_id', $cat->id)->count();
                $batchesCount = 0;
                if (DB::hasTable('training_batches')) {
                    $batchesCount = (int) DB::table('training_batches')
                        ->join('training_courses', 'training_courses.id', '=', 'training_batches.course_id')
                        ->where('training_courses.institute_id', $instituteId)
                        ->where('training_courses.category_id', $cat->id)
                        ->whereNull('training_batches.deleted_at')
                        ->count();
                }

                return [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'status' => $cat->status,
                    'sub_categories_count' => $cat->sub_categories_count,
                    'courses_count' => $coursesCount,
                    'subjects_count' => $subjectsCount,
                    'batches_count' => $batchesCount,
                    'total_dependents' => $coursesCount + $subjectsCount + $batchesCount,
                ];
            });

        return response()->json(['success' => true, 'data' => $categories]);
    }

    public function store(Request $request): JsonResponse
    {
        $instituteId = (int) $request->user()->institute_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $name = trim($data['name']);
        $slug = Str::slug($name);
        if ($slug === '') {
            $slug = 'category-'.Str::random(6);
        }

        $baseSlug = $slug;
        $suffix = 1;
        while (TrainingCourseCategory::where('institute_id', $instituteId)->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.(++$suffix);
        }

        $category = TrainingCourseCategory::create([
            'institute_id' => $instituteId,
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'subject_type' => 'professional',
        ]);

        return response()->json(['success' => true, 'message' => 'Category created.', 'data' => ['id' => $category->id, 'name' => $category->name, 'slug' => $category->slug]]);
    }

    public function update(Request $request, TrainingCourseCategory $category): JsonResponse
    {
        $instituteId = (int) $request->user()->institute_id;
        $this->assertOwned($category, $instituteId);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $name = trim($data['name']);
        if ($name === $category->name) {
            return response()->json(['success' => true, 'message' => 'No changes.', 'data' => ['id' => $category->id]]);
        }

        $slug = Str::slug($name);
        if ($slug === '') {
            $slug = $category->slug;
        }
        if ($slug !== $category->slug) {
            $baseSlug = $slug;
            $suffix = 1;
            while (TrainingCourseCategory::where('institute_id', $instituteId)->where('slug', $slug)->where('id', '!=', $category->id)->exists()) {
                $slug = $baseSlug.'-'.(++$suffix);
            }
        } else {
            $slug = $category->slug;
        }

        $category->update(['name' => $name, 'slug' => $slug]);

        return response()->json(['success' => true, 'message' => 'Category updated.', 'data' => ['id' => $category->id, 'name' => $category->name]]);
    }

    public function destroy(Request $request, TrainingCourseCategory $category): JsonResponse
    {
        $instituteId = (int) $request->user()->institute_id;
        $this->assertOwned($category, $instituteId);

        $coursesCount = TrainingCourse::withoutGlobalScopes()->where('institute_id', $instituteId)->where('category_id', $category->id)->count();
        $subjectsCount = TrainingSubject::withoutGlobalScopes()->where('institute_id', $instituteId)->where('category_id', $category->id)->count();
        $subCatsCount = TrainingCourseSubCategory::where('institute_id', $instituteId)->where('category_id', $category->id)->count();

        $hasDependents = ($coursesCount + $subjectsCount + $subCatsCount) > 0;

        $request->validate([
            'replacement_category_id' => [$hasDependents ? 'required' : 'nullable', 'integer', Rule::exists('training_course_categories', 'id')->where('institute_id', $instituteId)],
        ]);

        $replacementId = $request->input('replacement_category_id');

        if ($hasDependents) {
            if ((int) $replacementId === (int) $category->id) {
                return response()->json(['success' => false, 'message' => 'Replacement category must be different.'], 422);
            }
            $replacement = TrainingCourseCategory::where('institute_id', $instituteId)->where('id', (int) $replacementId)->first();
            if (! $replacement) {
                return response()->json(['success' => false, 'message' => 'Invalid replacement category.'], 422);
            }

            DB::transaction(function () use ($category, $replacement, $instituteId) {
                $subs = TrainingCourseSubCategory::where('institute_id', $instituteId)
                    ->where('category_id', $category->id)
                    ->get();
                foreach ($subs as $sub) {
                    $slug = $sub->slug;
                    $base = $slug;
                    $suffix = 1;
                    while (TrainingCourseSubCategory::where('category_id', $replacement->id)->where('slug', $slug)->where('id', '!=', $sub->id)->exists()) {
                        $slug = $base.'-'.(++$suffix);
                    }
                    $sub->update(['category_id' => $replacement->id, 'slug' => $slug]);
                }

                TrainingCourse::withoutGlobalScopes()->where('institute_id', $instituteId)->where('category_id', $category->id)->update(['category_id' => $replacement->id]);
                TrainingSubject::withoutGlobalScopes()->where('institute_id', $instituteId)->where('category_id', $category->id)->update(['category_id' => $replacement->id]);
            });
        }

        $category->delete();

        return response()->json(['success' => true, 'message' => 'Category deleted.']);
    }

    private function assertOwned(TrainingCourseCategory $category, int $instituteId): void
    {
        if ((int) $category->institute_id !== $instituteId) {
            abort(403, 'Category does not belong to your institute.');
        }
    }
}
