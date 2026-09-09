<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\BedRequest;
use App\Models\Medical\Admission;
use App\Models\Medical\Bed;
use App\Models\Medical\Ward;
use App\Services\Medical\BedAllocationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class BedController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_beds.view', only: ['index', 'show', 'available']),
            new Middleware('permission:medical_beds.create', only: ['create', 'store']),
            new Middleware('permission:medical_beds.edit', only: ['edit', 'update']),
            new Middleware('permission:medical_beds.delete', only: ['destroy']),
            new Middleware('permission:medical_beds.allocate', only: ['allocate', 'release']),
        ];
    }

    protected BedAllocationService $bedAllocation;

    public function __construct(BedAllocationService $bedAllocation)
    {
        $this->bedAllocation = $bedAllocation;
    }

    /**
     * List all beds.
     */
    public function index(Request $request)
    {
        $instituteId = $this->instituteId();
        $query = Bed::where('institute_id', $instituteId)->with('ward');

        if ($request->filled('ward_id')) {
            $query->where('ward_id', $request->ward_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $beds = $query->orderBy('bed_number')->paginate(50)->withQueryString();
        $wards = Ward::where('institute_id', $instituteId)->orderBy('name')->get();

        return view('medical.beds.index', compact('beds', 'wards'));
    }

    /**
     * Show bed creation form.
     */
    public function create()
    {
        $wards = Ward::where('institute_id', $this->instituteId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('medical.beds.create', compact('wards'));
    }

    /**
     * Store a new bed.
     */
    public function store(BedRequest $request)
    {
        $data = $request->validated();
        $data['institute_id'] = $this->instituteId();

        $bed = Bed::create($data);

        // Keep the ward counters in sync with the new bed row.
        $ward = Ward::where('institute_id', $data['institute_id'])->findOrFail($data['ward_id']);
        $ward->increment('total_beds');
        if ($bed->status === 'available') {
            $ward->increment('available_beds');
        }

        return redirect()->route('medical.beds.index')
            ->with('status', 'Bed created successfully!');
    }

    /**
     * Show bed details.
     */
    public function show(Bed $bed)
    {
        $this->ensureSameInstitute($bed, 'bed');
        $bed->load('ward');

        $activeAdmission = Admission::where('institute_id', $bed->institute_id)
            ->where('bed_id', $bed->id)
            ->where('status', 'active')
            ->with('patient')
            ->first();

        return view('medical.beds.show', compact('bed', 'activeAdmission'));
    }

    /**
     * Show bed edit form.
     */
    public function edit(Bed $bed)
    {
        $this->ensureSameInstitute($bed, 'bed');
        $wards = Ward::where('institute_id', $bed->institute_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('medical.beds.edit', compact('bed', 'wards'));
    }

    /**
     * Update bed.
     */
    public function update(BedRequest $request, Bed $bed)
    {
        $this->ensureSameInstitute($bed, 'bed');

        $oldWardId = $bed->ward_id;
        $oldStatus = $bed->status;

        $bed->update($request->validated());

        // Moving a bed between wards shifts both wards' counters.
        if ((int) $request->ward_id !== (int) $oldWardId) {
            $this->shiftWardCounters($oldWardId, $bed->institute_id, $oldStatus, -1);
            $this->shiftWardCounters((int) $request->ward_id, $bed->institute_id, $bed->status, 1);
        }

        return redirect()->route('medical.beds.show', $bed)
            ->with('status', 'Bed updated successfully!');
    }

    /**
     * Delete bed.
     */
    public function destroy(Bed $bed)
    {
        $this->ensureSameInstitute($bed, 'bed');

        if ($bed->status === 'occupied') {
            return redirect()->back()->with('error', 'Cannot delete an occupied bed.');
        }

        // Phase 03: a bed ever assigned to an admission carries bed-assignment
        // history (admissions.bed_id nulls out on bed removal). Only beds that
        // were never assigned may disappear; the rest stay as history.
        if ($bed->admissions()->exists()) {
            return redirect()->back()->with('error', 'Cannot delete a bed with admission history.');
        }

        // Update ward counts.
        $ward = $bed->ward;
        $ward->decrement('total_beds');
        if ($bed->status === 'available') {
            $ward->decrement('available_beds');
        }

        $bed->delete();

        return redirect()->route('medical.beds.index')
            ->with('status', 'Bed deleted successfully!');
    }

    /**
     * Show available beds.
     */
    public function available()
    {
        $instituteId = $this->instituteId();

        $availableBeds = Bed::where('institute_id', $instituteId)
            ->where('status', 'available')
            ->with('ward')
            ->orderBy('bed_number')
            ->get();

        $summary = $this->bedAllocation->getOccupancySummary($instituteId);

        return view('medical.beds.available', compact('availableBeds', 'summary'));
    }

    /**
     * Allocate a bed to an admission.
     */
    public function allocate(Request $request, Bed $bed)
    {
        $this->ensureSameInstitute($bed, 'bed');

        $request->validate([
            'admission_id' => 'required|integer|exists:admissions,id',
        ]);

        $admission = Admission::where('institute_id', $bed->institute_id)
            ->findOrFail($request->admission_id);

        if ($admission->status !== 'active') {
            return redirect()->back()->with('error', 'Beds can only be allocated to active admissions.');
        }

        // Free the admission's previous bed first so counts stay exact.
        if ($admission->bed_id && (int) $admission->bed_id !== (int) $bed->id) {
            try {
                $this->bedAllocation->releaseBed($bed->institute_id, (int) $admission->bed_id);
            } catch (\Throwable $e) {
                return redirect()->back()->with('error', $e->getMessage());
            }
        }

        try {
            $this->bedAllocation->allocateBed($bed->institute_id, $bed->id, $admission->id);

            return redirect()->back()->with('status', 'Bed allocated successfully!');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Release a bed.
     */
    public function release(Bed $bed)
    {
        $this->ensureSameInstitute($bed, 'bed');

        // Detach any active admission still pointing at this bed so no
        // dangling bed_id survives the release.
        Admission::where('institute_id', $bed->institute_id)
            ->where('bed_id', $bed->id)
            ->where('status', 'active')
            ->update(['bed_id' => null]);

        try {
            $this->bedAllocation->releaseBed($bed->institute_id, $bed->id);

            return redirect()->back()->with('status', 'Bed released successfully!');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    private function shiftWardCounters(int $wardId, int $instituteId, string $status, int $direction): void
    {
        $ward = Ward::where('institute_id', $instituteId)->find($wardId);
        if (! $ward) {
            return;
        }

        if ($direction > 0) {
            $ward->increment('total_beds');
            if ($status === 'available') {
                $ward->increment('available_beds');
            }
        } else {
            $ward->decrement('total_beds');
            if ($status === 'available') {
                $ward->decrement('available_beds');
            }
        }
    }
}
