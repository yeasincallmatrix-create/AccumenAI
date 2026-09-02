<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseSubCategory;
use App\Models\Institute;
use App\Models\Subject;
use App\Support\InstituteDomain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CourseCategoryManageController extends Controller
{
    /**
     * Return institute categories with usage counts.
     * Used by the manage modal to render list and by the main form to refresh dropdowns.
     */
    public function index(Request $request): JsonResponse
    {
        $instituteId = (int) $request->user()->institute_id;
        $institute = Institute::find($instituteId);
        $domainType = InstituteDomain::subjectTypeFor($institute);

        $categories = CourseCategory::query()
            ->where('institute_id', $instituteId)
            ->where('subject_type', $domainType)
            ->withCount(['subCategories'])
            ->orderBy('name')
            ->get()
            ->map(function (CourseCategory $cat) use ($instituteId) {
                $coursesCount = Course::query()->withoutGlobalScopes()->where('institute_id', $instituteId)->where('category_id', $cat->id)->count();
                $subjectsCount = Subject::query()->withoutGlobalScopes()->where('institute_id', $instituteId)->where('category_id', $cat->id)->count();
                // Batches are tied to courses; count batches whose course belongs to this category.
                $batchesCount = DB::table('batches')
                    ->join('courses', 'courses.id', '=', 'batches.course_id')
                    ->where('courses.institute_id', $instituteId)
                    ->where('courses.category_id', $cat->id)
                    ->whereNull('batches.deleted_at')
                    ->count();
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
        if ($slug === '') { $slug = 'category-'.Str::random(6); }

        // Ensure unique per institute+slug (original DB has UNIQUE uq_course_categories_inst_slug)
        $baseSlug = $slug; $suffix = 1;
        while (CourseCategory::withoutGlobalScope('institute')->where('institute_id', $instituteId)->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.(++$suffix);
        }

        $institute = Institute::find($instituteId);
        $domainType = InstituteDomain::subjectTypeFor($institute);
        $category = CourseCategory::create([
            'institute_id' => $instituteId,
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'subject_type' => $domainType,
        ]);

        return response()->json(['success' => true, 'message' => 'Category created.', 'data' => ['id' => $category->id, 'name' => $category->name, 'slug' => $category->slug]]);
    }

    public function update(Request $request, CourseCategory $category): JsonResponse
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
        if ($slug === '') { $slug = $category->slug; }
        if ($slug !== $category->slug) {
            $baseSlug = $slug; $suffix = 1;
            while (CourseCategory::withoutGlobalScope('institute')->where('institute_id', $instituteId)->where('slug', $slug)->where('id', '!=', $category->id)->exists()) {
                $slug = $baseSlug.'-'.(++$suffix);
            }
        } else {
            $slug = $category->slug;
        }

        $category->update(['name' => $name, 'slug' => $slug]);

        return response()->json(['success' => true, 'message' => 'Category updated.', 'data' => ['id' => $category->id, 'name' => $category->name]]);
    }

    public function destroy(Request $request, CourseCategory $category): JsonResponse
    {
        $instituteId = (int) $request->user()->institute_id;
        $this->assertOwned($category, $instituteId);

        $coursesCount = Course::withoutGlobalScopes()->where('institute_id', $instituteId)->where('category_id', $category->id)->count();
        $subjectsCount = Subject::withoutGlobalScopes()->where('institute_id', $instituteId)->where('category_id', $category->id)->count();
        $subCatsCount = CourseSubCategory::withoutGlobalScope('institute')->where('institute_id', $instituteId)->where('category_id', $category->id)->count();
        $batchesCount = DB::table('batches')
            ->join('courses', 'courses.id', '=', 'batches.course_id')
            ->where('courses.institute_id', $instituteId)
            ->where('courses.category_id', $category->id)
            ->whereNull('batches.deleted_at')
            ->count();

        $hasDependents = ($coursesCount + $subjectsCount + $subCatsCount + $batchesCount) > 0;

        $request->validate([
            'replacement_category_id' => [$hasDependents ? 'required' : 'nullable', 'integer', Rule::exists('course_categories', 'id')->where('institute_id', $instituteId)],
        ]);

        $replacementId = $request->input('replacement_category_id');

        if ($hasDependents) {
            if ((int) $replacementId === (int) $category->id) {
                return response()->json(['success' => false, 'message' => 'Replacement category must be different.'], 422);
            }
            $replacement = CourseCategory::withoutGlobalScope('institute')->where('institute_id', $instituteId)->where('id', (int) $replacementId)->first();
            if (! $replacement) {
                return response()->json(['success' => false, 'message' => 'Invalid replacement category.'], 422);
            }

            DB::transaction(function () use ($category, $replacement, $instituteId) {
                // Move sub-categories to replacement; handle slug collisions (uq_course_subcat_cat_slug)
                $subs = CourseSubCategory::withoutGlobalScope('institute')
                    ->where('institute_id', $instituteId)
                    ->where('category_id', $category->id)
                    ->get();
                foreach ($subs as $sub) {
                    $slug = $sub->slug;
                    $base = $slug; $suffix = 1;
                    while (CourseSubCategory::withoutGlobalScope('institute')->where('category_id', $replacement->id)->where('slug', $slug)->where('id', '!=', $sub->id)->exists()) {
                        $slug = $base.'-'.(++$suffix);
                    }
                    $sub->update(['category_id' => $replacement->id, 'slug' => $slug]);
                }

                // Reassign courses and subjects (FK is SET NULL, so we can re-point safely)
                Course::withoutGlobalScopes()->where('institute_id', $instituteId)->where('category_id', $category->id)->update(['category_id' => $replacement->id]);
                Subject::withoutGlobalScopes()->where('institute_id', $instituteId)->where('category_id', $category->id)->update(['category_id' => $replacement->id]);
            });
        }

        // If no dependents, sub-categories would be orphaned; with CASCADE they are deleted.
        // We have already moved them if there were dependents + replacement.
        $category->delete();

        return response()->json(['success' => true, 'message' => 'Category deleted.']);
    }

    private function assertOwned(CourseCategory $category, int $instituteId): void
    {
        if ((int) $category->institute_id !== $instituteId) {
            abort(403, 'Category does not belong to your institute.');
        }
    }
}
