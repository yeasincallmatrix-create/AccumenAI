<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Industry;
use App\Models\PlatformAuditLog;
use App\Models\SubIndustry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IndustryAdminController extends Controller
{
    public function index(): View
    {
        $industries = Industry::withCount('subIndustries')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.industries.index', compact('industries'));
    }

    public function create(): View
    {
        return view('admin.industries.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:60', 'unique:industries,slug'],
            'code' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $industry = Industry::create($data);

        PlatformAuditLog::record('industry', 'create', 'created', [
            'industry_id' => $industry->id,
            'slug' => $industry->slug,
        ]);

        return redirect()->route('admin.industries.index')
            ->with('status', "Industry \"{$industry->name}\" created.");
    }

    public function edit(Industry $industry): View
    {
        return view('admin.industries.edit', compact('industry'));
    }

    public function update(Request $request, Industry $industry): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:60', 'unique:industries,slug,' . $industry->id],
            'code' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $industry->update($data);

        PlatformAuditLog::record('industry', 'update', 'updated', [
            'industry_id' => $industry->id,
            'slug' => $industry->slug,
        ]);

        return redirect()->route('admin.industries.index')
            ->with('status', "Industry \"{$industry->name}\" updated.");
    }

    public function toggle(Industry $industry): RedirectResponse
    {
        $newStatus = $industry->status === 'active' ? 'inactive' : 'active';
        $industry->update(['status' => $newStatus]);

        PlatformAuditLog::record('industry', 'status', $newStatus, [
            'industry_id' => $industry->id,
            'slug' => $industry->slug,
        ]);

        return redirect()->route('admin.industries.index')
            ->with('status', "Industry \"{$industry->name}\" {$newStatus}.");
    }

    public function destroy(Industry $industry): RedirectResponse
    {
        if ($industry->institutes()->exists()) {
            return back()->withErrors([
                'error' => 'Cannot delete industry with existing institutes. Deactivate it instead.',
            ]);
        }

        if ($industry->subIndustries()->exists()) {
            return back()->withErrors([
                'error' => 'Cannot delete industry with existing sub-industries. Remove them first or deactivate the industry.',
            ]);
        }

        $name = $industry->name;
        $industry->delete();

        PlatformAuditLog::record('industry', 'delete', 'deleted', [
            'slug' => $industry->slug,
        ]);

        return redirect()->route('admin.industries.index')
            ->with('status', "Industry \"{$name}\" deleted.");
    }

    public function subIndustries(Request $request, Industry $industry): View
    {
        $query = $industry->subIndustries()->with('country');

        if ($request->filled('country')) {
            $countryValue = $request->query('country');
            if ($countryValue === 'global') {
                $query->whereNull('country_id');
            } else {
                $query->where('country_id', $countryValue);
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('q')) {
            $q = $request->query('q');
            $query->where(function ($qq) use ($q) {
                $qq->where('name', 'like', "%{$q}%")
                   ->orWhere('slug', 'like', "%{$q}%");
            });
        }

        $subIndustries = $query->orderBy('sort_order')->orderBy('name')->get();

        $countries = \App\Models\Country::where('status', true)->orderBy('name')->get();

        return view('admin.industries.sub-industries', compact('industry', 'subIndustries', 'countries'));
    }
}
