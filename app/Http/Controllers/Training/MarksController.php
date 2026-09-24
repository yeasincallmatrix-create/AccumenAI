<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Training\TrainingCourse;
use App\Models\Training\TrainingExam;
use App\Models\Training\TrainingExamResult;
use App\Models\Training\TrainingEnrollment;
use App\Models\Training\TrainingSubject;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarksController extends Controller
{
    public function index(Request $request): View
    {
        $instituteId = (int) $request->user()->institute_id;
        $exams = TrainingExam::query()
            ->where('institute_id', $instituteId)
            ->with(['batch:id,name', 'course:id,name', 'results'])
            ->withCount('results')
            ->orderByDesc('id')
            ->get();
        $selectedExamId = $request->query('exam_id') ? (int) $request->query('exam_id') : ($exams->first()?->id);
        $trainees = collect();
        $selectedExam = null;
        $components = [];
        $subjects = collect();
        $selectedSubjectId = null;
        $isOverall = false;

        if ($selectedExamId) {
            $selectedExam = TrainingExam::with(['batch.enrollments.student'])->find($selectedExamId);
            if ($selectedExam && (int) $selectedExam->institute_id === $instituteId) {
                $components = $this->componentsFor($selectedExam);
                $subjects = $this->subjectsForExam($selectedExam, $instituteId);

                $reqSubject = $request->query('subject_id');
                $wantsOverall = $reqSubject === '0' || $reqSubject === '' || $reqSubject === 'all';

                if ($wantsOverall) {
                    $isOverall = $subjects->isNotEmpty();
                    $selectedSubjectId = null;
                } elseif ($reqSubject !== null && $reqSubject !== '') {
                    $candidate = (int) $reqSubject;
                    $selectedSubjectId = $subjects->contains('id', $candidate) ? $candidate : null;
                    if ($selectedSubjectId === null && $subjects->isNotEmpty()) {
                        $selectedSubjectId = (int) $subjects->first()->id;
                    }
                } elseif ($subjects->isNotEmpty()) {
                    $selectedSubjectId = (int) $subjects->first()->id;
                }

                $batchId = $selectedExam->batch_id;
                $students = TrainingEnrollment::where('batch_id', $batchId)
                    ->where('institute_id', $instituteId)
                    ->with('student')
                    ->get()
                    ->map(fn ($e) => $e->student)
                    ->filter();

                $passingMarks = (float) ($selectedExam->pass_marks ?? 0);
                $fullMarks = (float) ($selectedExam->full_marks ?? 0);

                if ($isOverall) {
                    $allResults = TrainingExamResult::where('exam_id', $selectedExamId)
                        ->whereNotNull('subject_id')
                        ->get();

                    $trainees = $students->map(function ($student) use ($allResults, $passingMarks, $fullMarks) {
                        $rows = $allResults->where('student_id', $student->id);
                        $obtained = null;
                        $status = null;
                        if ($rows->isNotEmpty()) {
                            $obtained = round($rows->avg('marks_obtained'), 2);
                            $status = $obtained >= $passingMarks ? 'pass' : 'fail';
                        }

                        return (object) [
                            'enrollment' => null,
                            'trainee' => $student,
                            'trainee_id' => $student->id,
                            'obtained' => $obtained,
                            'written' => null,
                            'practical' => null,
                            'viva' => null,
                            'result_status' => $status,
                            'max_total' => $fullMarks,
                            'is_overall' => true,
                        ];
                    });
                } else {
                    $examResultsQuery = TrainingExamResult::where('exam_id', $selectedExamId);
                    if ($selectedSubjectId !== null) {
                        $examResultsQuery->where('subject_id', $selectedSubjectId);
                    } else {
                        $examResultsQuery->whereNull('subject_id');
                    }
                    $examResults = $examResultsQuery->get()->keyBy('student_id');

                    $trainees = $students->map(function ($student) use ($examResults, $passingMarks, $fullMarks) {
                        $tid = $student->id;
                        $res = $examResults->get($tid);
                        $written = $res?->written_marks !== null ? (float) $res->written_marks : null;
                        $practical = $res?->practical_marks !== null ? (float) $res->practical_marks : null;
                        $viva = $res?->viva_marks !== null ? (float) $res->viva_marks : null;
                        $obtained = $res?->marks_obtained !== null ? (float) $res->marks_obtained : null;

                        if ($obtained === null && ($written !== null || $practical !== null || $viva !== null)) {
                            $obtained = round((float) ($written ?? 0) + (float) ($practical ?? 0) + (float) ($viva ?? 0), 2);
                        }

                        $status = $res?->result_status;
                        if ($status === null && $obtained !== null) {
                            $status = $obtained >= $passingMarks ? 'pass' : 'fail';
                        }

                        return (object) [
                            'enrollment' => null,
                            'trainee' => $student,
                            'trainee_id' => $tid,
                            'obtained' => $obtained,
                            'written' => $written,
                            'practical' => $practical,
                            'viva' => $viva,
                            'result_status' => $status,
                            'max_total' => $fullMarks,
                            'is_overall' => false,
                        ];
                    });
                }
            }
        }
        $examsPaginated = TrainingExam::query()->where('institute_id', $instituteId)->orderByDesc('id')->paginate(20);
        return view('training.marks.index', [
            'exams' => $exams,
            'examsPaginated' => $examsPaginated,
            'selectedExamId' => $selectedExamId,
            'selectedExam' => $selectedExam,
            'trainees' => $trainees,
            'components' => $components,
            'subjects' => $subjects,
            'selectedSubjectId' => $selectedSubjectId,
            'isOverall' => $isOverall,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'exam_id' => 'required|exists:training_exams,id',
            'subject_id' => 'nullable|integer|exists:training_subjects,id',
            'marks' => 'nullable|array',
            'marks.*' => 'nullable|numeric|min:0',
            'written' => 'nullable|array',
            'written.*' => 'nullable|numeric|min:0',
            'practical' => 'nullable|array',
            'practical.*' => 'nullable|numeric|min:0',
            'viva' => 'nullable|array',
            'viva.*' => 'nullable|numeric|min:0',
        ]);

        $instituteId = (int) $request->user()->institute_id;
        $exam = TrainingExam::findOrFail($request->exam_id);
        if ((int) $exam->institute_id !== $instituteId) {
            abort(403);
        }

        $subjectId = $request->input('subject_id') ? (int) $request->input('subject_id') : null;
        if ($subjectId !== null) {
            $subjectExists = TrainingSubject::where('institute_id', $instituteId)
                ->where('id', $subjectId)
                ->exists();
            if (! $subjectExists) {
                abort(403);
            }
        }

        $components = $this->componentsFor($exam);
        $usesComponents = $components['written']['active']
            || $components['practical']['active']
            || $components['viva']['active'];

        $traineeIds = $usesComponents
            ? array_unique(array_merge(
                array_keys($request->input('written', [])),
                array_keys($request->input('practical', [])),
                array_keys($request->input('viva', [])),
            ))
            : array_keys($request->input('marks', []));

        foreach ($traineeIds as $traineeId) {
            $written = $this->componentValue($request->input('written', [])[$traineeId] ?? null, $components['written']['max']);
            $practical = $this->componentValue($request->input('practical', [])[$traineeId] ?? null, $components['practical']['max']);
            $viva = $this->componentValue($request->input('viva', [])[$traineeId] ?? null, $components['viva']['max']);

            if ($usesComponents) {
                $hasAnyComponent = $written !== null || $practical !== null || $viva !== null;
                if (! $hasAnyComponent) {
                    continue;
                }
                $obtained = round((float) ($written ?? 0) + (float) ($practical ?? 0) + (float) ($viva ?? 0), 2);
            } else {
                $raw = $request->input("marks.{$traineeId}");
                if ($raw === null || $raw === '') {
                    continue;
                }
                $obtained = min((float) $raw, (float) ($exam->full_marks ?? PHP_FLOAT_MAX));
                $written = null;
                $practical = null;
                $viva = null;
            }

            $status = $obtained >= (float) ($exam->pass_marks ?? 0) ? 'pass' : 'fail';
            $grade = \App\Support\TrainingGpa::grade(
                $obtained,
                (float) ($exam->full_marks ?? 0),
                \App\Support\TrainingGpa::bands($instituteId)
            );

            TrainingExamResult::updateOrCreate(
                [
                    'exam_id' => $exam->id,
                    'student_id' => (int) $traineeId,
                    'subject_id' => $subjectId,
                ],
                [
                    'marks_obtained' => $obtained,
                    'written_marks' => $written,
                    'practical_marks' => $practical,
                    'viva_marks' => $viva,
                    'result_status' => $status,
                    'grade' => $grade,
                    'institute_id' => $instituteId,
                    'entered_by' => $request->user()->id,
                ]
            );
        }

        $redirect = ['exam_id' => $exam->id];
        if ($subjectId !== null) {
            $redirect['subject_id'] = $subjectId;
        }

        return redirect()
            ->route('training.marks.index', $redirect)
            ->with('status', 'Marks saved for exam: '.$exam->title);
    }

    private function subjectsForExam(TrainingExam $exam, int $instituteId)
    {
        $courseId = $exam->course_id ?: $exam->batch?->course_id;
        if ($courseId) {
            $courseSubjects = TrainingCourse::find($courseId)?->subjects()
                ->where('training_subjects.status', 'active')
                ->orderBy('training_subjects.name')
                ->get();

            if ($courseSubjects !== null && $courseSubjects->isNotEmpty()) {
                return $courseSubjects;
            }
        }

        return TrainingSubject::where('institute_id', $instituteId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    private function componentsFor(TrainingExam $exam): array
    {
        $full = (float) ($exam->full_marks ?? 0);
        $writtenPct = max(0, min(100, (float) ($exam->written_percent ?? 0)));
        $practicalPct = max(0, min(100, (float) ($exam->practical_percent ?? 0)));
        $vivaPct = max(0, min(100, (float) ($exam->viva_percent ?? 0)));

        $anyActive = $writtenPct > 0 || $practicalPct > 0 || $vivaPct > 0;

        return [
            'active' => $anyActive,
            'full_marks' => $full,
            'pass_marks' => (float) ($exam->pass_marks ?? 0),
            'written' => ['active' => $writtenPct > 0, 'percent' => $writtenPct, 'max' => $full],
            'practical' => ['active' => $practicalPct > 0, 'percent' => $practicalPct, 'max' => $full],
            'viva' => ['active' => $vivaPct > 0, 'percent' => $vivaPct, 'max' => $full],
        ];
    }

    private function componentValue(mixed $value, float $max): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $num = (float) $value;
        if ($max > 0) {
            $num = min($num, $max);
        }
        return max(0, $num);
    }
}
