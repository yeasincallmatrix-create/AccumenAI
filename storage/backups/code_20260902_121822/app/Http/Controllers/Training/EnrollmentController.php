<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Training\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class EnrollmentController extends Controller
{
    public function index(Request $request): View
    {
        $instituteId = (int) $request->user()->institute_id;
        $enrollments = Enrollment::with(['batch', 'trainee'])
            ->where('institute_id', $instituteId)
            ->orderByDesc('id')
            ->paginate(25);

        return view('training.enrollments.index', compact('enrollments'));
    }

    public function create(Request $request): View
    {
        $instituteId = (int) $request->user()->institute_id;
        $batches = Batch::where('institute_id', $instituteId)
            ->whereIn('status', ['upcoming', 'ongoing', 'completed'])
            ->orderBy('name')
            ->get()
            ->map(function ($batch) {
                $enrolled = \App\Models\Training\Enrollment::where('batch_id', $batch->id)->count();
                $batch->enrolled_count = $enrolled;
                $batch->remaining = !empty($batch->capacity) ? max(0, (int) $batch->capacity - $enrolled) : ($batch->seat_capacity ? max(0, (int) $batch->seat_capacity - $enrolled) : null);
                return $batch;
            });
        $trainees = \App\Models\Student::withoutGlobalScope('branch')
            ->where('institute_id', $instituteId)
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'student_id', 'student_id_number', 'reg_no']);

        if ($trainees->isEmpty()) {
            $userIds = \App\Models\Membership::where('institution_id', $instituteId)
                ->where('status', 'active')
                ->pluck('user_id');
            $trainees = \App\Models\User::whereIn('id', $userIds)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name as first_name', 'email'])
                ->map(function ($u) {
                    $u->last_name = '';
                    $u->reg_no = $u->id;
                    return $u;
                });
        }

        return view('training.enrollments.create', compact('batches', 'trainees'));
    }

    public function store(Request $request): RedirectResponse
    {
        $instituteId = (int) $request->user()->institute_id;

        $request->validate([
            'batch_id' => 'required|exists:batches,id',
            'trainee_id' => 'required|exists:students,id',
            'roll_no' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('enrollments')->where(function ($query) use ($request, $instituteId) {
                    return $query->where('institute_id', $instituteId)
                        ->where('batch_id', $request->batch_id);
                }),
            ],
        ]);

        $batch = Batch::where('institute_id', $instituteId)->findOrFail($request->batch_id);

        // Tenant check for batch
        if ((int) $batch->institute_id !== $instituteId) {
            abort(403);
        }

        // Duplicate check
        $exists = Enrollment::where('batch_id', $batch->id)
            ->where('trainee_id', $request->trainee_id)
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['trainee_id' => 'This trainee is already enrolled in this batch.']);
        }

        // Capacity check
        if (!empty($batch->capacity)) {
            $currentCount = Enrollment::where('batch_id', $batch->id)->count();
            if ($currentCount >= (int) $batch->capacity) {
                throw ValidationException::withMessages(['batch_id' => 'This batch has reached its maximum capacity (' . $batch->capacity . ').']);
            }
        }

        DB::transaction(function () use ($request, $batch, $instituteId) {
            Enrollment::create([
                'institute_id' => $instituteId,
                'batch_id' => $batch->id,
                'trainee_id' => $request->trainee_id,
                'student_id' => $request->trainee_id,
                'roll_no' => (int) $request->roll_no,
                'enrollment_date' => now()->toDateString(),
                'status' => 'active',
                'payment_status' => 'pending',
            ]);
        });

        return redirect()->route('training.enrollments.index')->with('status', 'Enrollment created successfully.');
    }

    public function update(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $instituteId = (int) $request->user()->institute_id;
        if ((int) $enrollment->institute_id !== $instituteId) {
            abort(403);
        }

        $request->validate([
            'roll_no' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('enrollments')->where(function ($query) use ($request, $instituteId, $enrollment) {
                    return $query->where('institute_id', $instituteId)
                        ->where('batch_id', $enrollment->batch_id)
                        ->where('id', '!=', $enrollment->id);
                }),
            ],
        ]);

        $enrollment->update([
            'roll_no' => (int) $request->roll_no,
        ]);

        return redirect()->route('training.enrollments.index')->with('status', 'Enrollment updated successfully.');
    }

    public function destroy(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $instituteId = (int) $request->user()->institute_id;
        if ((int) $enrollment->institute_id !== $instituteId) {
            abort(403);
        }
        $enrollment->delete();

        return redirect()->route('training.enrollments.index')->with('status', 'Enrollment removed.');
    }
}
