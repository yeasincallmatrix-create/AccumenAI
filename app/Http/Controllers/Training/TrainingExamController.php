<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Training\TrainingBatch;
use App\Models\Training\TrainingEnrollment;
use App\Models\Training\TrainingExam;
use App\Models\Training\TrainingExamResult;
use App\Models\TrainingBatchResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrainingExamController extends Controller
{
    public function index(Request $request): View
    {
        $instituteId = (int) $request->user()->institute_id;

        $exams = TrainingExam::where('institute_id', $instituteId)
            ->with(['batch.course'])
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        $batches = TrainingBatch::where('institute_id', $instituteId)
            ->whereIn('status', ['upcoming', 'ongoing', 'running'])
            ->orderBy('name')
            ->get();

        $gpaModel = $this->gpaModel($request->user());

        return view('training.exams.index', compact('exams', 'batches', 'gpaModel'));
    }

    public function saveGpaModel(Request $request)
    {
        $validated = $request->validate([
            'rows' => 'required|array|min:1',
            'rows.*.grade' => 'required|string|max:20',
            'rows.*.min_score' => 'required|numeric|min:0|max:99999999',
            'rows.*.max_score' => 'required|numeric|min:0|max:99999999|gte:rows.*.min_score',
        ]);

        $rows = [];
        foreach ($validated['rows'] as $index => $row) {
            $rows[] = [
                'grade' => trim((string) $row['grade']),
                'min_score' => (float) $row['min_score'],
                'max_score' => (float) $row['max_score'],
                'display_order' => $index,
            ];
        }

        $user = $request->user();
        $instituteId = (int) $user->institute_id;
        $settings = \App\Models\InstituteSetting::where('institute_id', $instituteId)->first();
        $config = is_array($settings?->training_config) ? $settings->training_config : [];
        $config['gpa_model'] = $rows;

        if ($settings) {
            $settings->update(['training_config' => $config]);
        } else {
            \App\Models\InstituteSetting::create([
                'institute_id' => $instituteId,
                'training_config' => $config,
            ]);
        }

        $tab = in_array($request->input('tab'), ['exams', 'results', 'certificates'], true)
            ? $request->input('tab')
            : 'exams';

        return redirect()
            ->route('training.exams.index', ['tab' => $tab])
            ->with('success', 'GPA model updated.');
    }

    private function gpaModel($user): array
    {
        return \App\Support\TrainingGpa::bands((int) $user->institute_id);
    }

    public function create(Request $request): View
    {
        $instituteId = (int) $request->user()->institute_id;

        $batches = TrainingBatch::where('institute_id', $instituteId)
            ->whereIn('status', ['upcoming', 'ongoing', 'running'])
            ->orderBy('name')
            ->get(['id', 'name', 'batch_code', 'course_id']);

        $batchId = $request->query('batch_id');
        $selectedBatch = $batchId
            ? $batches->firstWhere('id', (int) $batchId)
            : null;

        return view('training.exams.create', compact('batches', 'selectedBatch'));
    }

    public function store(Request $request)
    {
        $instituteId = (int) $request->user()->institute_id;
        $validated = $request->validate([
            'title' => [
                'required',
                'string',
                'max:200',
                \Illuminate\Validation\Rule::unique('training_exams', 'title')
                    ->where('institute_id', $instituteId)
                    ->whereNull('deleted_at'),
            ],
            'batch_id' => [
                'required',
                'integer',
                \Illuminate\Validation\Rule::exists('training_batches', 'id')
                    ->where('institute_id', $instituteId)
                    ->whereNull('deleted_at'),
            ],
            'exam_date' => 'nullable|date',
            'full_marks' => 'required|numeric|min:0.01|max:99999999',
            'pass_marks' => 'required|numeric|min:0|max:99999999|lte:full_marks',
            'written_percent' => 'nullable|numeric|min:0|max:100',
            'practical_percent' => 'nullable|numeric|min:0|max:100',
            'viva_percent' => 'nullable|numeric|min:0|max:100',
            'weight_percent' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|string|in:scheduled,ongoing,completed,cancelled',
        ]);

        $batch = TrainingBatch::where('institute_id', $instituteId)
            ->findOrFail($validated['batch_id']);

        $validated['institute_id'] = $instituteId;
        $validated['course_id'] = $batch->course_id;
        $validated['status'] = $validated['status'] ?? 'scheduled';
        $validated['created_by'] = $request->user()->id;
        if (empty($validated['exam_date'])) {
            unset($validated['exam_date']);
        }

        $exam = TrainingExam::create($validated);

        if ($request->boolean('from_modal')) {
            $tab = in_array($request->input('tab'), ['exams', 'results', 'certificates'], true)
                ? $request->input('tab')
                : 'exams';

            return redirect()
                ->route('training.exams.index', ['tab' => $tab])
                ->with('success', 'Exam created.');
        }

        return redirect()->route('training.exams.show', $exam->id)
            ->with('success', 'Exam created.');
    }

    public function show($id): View
    {
        $exam = TrainingExam::with(['results.subject', 'results.student'])->findOrFail($id);
        $exam->setRelation('batch', $exam->batch_id ? TrainingBatch::find($exam->batch_id) : null);
        $summary = $exam->studentResultSummary();
        $gpaBands = \App\Support\TrainingGpa::bands((int) $exam->institute_id);

        // Exam-to-exam weight distribution for the batch (weighted final result).
        $weightRows = $exam->batch_id
            ? TrainingExam::where('batch_id', $exam->batch_id)
                ->where('institute_id', $exam->institute_id)
                ->orderBy('exam_date')
                ->orderBy('id')
                ->get(['id', 'title', 'exam_date', 'weight_percent'])
            : collect();

        return view('training.exams.show', compact('exam', 'summary', 'gpaBands', 'weightRows'));
    }

    /**
     * Publish an exam's results: stamp the exam as published and upsert
     * batch-level TrainingBatchResult rows for every marked trainee.
     *
     * Student-level obtained marks = average of subject rows (fallback: overall row),
     * matching the Overall marks view and studentResultSummary().
     */
    public function publish(Request $request, TrainingExam $exam): RedirectResponse
    {
        $instituteId = (int) $request->user()->institute_id;
        abort_unless((int) $exam->institute_id === $instituteId, 403);

        if (! $exam->results()->exists()) {
            return back()->with('error', 'No marks entered for this exam yet. Enter marks before publishing.');
        }

        $exam->update(['published_at' => now()]);

        $published = 0;
        $batch = $exam->batch;

        if ($batch) {
            $exams = TrainingExam::where('batch_id', $batch->id)->get();
            $resultsByStudent = TrainingExamResult::whereIn('exam_id', $exams->pluck('id'))
                ->get()
                ->groupBy('student_id');

            $enrolledIds = TrainingEnrollment::where('batch_id', $batch->id)->pluck('student_id')->all();
            $studentIds = array_values(array_unique(array_merge($enrolledIds, $resultsByStudent->keys()->all())));

            foreach ($studentIds as $studentId) {
                $studentResults = $resultsByStudent->get($studentId, collect());
                if ($studentResults->isEmpty()) {
                    continue;
                }

                $totalFull = 0.0;
                $passSum = 0.0;
                $obtained = 0.0;
                $obtainedByExam = [];
                $fullByExam = [];

                foreach ($exams as $batchExam) {
                    $examRows = $studentResults->where('exam_id', $batchExam->id);
                    if ($examRows->isEmpty()) {
                        continue;
                    }

                    $subjectRows = $examRows->filter(fn ($r) => $r->subject_id !== null);
                    $examObtained = $subjectRows->isNotEmpty()
                        ? (float) $subjectRows->avg('marks_obtained')
                        : (float) ($examRows->firstWhere('subject_id', null)?->marks_obtained ?? 0);

                    $totalFull += (float) $batchExam->full_marks;
                    $passSum += (float) $batchExam->pass_marks;
                    $obtained += $examObtained;

                    $obtainedByExam[(int) $batchExam->id] = $examObtained;
                    $fullByExam[(int) $batchExam->id] = (float) $batchExam->full_marks;
                }

                if (\App\Support\TrainingExamWeighting::enabled($exams)) {
                    $summary = \App\Support\TrainingExamWeighting::summarize($exams, $obtainedByExam, $fullByExam);
                    if ($summary['total_marks'] <= 0) {
                        continue;
                    }

                    TrainingBatchResult::updateOrCreate(
                        ['batch_id' => $batch->id, 'student_id' => $studentId],
                        [
                            'institute_id' => $instituteId,
                            'total_marks' => $summary['total_marks'],
                            'obtained_marks' => $summary['obtained_marks'],
                            'percentage' => $summary['percentage'],
                            'status' => $summary['percentage'] >= $summary['pass_percentage'] ? 'pass' : 'fail',
                            'published_at' => now(),
                        ]
                    );
                    $published++;

                    continue;
                }

                if ($totalFull <= 0) {
                    continue;
                }

                TrainingBatchResult::updateOrCreate(
                    ['batch_id' => $batch->id, 'student_id' => $studentId],
                    [
                        'institute_id' => $instituteId,
                        'total_marks' => round($totalFull, 2),
                        'obtained_marks' => round($obtained, 2),
                        'percentage' => round(($obtained / $totalFull) * 100, 2),
                        'status' => $obtained >= $passSum ? 'pass' : 'fail',
                        'published_at' => now(),
                    ]
                );
                $published++;
            }
        }

        $message = "Results published for '{$exam->title}'.";
        if ($batch && $published > 0) {
            $message .= " {$published} trainee(s) in batch '{$batch->name}'.";
        }

        return back()->with('status', $message);
    }
}
