<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Student;
use App\Models\Course;

class CertificateSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $students = Student::take(5)->get();
        $courses = Course::take(5)->get();

        foreach ($students as $i => $student) {
            $course = $courses[$i % count($courses)];

            DB::table('certificates')->insert([
                'student_id' => $student->id,
                'course_id' => $course->id,
                'institute_id' => 38,
                'batch_id' => 1,
                'certificate_type_id' => 1,
                'issue_date' => now()->subMonths($i),
                'status' => ['pending', 'active', 'revoked'][$i % 3],
                'certificate_number' => 'MNT-' . now()->format('Y') . '-' . sprintf('%05d', 10000 + $i),
            ]);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}