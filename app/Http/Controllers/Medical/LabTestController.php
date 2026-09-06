<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\LabTestRequest;
use App\Models\Medical\LabTest;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class LabTestController extends MedicalController implements HasMiddleware
{
    /**
     * NOTE: the resource param is {test} (singular of `lab/tests`), so
     * bound arguments must be named `$test` for implicit binding.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_lab.view', only: ['index', 'show']),
            new Middleware('permission:medical_lab.create', only: ['create', 'store']),
            new Middleware('permission:medical_lab.edit', only: ['edit', 'update']),
            new Middleware('permission:medical_lab.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $query = LabTest::where('institute_id', $this->instituteId());

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('code', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $tests = $query->orderBy('name')->paginate(20)->withQueryString();

        $categories = LabTest::where('institute_id', $this->instituteId())
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('medical.lab.tests.index', compact('tests', 'categories'));
    }

    public function create()
    {
        return view('medical.lab.tests.create');
    }

    public function store(LabTestRequest $request)
    {
        $data = $request->validated();
        $data['institute_id'] = $this->instituteId();

        $test = LabTest::create($data);

        return redirect()->route('medical.lab.tests.show', $test)
            ->with('status', 'Lab test created successfully!');
    }

    public function show(LabTest $test)
    {
        $this->ensureSameInstitute($test, 'test');

        return view('medical.lab.tests.show', compact('test'));
    }

    public function edit(LabTest $test)
    {
        $this->ensureSameInstitute($test, 'test');

        return view('medical.lab.tests.edit', compact('test'));
    }

    public function update(LabTestRequest $request, LabTest $test)
    {
        $this->ensureSameInstitute($test, 'test');
        $test->update($request->validated());

        return redirect()->route('medical.lab.tests.show', $test)
            ->with('status', 'Lab test updated successfully!');
    }

    public function destroy(LabTest $test)
    {
        $this->ensureSameInstitute($test, 'test');

        if ($test->results()->exists()) {
            return redirect()->back()->with('error', 'Cannot delete a test that has recorded results.');
        }

        $test->delete();

        return redirect()->route('medical.lab.tests.index')
            ->with('status', 'Lab test deleted successfully!');
    }
}
