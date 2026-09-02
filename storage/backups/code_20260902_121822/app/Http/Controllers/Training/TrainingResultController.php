<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Training\Enrollment;
use App\Models\TrainingBatchResult;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrainingResultController extends Controller
{
    public function publish(Request $request, Batch $batch): RedirectResponse
    {
        $instituteId = (int) $request->user()->institute_id;
        abort_unless((int) $batch->institute_id === $instituteId, 403);

        // Check if the batch is completed
        if ($batch->status !== 'completed') {
            return back()->with('error', 'Results can only be published for completed batches.');
        }

        // Fetch enrollments via unified enrollments table
        $enrollments = Enrollment::where('batch_id', $batch->id)
            ->where('institute_id', $instituteId)
            ->with('student:id,first_name,last_name,full_name')
            ->get()
            ->map(fn($e) => (object)['student_id' => $e->student_id]);

        if ($enrollments->isEmpty()) {
            return back()->with('error', 'No trainees enrolled in this batch. Enroll trainees first.');
        }

        $exams = Exam::where('batch_id', $batch->id)->where('institute_id', $instituteId)->get();
        if ($exams->isEmpty()) {
            return back()->with('error', 'No exams found for this batch. Create exams first.');
        }

        $published = 0;
        foreach ($enrollments as $enrollment) {
            $studentId = $enrollment->student_id;

            // Aggregate marks from ExamResults for this student (student_id is students.id)
            $results = ExamResult::whereIn('exam_id', $exams->pluck('id'))
                ->where('student_id', $studentId)
                ->get();

            // If no results at all, skip (not yet marked)
            if ($results->isEmpty()) {
                continue;
            }

            $totalMarks = 0;
            $obtainedMarks = 0;
            foreach ($exams as $exam) {
                $totalMarks += (float) $exam->full_marks;
                $examResultsForTrainee = $results->where('exam_id', $exam->id);
                foreach ($examResultsForTrainee as $r) {
                    $obtainedMarks += (float) ($r->marks_obtained ?? 0);
                }
            }

            $percentage = $totalMarks > 0 ? round(($obtainedMarks / $totalMarks) * 100, 2) : 0;
            $status = $percentage >= 40 ? 'pass' : 'fail';

            TrainingBatchResult::updateOrCreate(
                ['batch_id' => $batch->id, 'student_id' => $studentId],
                [
                    'institute_id' => $instituteId,
                    'total_marks' => $totalMarks,
                    'obtained_marks' => $obtainedMarks,
                    'percentage' => $percentage,
                    'status' => $status,
                    'published_at' => now(),
                ]
            );
            $published++;
        }

        return back()->with('status', "Published results for $published trainee(s) in batch '{$batch->name}'.");
    }

    public function reEvaluate(Request $request, Batch $batch): RedirectResponse
    {
        $instituteId = (int) $request->user()->institute_id;
        abort_unless((int) $batch->institute_id === $instituteId, 403);

        if ($batch->status !== 'completed') {
            return back()->with('error', 'Results can only be re-evaluated for completed batches.');
        }

        $enrollments = Enrollment::where('batch_id', $batch->id)
            ->where('institute_id', $instituteId)
            ->get()
            ->map(fn($e) => (object)['student_id' => $e->student_id]);

        if ($enrollments->isEmpty()) {
            return back()->with('error', 'No trainees enrolled in this batch.');
        }

        $exams = Exam::where('batch_id', $batch->id)->where('institute_id', $instituteId)->get();
        if ($exams->isEmpty()) {
            return back()->with('error', 'No exams found for this batch.');
        }

        $published = 0;
        foreach ($enrollments as $enrollment) {
            $studentId = $enrollment->student_id;

            // Correct calculation: sum full_marks from exams, sum marks_obtained from ExamResult
            $results = ExamResult::whereIn('exam_id', $exams->pluck('id'))
                ->where('student_id', $studentId)
                ->get();

            // Skip if no marks yet — keep existing result untouched
            if ($results->isEmpty()) {
                continue;
            }

            $fullMarks = 0;
            $totalMarks = 0;
            foreach ($exams as $exam) {
                $fullMarks += (float) $exam->full_marks;
                $totalMarks += (float) $results->where('exam_id', $exam->id)->sum('marks_obtained');
            }
            // Fallback if ExamResult has denormalized batch_id/full_marks (legacy attempts) — prefer exam-based totals
            if ($fullMarks <= 0) {
                $fullMarks = 1;
            }

            $percentage = round(($totalMarks / $fullMarks) * 100, 2);
            $status = $percentage >= 40 ? 'pass' : 'fail';

            TrainingBatchResult::updateOrCreate(
                ['batch_id' => $batch->id, 'student_id' => $studentId],
                [
                    'institute_id' => $instituteId,
                    'total_marks' => $fullMarks,
                    'obtained_marks' => $totalMarks,
                    'percentage' => $percentage,
                    'status' => $status,
                    'published_at' => now(),
                ]
            );
            $published++;
        }

        return redirect()->route('training.results.index')
            ->with('status', "Re-evaluated {$published} trainee(s).");
    }

    public function downloadMarksheet(Request $request, Batch $batch, $trainee)
    {
        $instituteId = (int) $request->user()->institute_id;
        abort_unless((int) $batch->institute_id === $instituteId, 403);

        $studentId = (int) $trainee;

        // Eager load batch relations
        $batch->loadMissing(['course', 'institute']);
        $student = \App\Models\Student::find($studentId);
        // Fallback to User for legacy data
        if (!$student) {
            $traineeUser = \App\Models\User::find($studentId);
            if ($traineeUser) {
                $student = \App\Models\Student::where('user_id', $studentId)->first();
            }
            if (!$student && isset($traineeUser) && $traineeUser) {
                $student = (object)[
                    'id' => $traineeUser->id,
                    'full_name' => trim(($traineeUser->first_name ?? '').' '.($traineeUser->last_name ?? '')) ?: ($traineeUser->name ?? 'Trainee #'.$studentId),
                    'name' => $traineeUser->name ?? null,
                    'first_name' => $traineeUser->first_name ?? '',
                    'last_name' => $traineeUser->last_name ?? '',
                    'student_id' => $traineeUser->id,
                    'student_id_number' => null,
                    'email' => $traineeUser->email ?? null,
                ];
            }
        }
        if (!$student) {
            return back()->with('error', 'Student not found.');
        }

        $institute = \App\Models\Institute::find($batch->institute_id) ?? $batch->institute ?? $request->user()->institute;

        $result = TrainingBatchResult::where('batch_id', $batch->id)
            ->where('student_id', $studentId)
            ->where('institute_id', $instituteId)
            ->first();

        if (!$result && \Illuminate\Support\Facades\Schema::hasColumn('training_batch_results', 'trainee_id')) {
            $result = TrainingBatchResult::where('batch_id', $batch->id)
                ->where('trainee_id', $studentId)
                ->where('institute_id', $instituteId)
                ->first();
        }

        if (!$result) {
            return back()->with('error', 'Result not yet published for this trainee.');
        }

        // Fetch exam results for this student in this batch (via exams belonging to batch)
        $exams = Exam::where('batch_id', $batch->id)->where('institute_id', $instituteId)->get();
        $examResults = \Illuminate\Support\Collection::make();
        if ($exams->isNotEmpty()) {
            $examResults = ExamResult::whereIn('exam_id', $exams->pluck('id'))
                ->where('student_id', $studentId)
                ->with('exam')
                ->get();
            if ($examResults->isEmpty()) {
                try {
                    $examResults = ExamResult::where('batch_id', $batch->id)
                        ->where('student_id', $studentId)
                        ->with('exam')
                        ->get();
                } catch (\Throwable $e) {}
            }
        }

        // Legacy support variables
        $studentModel = $student instanceof \App\Models\Student ? $student : \App\Models\Student::find($studentId);
        $traineeModel = \App\Models\User::find($studentId);
        $displayName = null;
        if ($student instanceof \App\Models\Student || (is_object($student) && isset($student->full_name))) {
            $displayName = is_object($student) && property_exists($student, 'full_name') ? $student->full_name : ($student->full_name ?? '');
            if (empty($displayName) && isset($student->name)) $displayName = $student->name;
            if (empty($displayName)) $displayName = trim(($student->first_name ?? '').' '.($student->last_name ?? ''));
        }
        if (empty($displayName)) {
            $displayName = $studentModel ? ($studentModel->full_name ?: trim(($studentModel->first_name ?? '').' '.($studentModel->last_name ?? '')) ?: 'Trainee #'.$studentId) : ($traineeModel ? trim(($traineeModel->first_name ?? '').' '.($traineeModel->last_name ?? '')) : 'Trainee #'.$studentId);
        }

        $course = $batch->course;
        $examDetails = [];
        $traineeId = $studentId;
        foreach ($exams as $exam) {
            $res = ExamResult::where('exam_id', $exam->id)->where('student_id', $studentId)->get();
            $obtained = $res->sum('marks_obtained');
            $examDetails[] = [
                'title' => $exam->title,
                'full_marks' => $exam->full_marks,
                'obtained' => $obtained,
                'subjects' => $exam->subjects->map(fn($s)=> $s->subject?->name ?? 'Subject')->implode(', '),
            ];
        }

        // Auto-detect orientation: if more than 5 exams, use landscape, else portrait
        $orientation = $examResults->count() > 5 ? 'landscape' : 'portrait';
        // Also fallback to examDetails count if examResults empty (legacy)
        if ($examResults->isEmpty() && count($examDetails) > 5) {
            $orientation = 'landscape';
        }

        $viewData = compact('batch', 'course', 'displayName', 'result', 'examDetails', 'traineeId', 'student', 'institute', 'examResults', 'orientation');

        // RAW preview — return HTML view for browser print preview (not readymade PDF)
        // The marksheet blade is styled for A4 print; browser's print dialog handles orientation.
        // We keep $orientation for @page hints but do not generate a PDF binary.
        return view('training.results.marksheet', $viewData);
    }
}
