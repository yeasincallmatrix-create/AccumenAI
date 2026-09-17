<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\VaccineMaster;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class VaccineMasterController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical.vaccination.view', only: ['index', 'show']),
            new Middleware('permission:medical.vaccination.manage', only: ['create', 'store', 'edit', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $instituteId = $this->instituteId();

        $query = VaccineMaster::forInstitute($instituteId);

        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $vaccines = $query->orderBy('name')->paginate(25)->withQueryString();
        $categories = VaccineMaster::CATEGORIES;

        return view('medical.vaccination.vaccine-masters.index', compact('vaccines', 'categories'));
    }

    public function create()
    {
        $categories = VaccineMaster::CATEGORIES;
        $routes = VaccineMaster::ROUTES;
        $sites = VaccineMaster::SITES;

        return view('medical.vaccination.vaccine-masters.create', compact('categories', 'routes', 'sites'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:200',
            'code' => 'nullable|string|max:30',
            'short_name' => 'nullable|string|max:100',
            'category' => 'nullable|string|in:' . implode(',', array_keys(VaccineMaster::CATEGORIES)),
            'description' => 'nullable|string|max:2000',
            'protects_against' => 'nullable|string|max:500',
            'route' => 'nullable|string|in:' . implode(',', array_keys(VaccineMaster::ROUTES)),
            'site' => 'nullable|string|in:' . implode(',', array_keys(VaccineMaster::SITES)),
            'dose_volume' => 'nullable|string|max:30',
            'doses_in_series' => 'nullable|integer|min:1|max:10',
            'min_age_days' => 'nullable|integer|min:0',
            'max_age_days' => 'nullable|integer|min:0',
            'interval_days_min' => 'nullable|integer|min:0',
            'default_fee' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $instituteId = $this->instituteId();
        $data = $request->all();
        $data['institute_id'] = $instituteId;
        $data['doses_in_series'] = $data['doses_in_series'] ?? 1;
        $data['default_fee'] = $data['default_fee'] ?? 0;
        $data['is_active'] = $request->boolean('is_active', true);

        $vaccine = VaccineMaster::create($data);

        ClinicalAuditLog::record($vaccine, 'created');

        return redirect()
            ->route('medical.vaccination.vaccine-masters.show', $vaccine)
            ->with('status', 'Vaccine created: ' . $vaccine->name);
    }

    public function show(VaccineMaster $vaccineMaster)
    {
        $vaccineMaster->load(['schedules.patient', 'records.patient', 'stocks']);

        return view('medical.vaccination.vaccine-masters.show', ['vaccine' => $vaccineMaster]);
    }

    public function edit(VaccineMaster $vaccineMaster)
    {
        $categories = VaccineMaster::CATEGORIES;
        $routes = VaccineMaster::ROUTES;
        $sites = VaccineMaster::SITES;

        return view('medical.vaccination.vaccine-masters.edit', [
            'vaccine' => $vaccineMaster,
            'categories' => $categories,
            'routes' => $routes,
            'sites' => $sites,
        ]);
    }

    public function update(Request $request, VaccineMaster $vaccineMaster)
    {
        $request->validate([
            'name' => 'required|string|max:200',
            'code' => 'nullable|string|max:30',
            'short_name' => 'nullable|string|max:100',
            'category' => 'nullable|string|in:' . implode(',', array_keys(VaccineMaster::CATEGORIES)),
            'description' => 'nullable|string|max:2000',
            'protects_against' => 'nullable|string|max:500',
            'route' => 'nullable|string|in:' . implode(',', array_keys(VaccineMaster::ROUTES)),
            'site' => 'nullable|string|in:' . implode(',', array_keys(VaccineMaster::SITES)),
            'dose_volume' => 'nullable|string|max:30',
            'doses_in_series' => 'nullable|integer|min:1|max:10',
            'min_age_days' => 'nullable|integer|min:0',
            'max_age_days' => 'nullable|integer|min:0',
            'interval_days_min' => 'nullable|integer|min:0',
            'default_fee' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $original = ClinicalAuditLog::snapshot($vaccineMaster);

        $vaccineMaster->update($request->only([
            'name', 'code', 'short_name', 'category', 'description',
            'protects_against', 'route', 'site', 'dose_volume',
            'doses_in_series', 'min_age_days', 'max_age_days',
            'interval_days_min', 'default_fee', 'is_active',
        ]));

        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($vaccineMaster->refresh()));
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($vaccineMaster, 'updated', ['old' => $old, 'new' => $new]);
        }

        return redirect()
            ->route('medical.vaccination.vaccine-masters.show', $vaccineMaster)
            ->with('status', 'Vaccine updated.');
    }

    public function destroy(VaccineMaster $vaccineMaster)
    {
        ClinicalAuditLog::record($vaccineMaster, 'deleted');
        $vaccineMaster->delete();

        return redirect()
            ->route('medical.vaccination.vaccine-masters.index')
            ->with('status', 'Vaccine deleted.');
    }
}
