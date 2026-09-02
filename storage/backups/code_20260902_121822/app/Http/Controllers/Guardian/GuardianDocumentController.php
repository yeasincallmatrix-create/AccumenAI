<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Guardian;
use App\Models\Student;
use App\Services\GuardianAuditService;
use App\Services\GuardianService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Step 47 — Guardian documents page (read-only).
 *
 * Only ACTIVE documents attached to a linked student are listed. Downloads go
 * through a dedicated guardian route that re-verifies ownership on every
 * request; the generic (permission-gated) documents.download route is never
 * used by guardians.
 */
class GuardianDocumentController extends Controller
{
    public function __construct(
        private readonly GuardianService $guardians,
        private readonly GuardianAuditService $audit,
    ) {}

    public function index(Request $request, int $student)
    {
        /** @var Guardian $guardian */
        $guardian = auth('guardian')->user();

        $student = $this->guardians->requireStudent($guardian, $student);

        $documents = Document::query()
            ->where('documentable_type', Student::class)
            ->where('documentable_id', $student->id)
            ->where('status', Document::STATUS_ACTIVE)
            ->with(['category'])
            ->orderByDesc('id')
            ->get();

        return view('guardian.documents', [
            'guardian' => $guardian,
            'student' => $student,
            'documents' => $documents,
        ]);
    }

    public function download(Request $request, int $student, int $document)
    {
        /** @var Guardian $guardian */
        $guardian = auth('guardian')->user();

        $document = Document::query()
            ->where('status', Document::STATUS_ACTIVE)
            ->findOrFail($document);

        if ($document->documentable_type !== Student::class) {
            abort(404);
        }

        $student = $guardian->linkedStudent((int) $document->documentable_id);

        if ($student === null) {
            abort(404);
        }

        if ($document->file_path === null || $document->file_path === '' || $document->file_path === 'null') {
            abort(404);
        }

        $disk = Storage::disk($document->disk ?? 'public');

        if (! $disk->exists($document->file_path)) {
            abort(404);
        }

        $this->audit->record(
            (int) $student->institute_id,
            (int) $guardian->getKey(),
            'guardian_downloaded_document',
            (int) $document->id,
            null,
            ['document' => $document->title ?? $document->original_filename, 'student_id' => (int) $student->id],
        );

        return $disk->download($document->file_path, $document->original_filename);
    }
}
