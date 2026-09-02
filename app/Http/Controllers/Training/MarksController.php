<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MarksController extends Controller
{
    public function index(Request $request): View
    {
        $instituteId = (int) $request->user()->institute_id;
        $exams = Exam::query()
            ->where('institute_id', $instituteId)
            ->with(['batch:id,name', 'course:id,name', 'results'])
            ->withCount('results')
            ->orderByDesc('id')
            ->get();
        $selectedExamId = $request->query('exam_id') ? (int) $request->query('exam_id') : ($exams->first()?->id);
        $trainees = collect();
        $selectedExam = null;
        if ($selectedExamId) {
            $selectedExam = Exam::with(['batch.enrollments.student'])->find($selectedExamId);
            if ($selectedExam && (int) $selectedExam->institute_id === $instituteId) {
                $batchId = $selectedExam->batch_id;
                $students = \App\Models\Training\Enrollment::where('batch_id', $batchId)
                    ->where('institute_id', $instituteId)
                    ->with('student')
                    ->get()
                    ->map(fn($e) => $e->student)
                    ->filter();
                // Fetch existing exam results (per subject) and aggregate by student
                $examResults = \App\Models\ExamResult::where('exam_id', $selectedExamId)
                    ->select('student_id', DB::raw('SUM(marks_obtained) as obtained_marks'))
                    ->groupBy('student_id')
                    ->get()
                    ->keyBy('student_id');

                $examForMarks = \App\Models\Exam::find($selectedExamId);
                $passingMarks = $examForMarks->pass_marks ?? 0;

                $results = $examResults->map(function ($item) use ($passingMarks) {
                    $item->result_status = ($item->obtained_marks >= $passingMarks) ? 'pass' : 'fail';
                    return $item;
                });
                $trainees = $students->map(function ($student) use ($results) {
                    $tid = $student->id;
                    $res = $results->get($tid);
                    return (object) [
                        'enrollment' => null,
                        'trainee' => $student,
                        'trainee_id' => $tid,
                        'obtained' => $res?->obtained_marks,
                        'result_status' => $res?->result_status,
                    ];
                });
            }
        }
        // For pagination compatibility, wrap exams in paginator if needed
        $examsPaginated = Exam::query()->where('institute_id', $instituteId)->orderByDesc('id')->paginate(20);
        return view('training.marks.index', ['exams' => $exams, 'examsPaginated' => $examsPaginated, 'selectedExamId' => $selectedExamId, 'selectedExam' => $selectedExam, 'trainees' => $trainees]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'marks' => 'required|array',
            'marks.*' => 'nullable|numeric|min:0',
        ]);
        $instituteId = (int) $request->user()->institute_id;
        $exam = Exam::findOrFail($request->exam_id);
        if ((int) $exam->institute_id !== $instituteId) abort(403);
        foreach ($request->marks as $traineeId => $obtained) {
            if ($obtained === null || $obtained === '') continue;
            $obtained = (float) $obtained;
            $status = $obtained >= (float) $exam->pass_marks ? 'pass' : 'fail';
            \App\Models\Result::updateOrCreate(
                ['exam_id' => $exam->id, 'student_id' => $traineeId, 'batch_id' => $exam->batch_id],
                ['obtained_marks' => $obtained, 'total_marks' => $exam->full_marks, 'result_status' => $status, 'institute_id' => $instituteId]
            );
        }
        return redirect()->route('training.marks.index', ['exam_id' => $exam->id])->with('status', 'Marks saved for exam: '.$exam->title);
    }
}
