<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Training\TrainingBatch;
use App\Models\Training\TrainingCourse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TrainingBatchController extends Controller
{
    public function index(Request $request)
    {
        $batches = TrainingBatch::with('course:id,name,course_code')
            ->where('institute_id', auth()->user()->institute_id)
            ->orderByDesc('id')->paginate(20)->withQueryString();
        return view('training.batches.index', compact('batches'));
    }

    public function create()
    {
        $courses = $this->courses();
        return view('training.batches.create', compact('courses'));
    }

    public function store(Request $request)
    {
        $instituteId = auth()->user()->institute_id;
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'course_id' => [
                'required',
                'integer',
                Rule::exists('training_courses', 'id')
                    ->where('institute_id', $instituteId)
                    ->whereNull('deleted_at'),
            ],
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'batch_code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('training_batches', 'batch_code')
                    ->where('institute_id', $instituteId)
                    ->whereNull('deleted_at'),
            ],
            'status' => 'nullable|string|in:upcoming,ongoing,completed,cancelled,archived',
            'seat_capacity' => 'nullable|integer|min:1|max:10000',
        ]);
        $validated['institute_id'] = $instituteId;
        $validated['status'] = $validated['status'] ?? 'upcoming';
        if (empty($validated['batch_code'])) {
            unset($validated['batch_code']);
        }
        $batch = TrainingBatch::create($validated);
        if (! $batch->batch_code) {
            $batch->update(['batch_code' => $this->nextBatchCode($instituteId, $batch->id)]);
        }
        return redirect()->route('training.batches.index')
            ->with('success', 'Batch created.');
    }

    public function show($id)
    {
        $batch = TrainingBatch::with([
            'enrollments.student',
            'course:id,name,course_code',
            'schedules.subject',
        ])->findOrFail($id);
        $batch->loadCount('enrollments');
        $exams = \App\Models\Training\TrainingExam::where('batch_id', $batch->id)
            ->withCount('results')->orderByDesc('id')->get();
        $availableSeats = max(0, ($batch->seat_capacity ?? 0) - ($batch->enrollments_count));
        $subjects = $this->scheduleSubjects($batch);
        return view('training.batches.show', compact('batch', 'exams', 'availableSeats', 'subjects'));
    }

    public function edit($id)
    {
        $batch = TrainingBatch::findOrFail($id);
        $courses = $this->courses();
        return view('training.batches.edit', compact('batch', 'courses'));
    }

    public function update(Request $request, $id)
    {
        $batch = TrainingBatch::findOrFail($id);
        $instituteId = auth()->user()->institute_id;
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'course_id' => [
                'required',
                'integer',
                Rule::exists('training_courses', 'id')
                    ->where('institute_id', $instituteId)
                    ->whereNull('deleted_at'),
            ],
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'batch_code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('training_batches', 'batch_code')
                    ->where('institute_id', $instituteId)
                    ->whereNull('deleted_at')
                    ->ignore($batch->id),
            ],
            'status' => 'nullable|string|in:upcoming,ongoing,completed,cancelled,archived',
            'seat_capacity' => 'nullable|integer|min:1|max:10000',
        ]);
        $filled = $batch->enrollments()->count();
        if (isset($validated['seat_capacity']) && (int) $validated['seat_capacity'] < $filled) {
            return back()->withErrors([
                'seat_capacity' => "Capacity cannot be lower than the {$filled} student(s) already enrolled.",
            ])->withInput();
        }
        if (($validated['status'] ?? null) === null) {
            unset($validated['status']);
        }
        $batch->update($validated);
        return redirect()->route('training.batches.index')
            ->with('success', 'Batch updated.');
    }

    public function destroy($id)
    {
        TrainingBatch::findOrFail($id)->delete();
        return redirect()->route('training.batches.index')
            ->with('success', 'Batch deleted.');
    }

    private function courses()
    {
        return TrainingCourse::where('institute_id', auth()->user()->institute_id)
            ->orderBy('name')
            ->get(['id', 'name', 'course_code']);
    }

    private function scheduleSubjects(TrainingBatch $batch)
    {
        $course = $batch->course_id ? TrainingCourse::find($batch->course_id) : null;

        $subjects = $course
            ? $course->subjects()->orderBy('training_subjects.name')->get()
            : collect();

        if ($subjects->isEmpty()) {
            $subjects = \App\Models\Training\TrainingSubject::where('institute_id', auth()->user()->institute_id)
                ->orderBy('name')
                ->get();
        }

        return $subjects;
    }

    private function nextBatchCode(int $instituteId, int $batchId): string
    {
        $code = 'B' . str_pad((string) $batchId, 3, '0', STR_PAD_LEFT);

        while (TrainingBatch::where('institute_id', $instituteId)
            ->where('batch_code', $code)
            ->whereNull('deleted_at')
            ->exists()) {
            $code = 'B' . str_pad((string) ($batchId++), 3, '0', STR_PAD_LEFT) . 'X';
        }

        return $code;
    }
}
