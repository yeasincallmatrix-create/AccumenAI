<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\HrEmployee;
use App\Services\DocumentService;
use App\Services\DocumentVerificationService;
use App\Services\HrDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * HR-3 — Employee Document Management endpoints.
 *
 * Reuses the generic DocumentService / DocumentVerificationService storage layer
 * (polymorphic hr-employee). All endpoints are tenant + branch isolated via
 * ResolvesInstitute + HrEmployee branch checks; every action is audited.
 */
class HrDocumentController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly DocumentService $documents,
        private readonly DocumentVerificationService $verification,
        private readonly HrDocumentService $hrDocs,
    ) {}

    private function can(Request $request, array $perms): bool
    {
        foreach ($perms as $perm) {
            if ($request->user()->hasPermission($perm)) {
                return true;
            }
        }
        return false;
    }

    private function ensureSameInstitute(HrEmployee $employee, int $instituteId, ?int $actingBranchId = null): void
    {
        abort_if((int) $employee->institute_id !== $instituteId, 404);
        if ($actingBranchId !== null && $employee->branch_id !== null && (int) $employee->branch_id !== (int) $actingBranchId) {
            abort(404);
        }
    }

    private function ensureDocumentAccess(Document $document, int $instituteId, ?int $actingBranchId = null): void
    {
        abort_if((int) $document->institute_id !== $instituteId, 404);
        if ($actingBranchId !== null && $document->branch_id !== null && (int) $document->branch_id !== (int) $actingBranchId) {
            abort(404);
        }
        // Also ensure document belongs to hr-employee of same institute when acting branch is set
        if ($document->documentable_type === HrEmployee::class && $actingBranchId !== null) {
            $emp = HrEmployee::withoutGlobalScopes()->find($document->documentable_id);
            if ($emp && $emp->branch_id !== null && (int) $emp->branch_id !== (int) $actingBranchId) {
                abort(404);
            }
        }
    }

    public function categories(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $categories = $this->documents->categories((int) $institute->id, 'hr-employee')
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'is_required' => (bool) $c->is_required,
                'expiry_applicable' => (bool) $c->expiry_applicable,
                'verification_required' => (bool) $c->verification_required,
            ]);
        return response()->json(['success' => true, 'data' => $categories]);
    }

    public function index(Request $request, HrEmployee $hrEmployee)
    {
        $institute = $this->requireInstitute($request);
        $this->ensureSameInstitute($hrEmployee, (int) $institute->id, $this->actingBranchId($request));

        $docs = Document::query()
            ->where('documentable_type', HrEmployee::class)
            ->where('documentable_id', $hrEmployee->id)
            ->when(! $request->boolean('include_archived'), fn ($q) => $q->where('status', Document::STATUS_ACTIVE))
            ->with(['category', 'uploader', 'verifier'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Document $d) => $this->present($d));

        return response()->json(['success' => true, 'data' => $docs]);
    }

    public function store(Request $request, HrEmployee $hrEmployee)
    {
        $request->validate([
            'category_id' => ['required', 'integer'],
            'file' => ['required', 'file'],
            'title' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
        ]);

        $institute = $this->requireInstitute($request);
        $this->ensureSameInstitute($hrEmployee, (int) $institute->id, $this->actingBranchId($request));

        $document = $this->documents->upload(
            instituteId: (int) $institute->id,
            entitySlug: 'hr-employee',
            entityId: (int) $hrEmployee->id,
            categoryId: (int) $request->integer('category_id'),
            file: $request->file('file'),
            actorId: $this->actorId($request),
            actingBranchId: $this->actingBranchId($request),
            title: $request->string('title')->toString() ?: null,
            description: $request->string('description')->toString() ?: null,
            documentNumber: $request->string('document_number')->toString() ?: null,
            issueDate: $request->string('issue_date')->toString() ?: null,
            expiryDate: $request->string('expiry_date')->toString() ?: null,
        );

        return response()->json(['success' => true, 'message' => 'Document uploaded.', 'data' => $this->present($document->load('category', 'uploader'))], 201);
    }

    public function update(Request $request, Document $document)
    {
        $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'category_id' => ['nullable', 'integer'],
        ]);

        $institute = $this->requireInstitute($request);
        $this->ensureDocumentAccess($document, (int) $institute->id, $this->actingBranchId($request));
        abort_if($document->documentable_type !== HrEmployee::class, 404);

        $data = $request->only(['title', 'description', 'document_number', 'issue_date', 'expiry_date', 'category_id']);
        $document = $this->documents->update($document, $data, $this->actorId($request));

        return response()->json(['success' => true, 'message' => 'Document updated.', 'data' => $this->present($document->load('category', 'uploader', 'verifier'))]);
    }

    public function download(Request $request, Document $document)
    {
        $institute = $this->requireInstitute($request);
        $this->ensureDocumentAccess($document, (int) $institute->id, $this->actingBranchId($request));
        abort_if($document->documentable_type !== HrEmployee::class, 404);

        if (! Storage::disk($document->disk)->exists($document->file_path)) {
            abort(404);
        }

        $response = Storage::disk($document->disk)->download($document->file_path, $document->original_filename);
        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return $response;
        }
        return $response;
    }

    public function replace(Request $request, Document $document)
    {
        $request->validate(['file' => ['required', 'file']]);
        $institute = $this->requireInstitute($request);
        $this->ensureDocumentAccess($document, (int) $institute->id, $this->actingBranchId($request));
        abort_if($document->documentable_type !== HrEmployee::class, 404);

        $document = $this->documents->replace($document, $request->file('file'), $this->actorId($request));
        return response()->json(['success' => true, 'message' => 'Document replaced (version '.$document->version.').', 'data' => $this->present($document->load('category', 'uploader'))]);
    }

    public function archive(Request $request, Document $document)
    {
        $institute = $this->requireInstitute($request);
        $this->ensureDocumentAccess($document, (int) $institute->id, $this->actingBranchId($request));
        abort_if($document->documentable_type !== HrEmployee::class, 404);
        $this->documents->archive($document, $this->actorId($request));
        return response()->json(['success' => true, 'message' => 'Document archived.', 'data' => $this->present($document->fresh()->load('category', 'uploader'))]);
    }

    public function destroy(Request $request, Document $document)
    {
        $institute = $this->requireInstitute($request);
        $this->ensureDocumentAccess($document, (int) $institute->id, $this->actingBranchId($request));
        abort_if($document->documentable_type !== HrEmployee::class, 404);
        $this->documents->delete($document, $this->actorId($request));
        return response()->json(['success' => true, 'message' => 'Document deleted.']);
    }

    public function verify(Request $request, Document $document)
    {
        $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);
        $institute = $this->requireInstitute($request);
        $this->ensureDocumentAccess($document, (int) $institute->id, $this->actingBranchId($request));
        abort_if($document->documentable_type !== HrEmployee::class, 404);
        $document = $this->verification->verify($document, (int) $this->actorId($request), $request->string('notes')->toString() ?: null);
        return response()->json(['success' => true, 'message' => 'Document verified.', 'data' => $this->present($document->load('category', 'uploader', 'verifier'))]);
    }

    public function reject(Request $request, Document $document)
    {
        $request->validate(['reason' => ['required', 'string', 'max:2000'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $institute = $this->requireInstitute($request);
        $this->ensureDocumentAccess($document, (int) $institute->id, $this->actingBranchId($request));
        abort_if($document->documentable_type !== HrEmployee::class, 404);
        $document = $this->verification->reject($document, (int) $this->actorId($request), $request->string('reason')->toString(), $request->string('notes')->toString() ?: null);
        return response()->json(['success' => true, 'message' => 'Document rejected.', 'data' => $this->present($document->load('category', 'uploader', 'verifier'))]);
    }

    public function versions(Request $request, Document $document)
    {
        $institute = $this->requireInstitute($request);
        $this->ensureDocumentAccess($document, (int) $institute->id, $this->actingBranchId($request));
        abort_if($document->documentable_type !== HrEmployee::class, 404);
        $versions = $document->versions()->with('uploader')->get()->map(fn ($v) => [
            'version' => $v->version,
            'original_filename' => $v->original_filename,
            'mime_type' => $v->mime_type,
            'file_size' => $v->file_size,
            'uploaded_by' => $v->uploader?->name ?? 'System',
            'created_at' => optional($v->created_at)->format('d M Y H:i'),
        ]);
        return response()->json(['success' => true, 'data' => ['current_version' => $document->version, 'versions' => $versions]]);
    }

    public function expiring(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $days = (int) ($request->query('days', 30));
        $expired = $this->hrDocs->expiredDocuments((int) $institute->id, $branchId)->map(fn ($d) => $this->present($d->load('category')));
        $expiringSoon = $this->hrDocs->expiringSoonDocuments((int) $institute->id, $days, $branchId)->map(fn ($d) => $this->present($d->load('category')));
        return response()->json(['success' => true, 'data' => ['expired' => $expired, 'expiring_soon' => $expiringSoon]]);
    }

    public function missing(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);
        $missing = $this->hrDocs->missingRequiredDocuments((int) $institute->id, $branchId)->map(fn ($row) => [
            'employee' => [
                'id' => $row['employee']->id,
                'display_name' => $row['employee']->display_name,
                'employee_code' => $row['employee']->employee_code,
                'branch' => $row['employee']->branch?->name,
                'department' => $row['employee']->department?->name,
            ],
            'missing' => $row['missing']->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug]),
        ]);
        return response()->json(['success' => true, 'data' => $missing]);
    }

    private function present(Document $document): array
    {
        return [
            'id' => $document->id,
            'title' => $document->title,
            'description' => $document->description,
            'document_number' => $document->document_number,
            'category' => $document->category?->name,
            'category_id' => $document->category_id,
            'original_filename' => $document->original_filename,
            'mime_type' => $document->mime_type,
            'extension' => $document->extension,
            'file_size' => $document->file_size,
            'version' => $document->version,
            'status' => $document->status,
            'verification_status' => $document->verification_status,
            'effective_verification_status' => $document->effectiveVerificationStatus(),
            'verified_by' => $document->verifier?->name,
            'verified_at' => optional($document->verified_at)->format('d M Y H:i'),
            'rejection_reason' => $document->rejection_reason,
            'verification_notes' => $document->verification_notes,
            'issue_date' => optional($document->issue_date)->format('Y-m-d'),
            'expiry_date' => optional($document->expiry_date)->format('Y-m-d'),
            'is_expired' => $document->isExpired(),
            'is_expiring_soon' => $document->isExpiringSoon(),
            'source' => $document->source,
            'uploaded_by' => $document->uploader?->name ?? 'System',
            'created_at' => optional($document->created_at)->format('d M Y H:i'),
            'download_url' => route('hr.documents.download', $document),
            'replace_url' => route('hr.documents.replace', $document),
            'update_url' => route('hr.documents.update', $document),
            'archive_url' => route('hr.documents.archive', $document),
            'delete_url' => route('hr.documents.destroy', $document),
            'verify_url' => route('hr.documents.verify', $document),
            'reject_url' => route('hr.documents.reject', $document),
            'versions_url' => route('hr.documents.versions', $document),
        ];
    }
}
