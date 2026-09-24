<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Training\TrainingCourse;
use App\Models\Training\TrainingCourseCategory;
use App\Models\Training\TrainingCourseSubCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TrainingCourseSubCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $instituteId = (int) $request->user()->institute_id;
        $categoryId = $request->query('category_id');

        $query = TrainingCourseSubCategory::where('institute_id', $instituteId)
            ->with('category:id,name');

        if ($categoryId) {
            $query->where('category_id', (int) $categoryId);
        }

        $subs = $query->orderBy('name')->get()->map(function (TrainingCourseSubCategory $sub) use ($instituteId) {
            $coursesCount = TrainingCourse::withoutGlobalScopes()->where('institute_id', $instituteId)->where('sub_category_id', $sub->id)->count();
            $batchesCount = 0;
            if (DB::hasTable('training_batches')) {
                $batchesCount = (int) DB::table('training_batches')
                    ->join('training_courses', 'training_courses.id', '=', 'training_batches.course_id')
                    ->where('training_courses.institute_id', $instituteId)
                    ->where('training_courses.sub_category_id', $sub->id)
                    ->whereNull('training_batches.deleted_at')
                    ->count();
            }

            return [
                'id' => $sub->id,
                'name' => $sub->name,
                'slug' => $sub->slug,
                'status' => $sub->status,
                'category_id' => $sub->category_id,
                'category_name' => $sub->category->name ?? '—',
                'courses_count' => $coursesCount,
                'batches_count' => $batchesCount,
                'total_dependents' => $coursesCount + $batchesCount,
            ];
        });

        $categories = TrainingCourseCategory::where('institute_id', $instituteId)
            ->where('subject_type', 'professional')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['success' => true, 'data' => ['sub_categories' => $subs, 'categories' => $categories]]);
    }

    public function store(Request $request): JsonResponse
    {
        $instituteId = (int) $request->user()->institute_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'category_id' => ['required', 'integer', Rule::exists('training_course_categories', 'id')->where('institute_id', $instituteId)->where('subject_type', 'professional')],
        ]);

        $category = TrainingCourseCategory::where('institute_id', $instituteId)->where('id', (int) $data['category_id'])->where('subject_type', 'professional')->first();
        if (! $category) {
            return response()->json(['success' => false, 'message' => 'Invalid category.'], 422);
        }

        $name = trim($data['name']);
        $slug = Str::slug($name);
        if ($slug === '') {
            $slug = 'sub-'.Str::random(6);
        }

        $baseSlug = $slug;
        $suffix = 1;
        while (TrainingCourseSubCategory::where('category_id', $category->id)->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.(++$suffix);
        }

        $sub = TrainingCourseSubCategory::create([
            'institute_id' => $instituteId,
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
        ]);

        return response()->json(['success' => true, 'message' => 'Sub Category created.', 'data' => ['id' => $sub->id]]);
    }

    public function update(Request $request, TrainingCourseSubCategory $subCategory): JsonResponse
    {
        $instituteId = (int) $request->user()->institute_id;
        $this->assertOwned($subCategory, $instituteId);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'category_id' => ['required', 'integer', Rule::exists('training_course_categories', 'id')->where('institute_id', $instituteId)->where('subject_type', 'professional')],
        ]);

        $newCategoryId = (int) $data['category_id'];
        $category = TrainingCourseCategory::where('institute_id', $instituteId)->where('id', $newCategoryId)->where('subject_type', 'professional')->first();
        if (! $category) {
            return response()->json(['success' => false, 'message' => 'Invalid category.'], 422);
        }

        $name = trim($data['name']);
        $slug = Str::slug($name);
        if ($slug === '') {
            $slug = $subCategory->slug;
        }
        if ($slug !== $subCategory->slug || $newCategoryId !== (int) $subCategory->category_id) {
            $baseSlug = $slug;
            $suffix = 1;
            while (TrainingCourseSubCategory::where('category_id', $newCategoryId)->where('slug', $slug)->where('id', '!=', $subCategory->id)->exists()) {
                $slug = $baseSlug.'-'.(++$suffix);
            }
        } else {
            $slug = $subCategory->slug;
        }

        $subCategory->update(['name' => $name, 'slug' => $slug, 'category_id' => $newCategoryId]);

        return response()->json(['success' => true, 'message' => 'Sub Category updated.']);
    }

    public function destroy(Request $request, TrainingCourseSubCategory $subCategory): JsonResponse
    {
        $instituteId = (int) $request->user()->institute_id;
        $this->assertOwned($subCategory, $instituteId);

        $coursesCount = TrainingCourse::withoutGlobalScopes()->where('institute_id', $instituteId)->where('sub_category_id', $subCategory->id)->count();

        $hasDependents = $coursesCount > 0;

        $request->validate([
            'replacement_sub_category_id' => [$hasDependents ? 'required' : 'nullable', 'integer', Rule::exists('training_course_sub_categories', 'id')->where('institute_id', $instituteId)],
        ]);

        $replacementId = $request->input('replacement_sub_category_id');

        if ($hasDependents) {
            if ((int) $replacementId === (int) $subCategory->id) {
                return response()->json(['success' => false, 'message' => 'Replacement sub category must be different.'], 422);
            }
            $replacement = TrainingCourseSubCategory::where('institute_id', $instituteId)->where('id', (int) $replacementId)->first();
            if (! $replacement) {
                return response()->json(['success' => false, 'message' => 'Invalid replacement sub category.'], 422);
            }

            TrainingCourse::withoutGlobalScopes()->where('institute_id', $instituteId)->where('sub_category_id', $subCategory->id)->update(['sub_category_id' => $replacement->id]);
        } else {
            TrainingCourse::withoutGlobalScopes()->where('institute_id', $instituteId)->where('sub_category_id', $subCategory->id)->update(['sub_category_id' => null]);
        }

        $subCategory->delete();

        return response()->json(['success' => true, 'message' => 'Sub Category deleted.']);
    }

    private function assertOwned(TrainingCourseSubCategory $sub, int $instituteId): void
    {
        if ((int) $sub->institute_id !== $instituteId) {
            abort(403, 'Sub Category does not belong to your institute.');
        }
    }
}
