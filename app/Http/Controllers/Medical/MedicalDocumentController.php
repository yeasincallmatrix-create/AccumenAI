<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\MedicalDocument;
use App\Models\Medical\NumberSequence;
use App\Services\Medical\MedicalDocumentCompressorService;
use App\Services\Medical\NumberSequenceService;
use App\Services\Medical\PatientTimelineEventService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;

class MedicalDocumentController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical.records.view', only: ['index', 'show', 'preview']),
            new Middleware('permission:medical.records.document.upload', only: ['create', 'store']),
            new Middleware('permission:medical.records.document.download', only: ['download']),
            new Middleware('permission:medical.records.document.delete', only: ['destroy']),
        ];
    }

    public function __construct(
        private readonly NumberSequenceService $sequences,
        private readonly PatientTimelineEventService $timeline,
        private readonly MedicalDocumentCompressorService $compressor,
    ) {}

    public function index(Request $request)
    {
        $instituteId = $this->instituteId();
        $query = MedicalDocument::forInstitute($instituteId);
        $this->scopeBranch($query);

        if ($request->filled('document_type')) {
            $query->where('document_type', $request->document_type);
        }
        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('document_number', 'like', "%{$search}%")
                    ->orWhereHas('patient', fn ($pq) => $pq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%"));
            });
        }

        $documents = $query->with(['patient', 'uploadedBy'])
            ->orderByDesc('document_date')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        $types = MedicalDocument::DOCUMENT_TYPES;

        return view('medical.records.documents.index', compact('documents', 'types'));
    }

    public function create(Request $request)
    {
        $instituteId = $this->instituteId();
        $documentNumber = $this->sequences->peek(NumberSequence::TYPE_MEDICAL_DOCUMENT, $instituteId);
        $types = MedicalDocument::DOCUMENT_TYPES;
        $levels = MedicalDocument::ACCESS_LEVELS;
        $patients = \App\Models\Medical\Patient::where('institute_id', $instituteId)->active()->patients()->orderBy('first_name')->get();
        $preselectedPatient = $request->input('patient_id');

        return view('medical.records.documents.create', compact(
            'documentNumber', 'types', 'levels', 'patients', 'preselectedPatient'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'document_type' => 'required|string|in:' . implode(',', array_keys(MedicalDocument::DOCUMENT_TYPES)),
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'file' => 'required|file|max:20480|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,txt,dicom,dcm',
            'document_date' => 'nullable|date',
            'is_confidential' => 'nullable|boolean',
            'is_patient_visible' => 'nullable|boolean',
            'access_level' => 'nullable|string|in:clinical,department,patient',
            'tags' => 'nullable|string|max:500',
        ]);

        $instituteId = $this->instituteId();
        $patient = \App\Models\Medical\Patient::findOrFail($request->patient_id);
        $this->ensureSameInstitute($patient, 'patient');

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $originalSize = (int) ($file->getSize() ?? filesize($file->getRealPath()));

        // Auto-compress toward ~100 KB (images fully; PDFs via Ghostscript when
        // available; office/txt/dicom stored as-is capped at 20 MB).
        $result = $this->compressor->compress($file);
        $file = $result['file'];
        $finalSize = (int) (is_file((string) $file->getRealPath()) ? filesize((string) $file->getRealPath()) : $file->getSize());

        $path = $file->store('medical-documents/' . $instituteId, 'local');

        // Temp compressed copies live in sys temp (test mode); clean them up.
        if (($result['compressed'] ?? false) && str_starts_with((string) $file->getRealPath(), rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR))) {
            @unlink((string) $file->getRealPath());
        }

        $document = MedicalDocument::create([
            'institute_id' => $instituteId,
            'branch_id' => $this->resolveBranchId($request->branch_id),
            'patient_id' => $patient->id,
            'document_number' => $this->sequences->next(NumberSequence::TYPE_MEDICAL_DOCUMENT, $instituteId),
            'document_type' => $request->document_type,
            'title' => $request->title,
            'description' => $request->description,
            'file_path' => $path,
            'original_filename' => $originalName,
            'mime_type' => Storage::disk('local')->mimeType($path) ?: $file->getMimeType(),
            'file_size' => Storage::disk('local')->size($path) ?: $finalSize,
            'file_hash' => hash('sha256', Storage::disk('local')->get($path)),
            'document_date' => $request->document_date ?? today(),
            'is_confidential' => (bool) $request->boolean('is_confidential'),
            'is_patient_visible' => (bool) $request->boolean('is_patient_visible'),
            'access_level' => $request->access_level ?? 'clinical',
            'tags' => $request->filled('tags') ? array_map('trim', explode(',', $request->tags)) : null,
            'uploaded_by' => auth()->id(),
        ]);

        ClinicalAuditLog::record($document, 'created');

        $this->timeline->recordEvent([
            'institute_id' => $instituteId,
            'branch_id' => $document->branch_id,
            'patient_id' => $patient->id,
            'event_type' => 'document',
            'title' => "Document: {$document->title}",
            'description' => $document->documentTypeLabel(),
            'event_at' => now(),
            'source_type' => MedicalDocument::class,
            'source_id' => $document->id,
            'icon' => $document->icon(),
        ]);

        $status = 'Document uploaded: ' . $document->document_number;
        if (($result['compressed'] ?? false) && $originalSize > 0) {
            $status .= sprintf(
                ' (auto-compressed %s → %s)',
                $this->humanBytes($originalSize),
                $this->humanBytes((int) $document->file_size)
            );
        } elseif (! empty($result['note'])) {
            $status .= ' (' . $result['note'] . ')';
        }

        return redirect()
            ->route('medical.records.documents.show', $document)
            ->with('status', $status);
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 0) . ' KB';
        }

        return $bytes . ' B';
    }

    public function show(MedicalDocument $document)
    {
        $this->ensureSameInstitute($document, 'document');
        $this->ensureBranchAccess($document, 'branch_id', 'document');
        $document->load(['patient', 'uploadedBy', 'verifiedBy']);

        return view('medical.records.documents.show', compact('document'));
    }

    public function edit(MedicalDocument $document)
    {
        $this->ensureSameInstitute($document, 'document');
        $this->ensureBranchAccess($document, 'branch_id', 'document');

        return view('medical.records.documents.edit', [
            'document' => $document,
            'types' => MedicalDocument::DOCUMENT_TYPES,
            'levels' => MedicalDocument::ACCESS_LEVELS,
        ]);
    }

    public function update(Request $request, MedicalDocument $document)
    {
        $this->ensureSameInstitute($document, 'document');
        $this->ensureBranchAccess($document, 'branch_id', 'document');

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'document_type' => 'required|string|in:' . implode(',', array_keys(MedicalDocument::DOCUMENT_TYPES)),
            'document_date' => 'nullable|date',
            'access_level' => 'nullable|string|in:clinical,department,patient',
        ]);

        $original = ClinicalAuditLog::snapshot($document);
        $document->update($request->only([
            'title', 'description', 'document_type', 'document_date', 'access_level',
        ]) + [
            'is_confidential' => $request->boolean('is_confidential'),
            'is_patient_visible' => $request->boolean('is_patient_visible'),
        ]);

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($document->refresh()));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($document, 'updated', ['old' => $old, 'new' => $new]);
        }

        return redirect()
            ->route('medical.records.documents.show', $document)
            ->with('status', 'Document updated.');
    }

    public function destroy(MedicalDocument $document)
    {
        $this->ensureSameInstitute($document, 'document');
        $this->ensureBranchAccess($document, 'branch_id', 'document');

        ClinicalAuditLog::record($document, 'deleted');
        $document->delete();

        return redirect()
            ->route('medical.records.documents.index')
            ->with('status', 'Document deleted.');
    }

    public function download(MedicalDocument $document)
    {
        $this->ensureSameInstitute($document, 'document');
        $this->ensureBranchAccess($document, 'branch_id', 'document');

        if (! Storage::disk('local')->exists($document->file_path)) {
            abort(404, 'File not found on disk.');
        }

        return Storage::disk('local')->download($document->file_path, $document->original_filename);
    }

    public function preview(MedicalDocument $document)
    {
        $this->ensureSameInstitute($document, 'document');
        $this->ensureBranchAccess($document, 'branch_id', 'document');

        if (! Storage::disk('local')->exists($document->file_path)) {
            abort(404, 'File not found on disk.');
        }

        return Storage::disk('local')->response($document->file_path, $document->original_filename, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'inline; filename="' . $document->original_filename . '"',
        ]);
    }
}
