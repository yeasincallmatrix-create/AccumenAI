<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Idempotent migration of training-center rows from shared education tables
 * into the separated training_* course tables (Option B).
 */
class MigrateTrainingCoursesData extends Command
{
    protected $signature = 'training:migrate-courses-data {--dry-run}';

    protected $description = 'Copy training-center courses/categories from shared tables into training_* tables';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        foreach (['training_courses', 'training_course_categories', 'training_course_sub_categories', 'training_course_materials', 'training_course_subjects'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->error("Missing table: {$t}. Run php artisan migrate first.");

                return self::FAILURE;
            }
        }

        $tcInstitutes = DB::table('institutes')->where('industry', 'training_center')->pluck('id');
        if ($tcInstitutes->isEmpty()) {
            $this->info('No training_center institutes — nothing to migrate.');

            return self::SUCCESS;
        }

        $srcCategories = DB::table('course_categories')
            ->whereIn('institute_id', $tcInstitutes)
            ->where('subject_type', 'professional')
            ->get();

        $srcCourses = DB::table('courses')
            ->whereIn('institute_id', $tcInstitutes)
            ->whereNull('deleted_at')
            ->get();

        $srcSubCats = DB::table('course_sub_categories')
            ->whereIn('institute_id', $tcInstitutes)
            ->get();

        $srcSubjects = DB::table('subjects')
            ->whereIn('institute_id', $tcInstitutes)
            ->where('subject_type', 'professional')
            ->whereNull('deleted_at')
            ->get();

        $this->table(['Source', 'Count'], [
            ['course_categories (professional, TC)', $srcCategories->count()],
            ['courses (TC institutes)', $srcCourses->count()],
            ['course_sub_categories (TC)', $srcSubCats->count()],
            ['subjects (professional, TC)', $srcSubjects->count()],
            ['training_courses (existing)', DB::table('training_courses')->count()],
            ['training_course_categories (existing)', DB::table('training_course_categories')->count()],
        ]);

        if ($dryRun) {
            $this->warn('DRY RUN — no rows written.');

            return self::SUCCESS;
        }

        $catMap = [];
        $subMap = [];

        DB::transaction(function () use ($srcCategories, $srcSubCats, $srcCourses, $srcSubjects, &$catMap, &$subMap) {
            foreach ($srcCategories as $cat) {
                $exists = DB::table('training_course_categories')
                    ->where('institute_id', $cat->institute_id)
                    ->where('slug', $cat->slug)
                    ->first();
                if ($exists) {
                    $catMap[$cat->id] = $exists->id;

                    continue;
                }
                $id = DB::table('training_course_categories')->insertGetId([
                    'institute_id' => $cat->institute_id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'status' => $cat->status,
                    'subject_type' => 'professional',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $catMap[$cat->id] = $id;
            }

            foreach ($srcSubCats as $sub) {
                $newCatId = $catMap[$sub->category_id] ?? null;
                if ($newCatId === null) {
                    continue;
                }
                $exists = DB::table('training_course_sub_categories')
                    ->where('category_id', $newCatId)
                    ->where('slug', $sub->slug)
                    ->first();
                if ($exists) {
                    $subMap[$sub->id] = $exists->id;

                    continue;
                }
                $id = DB::table('training_course_sub_categories')->insertGetId([
                    'institute_id' => $sub->institute_id,
                    'category_id' => $newCatId,
                    'name' => $sub->name,
                    'slug' => $sub->slug,
                    'status' => $sub->status,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $subMap[$sub->id] = $id;
            }

            foreach ($srcCourses as $course) {
                $exists = DB::table('training_courses')
                    ->where('institute_id', $course->institute_id)
                    ->where('slug', $course->slug)
                    ->first();
                if ($exists) {
                    continue;
                }
                $newCatId = $course->category_id !== null ? ($catMap[$course->category_id] ?? null) : null;
                $newSubId = $course->sub_category_id !== null ? ($subMap[$course->sub_category_id] ?? null) : null;

                DB::table('training_courses')->insert([
                    'is_test' => $course->is_test ?? 0,
                    'institute_id' => $course->institute_id,
                    'category_id' => $newCatId,
                    'sub_category_id' => $newSubId,
                    'course_code' => $course->course_code,
                    'name' => $course->name,
                    'slug' => $course->slug,
                    'short_name' => $course->short_name,
                    'description' => $course->description,
                    'short_description' => $course->short_description,
                    'modules' => $course->modules,
                    'level' => $course->level,
                    'language' => $course->language,
                    'duration_type' => $course->duration_type,
                    'duration_value' => $course->duration_value,
                    'weekly_classes' => $course->weekly_classes,
                    'class_duration_minutes' => $course->class_duration_minutes,
                    'total_classes' => $course->total_classes,
                    'total_hours' => $course->total_hours,
                    'mode' => $course->mode,
                    'batch_capacity_default' => $course->batch_capacity_default,
                    'fee' => $course->fee,
                    'discount' => $course->discount,
                    'admission_fee' => $course->admission_fee,
                    'exam_fee' => $course->exam_fee,
                    'certificate_fee' => $course->certificate_fee,
                    'thumbnail' => $course->thumbnail,
                    'banner' => $course->banner,
                    'intro_video' => $course->intro_video,
                    'is_featured' => $course->is_featured,
                    'display_order' => $course->display_order,
                    'meta_title' => $course->meta_title,
                    'meta_description' => $course->meta_description,
                    'meta_keywords' => $course->meta_keywords,
                    'requirements' => $course->requirements,
                    'outcomes' => $course->outcomes,
                    'prerequisites' => $course->prerequisites,
                    'status' => $course->status,
                    'created_at' => $course->created_at,
                    'updated_at' => $course->updated_at,
                ]);
            }

            foreach ($srcSubjects as $subject) {
                $exists = DB::table('training_subjects')
                    ->where('institute_id', $subject->institute_id)
                    ->where('slug', $subject->slug)
                    ->first();
                if ($exists) {
                    continue;
                }
                $newCatId = $subject->category_id !== null ? ($catMap[$subject->category_id] ?? null) : null;
                DB::table('training_subjects')->insert([
                    'institute_id' => $subject->institute_id,
                    'category_id' => $newCatId,
                    'subject_type' => 'professional',
                    'subject_code' => $subject->subject_code,
                    'name' => $subject->name,
                    'slug' => $subject->slug,
                    'short_name' => $subject->short_name,
                    'description' => $subject->description,
                    'status' => $subject->status,
                    'created_at' => $subject->created_at,
                    'updated_at' => $subject->updated_at,
                ]);
            }
        });

        $this->info('Migration complete.');
        $this->table(['Target', 'Count'], [
            ['training_course_categories', DB::table('training_course_categories')->count()],
            ['training_course_sub_categories', DB::table('training_course_sub_categories')->count()],
            ['training_courses', DB::table('training_courses')->count()],
            ['training_subjects', DB::table('training_subjects')->count()],
        ]);

        return self::SUCCESS;
    }
}
