<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\MedicineRequest;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\Medicine;
use App\Services\Medical\DgdaService;
use App\Services\Medical\MedicineDuplicateService;
use App\Support\MedicineDosageForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    /**
     * Show the bulk CSV import form.
     */
    public function importForm()
    {
        return view('medical.medicines.import');
    }

    /**
     * Download the CSV import template.
     */
    public function downloadTemplate()
    {
        $path = resource_path('templates/medicine-import-template.csv');
        if (! file_exists($path)) {
            abort(404, 'Template not found.');
        }

        return response()->download($path, 'medicine-import-template.csv');
    }

    /**
     * Process a bulk CSV import of medicines.
     */
    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $file = $request->file('csv_file');
        $instituteId = $this->instituteId();

        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle);
        $header = array_map(fn ($h) => strtolower(trim($h)), $header);

        $required = ['brand_name', 'strength', 'dosage_form'];
        $missing = array_diff($required, $header);
        if (! empty($missing)) {
            fclose($handle);

            return back()->withErrors(['csv_file' => 'Missing required columns: '.implode(', ', $missing)]);
        }

        $canonicalForms = array_keys(config('medicine.dosage_forms'));

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $rowNum = 1;

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;
                if (count(array_filter($row)) === 0) {
                    continue;
                }

                $data = array_combine($header, array_pad($row, count($header), null));

                if (empty($data['brand_name']) || empty($data['strength']) || empty($data['dosage_form'])) {
                    $errors[] = "Row {$rowNum}: missing required field (brand_name, strength, or dosage_form)";
                    $skipped++;
                    continue;
                }

                $form = ucfirst(strtolower(trim($data['dosage_form'])));
                if (! in_array($form, $canonicalForms, true)) {
                    $errors[] = "Row {$rowNum}: invalid dosage_form '{$data['dosage_form']}'. Allowed: ".implode(', ', $canonicalForms);
                    $skipped++;
                    continue;
                }

                $normalized = preg_replace('/[^a-z0-9]/', '', strtolower(trim($data['brand_name'].' '.$data['strength'])));

                $existing = Medicine::where('institute_id', $instituteId)
                    ->where('normalized_name', $normalized)
                    ->whereNull('deleted_at')
                    ->first();

                if ($existing) {
                    $errors[] = "Row {$rowNum}: duplicate of existing medicine '{$existing->brand_name}' ({$existing->strength})";
                    $skipped++;
                    continue;
                }

                $code = ! empty($data['code'])
                    ? trim($data['code'])
                    : 'MED-'.$instituteId.'-'.strtoupper(uniqid());

                if (! empty($data['code'])) {
                    $codeExists = Medicine::where('institute_id', $instituteId)
                        ->where('code', $code)
                        ->whereNull('deleted_at')
                        ->exists();
                    if ($codeExists) {
                        $errors[] = "Row {$rowNum}: code '{$code}' already in use";
                        $skipped++;
                        continue;
                    }
                }

                try {
                    Medicine::create([
                        'institute_id' => $instituteId,
                        'brand_name' => trim($data['brand_name']),
                        'generic_name' => ! empty($data['generic_name']) ? trim($data['generic_name']) : null,
                        'strength' => trim($data['strength']),
                        'dosage_form' => $form,
                        'route' => ! empty($data['route']) ? trim($data['route']) : null,
                        'unit' => ! empty($data['unit']) ? trim($data['unit']) : null,
                        'pack_size' => ! empty($data['pack_size']) ? (int) $data['pack_size'] : null,
                        'manufacturer' => ! empty($data['manufacturer']) ? trim($data['manufacturer']) : null,
                        'category' => ! empty($data['category']) ? trim($data['category']) : null,
                        'selling_price' => ! empty($data['selling_price']) ? (float) $data['selling_price'] : 0,
                        'reorder_level' => ! empty($data['reorder_level']) ? (int) $data['reorder_level'] : 10,
                        'code' => $code,
                        'dgda_code' => ! empty($data['dgda_code']) ? trim($data['dgda_code']) : null,
                        'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
                    ]);
                    $imported++;
                } catch (\Throwable $e) {
                    $errors[] = "Row {$rowNum}: DB error — ".$e->getMessage();
                    $skipped++;
                }
            }

            fclose($handle);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($handle);

            return back()->withErrors(['csv_file' => 'Import failed: '.$e->getMessage()]);
        }

        return redirect()
            ->route('medical.pharmacy.medicines.index')
            ->with('import_summary', [
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => array_slice($errors, 0, 50),
                'total_errors' => count($errors),
            ]);
    }

    /**
     * Show DGDA migration analysis page.
     */
    public function migrateForm()
    {
        $instituteId = $this->instituteId();
        $service = app(\App\Services\Medical\DgdaMigrationService::class);
        $analysis = $service->analyze($instituteId);

        return view('medical.medicines.migrate', compact('analysis'));
    }

    /**
     * Apply auto-matched migrations.
     */
    public function migrateApplyAuto()
    {
        $instituteId = $this->instituteId();
        $service = app(\App\Services\Medical\DgdaMigrationService::class);
        $count = $service->applyAuto($instituteId);

        return redirect()
            ->route('medical.pharmacy.medicines.migrate')
            ->with('status', "Linked {$count} medicine(s) to DGDA registry.");
    }

    /**
     * Apply a single manual migration match.
     */
    public function migrateApplyManual(Request $request)
    {
        $request->validate([
            'medicine_id' => 'required|integer|exists:medicines,id',
            'dgda_registration_id' => 'required|integer|exists:dgda_registrations,id',
        ]);

        $service = app(\App\Services\Medical\DgdaMigrationService::class);
        $ok = $service->applyManual(
            (int) $request->medicine_id,
            (int) $request->dgda_registration_id
        );

        if (! $ok) {
            return back()->with('error', 'Migration failed. Medicine or DGDA registration not found.');
        }

        return back()->with('status', 'Medicine linked to DGDA registration.');
    }

    /**
     * Search DGDA registry for hybrid mode create form.
     */
    public function dgdaSearch(Request $request)
    {
        $q = $request->string('q')->toString();
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $results = \App\Models\Medical\DgdaRegistration::where(function ($query) use ($q) {
            $query->where('brand_name', 'like', "%{$q}%")
                ->orWhere('generic_name', 'like', "%{$q}%")
                ->orWhere('dar_number', 'like', "%{$q}%");
        })
            ->where('match_status', '!=', 'invalid')
            ->limit(20)
            ->get(['id', 'dar_number', 'brand_name', 'generic_name', 'strength_raw', 'dosage_form_raw', 'manufacturer_name']);

        return response()->json($results);
    }
}
