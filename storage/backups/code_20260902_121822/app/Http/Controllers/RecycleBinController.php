<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Exam;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Support\PasswordHash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Recycle bin for the institute-user panel. Lists soft-deleted students and
 * batches belonging to the current institute and allows restoring or
 * permanently deleting them.
 */
class RecycleBinController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));

        $students = Student::query()
            ->onlyTrashed()
            ->with('branch')
            ->search($q)
            ->latest('deleted_at')
            ->paginate(15)
            ->withQueryString();

        $batches = Batch::query()
            ->onlyTrashed()
            ->with('course:id,name')
            ->withCount(['exams as attended_exams' => fn ($q) => $q->whereHas('results')])
            ->latest('deleted_at')
            ->paginate(15)
            ->withQueryString();

        return view('recycle.index', [
            'students' => $students,
            'batches' => $batches,
            'q' => $q,
        ]);
    }

    public function restore(Request $request, Student $student): RedirectResponse|JsonResponse
    {
        if ($student->deleted_at === null) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student is not in the recycle bin.',
                ], 422);
            }

            return redirect()->route('recycle.index')->with('status', 'Student is not in the recycle bin.');
        }

        $student->restore();

        $message = "Student {$student->full_name} restored.";

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return redirect()->route('recycle.index')->with('status', $message);
    }

    public function forceDelete(Request $request, Student $student): RedirectResponse|JsonResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        $user = $request->user();
        if (! PasswordHash::safeCheck((string) $request->input('password'), (string) $user->getAuthPassword())) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your password is incorrect.',
                    'errors' => ['password' => ['Your password is incorrect.']],
                ], 422);
            }

            return back()->withErrors(['password' => 'Your password is incorrect.']);
        }

        if ($student->photo) {
            Storage::disk('public')->delete($student->photo);
        }
        if ($student->document) {
            Storage::disk('public')->delete($student->document);
        }

        $name = $student->full_name;
        $student->forceDelete();

        $message = "Student {$name} permanently deleted.";

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return redirect()->route('recycle.index')->with('status', $message);
    }

    public function restoreBatch(Request $request, Batch $batch): RedirectResponse|JsonResponse
    {
        if ($batch->deleted_at === null) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Batch is not in the recycle bin.',
                ], 422);
            }

            return redirect()->route('recycle.index')->with('status', 'Batch is not in the recycle bin.');
        }

        $batch->restore();

        $message = "Batch {$batch->name} restored.";

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return redirect()->route('recycle.index')->with('status', $message);
    }

    public function forceDeleteBatch(Request $request, Batch $batch): RedirectResponse|JsonResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        $user = $request->user();
        if (! PasswordHash::safeCheck((string) $request->input('password'), (string) $user->getAuthPassword())) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your password is incorrect.',
                    'errors' => ['password' => ['Your password is incorrect.']],
                ], 422);
            }

            return back()->withErrors(['password' => 'Your password is incorrect.']);
        }

        $attended = Exam::query()
            ->where('batch_id', $batch->id)
            ->whereHas('results')
            ->exists();

        if ($attended) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => mawa_lang('batches.cannot_delete_attended'),
                ], 422);
            }

            return back()->withErrors(['batch' => mawa_lang('batches.cannot_delete_attended')]);
        }

        $name = $batch->name;
        $batch->forceDelete();

        $message = "Batch {$name} permanently deleted.";

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return redirect()->route('recycle.index')->with('status', $message);
    }
}
