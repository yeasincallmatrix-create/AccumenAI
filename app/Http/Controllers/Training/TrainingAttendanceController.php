<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Training\TrainingCertificate;
use Illuminate\Http\Request;

class TrainingAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $certificates = TrainingCertificate::where('institute_id', auth()->user()->institute_id)
            ->orderByDesc('id')->paginate(20)->withQueryString();
        return view('training.certificates.index', compact('certificates'));
    }

    public function create()
    {
        return view('training.certificates.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate(['title' => 'required|string|max:255']);
        $validated['institute_id'] = auth()->user()->institute_id;
        TrainingCertificate::create($validated);
        return redirect()->route('training.certificates.index')
            ->with('success', 'Certificate created.');
    }

    public function show($id)
    {
        $certificate = TrainingCertificate::findOrFail($id);
        return view('training.certificates.show', compact('certificate'));
    }

    public function edit($id)
    {
        $certificate = TrainingCertificate::findOrFail($id);
        return view('training.certificates.edit', compact('certificate'));
    }

    public function update(Request $request, $id)
    {
        $certificate = TrainingCertificate::findOrFail($id);
        $certificate->update($request->validate(['title' => 'required|string|max:255']));
        return redirect()->route('training.certificates.index')
            ->with('success', 'Certificate updated.');
    }

    public function destroy($id)
    {
        TrainingCertificate::findOrFail($id)->delete();
        return redirect()->route('training.certificates.index')
            ->with('success', 'Certificate deleted.');
    }
}
