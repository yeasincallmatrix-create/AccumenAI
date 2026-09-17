<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\PhysiotherapyExercise;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PhysiotherapyExerciseController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical.physiotherapy.view', only: ['index', 'show']),
            new Middleware('permission:medical.physiotherapy.plan.create', only: ['create', 'store']),
            new Middleware('permission:medical.physiotherapy.plan.edit', only: ['edit', 'update']),
            new Middleware('permission:medical.physiotherapy.plan.create', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $query = PhysiotherapyExercise::where('institute_id', $this->instituteId());

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('body_area')) {
            $query->where('body_area', $request->body_area);
        }
        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $exercises = $query->orderBy('name')->paginate(25)->withQueryString();

        $categories = PhysiotherapyExercise::where('institute_id', $this->instituteId())
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('medical.physiotherapy.exercises.index', compact('exercises', 'categories'));
    }

    public function create()
    {
        $categories = PhysiotherapyExercise::CATEGORIES;
        $bodyAreas = PhysiotherapyExercise::BODY_AREAS;
        $difficulties = PhysiotherapyExercise::DIFFICULTIES;

        return view('medical.physiotherapy.exercises.create', compact('categories', 'bodyAreas', 'difficulties'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'category' => 'required|string|in:' . implode(',', array_keys(PhysiotherapyExercise::CATEGORIES)),
            'body_area' => 'required|string|in:' . implode(',', array_keys(PhysiotherapyExercise::BODY_AREAS)),
            'difficulty' => 'required|string|in:' . implode(',', array_keys(PhysiotherapyExercise::DIFFICULTIES)),
            'default_sets' => 'nullable|integer|min:1|max:20',
            'default_reps' => 'nullable|integer|min:1|max:100',
            'default_hold_seconds' => 'nullable|integer|min:1|max:3600',
            'instructions' => 'nullable|string|max:2000',
        ]);

        $data = $request->all();
        $data['institute_id'] = $this->instituteId();
        $data['is_active'] = true;

        $exercise = PhysiotherapyExercise::create($data);

        ClinicalAuditLog::record($exercise, 'created');

        return redirect()
            ->route('medical.physiotherapy.exercises.show', $exercise)
            ->with('status', 'Exercise created: ' . $exercise->name);
    }

    public function show(PhysiotherapyExercise $exercise)
    {
        $this->ensureSameInstitute($exercise, 'physiotherapy_exercise');

        return view('medical.physiotherapy.exercises.show', ['exercise' => $exercise]);
    }

    public function edit(PhysiotherapyExercise $exercise)
    {
        $this->ensureSameInstitute($exercise, 'physiotherapy_exercise');

        $categories = PhysiotherapyExercise::CATEGORIES;
        $bodyAreas = PhysiotherapyExercise::BODY_AREAS;
        $difficulties = PhysiotherapyExercise::DIFFICULTIES;

        return view('medical.physiotherapy.exercises.edit', [
            'exercise' => $exercise,
            'categories' => $categories,
            'bodyAreas' => $bodyAreas,
            'difficulties' => $difficulties,
        ]);
    }

    public function update(Request $request, PhysiotherapyExercise $exercise)
    {
        $this->ensureSameInstitute($exercise, 'physiotherapy_exercise');

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'category' => 'required|string|in:' . implode(',', array_keys(PhysiotherapyExercise::CATEGORIES)),
            'body_area' => 'required|string|in:' . implode(',', array_keys(PhysiotherapyExercise::BODY_AREAS)),
            'difficulty' => 'required|string|in:' . implode(',', array_keys(PhysiotherapyExercise::DIFFICULTIES)),
            'default_sets' => 'nullable|integer|min:1|max:20',
            'default_reps' => 'nullable|integer|min:1|max:100',
            'default_hold_seconds' => 'nullable|integer|min:1|max:3600',
            'instructions' => 'nullable|string|max:2000',
            'is_active' => 'nullable|boolean',
        ]);

        $original = ClinicalAuditLog::snapshot($exercise);

        $exercise->update($request->only([
            'name', 'description', 'category', 'body_area',
            'difficulty', 'default_sets', 'default_reps', 'default_hold_seconds',
            'instructions', 'is_active',
        ]));

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($exercise->refresh()));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($exercise, 'updated', ['old' => $old, 'new' => $new]);
        }

        return redirect()
            ->route('medical.physiotherapy.exercises.show', $exercise)
            ->with('status', 'Exercise updated.');
    }

    public function destroy(PhysiotherapyExercise $exercise)
    {
        $this->ensureSameInstitute($exercise, 'physiotherapy_exercise');

        ClinicalAuditLog::record($exercise, 'archived');

        $exercise->delete();

        return redirect()
            ->route('medical.physiotherapy.exercises.index')
            ->with('status', 'Exercise archived.');
    }
}
