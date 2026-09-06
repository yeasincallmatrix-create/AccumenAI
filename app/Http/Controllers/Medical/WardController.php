<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\WardRequest;
use App\Models\Medical\Ward;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class WardController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_wards.view', only: ['index', 'show']),
            new Middleware('permission:medical_wards.create', only: ['create', 'store']),
            new Middleware('permission:medical_wards.edit', only: ['edit', 'update']),
            new Middleware('permission:medical_wards.delete', only: ['destroy']),
        ];
    }

    /**
     * List all wards.
     */
    public function index(Request $request)
    {
        $query = Ward::where('institute_id', $this->instituteId());

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $wards = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('medical.wards.index', compact('wards'));
    }

    /**
     * Show ward creation form.
     */
    public function create()
    {
        return view('medical.wards.create');
    }

    /**
     * Store a new ward.
     */
    public function store(WardRequest $request)
    {
        $data = $request->validated();
        $data['institute_id'] = $this->instituteId();
        $data['available_beds'] = $data['total_beds'];

        $ward = Ward::create($data);

        return redirect()->route('medical.wards.show', $ward)
            ->with('status', 'Ward created successfully!');
    }

    /**
     * Show ward details with bed map.
     */
    public function show(Ward $ward)
    {
        $this->ensureSameInstitute($ward, 'ward');

        $ward->load(['beds' => function ($q) {
            $q->orderBy('bed_number');
        }]);

        return view('medical.wards.show', compact('ward'));
    }

    /**
     * Show ward edit form.
     */
    public function edit(Ward $ward)
    {
        $this->ensureSameInstitute($ward, 'ward');

        return view('medical.wards.edit', compact('ward'));
    }

    /**
     * Update ward.
     */
    public function update(WardRequest $request, Ward $ward)
    {
        $this->ensureSameInstitute($ward, 'ward');

        $data = $request->validated();

        // If total beds changed, shift available_beds by the same delta so
        // the occupied count stays truthful.
        if (isset($data['total_beds']) && (int) $data['total_beds'] !== (int) $ward->total_beds) {
            $occupied = $ward->total_beds - $ward->available_beds;
            $data['available_beds'] = max(0, (int) $data['total_beds'] - $occupied);
        }

        $ward->update($data);

        return redirect()->route('medical.wards.show', $ward)
            ->with('status', 'Ward updated successfully!');
    }

    /**
     * Delete ward.
     *
     * Blocked while any bed rows belong to the ward: beds.ward_id cascades
     * on delete, so removing a ward with beds would silently wipe bed
     * records. Empty the ward first (delete its beds), then delete it.
     */
    public function destroy(Ward $ward)
    {
        $this->ensureSameInstitute($ward, 'ward');

        if ($ward->beds()->where('status', 'occupied')->exists()) {
            return redirect()->back()->with('error', 'Cannot delete ward with occupied beds.');
        }

        if ($ward->beds()->exists()) {
            return redirect()->back()->with('error', 'Cannot delete ward that still has beds. Delete its beds first.');
        }

        $ward->delete();

        return redirect()->route('medical.wards.index')
            ->with('status', 'Ward deleted successfully!');
    }
}
