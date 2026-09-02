<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\CrmContact;
use App\Models\CrmLead;
use App\Models\CrmOrganization;
use App\Models\Document;
use App\Models\Documentable;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\HrEmployee;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Step 46 — Generic Document Management service.
 *
 * One reusable implementation of the full document lifecycle:
 *   upload → validate → store → link to entity → categorize → download →
 *   replace (version++) → archive/delete → audit.
 *
 * Entities are resolved from a config map and always verified against the
 * acting institute (never trusted from request input). Files are stored on
 * the application's public disk with a random UUID name and a server-side
 * MIME whitelist, mirroring the existing upload convention.
 */
class DocumentService
{
    public function __construct(private readonly DocumentAuditService $audit) {}

    /**
     * Resolve and tenant-verify an entity that documents can attach to.
     *
     * @return Model&Documentable
     */
    public function resolveEntity(string $slug, int $id, int $instituteId): Model
    {
        $definition = config("documents.entities.{$slug}");

        if ($definition === null) {
            abort(404);
        }

        /** @var class-string<Model> $model */
        $model = $definition['model'];

        /** @var Model|null $instance */
        $instance = $model::query()->find($id);

        if ($instance === null) {
            abort(404);
        }

        if ($model === Institute::class) {
            abort_if((int) $instance->getKey() !== $instituteId, 404);
        } elseif (isset($instance->institute_id)) {
            abort_if((int) $instance->institute_id !== $instituteId, 404);
        }

        return $instance;
    }

    /**
     * The branch a new document inherits: the owning entity's branch when it
     * has one (Students, Batches, InstituteUsers, CRM rows), otherwise the
     * acting branch (or null for whole-institute records).
     */
    public function branchFor(Model $entity, ?int $actingBranchId): ?int
    {
        if ($entity instanceof Student
            || $entity instanceof Batch
            || $entity instanceof InstituteUser
            || $entity instanceof CrmLead
            || $entity instanceof CrmContact
            || $entity instanceof CrmOrganization
            || $entity instanceof HrEmployee) {
            return $entity->branch_id !== null ? (int) $entity->branch_id : null;
        }

        return $actingBranchId;
    }

    /**
     * Active categories available to an entity (global + institute-scoped).
     *
     * @return Collection<int, DocumentCategory>
     */
    public function categories(int $instituteId, string $entitySlug)
    {
        return DocumentCategory::query()
            ->where(fn ($q) => $q->whereNull('institute_id')->orWhere('institute_id', $instituteId))
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (DocumentCategory $category) => $category->appliesTo($entitySlug))
            ->values();
    }

    public function upload(
        int $instituteId,
        string $entitySlug,
        int $entityId,
        int $categoryId,
        UploadedFile $file,
        ?int $actorId,
        ?int $actingBranchId = null,
        ?string $title = null,
        ?string $description = null,
        ?string $documentNumber = null,
        ?string $issueDate = null,
        ?string $expiryDate = null,
    ): Document {
        $entity = $this->resolveEntity($entitySlug, $entityId, $instituteId);
        $category = $this->requireCategory($categoryId, $entitySlug);
        $this->validateFile($file);

        $extension = $this->extensionFor($file);
        $disk = \App\Support\StorageConfig::disk();
        // Fallback to documents disk if platform not configured
        if (! in_array($disk, ['local', 'public', 's3'], true)) {
            $disk = config('documents.disk', 'public');
        }
        $path = $file->storeAs(
            'documents/'.$instituteId.'/'.$entitySlug,
            Str::uuid()->toString().'.'.$extension,
            ['disk' => $disk]
        );

        if ($path === false) {
            throw ValidationException::withMessages([
                'file' => 'The file could not be stored. Please try again.',
            ]);
        }

        $document = Document::create([
            'institute_id' => $instituteId,
            'branch_id' => $this->branchFor($entity, $actingBranchId),
            'documentable_type' => get_class($entity),
            'documentable_id' => $entity->getKey(),
            'category_id' => $category->id,
            'title' => $title !== null && trim($title) !== '' ? trim($title) : null,
            'description' => $description !== null && trim($description) !== '' ? trim($description) : null,
            'document_number' => $documentNumber !== null && trim($documentNumber) !== '' ? trim($documentNumber) : null,
            'issue_date' => $issueDate ?: null,
            'expiry_date' => $expiryDate ?: null,
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'disk' => $disk,
            'mime_type' => $file->getMimeType(),
            'extension' => $extension,
            'file_size' => $file->getSize(),
            'checksum' => hash_file('sha256', (string) $file->getRealPath()),
            'version' => 1,
            'uploaded_by' => $actorId,
            'status' => Document::STATUS_ACTIVE,
            'verification_status' => Document::VERIFICATION_PENDING,
            'source' => Document::SOURCE_UPLOADED,
        ]);

        $this->audit->record($instituteId, $actorId, 'document_uploaded', $document->id, null, [
            'title' => $document->title,
            'category_id' => $document->category_id,
            'documentable_type' => $document->documentable_type,
            'documentable_id' => $document->documentable_id,
            'original_filename' => $document->original_filename,
            'file_path' => $document->file_path,
            'mime_type' => $document->mime_type,
            'file_size' => $document->file_size,
            'version' => $document->version,
        ]);

        return $document;
    }

    /**
     * Replace the physical file behind an existing document. Step 51: the
     * previous version is preserved in document_versions (history is never
     * destroyed) and the version increments. Metadata is refreshed; the row
     * stays linked to the same entity/category.
     */
    public function replace(Document $document, UploadedFile $file, ?int $actorId): Document
    {
        $this->validateFile($file);

        $extension = $this->extensionFor($file);
        $disk = \App\Support\StorageConfig::disk();
        if (! in_array($disk, ['local', 'public', 's3'], true)) {
            $disk = config('documents.disk', 'public');
        }
        $path = $file->storeAs(
            'documents/'.$document->institute_id.'/'.$this->entitySlugFor($document->documentable_type),
            Str::uuid()->toString().'.'.$extension,
            ['disk' => $disk]
        );

        if ($path === false) {
            throw ValidationException::withMessages([
                'file' => 'The file could not be stored. Please try again.',
            ]);
        }

        $old = $this->snapshot($document);

        // Preserve the outgoing version in history before overwriting.
        DocumentVersion::create([
            'document_id' => $document->id,
            'version' => $document->version,
            'original_filename' => $document->original_filename,
            'file_path' => $document->file_path,
            'disk' => $document->disk,
            'mime_type' => $document->mime_type,
            'extension' => $document->extension,
            'file_size' => $document->file_size,
            'checksum' => $document->checksum,
            'uploaded_by' => $document->uploaded_by,
            'created_at' => now(),
        ]);

        $document->update([
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'disk' => $disk,
            'mime_type' => $file->getMimeType(),
            'extension' => $extension,
            'file_size' => $file->getSize(),
            'checksum' => hash_file('sha256', (string) $file->getRealPath()),
            'version' => $document->version + 1,
            'verification_status' => Document::VERIFICATION_PENDING,
            'verified_by' => null,
            'verified_at' => null,
            'rejection_reason' => null,
        ]);

        $this->audit->record((int) $document->institute_id, $actorId, 'document_replaced', $document->id, $old, $this->snapshot($document));

        return $document;
    }

    public function update(Document $document, array $data, ?int $actorId): Document
    {
        $category = isset($data['category_id'])
            ? $this->requireCategory((int) $data['category_id'], $this->entitySlugFor($document->documentable_type))
            : null;

        $old = $this->snapshot($document);

        $document->update([
            'title' => isset($data['title']) ? trim((string) $data['title']) : $document->title,
            'description' => isset($data['description']) ? trim((string) $data['description']) : $document->description,
            'document_number' => array_key_exists('document_number', $data) ? (trim((string) $data['document_number']) !== '' ? trim((string) $data['document_number']) : null) : $document->document_number,
            'issue_date' => array_key_exists('issue_date', $data) ? ($data['issue_date'] ?: null) : $document->issue_date,
            'expiry_date' => array_key_exists('expiry_date', $data) ? ($data['expiry_date'] ?: null) : $document->expiry_date,
            'category_id' => $category?->id ?? $document->category_id,
        ]);

        $this->audit->record((int) $document->institute_id, $actorId, 'document_updated', $document->id, $old, $this->snapshot($document));

        return $document;
    }

    public function archive(Document $document, ?int $actorId): void
    {
        $old = $this->snapshot($document);

        $document->update(['status' => Document::STATUS_ARCHIVED]);

        $this->audit->record((int) $document->institute_id, $actorId, 'document_archived', $document->id, $old, $this->snapshot($document));
    }

    public function restoreFromArchive(Document $document, ?int $actorId): void
    {
        $old = $this->snapshot($document);

        $document->update(['status' => Document::STATUS_ACTIVE]);

        $this->audit->record((int) $document->institute_id, $actorId, 'document_restored', $document->id, $old, $this->snapshot($document));
    }

    /**
     * Soft-delete the document row. The physical file is kept so a future
     * restore is possible; forceDelete removes it permanently.
     */
    public function delete(Document $document, ?int $actorId): void
    {
        $old = $this->snapshot($document);

        $document->delete();

        $this->audit->record((int) $document->institute_id, $actorId, 'document_deleted', $document->id, $old, null);
    }

    /**
     * Permanently remove the row and its physical file.
     */
    public function forceDelete(Document $document, ?int $actorId): void
    {
        $old = $this->snapshot($document);

        Storage::disk($document->disk)->delete($document->file_path);

        $document->forceDelete();

        $this->audit->record((int) $document->institute_id, $actorId, 'document_force_deleted', $document->id, $old, null);
    }

    /**
     * Server-side MIME + size validation (never trusts the client).
     */
    public function validateFile(UploadedFile $file): void
    {
        $mime = (string) $file->getMimeType();

        if (! in_array($mime, config('documents.allowed_mimes', []), true)) {
            throw ValidationException::withMessages([
                'file' => 'This file type is not allowed. Upload a PDF, Word, Excel, PowerPoint, ZIP, text/CSV, or image document.',
            ]);
        }

        $maxBytes = (int) \App\Support\StorageConfig::maxSizeKb() * 1024;

        if ($file->getSize() > $maxBytes) {
            throw ValidationException::withMessages([
                'file' => 'The file must not be larger than '.\App\Support\StorageConfig::maxSizeKb().' KB.',
            ]);
        }
    }

    private function requireCategory(int $categoryId, string $entitySlug): DocumentCategory
    {
        $category = DocumentCategory::query()->find($categoryId);

        abort_if($category === null || ! $category->appliesTo($entitySlug), 422, 'The selected category is not valid for this entity.');

        return $category;
    }

    private function extensionFor(UploadedFile $file): string
    {
        $mime = (string) $file->getMimeType();
        $extension = config("documents.mime_extensions.{$mime}");

        if ($extension === null) {
            $extension = strtolower((string) $file->getClientOriginalExtension());
        }

        return $extension !== '' ? $extension : 'bin';
    }

    private function entitySlugFor(string $documentableType): string
    {
        foreach ((array) config('documents.entities', []) as $slug => $definition) {
            if (($definition['model'] ?? null) === $documentableType) {
                return $slug;
            }
        }

        return 'other';
    }

    private function snapshot(Document $document): array
    {
        return [
            'title' => $document->title,
            'category_id' => $document->category_id,
            'original_filename' => $document->original_filename,
            'file_path' => $document->file_path,
            'disk' => $document->disk,
            'mime_type' => $document->mime_type,
            'file_size' => $document->file_size,
            'checksum' => $document->checksum,
            'version' => $document->version,
            'status' => $document->status,
        ];
    }
}
