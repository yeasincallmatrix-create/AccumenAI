<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Training\TrainingBatch;
use Illuminate\Http\Request;

class TrainingBatchController extends Controller
{
    public function index(Request $request)
    {
        $batches = TrainingBatch::where('institute_id', auth()->user()->institute_id)
            ->orderByDesc('id')->paginate(20)->withQueryString();
        return view('training.batches.index', compact('batches'));
    }

    public function create()
    {
        return view('training.batches.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'course_id' => 'nullable|integer',
        ]);
        $validated['institute_id'] = auth()->user()->institute_id;
        TrainingBatch::create($validated);
        return redirect()->route('training.batches.index')
            ->with('success', 'Batch created.');
    }

    public function show($id)
    {
        $batch = TrainingBatch::with(['enrollments.student'])->findOrFail($id);
        $batch->loadCount('enrollments');
        $exams = \App\Models\Training\TrainingExam::where('batch_id', $batch->id)
            ->withCount('results')->orderByDesc('id')->get();
        $availableSeats = max(0, ($batch->seat_capacity ?? 0) - $batch->enrollments_count);
        return view('training.batches.show', compact('batch', 'exams', 'availableSeats'));
    }

    public function edit($id)
    {
        $batch = TrainingBatch::findOrFail($id);
        return view('training.batches.edit', compact('batch'));
    }

    public function update(Request $request, $id)
    {
        $batch = TrainingBatch::findOrFail($id);
        $validated = $request->validate(['name' => 'required|string|max:255']);
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
}
