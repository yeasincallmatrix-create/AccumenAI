<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\LabAnalyzerMapRequest;
use App\Models\LabIntegration\LabAnalyzer;
use App\Models\LabIntegration\LabAnalyzerParameterMap;
use App\Models\Medical\LabTest;

class LabAnalyzerMapController extends MedicalController
{
    protected function analyzer(LabAnalyzer $analyzer): LabAnalyzer
    {
        $this->ensureSameInstitute($analyzer);

        return $analyzer;
    }

    public function index(LabAnalyzer $analyzer)
    {
        $this->analyzer($analyzer);
        $maps = $analyzer->parameterMaps()->orderBy('sort_order')->orderBy('vendor_code')->paginate(50)->withQueryString();
        $labTests = LabTest::where('institute_id', $this->instituteId())->where('is_active', true)->orderBy('code')->get();

        return view('medical.lab.analyzers.maps.index', compact('analyzer', 'maps', 'labTests'));
    }

    public function store(LabAnalyzerMapRequest $request, LabAnalyzer $analyzer)
    {
        $this->analyzer($analyzer);
        $data = $request->validated();
        $data['institute_id'] = $this->instituteId();
        $data['analyzer_id'] = $analyzer->id;
        $data['is_active'] = $request->boolean('is_active', true);

        LabAnalyzerParameterMap::updateOrCreate(
            ['analyzer_id' => $analyzer->id, 'vendor_code' => $data['vendor_code']],
            $data
        );

        return back()->with('success', "Map for '{$data['vendor_code']}' saved.");
    }

    public function update(LabAnalyzerMapRequest $request, LabAnalyzer $analyzer, LabAnalyzerParameterMap $map)
    {
        $this->analyzer($analyzer);
        $this->ensureMapBelongs($analyzer, $map);

        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $map->update($data);

        return back()->with('success', "Map for '{$map->vendor_code}' updated.");
    }

    public function destroy(LabAnalyzer $analyzer, LabAnalyzerParameterMap $map)
    {
        $this->analyzer($analyzer);
        $this->ensureMapBelongs($analyzer, $map);
        $map->delete();

        return back()->with('success', "Map for '{$map->vendor_code}' deleted.");
    }

    /**
     * Phase 9: pathologist reference-range sign-off.
     * Only clinical approver roles may sign; institute owners bypass
     * (super-user invariant, mirrors CheckPermission).
     */
    public function approveRefRange(\Illuminate\Http\Request $request, LabAnalyzer $analyzer, LabAnalyzerParameterMap $map)
    {
        $this->analyzer($analyzer);
        $this->ensureMapBelongs($analyzer, $map);

        $user = auth()->user();
        $isOwner = method_exists($user, 'isOwner') ? $user->isOwner() : false;
        $canApprove = $isOwner
            || ($user && method_exists($user, 'hasRole') && $user->hasRole(['pathologist', 'lab_manager', 'hospital_admin', 'institute-owner', 'institute-admin']));
        abort_unless($canApprove, 403, 'Only a pathologist, lab manager, or administrator may approve reference ranges.');

        $request->validate(['notes' => 'nullable|string|max:500']);
        $map->update([
            'ref_range_approved_by' => auth()->id(),
            'ref_range_approved_at' => now(),
            'ref_range_approval_notes' => $request->input('notes'),
        ]);

        return back()->with('success', "Reference range for '{$map->vendor_code}' approved.");
    }

    /**
     * Phase 9: bulk CSV import form.
     */
    public function importForm(LabAnalyzer $analyzer)
    {
        $this->ensureSameInstitute($analyzer);

        return view('medical.lab.analyzers.maps.import', compact('analyzer'));
    }

    public function downloadTemplate()
    {
        $path = resource_path('templates/lab-parameter-map-import-template.csv');

        return response()->download($path, 'lab-parameter-map-import-template.csv');
    }

    public function import(\Illuminate\Http\Request $request, LabAnalyzer $analyzer, \App\Services\LabIntegration\ParameterMapImportService $service)
    {
        $this->ensureSameInstitute($analyzer);
        $request->validate(['csv_file' => 'required|file|mimes:csv,txt|max:2048']);

        $result = $service->import($analyzer, $request->file('csv_file')->getRealPath());

        if (! empty($result['errors'])) {
            return back()->with('warning', "Imported {$result['imported']}, updated {$result['updated']}, skipped {$result['skipped']}. First issue: {$result['errors'][0]}");
        }

        return back()->with('success', "Imported {$result['imported']}, updated {$result['updated']}, skipped {$result['skipped']}.");
    }

    public function seedSysmex(LabAnalyzer $analyzer)
    {
        $this->analyzer($analyzer);

        if ($analyzer->adapter_key !== 'sysmex_xn') {
            return back()->with('warning', 'Sysmex seed only applies to analyzers with adapter sysmex_xn.');
        }

        // Reuse the Phase 3 seeder (idempotent updateOrCreate per analyzer).
        // It scopes itself to sysmex_xn analyzers; tenant scoping comes from
        // the active request context inside its query layer.
        \Artisan::call('db:seed', ['--class' => \Database\Seeders\SysmexXn550ParameterMapSeeder::class, '--force' => true]);

        return back()->with('success', 'Sysmex XN-550 parameter maps seeded (36 codes).');
    }

    protected function ensureMapBelongs(LabAnalyzer $analyzer, LabAnalyzerParameterMap $map): void
    {
        if ((int) $map->analyzer_id !== (int) $analyzer->id || (int) $map->institute_id !== $this->instituteId()) {
            abort(404);
        }
    }
}
