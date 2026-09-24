<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Training\TrainingBatch;
use App\Models\Training\TrainingEnrollment;
use App\Models\Training\TrainingStudent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
        $student = TrainingStudent::with(['enrollments.batch', 'certificates'])->findOrFail($id);
        $batches = \App\Models\Training\TrainingBatch::where('institute_id', $student->institute_id)
            ->orderBy('name')->get();
        $results = collect();
        $lifecycle = [
            'outcome' => $student->status === 'withdrawn' ? 'withdrawn' : ($student->status === 'completed' ? 'completed' : 'active'),
            'hasActivePlacement' => $student->enrollments->contains('status', 'active'),
        ];
        return view('training.students.show', compact('student', 'batches', 'results', 'lifecycle'));
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

    public function transfer(Request $request, $student)
    {
        $studentModel = TrainingStudent::findOrFail($student);
        $data = $request->validate([
            'batch_id' => 'required|exists:training_batches,id',
            'reason'   => 'nullable|string|max:500',
        ]);
        $batch = TrainingBatch::where('id', $data['batch_id'])
            ->where('institute_id', $studentModel->institute_id)
            ->firstOrFail();
        TrainingEnrollment::where('student_id', $studentModel->id)
            ->where('status', 'active')
            ->update(['status' => 'transferred']);
        TrainingEnrollment::create([
            'institute_id'    => $studentModel->institute_id,
            'student_id'      => $studentModel->id,
            'batch_id'        => $batch->id,
            'status'          => 'active',
            'enrollment_date' => now()->toDateString(),
        ]);
        $studentModel->update(['preferred_batch_id' => $batch->id]);
        return back()->with('success', 'Student transferred successfully.');
    }

    public function withdraw(Request $request, $student)
    {
        $studentModel = TrainingStudent::findOrFail($student);
        $request->validate(['reason' => 'nullable|string|max:500']);
        TrainingEnrollment::where('student_id', $studentModel->id)
            ->where('status', 'active')
            ->update(['status' => 'withdrawn']);
        $studentModel->update(['status' => 'withdrawn']);
        return redirect()
            ->route('training.students.index')
            ->with('success', 'Student withdrawn successfully.');
    }

    public function photo(Request $request, $student)
    {
        $studentModel = TrainingStudent::findOrFail($student);
        $request->validate(['photo' => 'required|image|mimes:jpeg,jpg,png|max:2048']);
        if ($studentModel->photo && Storage::disk('public')->exists($studentModel->photo)) {
            Storage::disk('public')->delete($studentModel->photo);
        }
        $path = $request->file('photo')->store('training/students/photos', 'public');
        $studentModel->update(['photo' => $path]);
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Photo updated.', 'data' => ['photo' => Storage::url($path)]]);
        }
        return back()->with('success', 'Photo uploaded successfully.');
    }

    public function enroll(Request $request, $student)
    {
        $studentModel = TrainingStudent::findOrFail($student);
        $data = $request->validate([
            'batch_id' => 'required|exists:training_batches,id',
        ]);
        $batch = TrainingBatch::where('id', $data['batch_id'])
            ->where('institute_id', $studentModel->institute_id)
            ->firstOrFail();
        TrainingEnrollment::create([
            'institute_id'    => $studentModel->institute_id,
            'student_id'      => $studentModel->id,
            'batch_id'        => $batch->id,
            'status'          => 'active',
            'enrollment_date' => now()->toDateString(),
        ]);
        $studentModel->update(['preferred_batch_id' => $batch->id]);
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Student enrolled to batch successfully.']);
        }
        return back()->with('success', 'Student enrolled to batch successfully.');
    }
}
