<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\MedicineRequest;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\Medicine;
use App\Services\Medical\DgdaService;
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
            new Middleware('permission:medical_medicines.edit', only: ['edit', 'update', 'syncDgda']),
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
                    ->orWhere('code', 'LIKE', "%{$search}%")
                    ->orWhere('dgda_code', 'LIKE', "%{$search}%")
                    ->orWhere('dgda_dar_number', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->filled('dgda')) {
            if ($request->dgda === 'coded') {
                $query->whereNotNull('dgda_code')->where('dgda_code', '!=', '');
            } elseif ($request->dgda === 'pending') {
                $query->where(function ($q) {
                    $q->whereNull('dgda_code')->orWhere('dgda_code', '');
                });
            }
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

        // Phase 10: best-effort terminology mapping for the new catalog row.
        // Non-blocking by design — catalog creation must never fail because
        // terminology mapping is ambiguous; the backfill command reconciles.
        try {
            app(\App\Services\Medical\MedicineTerminologyService::class)->mapMedicine($medicine->fresh());
        } catch (\Throwable $e) {
            report($e);
        }

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

        // Phase 10: re-map on edit (same best-effort contract as store).
        try {
            app(\App\Services\Medical\MedicineTerminologyService::class)->mapMedicine($medicine->fresh());
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('medical.pharmacy.medicines.show', $medicine)
            ->with('status', 'Medicine updated successfully!');
    }

    /**
     * Validate this row's DGDA code against the registry and stamp the
     * result (synced / failed). Remote calls fail soft inside the service.
     */
    public function syncDgda(Medicine $medicine, DgdaService $dgda)
    {
        $this->ensureSameInstitute($medicine, 'medicine');

        if (! DgdaService::isEnabledForInstitute($medicine->institute_id)) {
            return redirect()->back()->with('error', 'DGDA sync is not enabled for this institute.');
        }

        $code = trim((string) ($medicine->dgda_code ?? ''));
        if ($code === '') {
            return redirect()->back()->with('error', 'Enter a DGDA code first, then sync.');
        }

        $result = $dgda->validateCode($code);
        if (! ($result['ok'] ?? false)) {
            return redirect()->back()->with('error', 'DGDA sync failed: '.($result['error'] ?? 'unknown error.'));
        }
        if (($result['valid'] ?? null) === false) {
            $dgda->markFailed($medicine);

            return redirect()->back()->with('error', 'Registry reports this DGDA code as invalid.');
        }
        $dgda->markSynced($medicine);

        return redirect()->back()->with('status', 'DGDA code validated and marked synced.');
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

        // Phase 03: master deletion leaves an attributable trail (zero-
        // quantity batches cascade with the medicine; dispense-linked
        // batches are already protected at the stock level).
        ClinicalAuditLog::record($medicine, 'deleted', [
            'old' => array_merge(ClinicalAuditLog::snapshot($medicine), [
                'batch_count' => $medicine->stocks()->count(),
            ]),
        ]);
        $medicine->delete();

        return redirect()->route('medical.pharmacy.medicines.index')
            ->with('status', 'Medicine deleted successfully!');
    }
}
