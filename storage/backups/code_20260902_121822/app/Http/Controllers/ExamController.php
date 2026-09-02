<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Result;
use App\Models\Student;
use App\Support\TenantContext;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExamController extends Controller
{
    private const STATUSES = ['scheduled', 'ongoing', 'completed', 'cancelled'];

    private const EXAMS_COLUMNS = ['serial', 'title', 'course', 'batch', 'subjects', 'date', 'marks', 'students', 'pass', 'fail', 'status', 'action'];

    private const RESULTS_COLUMNS = ['serial', 'student', 'student_id', 'batch', 'total_marks', 'obtained', 'percentage', 'grade', 'status', 'published_at'];

    public function index(Request $request): View
    {
        $activeTab = $request->query('tab', 'exams');
        if (! in_array($activeTab, ['exams', 'results'], true)) {
            $activeTab = 'exams';
        }

        $q = trim((string) $request->query('q'));
        $batchId = $request->query('batch_id');
        $status = $request->query('status');
        $resultBatchId = $request->query('result_batch_id');
        $resultStatus = $request->query('result_status');

        $examQuery = Exam::query()
            ->with(['batch:id,name,batch_code', 'course:id,name', 'subjects.subject:id,name'])
            ->withCount('results')
            ->when($q !== '', fn (Builder $query) => $query->where('title', 'like', "%{$q}%"))
            ->when($batchId, fn (Builder $query) => $query->where('batch_id', (int) $batchId))
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->latest('id');

        $exams = (clone $examQuery)->paginate(20)->withQueryString();

        // Attach student-centric pass/fail counts for print view
        $this->attachStudentCentricCounts($exams);

        $visibleExamColumns = $request->user()->preference('columns_exams', self::EXAMS_COLUMNS);
        $visibleExamColumns = array_values(array_intersect(self::EXAMS_COLUMNS, (array) $visibleExamColumns));

        $resultQuery = Result::query()
            ->with(['student:id,first_name,last_name,student_id_number', 'batch:id,name,batch_code', 'course:id,name'])
            ->withCount('certificate')
            ->when($rq = trim((string) $request->query('rq')), function (Builder $query) use ($rq) {
                $query->whereHas('student', fn (Builder $w) => $w->where('first_name', 'like', "%{$rq}%")
                    ->orWhere('last_name', 'like', "%{$rq}%")
                    ->orWhere('student_id_number', 'like', "%{$rq}%"));
            })
            ->when($resultBatchId, fn (Builder $query) => $query->where('batch_id', (int) $resultBatchId))
            ->when($resultStatus, fn (Builder $query) => $query->where('result_status', $resultStatus))
            ->latest('id');

        $results = (clone $resultQuery)->paginate(20)->withQueryString();

        $visibleResultColumns = $request->user()->preference('columns_exam_results', self::RESULTS_COLUMNS);
        $visibleResultColumns = array_values(array_intersect(self::RESULTS_COLUMNS, (array) $visibleResultColumns));

        $batches = Batch::query()
            ->orderBy('name')
            ->with(['course.subjects:id,name'])
            ->get(['id', 'name', 'batch_code', 'institute_id', 'course_id']);

        return view('exams.index', [
            'activeTab' => $activeTab,
            'exams' => $exams,
            'visibleExamColumns' => $visibleExamColumns,
            'results' => $results,
            'visibleResultColumns' => $visibleResultColumns,
            
            'q' => $q,
            'rq' => $request->query('rq'),
            'batchId' => $batchId,
            'status' => $status,
            'resultBatchId' => $resultBatchId,
            'resultStatus' => $resultStatus,
            'batches' => $batches,
            'sendExamSubjects' => $batches
                ->mapWithKeys(fn (Batch $batch) => [
                    $batch->id => $batch->course?->subjects?->map(fn ($subject) => [
                        'id' => $subject->id,
                        'name' => $subject->name,
                    ])->unique('id')->values()->all() ?? [],
                ])
                ->all(),
        ]);
    }

    /**
     * Attach student-centric pass_count / fail_count to each exam in a paginator.
     */
    private function attachStudentCentricCounts($exams): void
    {
        $examIds = $exams->pluck('id')->all();
        if (empty($examIds)) {
            return;
        }

        $examsMap = Exam::query()
            ->with([
                'subjects:id,exam_id,subject_id',
                'results' => fn ($q) => $q->select('id', 'exam_id', 'student_id', 'subject_id', 'result_status'),
                'batch.enrollments' => fn ($q) => $q->select('id', 'batch_id', 'student_id'),
            ])
            ->whereIn('id', $examIds)
            ->get()
            ->keyBy('id');

        foreach ($exams as $exam) {
            $fresh = $examsMap->get($exam->id);
            if (! $fresh) {
                $exam->pass_count = 0;
                $exam->fail_count = 0;
                continue;
            }

            $subjectIds = $fresh->subjects->pluck('subject_id')->filter()->values()->all();
            $resultsByStudent = $fresh->results->groupBy('student_id');
            $studentIds = $fresh->batch?->enrollments?->pluck('student_id')->values()->all() ?? [];

            $passCount = 0;
            $failCount = 0;

            foreach ($studentIds as $studentId) {
                $studentResults = $resultsByStudent->get($studentId, collect());
                $allPassed = true;

                foreach ($subjectIds as $sid) {
                    $r = $studentResults->firstWhere('subject_id', $sid);
                    if (! $r || $r->result_status !== 'pass') {
                        $allPassed = false;
                        break;
                    }
                }

                if ($allPassed && count($subjectIds) > 0) {
                    $passCount++;
                } else {
                    $failCount++;
                }
            }

            $exam->pass_count = $passCount;
            $exam->fail_count = $failCount;
            $exam->students_count = count($studentIds);
        }
    }

    public function sendToExam(Request $request, Batch $batch): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'title' => [
                'required', 'string', 'max:150',
                Rule::unique('exams', 'title')->where(fn ($q) => $q->where('institute_id', $batch->institute_id)),
            ],
            'exam_date' => ['sometimes', 'nullable', 'date'],
            'subject_dates' => ['nullable', 'array'],
            'subject_dates.*' => ['nullable', 'date'],
            'subjects' => ['required', 'array', 'min:1'],
            'subjects.*' => ['integer', 'distinct'],
            'marks' => ['required', 'array'],
            'marks.*.written' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1000000'],
            'marks.*.practical' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1000000'],
            'marks.*.viva' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1000000'],
            'marks.*.attendance' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1000000'],
            'marks.*.other' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1000000'],
            'pass_marks' => ['nullable', 'array'],
            'pass_marks.*' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
        ], [
            'title.unique' => mawa_lang('exams.title_unique'),
        ]);

        $subjectIds = collect($data['subjects'])
            ->filter(fn ($id) => isset($data['marks'][$id]))
            ->values();

        $totalMarks = $subjectIds->sum(fn ($id) => (float) ($data['marks'][$id]['written'] ?? 0)
            + (float) ($data['marks'][$id]['practical'] ?? 0)
            + (float) ($data['marks'][$id]['viva'] ?? 0)
            + (float) ($data['marks'][$id]['attendance'] ?? 0)
            + (float) ($data['marks'][$id]['other'] ?? 0));

        if ($subjectIds->isEmpty() || $totalMarks <= 0) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => mawa_lang('exams.marks_required'),
                    'errors' => ['marks' => [mawa_lang('exams.marks_required')]],
                ], 422);
            }

            return back()->withErrors(['marks' => mawa_lang('exams.marks_required')]);
        }

        $examDate = isset($data['exam_date']) && $data['exam_date'] !== '' && $data['exam_date'] !== null
            ? Carbon::parse($data['exam_date'])->format('Y-m-d H:i:s')
            : now();

        $exam = Exam::create([
            'institute_id' => $batch->institute_id,
            'course_id' => $batch->course_id,
            'batch_id' => $batch->id,
            'title' => $data['title'],
            'exam_date' => $examDate,
            'full_marks' => $totalMarks,
            'pass_marks' => round($totalMarks * 0.4, 2),
            'written_percent' => 0,
            'practical_percent' => 0,
            'viva_percent' => 0,
            'status' => 'scheduled',
            'created_by' => $request->user() instanceof InstituteUser ? $request->user()->id : null,
        ]);

        foreach ($subjectIds as $id) {
            $subjectTotal = (float) ($data['marks'][$id]['practical'] ?? 0)
                + (float) ($data['marks'][$id]['viva'] ?? 0);
            $subjectPass = $data['pass_marks'][$id] ?? null;
            $subjectPass = ($subjectPass !== null && $subjectPass !== '')
                ? round((float) $subjectPass, 2)
                : round($subjectTotal * 0.4, 2);

            $exam->subjects()->create([
                'subject_id' => $id,
                'written_marks' => (float) ($data['marks'][$id]['written'] ?? 0),
                'practical_marks' => (float) ($data['marks'][$id]['practical'] ?? 0),
                'viva_marks' => (float) ($data['marks'][$id]['viva'] ?? 0),
                'attendance_marks' => (float) ($data['marks'][$id]['attendance'] ?? 0),
                'other_marks' => (float) ($data['marks'][$id]['other'] ?? 0),
                'pass_marks' => $subjectPass,
                'exam_date' => isset($data['subject_dates'][$id]) && $data['subject_dates'][$id] !== '' && $data['subject_dates'][$id] !== null
                    ? Carbon::parse($data['subject_dates'][$id])->format('Y-m-d H:i:s')
                    : $examDate,
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => mawa_lang('exams.created'),
                'data' => ['id' => $exam->id, 'url' => route('exams.show', $exam)],
            ]);
        }

        return redirect()->route('exams.show', $exam)->with('status', mawa_lang('exams.created'));
    }

    public function show(Exam $exam): View
    {
        if ((int) $exam->institute_id !== (int) TenantContext::id()) {
            abort(403, 'Unauthorized access to this exam.');
        }

        $exam->load([
            'batch:id,name,batch_code',
            'course:id,name',
            'subjects.subject:id,name',
            'subjects.components',
            'results.student:id,first_name,last_name,student_id,student_id_number,phone',
            'batch.enrollments.student:id,first_name,last_name,student_id,student_id_number,phone',
        ]);

        $students = $exam->batch->enrollments
            ->sortBy(fn ($enrollment) => (string) $enrollment->roll_no)
            ->values();

        $results = $exam->results->keyBy(fn ($result) => $result->student_id.'-'.$result->subject_id);

        // Student-centric pass/fail: a student passes only if ALL subjects have 'pass'
        $subjectIds = $exam->subjects->pluck('subject_id')->filter()->values()->all();
        $resultsByStudent = $exam->results->groupBy('student_id');
        $passCount = 0;
        $failCount = 0;
        foreach ($students as $enrollment) {
            $studentResults = $resultsByStudent->get($enrollment->student_id, collect());
            $allPassed = true;
            foreach ($subjectIds as $sid) {
                $r = $studentResults->firstWhere('subject_id', $sid);
                if (! $r || $r->result_status !== 'pass') {
                    $allPassed = false;
                    break;
                }
            }
            if ($allPassed && count($subjectIds) > 0) {
                $passCount++;
            } else {
                $failCount++;
            }
        }

        return view('exams.show', [
            'exam' => $exam,
            'students' => $students,
            'results' => $results,
            'passCount' => $passCount,
            'failCount' => $failCount,
        ]);
    }

    public function update(Request $request, Exam $exam): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'exam_date' => ['required', 'date'],
            'full_marks' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'pass_marks' => ['required', 'numeric', 'min:0', 'lte:full_marks'],
            'written_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'practical_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'viva_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'components' => ['nullable', 'array'],
            'components.*' => ['array'],
            'components.*.*.id' => ['nullable', 'integer', 'exists:exam_subject_components,id'],
            'components.*.*.component_name' => ['required_with:components.*.*.max_marks', 'string', 'max:80'],
            'components.*.*.max_marks' => ['required_with:components.*.*.component_name', 'numeric', 'min:0', 'max:10000'],
            'components.*.*.weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'components.*.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);

        $data['exam_date'] = Carbon::parse($data['exam_date'])->format('Y-m-d H:i:s');

        $examData = collect($data)->except('components')->all();
        $exam->update($examData);

        // Per-subject component sync
        if ($request->has('components')) {
            $componentsInput = $request->input('components', []);
            $exam->load('subjects');
            foreach ($exam->subjects as $examSubject) {
                $subjectId = $examSubject->id;
                $rows = $componentsInput[$subjectId] ?? $componentsInput[(string)$subjectId] ?? null;
                if ($rows === null) {
                    // No data for this subject — skip (do not delete)
                    continue;
                }
                $keepIds = [];
                foreach ($rows as $idx => $row) {
                    if (empty($row['component_name']) && empty($row['max_marks'])) continue;
                    $payload = [
                        'component_name' => trim($row['component_name']),
                        'max_marks' => round((float)($row['max_marks'] ?? 0), 2),
                        'weight' => $row['weight'] !== null && $row['weight'] !== '' ? round((float)$row['weight'], 2) : null,
                        'sort_order' => isset($row['sort_order']) ? (int)$row['sort_order'] : $idx,
                        'is_active' => true,
                    ];
                    if (!empty($row['id'])) {
                        $comp = \App\Models\ExamSubjectComponent::where('id', $row['id'])->where('exam_subject_id', $examSubject->id)->first();
                        if ($comp) {
                            $comp->update($payload);
                            $keepIds[] = $comp->id;
                        }
                    } else {
                        $comp = $examSubject->components()->create($payload);
                        $keepIds[] = $comp->id;
                    }
                }
                // Delete removed components
                if (!empty($keepIds)) {
                    \App\Models\ExamSubjectComponent::where('exam_subject_id', $examSubject->id)->whereNotIn('id', $keepIds)->delete();
                } else {
                    // If all rows were empty, keep existing (do not wipe)
                }
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => mawa_lang('exams.updated'),
                'data' => ['id' => $exam->id],
            ]);
        }

        return redirect()->route('exams.show', $exam)->with('status', mawa_lang('exams.updated'));
    }

    public function saveMarks(Request $request, Exam $exam): RedirectResponse|JsonResponse
    {
        // Legacy exams (created before subject support) post a plain marks array.
        if ($exam->subjects->isEmpty() && $request->has('marks')) {
            return $this->saveLegacyMarks($request, $exam);
        }

        $data = $request->validate([
            'written' => ['nullable', 'array'],
            'written.*.*' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'practical' => ['nullable', 'array'],
            'practical.*.*' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'viva' => ['nullable', 'array'],
            'viva.*.*' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'attendance' => ['nullable', 'array'],
            'attendance.*.*' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'other' => ['nullable', 'array'],
            'other.*.*' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'remarks' => ['nullable', 'array'],
            'remarks.*.*' => ['nullable', 'string', 'max:255'],
            'pass_marks' => ['nullable', 'array'],
            'pass_marks.*' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'component_marks' => ['nullable', 'array'],
            'component_marks.*' => ['array'],
            'component_marks.*.*' => ['array'],
            'component_marks.*.*.*' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ]);

        $passMarks = (float) $exam->pass_marks;

        $examSubjects = $exam->subjects->pluck('id')->all();
        $students = $exam->batch->enrollments->pluck('student_id')->all();

        $subjectPassMarks = [];
        foreach ($exam->subjects as $subject) {
            $subjectPassMarks[$subject->id] = $subject->pass_marks !== null
                ? (float) $subject->pass_marks
                : $passMarks;
        }

        $exam->loadMissing('subjects.components');
        // FIX 3: Student-Institute verification - ensure submitted students belong to same institute
        $submittedStudentIds = collect();
        foreach (['written', 'practical', 'viva', 'attendance', 'other', 'remarks'] as $field) {
            foreach (($data[$field] ?? []) as $group) {
                if (is_array($group)) {
                    foreach (array_keys($group) as $sid) {
                        $submittedStudentIds->push($sid);
                    }
                }
            }
        }
        if (!empty($data['component_marks'])) {
            foreach ($data['component_marks'] as $subjectComponents) {
                if (!is_array($subjectComponents)) continue;
                foreach ($subjectComponents as $componentMarks) {
                    if (!is_array($componentMarks)) continue;
                    foreach (array_keys($componentMarks) as $sid) {
                        $submittedStudentIds->push($sid);
                    }
                }
            }
        }
        $submittedStudentIds = $submittedStudentIds->map(fn ($v) => (int) $v)->unique()->filter()->values()->all();
        if (! empty($submittedStudentIds)) {
            $currentInstituteId = TenantContext::id() !== null ? (int) TenantContext::id() : (int) $exam->institute_id;
            $validStudents = Student::whereIn('id', $submittedStudentIds)
                ->where('institute_id', $currentInstituteId)
                ->pluck('id')
                ->map(fn ($v) => (int) $v)
                ->all();
            if (count($validStudents) !== count($submittedStudentIds) || array_diff($submittedStudentIds, $validStudents)) {
                if ($request->expectsJson()) {
                    return response()->json(['success' => false, 'message' => 'Invalid student data - institute mismatch.', 'errors' => ['students' => ['Invalid student data.']]], 422);
                }
                return back()->withErrors(['students' => 'Invalid student data - students must belong to your institute.']);
            }
        }

        DB::transaction(function () use ($request, $exam, $data, $passMarks, $students, $subjectPassMarks) {
            // subjects present in the submitted payload (including dynamic components)
            $submittedSubjects = array_unique(array_merge(
                array_keys($data['written'] ?? []),
                array_keys($data['practical'] ?? []),
                array_keys($data['viva'] ?? []),
                array_keys($data['attendance'] ?? []),
                array_keys($data['other'] ?? []),
                array_keys($data['pass_marks'] ?? []),
                array_keys($data['remarks'] ?? []),
                array_keys($data['component_marks'] ?? [])
            ));

            foreach ($submittedSubjects as $subjectId) {
                if (isset($data['pass_marks'][$subjectId]) && $data['pass_marks'][$subjectId] !== null && $data['pass_marks'][$subjectId] !== '') {
                    $subjectPassMarks[$subjectId] = (float) $data['pass_marks'][$subjectId];
                    $exam->subjects()->where('id', $subjectId)->update([
                        'pass_marks' => round((float) $data['pass_marks'][$subjectId], 2),
                    ]);
                }

                $threshold = (float) ($subjectPassMarks[$subjectId] ?? $passMarks);
                $examSubject = $exam->subjects->firstWhere('id', $subjectId);
                $hasComponents = $examSubject && $examSubject->components && $examSubject->components->isNotEmpty();
                $componentMarksForSubject = $data['component_marks'][$subjectId] ?? $data['component_marks'][(string)$subjectId] ?? null;

                foreach ($students as $studentId) {
                    if ($hasComponents && $componentMarksForSubject) {
                        $componentScores = [];
                        $componentTotal = 0;
                        $hasAny = false;
                        foreach ($examSubject->components as $comp) {
                            $val = $componentMarksForSubject[$comp->id][$studentId] ?? $componentMarksForSubject[(string)$comp->id][$studentId] ?? null;
                            if ($val !== null && $val !== '') {
                                $floatVal = (float) $val;
                                $componentScores[$comp->id] = $floatVal;
                                $componentTotal += $floatVal;
                                $hasAny = true;
                            } else {
                                $componentScores[$comp->id] = null;
                            }
                        }
                        if (!$hasAny) {
                            ExamResult::where('exam_id', $exam->id)
                                ->where('student_id', $studentId)
                                ->where('subject_id', $subjectId)
                                ->delete();
                            continue;
                        }
                        $total = $componentTotal;
                        $storeScores = array_filter($componentScores, fn($v) => $v !== null);
                        ExamResult::updateOrCreate(
                            ['exam_id' => $exam->id, 'student_id' => (int) $studentId, 'subject_id' => (int) $subjectId],
                            [
                                'institute_id' => $exam->institute_id,
                                'marks_obtained' => $total,
                                'written_marks' => null,
                                'practical_marks' => null,
                                'viva_marks' => null,
                                'attendance_marks' => null,
                                'other_marks' => null,
                                'component_marks' => !empty($storeScores) ? json_encode($storeScores) : null,
                                'result_status' => $total >= $threshold ? 'pass' : 'fail',
                                'remarks' => $data['remarks'][$subjectId][$studentId] ?? $data['remarks'][(string)$subjectId][$studentId] ?? null,
                                'entered_by' => $request->user() instanceof InstituteUser ? $request->user()->id : null,
                            ]
                        );
                        continue;
                    }
                    $written = isset($data['written'][$subjectId][$studentId]) && $data['written'][$subjectId][$studentId] !== '' && $data['written'][$subjectId][$studentId] !== null
                        ? (float) $data['written'][$subjectId][$studentId]
                        : null;
                    $practical = isset($data['practical'][$subjectId][$studentId]) && $data['practical'][$subjectId][$studentId] !== '' && $data['practical'][$subjectId][$studentId] !== null
                        ? (float) $data['practical'][$subjectId][$studentId]
                        : null;
                    $viva = isset($data['viva'][$subjectId][$studentId]) && $data['viva'][$subjectId][$studentId] !== '' && $data['viva'][$subjectId][$studentId] !== null
                        ? (float) $data['viva'][$subjectId][$studentId]
                        : null;
                    $attendance = isset($data['attendance'][$subjectId][$studentId]) && $data['attendance'][$subjectId][$studentId] !== '' && $data['attendance'][$subjectId][$studentId] !== null
                        ? (float) $data['attendance'][$subjectId][$studentId]
                        : null;
                    $other = isset($data['other'][$subjectId][$studentId]) && $data['other'][$subjectId][$studentId] !== '' && $data['other'][$subjectId][$studentId] !== null
                        ? (float) $data['other'][$subjectId][$studentId]
                        : null;

                    if ($written === null && $practical === null && $viva === null && $attendance === null && $other === null) {
                        ExamResult::where('exam_id', $exam->id)
                            ->where('student_id', $studentId)
                            ->where('subject_id', $subjectId)
                            ->delete();

                        continue;
                    }

                    $total = (float) ($written ?? 0) + (float) ($practical ?? 0) + (float) ($viva ?? 0) + (float) ($attendance ?? 0) + (float) ($other ?? 0);

                    ExamResult::updateOrCreate(
                        ['exam_id' => $exam->id, 'student_id' => (int) $studentId, 'subject_id' => (int) $subjectId],
                        [
                            'institute_id' => $exam->institute_id,
                            'marks_obtained' => $total,
                            'written_marks' => $written,
                            'practical_marks' => $practical,
                            'viva_marks' => $viva,
                            'attendance_marks' => $attendance,
                            'other_marks' => $other,
                            'component_marks' => null,
                            'result_status' => $total >= $threshold ? 'pass' : 'fail',
                            'remarks' => $data['remarks'][$subjectId][$studentId] ?? null,
                            'entered_by' => $request->user() instanceof InstituteUser ? $request->user()->id : null,
                        ]
                    );
                }
            }
        });

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => mawa_lang('exams.marks_saved'),
                'data' => ['id' => $exam->id],
            ]);
        }

        return redirect()->route('exams.show', $exam)->with('status', mawa_lang('exams.marks_saved'));
    }

    private function saveLegacyMarks(Request $request, Exam $exam): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'marks' => ['required', 'array'],
            'marks.*' => ['nullable', 'numeric', 'min:0', 'max:'.(float) $exam->full_marks],
            'remarks.*' => ['nullable', 'string', 'max:255'],
        ]);

        // FIX 3 (legacy): Verify students belong to same institute
        $legacyStudentIds = array_map('intval', array_keys($data['marks'] ?? []));
        $legacyStudentIds = array_values(array_unique(array_filter($legacyStudentIds)));
        if (! empty($legacyStudentIds)) {
            $currentInstituteId = TenantContext::id() !== null ? (int) TenantContext::id() : (int) $exam->institute_id;
            $validLegacy = Student::whereIn('id', $legacyStudentIds)
                ->where('institute_id', $currentInstituteId)
                ->pluck('id')
                ->map(fn ($v) => (int) $v)
                ->all();
            if (count($validLegacy) !== count($legacyStudentIds) || array_diff($legacyStudentIds, $validLegacy)) {
                if ($request->expectsJson()) {
                    return response()->json(['success' => false, 'message' => 'Invalid student data - institute mismatch.', 'errors' => ['marks' => ['Invalid student data.']]], 422);
                }
                return back()->withErrors(['marks' => 'Invalid student data - students must belong to your institute.']);
            }
        }

        DB::transaction(function () use ($request, $exam, $data) {
            foreach ($data['marks'] as $studentId => $marks) {
                if ($marks === null || $marks === '') {
                    ExamResult::where('exam_id', $exam->id)->where('student_id', $studentId)->delete();

                    continue;
                }

                $pass = (float) $exam->pass_marks;

                ExamResult::updateOrCreate(
                    ['exam_id' => $exam->id, 'student_id' => (int) $studentId, 'subject_id' => null],
                    [
                        'institute_id' => $exam->institute_id,
                        'marks_obtained' => (float) $marks,
                        'result_status' => (float) $marks >= $pass ? 'pass' : 'fail',
                        'remarks' => $data['remarks'][$studentId] ?? null,
                        'entered_by' => $request->user() instanceof InstituteUser ? $request->user()->id : null,
                    ]
                );
            }
        });

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => mawa_lang('exams.marks_saved'),
                'data' => ['id' => $exam->id],
            ]);
        }

        return redirect()->route('exams.show', $exam)->with('status', mawa_lang('exams.marks_saved'));
    }

    public function destroy(Request $request, Exam $exam): RedirectResponse|JsonResponse
    {
        $instituteId = (int) $request->user()->institute_id;
        if ((int) $exam->institute_id !== $instituteId) {
            abort(403);
        }

        $hasResults = ExamResult::where('exam_id', $exam->id)->exists();
        if ($hasResults) {
            $msg = 'Cannot delete exam because it has student results. Delete the results first or archive the exam.';
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return redirect()->back()->with('error', $msg);
        }

        DB::transaction(function () use ($exam) {
            $exam->subjects()->delete();
            // Use forceDelete for permanent deletion (Exam has no SoftDeletes, delete is hard)
            if (method_exists($exam, 'forceDelete')) {
                $exam->forceDelete();
            } else {
                $exam->delete();
            }
        });

        $msg = 'Exam deleted successfully.';
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'data' => ['id' => $exam->id],
            ]);
        }

        return redirect()->route('exams.index')->with('success', $msg);
    }
}
