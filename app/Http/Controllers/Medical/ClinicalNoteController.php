<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\ClinicalNote;
use App\Models\Medical\NumberSequence;
use App\Services\Medical\NumberSequenceService;
use App\Services\Medical\PatientTimelineEventService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ClinicalNoteController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical.records.view', only: ['index', 'show']),
            new Middleware('permission:medical.records.note.create', only: ['create', 'store', 'edit', 'update', 'destroy']),
            new Middleware('permission:medical.records.note.sign', only: ['sign', 'amend']),
        ];
    }

    public function __construct(
        private readonly NumberSequenceService $sequences,
        private readonly PatientTimelineEventService $timeline,
    ) {}

    public function index(Request $request)
    {
        $instituteId = $this->instituteId();
        $query = ClinicalNote::forInstitute($instituteId);
        $this->scopeBranch($query);

        if ($request->filled('note_type')) {
            $query->where('clinical_notes.note_type', $request->note_type);
        }
        if ($request->filled('patient_id')) {
            $query->where('clinical_notes.patient_id', $request->patient_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('note_number', 'like', "%{$search}%")
                    ->orWhere('assessment', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $notes = $query->with(['patient', 'author'])
            ->orderByDesc('noted_at')->paginate(25)->withQueryString();
        $types = ClinicalNote::NOTE_TYPES;

        return view('medical.records.notes.index', compact('notes', 'types'));
    }

    public function create(Request $request)
    {
        $instituteId = $this->instituteId();
        $noteNumber = $this->sequences->peek(NumberSequence::TYPE_CLINICAL_NOTE, $instituteId);
        $types = ClinicalNote::NOTE_TYPES;
        $patients = \App\Models\Medical\Patient::where('institute_id', $instituteId)->active()->patients()->orderBy('first_name')->get();
        $preselectedPatient = $request->input('patient_id');

        return view('medical.records.notes.create', compact('noteNumber', 'types', 'patients', 'preselectedPatient'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'note_type' => 'required|string|in:' . implode(',', array_keys(ClinicalNote::NOTE_TYPES)),
            'subjective' => 'nullable|string',
            'objective' => 'nullable|string',
            'assessment' => 'nullable|string',
            'plan' => 'nullable|string',
            'content' => 'nullable|string',
            'noted_at' => 'required|date',
        ]);

        $instituteId = $this->instituteId();
        $patient = \App\Models\Medical\Patient::findOrFail($request->patient_id);
        $this->ensureSameInstitute($patient, 'patient');

        $note = ClinicalNote::create([
            'institute_id' => $instituteId,
            'branch_id' => $this->resolveBranchId($request->branch_id),
            'note_number' => $this->sequences->next(NumberSequence::TYPE_CLINICAL_NOTE, $instituteId),
            'patient_id' => $patient->id,
            'author_id' => auth()->id(),
            'note_type' => $request->note_type,
            'encounter_type' => $request->encounter_type,
            'encounter_id' => $request->encounter_id,
            'subjective' => $request->subjective,
            'objective' => $request->objective,
            'assessment' => $request->assessment,
            'plan' => $request->plan,
            'content' => $request->content,
            'noted_at' => $request->noted_at,
        ]);

        ClinicalAuditLog::record($note, 'created');

        $this->timeline->recordEvent([
            'institute_id' => $instituteId,
            'branch_id' => $note->branch_id,
            'patient_id' => $patient->id,
            'event_type' => 'note',
            'title' => "Clinical Note {$note->note_number} ({$note->noteTypeLabel()})",
            'description' => $note->assessment,
            'event_at' => $note->noted_at,
            'source_type' => ClinicalNote::class,
            'source_id' => $note->id,
            'doctor_id' => auth()->id(),
            'icon' => 'bi-journal-text',
        ]);

        return redirect()
            ->route('medical.records.notes.show', $note)
            ->with('status', 'Clinical note created: ' . $note->note_number);
    }

    public function show(ClinicalNote $note)
    {
        $this->ensureSameInstitute($note, 'clinical_note');
        $this->ensureBranchAccess($note, 'branch_id', 'clinical_note');
        $note->load(['patient', 'author']);

        return view('medical.records.notes.show', compact('note'));
    }

    public function edit(ClinicalNote $note)
    {
        $this->ensureSameInstitute($note, 'clinical_note');
        $this->ensureBranchAccess($note, 'branch_id', 'clinical_note');

        if ($note->isSigned()) {
            abort(403, 'Signed notes cannot be edited. Use amendment instead.');
        }

        return view('medical.records.notes.edit', [
            'note' => $note,
            'types' => ClinicalNote::NOTE_TYPES,
        ]);
    }

    public function update(Request $request, ClinicalNote $note)
    {
        $this->ensureSameInstitute($note, 'clinical_note');
        $this->ensureBranchAccess($note, 'branch_id', 'clinical_note');

        if ($note->isSigned()) {
            abort(403, 'Signed notes cannot be edited. Use amendment instead.');
        }

        $request->validate([
            'note_type' => 'required|string|in:' . implode(',', array_keys(ClinicalNote::NOTE_TYPES)),
            'subjective' => 'nullable|string',
            'objective' => 'nullable|string',
            'assessment' => 'nullable|string',
            'plan' => 'nullable|string',
            'content' => 'nullable|string',
        ]);

        $original = ClinicalAuditLog::snapshot($note);
        $note->update($request->only(['note_type', 'subjective', 'objective', 'assessment', 'plan', 'content']));

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($note->refresh()));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($note, 'updated', ['old' => $old, 'new' => $new]);
        }

        return redirect()
            ->route('medical.records.notes.show', $note)
            ->with('status', 'Clinical note updated.');
    }

    public function destroy(ClinicalNote $note)
    {
        $this->ensureSameInstitute($note, 'clinical_note');
        $this->ensureBranchAccess($note, 'branch_id', 'clinical_note');

        if ($note->isSigned()) {
            abort(403, 'Signed notes cannot be deleted.');
        }

        ClinicalAuditLog::record($note, 'deleted');
        $note->delete();

        return redirect()
            ->route('medical.records.notes.index')
            ->with('status', 'Clinical note deleted.');
    }

    public function sign(ClinicalNote $note)
    {
        $this->ensureSameInstitute($note, 'clinical_note');
        $this->ensureBranchAccess($note, 'branch_id', 'clinical_note');

        if ($note->isSigned()) {
            return back()->with('status', 'Note is already signed.');
        }

        $original = ClinicalAuditLog::snapshot($note);
        $note->sign(auth()->id());

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($note->refresh()));
        ClinicalAuditLog::record($note, 'signed', ['old' => $old, 'new' => $new]);

        return redirect()
            ->route('medical.records.notes.show', $note)
            ->with('status', 'Clinical note signed.');
    }

    public function amend(Request $request, ClinicalNote $note)
    {
        $this->ensureSameInstitute($note, 'clinical_note');
        $this->ensureBranchAccess($note, 'branch_id', 'clinical_note');

        $request->validate([
            'amendment_reason' => 'required|string|max:2000',
            'addendum' => 'nullable|string',
        ]);

        $original = ClinicalAuditLog::snapshot($note);
        $note->amend($request->amendment_reason, $request->addendum);

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($note->refresh()));
        ClinicalAuditLog::record($note, 'amended', ['old' => $old, 'new' => $new, 'reason' => $request->amendment_reason]);

        return redirect()
            ->route('medical.records.notes.show', $note)
            ->with('status', 'Clinical note amended.');
    }
}
