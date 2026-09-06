<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\MedicineRequest;
use App\Models\Medical\Medicine;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class MedicineController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_medicines.view', only: ['index', 'show']),
            new Middleware('permission:medical_medicines.create', only: ['create', 'store']),
            new Middleware('permission:medical_medicines.edit', only: ['edit', 'update']),
            new Middleware('permission:medical_medicines.delete', only: ['destroy']),
        ];
    }

    /**
     * List all medicines (stock counts come from the model accessors).
     */
    public function index(Request $request)
    {
        $query = Medicine::where('institute_id', $this->instituteId());

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('generic_name', 'LIKE', "%{$search}%")
                    ->orWhere('brand_name', 'LIKE', "%{$search}%")
                    ->orWhere('code', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $medicines = $query->orderBy('generic_name')->paginate(20)->withQueryString();

        $categories = Medicine::where('institute_id', $this->instituteId())
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('medical.medicines.index', compact('medicines', 'categories'));
    }

    /**
     * Show medicine creation form.
     */
    public function create()
    {
        return view('medical.medicines.create');
    }

    /**
     * Store a new medicine.
     */
    public function store(MedicineRequest $request)
    {
        $data = $request->validated();
        $data['institute_id'] = $this->instituteId();

        $medicine = Medicine::create($data);

        return redirect()->route('medical.pharmacy.medicines.show', $medicine)
            ->with('status', 'Medicine created successfully!');
    }

    /**
     * Show medicine details with stock.
     */
    public function show(Medicine $medicine)
    {
        $this->ensureSameInstitute($medicine, 'medicine');

        $medicine->load(['stocks' => function ($q) {
            $q->orderBy('expiry_date');
        }]);

        return view('medical.medicines.show', compact('medicine'));
    }

    /**
     * Show medicine edit form.
     */
    public function edit(Medicine $medicine)
    {
        $this->ensureSameInstitute($medicine, 'medicine');

        return view('medical.medicines.edit', compact('medicine'));
    }

    /**
     * Update medicine.
     */
    public function update(MedicineRequest $request, Medicine $medicine)
    {
        $this->ensureSameInstitute($medicine, 'medicine');
        $medicine->update($request->validated());

        return redirect()->route('medical.pharmacy.medicines.show', $medicine)
            ->with('status', 'Medicine updated successfully!');
    }

    /**
     * Delete medicine (blocked while live stock exists).
     */
    public function destroy(Medicine $medicine)
    {
        $this->ensureSameInstitute($medicine, 'medicine');

        $hasStock = $medicine->stocks()->where('current_quantity', '>', 0)->exists();
        if ($hasStock) {
            return redirect()->back()->with('error', 'Cannot delete medicine with existing stock.');
        }

        $medicine->delete();

        return redirect()->route('medical.pharmacy.medicines.index')
            ->with('status', 'Medicine deleted successfully!');
    }
}
