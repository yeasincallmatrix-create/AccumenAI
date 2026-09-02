<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\Document;
use App\Models\Student;
use App\Services\DocumentChecklistService;
use App\Services\DocumentService;
use App\Services\DocumentVerificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Step 46 — Generic Document Management endpoints.
 *
 * All endpoints speak the app's JSON response standard ({ success, message,
 * data }) so the reusable documents panel can drive uploads, downloads,
 * replaces, categorization, archive/restore and deletion over fetch. Writes
 * are gated by documents.manage, reads by documents.view; the tenant and the
 * owning entity are always derived from the authenticated context, never from
 * arbitrary request input.
 */
class DocumentController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly DocumentService $documents,
        private readonly DocumentVerificationService $verification,
        private readonly DocumentChecklistService $checklist,
    ) {}

    public function categories(Request $request)
    {
        $entity = $this->validatedEntitySlug($request);
        $institute = $this->requireInstitute($request);

        $categories = $this->documents->categories((int) $institute->id, $entity)
            ->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ]);

        return response()->json(['success' => true, 'data' => $categories]);
    }

    public function index(Request $request)
    {
        $entity = $this->requireEntity($request);
        $includeArchived = $request->boolean('include_archived');

        $documents = Document::query()
            ->where('documentable_type', get_class($entity))
            ->where('documentable_id', $entity->getKey())
            ->when(! $includeArchived, fn ($q) => $q->where('status', Document::STATUS_ACTIVE))
            ->with(['category', 'uploader'])
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $documents->map(fn (Document $document) => $this->present($document)),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'entity' => ['required', 'string'],
            'entity_id' => ['required', 'integer'],
            'category_id' => ['required', 'integer'],
            'file' => ['required', 'file'],
            'title' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $institute = $this->requireInstitute($request);
        $slug = $this->validatedEntitySlug($request);

        $document = $this->documents->upload(
            instituteId: (int) $institute->id,
            entitySlug: $slug,
            entityId: (int) $request->integer('entity_id'),
            categoryId: (int) $request->integer('category_id'),
            file: $this->file($request),
            actorId: $this->actorId($request),
            actingBranchId: $this->actingBranchId($request),
            title: $request->string('title')->toString(),
            description: $request->string('description')->toString(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded.',
            'data' => $this->present($document->load('category', 'uploader')),
        ], 201);
    }

    public function download(Document $document)
    {
        if (! Storage::disk($document->disk)->exists($document->file_path)) {
            abort(404);
        }

        $response = Storage::disk($document->disk)->download($document->file_path, $document->original_filename);

        if ($response instanceof BinaryFileResponse) {
            return $response;
        }

        if ($response instanceof StreamedResponse) {
            return $response;
        }

        return $response;
    }

    public function replace(Request $request, Document $document)
    {
        $request->validate([
            'file' => ['required', 'file'],
        ]);

        $document = $this->documents->replace($document, $this->file($request), $this->actorId($request));

        return response()->json([
            'success' => true,
            'message' => 'Document replaced (version '.$document->version.').',
            'data' => $this->present($document->load('category', 'uploader')),
        ]);
    }

    public function update(Request $request, Document $document)
    {
        $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category_id' => ['nullable', 'integer'],
        ]);

        $document = $this->documents->update($document, $request->only('title', 'description', 'category_id'), $this->actorId($request));

        return response()->json([
            'success' => true,
            'message' => 'Document updated.',
            'data' => $this->present($document->load('category', 'uploader')),
        ]);
    }

    public function archive(Request $request, Document $document)
    {
        $this->documents->archive($document, $this->actorId($request));

        return response()->json([
            'success' => true,
            'message' => 'Document archived.',
            'data' => $this->present($document->fresh()->load('category', 'uploader')),
        ]);
    }

    public function restore(Request $request, Document $document)
    {
        $this->documents->restoreFromArchive($document, $this->actorId($request));

        return response()->json([
            'success' => true,
            'message' => 'Document restored.',
            'data' => $this->present($document->fresh()->load('category', 'uploader')),
        ]);
    }

    public function destroy(Request $request, Document $document)
    {
        $this->documents->delete($document, $this->actorId($request));

        return response()->json([
            'success' => true,
            'message' => 'Document deleted.',
        ]);
    }

    public function forceDestroy(Request $request, Document $document)
    {
        $this->documents->forceDelete($document, $this->actorId($request));

        return response()->json([
            'success' => true,
            'message' => 'Document permanently deleted.',
        ]);
    }

    /**
     * Step 51 — Verify a document.
     */
    public function verify(Request $request, Document $document)
    {
        $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $document = $this->verification->verify(
            $document,
            (int) $this->actorId($request),
            $request->string('notes')->toString() ?: null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Document verified.',
            'data' => $this->present($document->load('category', 'uploader', 'verifier')),
        ]);
    }

    /**
     * Step 51 — Reject a document (reason required).
     */
    public function reject(Request $request, Document $document)
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $document = $this->verification->reject(
            $document,
            (int) $this->actorId($request),
            $request->string('reason')->toString(),
            $request->string('notes')->toString() ?: null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Document rejected.',
            'data' => $this->present($document->load('category', 'uploader', 'verifier')),
        ]);
    }

    /**
     * Step 51 — Version history for a document.
     */
    public function versions(Document $document)
    {
        $versions = $document->versions()->with('uploader')->get()->map(fn ($v) => [
            'version' => $v->version,
            'original_filename' => $v->original_filename,
            'mime_type' => $v->mime_type,
            'file_size' => $v->file_size,
            'uploaded_by' => $v->uploader?->name ?? 'System',
            'created_at' => optional($v->created_at)->format('d M Y H:i'),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'current_version' => $document->version,
                'versions' => $versions,
            ],
        ]);
    }

    /**
     * Step 51 — Document requirement checklist + readiness for a student.
     */
    public function checklist(Request $request, Student $student)
    {
        abort_if((int) $student->institute_id !== (int) $this->requireInstitute($request)->id, 404);

        $stage = $request->query('stage');

        $result = $this->checklist->forStudent($student, $stage !== null && $stage !== '' ? (string) $stage : null);

        return response()->json([
            'success' => true,
            'data' => [
                'student_id' => $student->id,
                'stage' => $stage,
                'readiness' => $result['readiness'],
                'summary' => $result['summary'],
                'requirements' => $result['requirements'],
            ],
        ]);
    }

    private function present(Document $document): array
    {
        return [
            'id' => $document->id,
            'title' => $document->title,
            'description' => $document->description,
            'category' => $document->category?->name,
            'category_id' => $document->category_id,
            'original_filename' => $document->original_filename,
            'mime_type' => $document->mime_type,
            'extension' => $document->extension,
            'file_size' => $document->file_size,
            'size_label' => $this->sizeLabel((int) $document->file_size),
            'version' => $document->version,
            'status' => $document->status,
            'verification_status' => $document->verification_status,
            'effective_verification_status' => $document->effectiveVerificationStatus(),
            'verified_by' => $document->verifier?->name,
            'verified_at' => optional($document->verified_at)->format('d M Y H:i'),
            'rejection_reason' => $document->rejection_reason,
            'issue_date' => optional($document->issue_date)->format('d M Y'),
            'expiry_date' => optional($document->expiry_date)->format('d M Y'),
            'is_expired' => $document->isExpired(),
            'is_expiring_soon' => $document->isExpiringSoon(),
            'source' => $document->source,
            'uploaded_by' => $document->uploader?->name ?? 'System',
            'created_at' => optional($document->created_at)->format('d M Y H:i'),
            'download_url' => route('documents.download', $document),
            'replace_url' => route('documents.replace', $document),
            'update_url' => route('documents.update', $document),
            'archive_url' => route('documents.archive', $document),
            'restore_url' => route('documents.restore', $document),
            'delete_url' => route('documents.destroy', $document),
            'verify_url' => route('documents.verify', $document),
            'reject_url' => route('documents.reject', $document),
            'versions_url' => route('documents.versions', $document),
        ];
    }

    private function sizeLabel(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }

    private function validatedEntitySlug(Request $request): string
    {
        $slug = (string) $request->input('entity', '');

        abort_if(config("documents.entities.{$slug}") === null, 422, 'Unknown document entity.');

        return $slug;
    }

    /**
     * Resolve + tenant-verify the owning entity from request input.
     *
     * @return Model
     */
    private function requireEntity(Request $request)
    {
        $request->validate([
            'entity' => ['required', 'string'],
            'id' => ['required', 'integer'],
        ]);

        $institute = $this->requireInstitute($request);

        return $this->documents->resolveEntity(
            $this->validatedEntitySlug($request),
            (int) $request->integer('id'),
            (int) $institute->id
        );
    }

    private function file(Request $request): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');

        return $file;
    }
}
