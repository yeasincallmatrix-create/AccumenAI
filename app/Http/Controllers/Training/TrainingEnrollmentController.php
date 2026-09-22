<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Training\TrainingExam;
use Illuminate\Http\Request;

class TrainingExamController extends Controller
{
    public function index(Request $request)
    {
        $exams = TrainingExam::where('institute_id', auth()->user()->institute_id)
            ->orderByDesc('id')->paginate(20)->withQueryString();
        return view('training.exams.index', compact('exams'));
    }

    public function create()
    {
        return view('training.exams.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'course_id' => 'nullable|integer',
            'batch_id' => 'nullable|integer',
        ]);
        $validated['institute_id'] = auth()->user()->institute_id;
        TrainingExam::create($validated);
        return redirect()->route('training.exams.index')
            ->with('success', 'Exam created.');
    }

    public function show($id)
    {
        $exam = TrainingExam::findOrFail($id);
        return view('training.exams.show', compact('exam'));
    }

    public function edit($id)
    {
        $exam = TrainingExam::findOrFail($id);
        return view('training.exams.edit', compact('exam'));
    }

    public function update(Request $request, $id)
    {
        $exam = TrainingExam::findOrFail($id);
        $validated = $request->validate(['title' => 'required|string|max:255']);
        $exam->update($validated);
        return redirect()->route('training.exams.index')
            ->with('success', 'Exam updated.');
    }

    public function destroy($id)
    {
        TrainingExam::findOrFail($id)->delete();
        return redirect()->route('training.exams.index')
            ->with('success', 'Exam deleted.');
    }
}
