<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Training\TrainingClass;
use Illuminate\Http\Request;

class TrainingClassController extends Controller
{
    private const INDEX_COLUMNS = ['serial', 'code', 'class', 'category', 'mode', 'fee', 'subjects', 'batches', 'status'];

    public function index(Request $request)
    {
        $instituteId = auth()->user()->institute_id;
        $q = trim((string) $request->query('q'));
        $branchId = $request->query('branch_id');

        $classes = TrainingClass::where('institute_id', $instituteId)
            ->orderByDesc('id')->paginate(20)->withQueryString();

        $visibleColumns = $request->user()->preference('columns_training_classes', self::INDEX_COLUMNS);
        $visibleColumns = array_values(array_intersect(self::INDEX_COLUMNS, (array) $visibleColumns));

        return view('training.classes.index', [
            'classes' => $classes,
            'q' => $q,
            'branchId' => $branchId,
            'branches' => Branch::where('institute_id', $instituteId)->orderBy('name')->get(),
            'visibleColumns' => $visibleColumns,
            'classesCount' => $classes->total(),
        ]);
    }

    public function create()
    {
        return view('training.classes.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'code' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:30',
        ]);
        $class = new TrainingClass($validated);
        $class->institute_id = auth()->user()->institute_id;
        $class->save();
        return redirect()->route('training.classes.index')
            ->with('success', 'Class created.');
    }

    public function show($id)
    {
        $class = TrainingClass::findOrFail($id);
        return view('training.classes.show', compact('class'));
    }

    public function edit($id)
    {
        $class = TrainingClass::findOrFail($id);
        return view('training.classes.edit', compact('class'));
    }

    public function update(Request $request, $id)
    {
        $class = TrainingClass::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'code' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:30',
        ]);
        $class->update($validated);
        return redirect()->route('training.classes.index')
            ->with('success', 'Class updated.');
    }

    public function destroy($id)
    {
        TrainingClass::findOrFail($id)->delete();
        return redirect()->route('training.classes.index')
            ->with('success', 'Class deleted.');
    }
}
