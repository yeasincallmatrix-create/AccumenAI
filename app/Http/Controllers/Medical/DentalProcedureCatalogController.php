<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\DentalProcedureCatalog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DentalProcedureCatalogController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical.dental.view', only: ['index']),
            new Middleware('permission:medical.dental.catalog.manage', only: ['create', 'store', 'edit', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $instituteId = $this->instituteId();

        $query = DentalProcedureCatalog::forInstitute($instituteId)->active();

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

        $catalog = $query->orderBy('category')->orderBy('name')->paginate(25)->withQueryString();
        $categories = DentalProcedureCatalog::CATEGORIES;

        return view('medical.dental.catalog.index', compact('catalog', 'categories'));
    }

    public function create()
    {
        $categories = DentalProcedureCatalog::CATEGORIES;

        return view('medical.dental.catalog.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'nullable|string|max:30',
            'name' => 'required|string|max:200',
            'category' => 'required|string|in:' . implode(',', array_keys(DentalProcedureCatalog::CATEGORIES)),
            'body_site' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:2000',
            'default_fee' => 'nullable|numeric|min:0',
            'default_duration_minutes' => 'nullable|integer|min:1',
        ]);

        $data = $request->all();
        $data['institute_id'] = $this->instituteId();
        $data['default_fee'] = $data['default_fee'] ?? 0;
        $data['default_duration_minutes'] = $data['default_duration_minutes'] ?? 30;

        $catalog = DentalProcedureCatalog::create($data);

        return redirect()
            ->route('medical.dental.catalog.index')
            ->with('status', 'Procedure catalog entry created.');
    }

    public function edit(DentalProcedureCatalog $catalog)
    {
        $this->ensureSameInstitute($catalog, 'dental_procedure_catalog');
        $categories = DentalProcedureCatalog::CATEGORIES;

        return view('medical.dental.catalog.edit', compact('catalog', 'categories'));
    }

    public function update(Request $request, DentalProcedureCatalog $catalog)
    {
        $this->ensureSameInstitute($catalog, 'dental_procedure_catalog');

        $request->validate([
            'code' => 'nullable|string|max:30',
            'name' => 'required|string|max:200',
            'category' => 'required|string|in:' . implode(',', array_keys(DentalProcedureCatalog::CATEGORIES)),
            'body_site' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:2000',
            'default_fee' => 'nullable|numeric|min:0',
            'default_duration_minutes' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $catalog->update($request->only([
            'code', 'name', 'category', 'body_site', 'description',
            'default_fee', 'default_duration_minutes', 'is_active',
        ]));

        return redirect()
            ->route('medical.dental.catalog.index')
            ->with('status', 'Procedure catalog entry updated.');
    }

    public function destroy(DentalProcedureCatalog $catalog)
    {
        $this->ensureSameInstitute($catalog, 'dental_procedure_catalog');

        $catalog->delete();

        return redirect()
            ->route('medical.dental.catalog.index')
            ->with('status', 'Procedure catalog entry deleted.');
    }
}
