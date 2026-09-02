<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Course;
use App\Models\Institute;

class ExamSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $courses = Course::take(5)->get();
        $institute = Institute::first();

        if ($institute && $courses->isNotEmpty()) {
            $instituteId = $institute->id;

            // Insert dummy exams directly (batch_id=1, certificate_type_id=1 - FK checks disabled)
            $statuses = ['scheduled', 'ongoing', 'completed', 'cancelled'];
            $i = 0;

            foreach ($courses as $course) {
                foreach (range(1, 3) as $batchId) {
                    $status = $statuses[array_rand($statuses)];
                    DB::table('exams')->insert([
                        'institute_id' => $instituteId,
                        'course_id' => $course->id,
                        'batch_id' => $batchId,
                        'title' => 'Exam ' . ($i + 1),
                        'exam_date' => now()->subDays(random_int(1, 90))->format('Y-m-d'),
                        'full_marks' => random_int(100, 200),
                        'pass_marks' => random_int(40, 60),
                        'status' => $status,
                    ]);
                    $i++;
                }
            }

            echo "Created $i dummy exams across " . count($courses) . " courses and 3 batches\n";
        } else {
            echo "Could not create exams: institute or courses not available\n";
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}