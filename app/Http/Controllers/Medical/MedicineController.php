<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\MedicineRequest;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\Medicine;
use App\Services\Medical\DgdaService;
use App\Services\Medical\MedicineDuplicateService;
use App\Support\MedicineDosageForm;
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
            new Middleware('permission:medical_medicines.edit', only: ['edit', 'update', 'syncDgda', 'restore']),
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
            $query->search($request->string('search')->toString());
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('status')) {
            match ($request->status) {
                'active' => $query->active(),
                'inactive' => $query->inactive(),
                default => null,
            };
        }

        if ($request->filled('dosage_form')) {
            $query->ofForm($request->dosage_form);
        }

        if ($request->filled('dgda')) {
            match ($request->dgda) {
                'coded' => $query->dgdaCoded(),
                'pending' => $query->dgdaPending(),
                default => null,
            };
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
     * AJAX quick-create from the prescription form popup.
     */
    public function quickStore(Request $request)
    {
        $dosageForms = implode(',', MedicineDosageForm::all());

        $validated = $request->validate([
            'dosage_form'  => "required|string|in:{$dosageForms}",
            'generic_name' => 'required|string|max:150',
            'strength'     => 'nullable|string|max:50',
            'brand_name'   => 'nullable|string|max:150',
        ]);

        $instituteId = $this->instituteId();

        // Case-insensitive duplicate check
        $dupService = app(MedicineDuplicateService::class);
        if ($dupService->exists($instituteId, $validated['brand_name'] ?? null, $validated['strength'] ?? null)) {
            return response()->json([
                'errors' => ['brand_name' => ['A medicine with this name and strength already exists.']],
            ], 422);
        }

        $code = 'MED-'.$instituteId.'-'.strtoupper(uniqid());

        $medicine = Medicine::create([
            'institute_id'    => $instituteId,
            'code'            => $code,
            'generic_name'    => $validated['generic_name'],
            'brand_name'      => $validated['brand_name'] ?? null,
            'dosage_form'     => $validated['dosage_form'],
            'strength'        => $validated['strength'] ?? null,
            'unit'            => 'pcs',
            'pack_size'       => 1,
            'purchase_price'  => 0,
            'selling_price'   => 0,
            'reorder_level'   => 0,
            'reorder_quantity'=> 0,
            'is_active'       => true,
        ]);

        return response()->json([
            'id'           => $medicine->id,
            'display_name' => $medicine->display_name,
            'dosage_form'  => $medicine->dosage_form,
            'strength'     => $medicine->strength,
        ]);
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
     * Archive (soft-delete) medicine — blocked while live stock exists.
     *
     * Preserves prescription history references. The medicine remains
     * queryable via withTrashed() but excluded from normal index queries.
     */
    public function destroy(Medicine $medicine)
    {
        $this->ensureSameInstitute($medicine, 'medicine');

        $hasStock = $medicine->stocks()->where('current_quantity', '>', 0)->exists();
        if ($hasStock) {
            return redirect()->back()->with('error', 'Cannot archive medicine with existing stock. Please adjust stock to zero first.');
        }

        ClinicalAuditLog::record($medicine, 'archived', [
            'old' => array_merge(ClinicalAuditLog::snapshot($medicine), [
                'batch_count' => $medicine->stocks()->count(),
            ]),
        ]);
        $medicine->delete();

        return redirect()->route('medical.pharmacy.medicines.index')
            ->with('status', 'Medicine archived successfully. It can be restored if needed.');
    }

    /**
     * Restore a soft-deleted (archived) medicine.
     */
    public function restore(int $id)
    {
        $medicine = Medicine::withTrashed()->findOrFail($id);
        $this->ensureSameInstitute($medicine, 'medicine');

        if (! $medicine->trashed()) {
            return back()->with('info', 'Medicine is not archived.');
        }

        $medicine->restore();

        ClinicalAuditLog::record($medicine, 'restored', [
            'new' => ClinicalAuditLog::snapshot($medicine),
        ]);

        return redirect()->route('medical.pharmacy.medicines.show', $medicine)
            ->with('status', 'Medicine restored successfully.');
    }
}
