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

        $editingStudent = null;
        $editingId = (int) old('student_id');
        if ($editingId && $request->session()->has('errors')) {
            $editingStudent = TrainingStudent::find($editingId);
        }

        $canManage = $request->user()->hasPermission('students.manage');
        $editData = $students->mapWithKeys(function (TrainingStudent $student) use ($canManage) {
            $data = [
                'id' => $student->id,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'roll_number' => $student->roll_number,
                'reg_no' => $student->reg_no,
                'gender' => $student->gender,
                'dob' => $student->dob?->format('Y-m-d'),
                'admission_date' => $student->admission_date?->format('Y-m-d'),
                'phone' => $student->phone,
                'email' => $student->email,
                'religion' => $student->religion,
                'status' => $student->status,
                'father_name' => $student->father_name,
                'mother_name' => $student->mother_name,
                'guardian_phone' => $student->guardian_phone,
                'nationality' => $student->nationality,
                'nid_number' => $student->nid_number,
                'birth_cert_number' => $student->birth_cert_number,
                'passport_number' => $student->passport_number,
                'blood_group' => $student->blood_group,
                'present_country_id' => $student->present_country_id,
                'present_admin_1_id' => $student->present_admin_1_id,
                'present_admin_2_id' => $student->present_admin_2_id,
                'present_admin_3_id' => $student->present_admin_3_id,
                'present_post_office' => $student->present_post_office,
                'present_zip_code' => $student->present_zip_code,
                'present_address' => $student->present_address,
                'permanent_country_id' => $student->permanent_country_id,
                'permanent_admin_1_id' => $student->permanent_admin_1_id,
                'permanent_admin_2_id' => $student->permanent_admin_2_id,
                'permanent_admin_3_id' => $student->permanent_admin_3_id,
                'permanent_post_office' => $student->permanent_post_office,
                'permanent_zip_code' => $student->permanent_zip_code,
                'permanent_address' => $student->permanent_address,
                'emergency_contact_name' => $student->emergency_contact_name,
                'emergency_contact_phone' => $student->emergency_contact_phone,
            ];
            if (! $canManage) {
                unset($data['nid_number'], $data['birth_cert_number'], $data['passport_number']);
            }

            return [$student->id => $data];
        })->all();

        $defaultCountryId = \App\Models\Institute::query()
            ->where('id', auth()->user()->institute_id)
            ->value('country_id');

        return view('training.students.index', compact('students', 'editData', 'editingStudent', 'defaultCountryId'));
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
