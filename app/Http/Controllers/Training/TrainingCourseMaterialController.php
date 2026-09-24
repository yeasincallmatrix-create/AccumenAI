<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Training\TrainingCourse;
use App\Models\Training\TrainingCourseMaterial;
use App\Services\CourseAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Training course material upload/delete — training_course_materials only.
 */
class TrainingCourseMaterialController extends Controller
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

    public function store(Request $request, TrainingCourse $course): RedirectResponse|JsonResponse
    {
        if ($course->institute_id === null || (int) $course->institute_id !== (int) $request->user()->institute_id) {
            abort(403, 'This course does not belong to your institute.');
        }

        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'title' => ['nullable', 'string', 'max:200'],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ]);

        /** @var UploadedFile $file */
        $file = $data['file'];

        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages([
                'file' => 'This file type is not allowed. Upload a PDF, Word, Excel, PowerPoint, ZIP, text/CSV, or image document.',
            ]);
        }

        $instituteId = (int) $request->user()->institute_id;
        $path = $file->store('course-materials/'.$instituteId, ['disk' => 'public']);

        if ($path === false) {
            throw ValidationException::withMessages([
                'file' => 'The file could not be stored. Please try again.',
            ]);
        }

        $material = TrainingCourseMaterial::create([
            'institute_id' => $instituteId,
            'course_id' => $course->id,
            'curriculum_module_id' => null,
            'title' => $data['title'] ?? $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'display_order' => (int) ($data['display_order'] ?? 0),
            'status' => TrainingCourseMaterial::STATUS_ACTIVE,
            'uploaded_by' => $instituteId ? (int) $request->user()->id : null,
        ]);

        $this->audit->record($instituteId, (int) $request->user()->id, 'training_course_material_uploaded', $material->id, null, [
            'title' => $material->title,
            'file_path' => $material->file_path,
            'file_type' => $material->file_type,
            'file_size' => $material->file_size,
        ], 'training_course_materials');

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Material uploaded.', 'data' => ['id' => $material->id]]);
        }

        return redirect()->back()->with('status', 'Material uploaded successfully.');
    }

    public function destroy(Request $request, TrainingCourse $course, TrainingCourseMaterial $material): RedirectResponse|JsonResponse
    {
        if ((int) $material->institute_id !== (int) $request->user()->institute_id) {
            abort(403);
        }

        $old = [
            'title' => $material->title,
            'file_path' => $material->file_path,
        ];

        Storage::disk('public')->delete($material->file_path);
        $material->delete();

        $this->audit->record((int) $material->institute_id, (int) $request->user()->id, 'training_course_material_deleted', $material->id, $old, null, 'training_course_materials');

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Material deleted.', 'data' => ['id' => $material->id]]);
        }

        return redirect()->back()->with('status', 'Material deleted.');
    }
}
