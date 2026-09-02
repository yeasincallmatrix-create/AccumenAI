<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseSubCategory;
use App\Models\Institute;
use App\Support\InstituteDomain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CourseSubCategoryManageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $instituteId = (int) $request->user()->institute_id;

        $categoryId = $request->query('category_id');

        $query = CourseSubCategory::withoutGlobalScope('institute')
            ->where('institute_id', $instituteId)
            ->with('category:id,name');

        if ($categoryId) {
            $query->where('category_id', (int) $categoryId);
        }

        $subs = $query->orderBy('name')->get()->map(function (CourseSubCategory $sub) use ($instituteId) {
            $coursesCount = Course::withoutGlobalScopes()->where('institute_id', $instituteId)->where('sub_category_id', $sub->id)->count();
            $batchesCount = DB::table('batches')
                ->join('courses', 'courses.id', '=', 'batches.course_id')
                ->where('courses.institute_id', $instituteId)
                ->where('courses.sub_category_id', $sub->id)
                ->whereNull('batches.deleted_at')
                ->count();
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

        // Also return categories for dropdown — domain-aware
        $institute = Institute::find($instituteId);
        $domainType = InstituteDomain::subjectTypeFor($institute);
        $categories = CourseCategory::where('institute_id', $instituteId)
            ->where('subject_type', $domainType)
            ->orderBy('name')
            ->get(['id','name']);

        return response()->json(['success' => true, 'data' => ['sub_categories' => $subs, 'categories' => $categories]]);
    }

    public function store(Request $request): JsonResponse
    {
        $instituteId = (int) $request->user()->institute_id;

        $institute = Institute::find($instituteId);
        $domainType = InstituteDomain::subjectTypeFor($institute);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'category_id' => ['required', 'integer', Rule::exists('course_categories', 'id')->where('institute_id', $instituteId)->where('subject_type', $domainType)],
        ]);

        $category = CourseCategory::where('institute_id', $instituteId)->where('id', (int) $data['category_id'])->where('subject_type', $domainType)->first();
        if (! $category) {
            return response()->json(['success' => false, 'message' => 'Invalid category.'], 422);
        }

        $name = trim($data['name']);
        $slug = Str::slug($name);
        if ($slug === '') { $slug = 'sub-'.Str::random(6); }

        $baseSlug = $slug; $suffix = 1;
        while (CourseSubCategory::withoutGlobalScope('institute')->where('category_id', $category->id)->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.(++$suffix);
        }

        $sub = CourseSubCategory::create([
            'institute_id' => $instituteId,
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
        ]);

        return response()->json(['success' => true, 'message' => 'Sub Category created.', 'data' => ['id' => $sub->id]]);
    }

    public function update(Request $request, CourseSubCategory $subCategory): JsonResponse
    {
        $instituteId = (int) $request->user()->institute_id;
        $this->assertOwned($subCategory, $instituteId);

        $institute = Institute::find($instituteId);
        $domainType = InstituteDomain::subjectTypeFor($institute);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'category_id' => ['required', 'integer', Rule::exists('course_categories', 'id')->where('institute_id', $instituteId)->where('subject_type', $domainType)],
        ]);

        $newCategoryId = (int) $data['category_id'];
        $category = CourseCategory::where('institute_id', $instituteId)->where('id', $newCategoryId)->where('subject_type', $domainType)->first();
        if (! $category) {
            return response()->json(['success' => false, 'message' => 'Invalid category.'], 422);
        }

        $name = trim($data['name']);
        $slug = Str::slug($name);
        if ($slug === '') { $slug = $subCategory->slug; }
        if ($slug !== $subCategory->slug || $newCategoryId !== (int) $subCategory->category_id) {
            $baseSlug = $slug; $suffix = 1;
            while (CourseSubCategory::withoutGlobalScope('institute')->where('category_id', $newCategoryId)->where('slug', $slug)->where('id', '!=', $subCategory->id)->exists()) {
                $slug = $baseSlug.'-'.(++$suffix);
            }
        } else {
            $slug = $subCategory->slug;
        }

        $subCategory->update(['name' => $name, 'slug' => $slug, 'category_id' => $newCategoryId]);

        return response()->json(['success' => true, 'message' => 'Sub Category updated.']);
    }

    public function destroy(Request $request, CourseSubCategory $subCategory): JsonResponse
    {
        $instituteId = (int) $request->user()->institute_id;
        $this->assertOwned($subCategory, $instituteId);

        $coursesCount = Course::withoutGlobalScopes()->where('institute_id', $instituteId)->where('sub_category_id', $subCategory->id)->count();
        $batchesCount = DB::table('batches')
            ->join('courses', 'courses.id', '=', 'batches.course_id')
            ->where('courses.institute_id', $instituteId)
            ->where('courses.sub_category_id', $subCategory->id)
            ->whereNull('batches.deleted_at')
            ->count();

        $hasDependents = ($coursesCount + $batchesCount) > 0;

        $request->validate([
            'replacement_sub_category_id' => [$hasDependents ? 'required' : 'nullable', 'integer', Rule::exists('course_sub_categories', 'id')->where('institute_id', $instituteId)],
        ]);

        $replacementId = $request->input('replacement_sub_category_id');

        if ($hasDependents) {
            if ((int) $replacementId === (int) $subCategory->id) {
                return response()->json(['success' => false, 'message' => 'Replacement sub category must be different.'], 422);
            }
            $replacement = CourseSubCategory::withoutGlobalScope('institute')->where('institute_id', $instituteId)->where('id', (int) $replacementId)->first();
            if (! $replacement) {
                return response()->json(['success' => false, 'message' => 'Invalid replacement sub category.'], 422);
            }

            Course::withoutGlobalScopes()->where('institute_id', $instituteId)->where('sub_category_id', $subCategory->id)->update(['sub_category_id' => $replacement->id]);
        } else {
            // No dependents, just null out any remaining refs (defensive, FK SET NULL would handle)
            Course::withoutGlobalScopes()->where('institute_id', $instituteId)->where('sub_category_id', $subCategory->id)->update(['sub_category_id' => null]);
        }

        $subCategory->delete();

        return response()->json(['success' => true, 'message' => 'Sub Category deleted.']);
    }

    private function assertOwned(CourseSubCategory $sub, int $instituteId): void
    {
        if ((int) $sub->institute_id !== $instituteId) {
            abort(403, 'Sub Category does not belong to your institute.');
        }
    }
}
