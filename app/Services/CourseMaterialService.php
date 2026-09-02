<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseMaterial;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Course material management (Step 42).
 *
 * Uploads validate a strict whitelist (documents + images, no executables)
 * and land on the application's public disk under course-materials/{instituteId}.
 * Deleting a material removes the stored file and the row; each event is
 * audited with module='course_materials' via CourseAuditService.
 */
class CourseMaterialService
{
    private const ALLOWED_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/zip',
        'text/plain',
        'text/csv',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    public function __construct(private readonly CourseAuditService $audit) {}

    public function upload(int $instituteId, Course $course, array $data, int $actorId): CourseMaterial
    {
        /** @var UploadedFile $file */
        $file = $data['file'];

        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages([
                'file' => 'This file type is not allowed. Upload a PDF, Word, Excel, PowerPoint, ZIP, text/CSV, or image document.',
            ]);
        }

        $path = $file->store(
            'course-materials/'.$instituteId,
            ['disk' => 'public']
        );

        if ($path === false) {
            throw ValidationException::withMessages([
                'file' => 'The file could not be stored. Please try again.',
            ]);
        }

        $material = CourseMaterial::create([
            'institute_id' => $instituteId,
            'course_id' => $course->id,
            'curriculum_module_id' => $data['curriculum_module_id'] ?? null,
            'title' => $data['title'] ?? $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'display_order' => (int) ($data['display_order'] ?? 0),
            'status' => CourseMaterial::STATUS_ACTIVE,
            'uploaded_by' => $actorId,
        ]);

        $this->audit->record($instituteId, $actorId, 'course_material_uploaded', $material->id, null, [
            'title' => $material->title,
            'file_path' => $material->file_path,
            'file_type' => $material->file_type,
            'file_size' => $material->file_size,
        ], 'course_materials');

        return $material;
    }

    public function destroy(CourseMaterial $material, int $actorId): void
    {
        $old = [
            'title' => $material->title,
            'file_path' => $material->file_path,
        ];

        Storage::disk('public')->delete($material->file_path);

        $material->delete();

        $this->audit->record((int) $material->institute_id, $actorId, 'course_material_deleted', $material->id, $old, null, 'course_materials');
    }
}
