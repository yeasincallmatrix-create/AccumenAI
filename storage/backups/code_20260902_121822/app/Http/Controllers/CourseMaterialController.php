<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\CurriculumModule;
use App\Services\CourseMaterialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Course material upload/delete (Step 42).
 *
 * Materials are course-scoped (optionally attached to a curriculum module).
 * Files are validated and stored on the public disk by CourseMaterialService.
 */
class CourseMaterialController extends Controller
{
    public function __construct(private readonly CourseMaterialService $materials) {}

    public function store(Request $request, Course $course): RedirectResponse|JsonResponse
    {
        if ($course->institute_id === null || (int) $course->institute_id !== (int) $request->user()->institute_id) {
            abort(403, 'This course does not belong to your institute.');
        }

        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'title' => ['nullable', 'string', 'max:200'],
            'curriculum_module_id' => ['nullable', 'integer', Rule::exists('curriculum_modules', 'id')],
            'display_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if (filled($data['curriculum_module_id'] ?? null)) {
            $module = CurriculumModule::query()->with('curriculum')->find((int) $data['curriculum_module_id']);
            if ($module === null || (int) $module->curriculum?->course_id !== (int) $course->id || (int) $module->institute_id !== (int) $request->user()->institute_id) {
                if ($request->expectsJson()) {
                    return response()->json(['success' => false, 'message' => 'The selected module does not belong to this course.'], 422);
                }

                return back()->withErrors(['curriculum_module_id' => 'The selected module does not belong to this course.']);
            }
        }

        $user = $request->user();

        try {
            $material = $this->materials->upload((int) $user->institute_id, $course, $data, (int) $user->id);
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->errors()['file'][0] ?? 'The file could not be uploaded.', 'errors' => $e->errors()], 422);
            }

            return back()->withErrors($e->errors());
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Material uploaded.', 'data' => ['id' => $material->id]]);
        }

        return redirect()->back()->with('status', 'Material uploaded successfully.');
    }

    public function destroy(Request $request, Course $course, CourseMaterial $material): RedirectResponse|JsonResponse
    {
        if ((int) $material->institute_id !== (int) $request->user()->institute_id) {
            abort(403);
        }

        $this->materials->destroy($material, (int) $request->user()->id);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Material deleted.', 'data' => ['id' => $material->id]]);
        }

        return redirect()->back()->with('status', 'Material deleted.');
    }
}
