<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Training\TrainingStudent;
use Illuminate\Http\Request;

class TrainingStudentController extends Controller
{
    public function index(Request $request)
    {
        $students = TrainingStudent::where('institute_id', auth()->user()->institute_id)
            ->orderByDesc('id')->paginate(20)->withQueryString();
        return view('training.students.index', compact('students'));
    }

    public function create()
    {
        return view('training.students.form');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'status' => 'nullable|string',
        ]);
        $validated['institute_id'] = auth()->user()->institute_id;
        $student = TrainingStudent::create($validated);
        return redirect()->route('training.students.index')
            ->with('success', 'Student added successfully.');
    }

    public function show($id)
    {
        $student = TrainingStudent::findOrFail($id);
        return view('training.students.show', compact('student'));
    }

    public function edit($id)
    {
        $student = TrainingStudent::findOrFail($id);
        return view('training.students.form', compact('student'));
    }

    public function update(Request $request, $id)
    {
        $student = TrainingStudent::findOrFail($id);
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
        ]);
        $student->update($validated);
        return redirect()->route('training.students.index')
            ->with('success', 'Student updated.');
    }

    public function destroy($id)
    {
        TrainingStudent::findOrFail($id)->delete();
        return redirect()->route('training.students.index')
            ->with('success', 'Student deleted.');
    }
}
