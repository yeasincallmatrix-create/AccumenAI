<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\VitalSignRequest;
use App\Models\Medical\Admission;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\NursingNote;
use App\Models\Medical\VitalSign;
use App\Support\MedicalScope;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class VitalSignController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_vitals.view', only: ['index', 'show']),
            new Middleware('permission:medical_vitals.create', only: ['create', 'store', 'storeNote']),
            new Middleware('permission:medical_vitals.delete', only: ['destroy', 'destroyNote']),
        ];
    }

    /**
     * Show vitals entry form for an admission.
     */
    public function create(Request $request)
    {
        $admissionId = $request->query('admission_id');

        if (! $admissionId) {
            return redirect()->route('medical.admissions.current')
                ->with('error', 'Please select an admission first.');
        }

        $admission = Admission::where('institute_id', $this->instituteId())
            ->with(['patient', 'bed.ward'])
            ->findOrFail($admissionId);
        $this->ensureDoctorOwns($admission, 'admitting_doctor_id', 'admission');

        if ($admission->status !== 'active') {
            return redirect()->back()->with('error', 'Cannot record vitals for a discharged patient.');
        }

        return view('medical.admissions.vitals', compact('admission'));
    }

    /**
     * Store vitals.
     */
    public function store(VitalSignRequest $request)
    {
        $data = $request->validated();
        $data['recorded_by'] = MedicalScope::recorderId();
        $data['recorded_at'] = now();

        $admission = Admission::where('institute_id', $this->instituteId())
            ->findOrFail($data['admission_id']);
        $this->ensureDoctorOwns($admission, 'admitting_doctor_id', 'admission');

        VitalSign::create($data);

        return redirect()->route('medical.admissions.show', $data['admission_id'])
            ->with('status', 'Vitals recorded successfully!');
    }

    /**
     * Show vitals history for an admission.
     */
    public function index(Request $request)
    {
        $admissionId = $request->query('admission_id');

        if (! $admissionId) {
            return redirect()->route('medical.admissions.current')
                ->with('error', 'Please select an admission.');
        }

        $admission = Admission::where('institute_id', $this->instituteId())
            ->with('patient')
            ->findOrFail($admissionId);
        $this->ensureDoctorOwns($admission, 'admitting_doctor_id', 'admission');

        $vitals = VitalSign::where('admission_id', $admission->id)
            ->orderBy('recorded_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('medical.admissions.vitals_history', compact('admission', 'vitals'));
    }

    /**
     * Show vitals details.
     */
    public function show(VitalSign $vital)
    {
        $admission = Admission::where('institute_id', $this->instituteId())
            ->with('patient')
            ->findOrFail($vital->admission_id);

        $vital->load('recordedBy');

        return view('medical.admissions.vitals_show', compact('vital', 'admission'));
    }

    /**
     * Delete vitals entry.
     */
    public function destroy(VitalSign $vital)
    {
        $admission = Admission::where('institute_id', $this->instituteId())->findOrFail($vital->admission_id);
        $this->ensureDoctorOwns($admission, 'admitting_doctor_id', 'admission');

        $admissionId = $vital->admission_id;
        // Phase 01: vitals removal is archival (soft delete) + audited.
        ClinicalAuditLog::record($vital, 'deleted', ['old' => ClinicalAuditLog::snapshot($vital)]);
        $vital->delete();

        return redirect()->route('medical.admissions.show', $admissionId)
            ->with('status', 'Vitals entry deleted.');
    }

    /**
     * Store a nursing note for an admission.
     */
    public function storeNote(Request $request, Admission $admission)
    {
        $this->ensureSameInstitute($admission, 'admission');
        $this->ensureDoctorOwns($admission, 'admitting_doctor_id', 'admission');

        if ($admission->status !== 'active') {
            return redirect()->back()->with('error', 'Cannot add notes to a discharged admission.');
        }

        $request->validate(['note' => 'required|string|max:5000']);

        NursingNote::create([
            'admission_id' => $admission->id,
            'note' => $request->note,
            'recorded_by' => MedicalScope::recorderId(),
            'recorded_at' => now(),
        ]);

        return redirect()->route('medical.admissions.show', $admission)
            ->with('status', 'Nursing note added successfully!');
    }

    /**
     * Delete a nursing note.
     */
    public function destroyNote(NursingNote $note)
    {
        $admission = Admission::where('institute_id', $this->instituteId())->findOrFail($note->admission_id);
        $this->ensureDoctorOwns($admission, 'admitting_doctor_id', 'admission');

        $admissionId = $note->admission_id;
        // Phase 01: note removal is archival (soft delete) + audited.
        ClinicalAuditLog::record($note, 'deleted', ['old' => ClinicalAuditLog::snapshot($note)]);
        $note->delete();

        return redirect()->route('medical.admissions.show', $admissionId)
            ->with('status', 'Nursing note deleted.');
    }
}
