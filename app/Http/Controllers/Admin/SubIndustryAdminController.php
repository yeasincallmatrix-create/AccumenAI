<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Industry;
use App\Models\PlatformAuditLog;
use App\Models\SubIndustry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubIndustryAdminController extends Controller
{
    public function create(Industry $industry): View
    {
        $countries = Country::where('status', true)->orderBy('name')->get();

        return view('admin.industries.sub-industry-create', compact('industry', 'countries'));
    }

    public function store(Request $request, Industry $industry): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:60'],
            'code' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'countries' => ['nullable', 'array'],
            'countries.*' => ['exists:countries,id'],
        ]);

        $countryIds = $data['countries'] ?? [];
        unset($data['countries']);

        if (empty($countryIds)) {
            $sub = $this->upsertSubIndustry(
                $industry->id,
                null,
                $data['name'],
                $data['slug'],
                $data
            );

            PlatformAuditLog::record('sub_industry', 'create', 'created', [
                'sub_industry_id' => $sub->id,
                'industry_id' => $industry->id,
                'slug' => $sub->slug,
                'scope' => 'global',
            ]);
        } else {
            foreach ($countryIds as $countryId) {
                $sub = $this->upsertSubIndustry(
                    $industry->id,
                    $countryId,
                    $data['name'],
                    $data['slug'],
                    $data
                );

                PlatformAuditLog::record('sub_industry', 'create', 'created', [
                    'sub_industry_id' => $sub->id,
                    'industry_id' => $industry->id,
                    'country_id' => $countryId,
                    'slug' => $sub->slug,
                ]);
            }
        }

        return redirect()->route('admin.industries.sub-industries', $industry)
            ->with('status', "Sub-industry \"{$data['name']}\" created.");
    }

    public function edit(Industry $industry, SubIndustry $subIndustry): View
    {
        $countries = Country::where('status', true)->orderBy('name')->get();

        return view('admin.industries.sub-industry-edit', compact('industry', 'subIndustry', 'countries'));
    }

    public function update(Request $request, Industry $industry, SubIndustry $subIndustry): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:60'],
            'code' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'country_id' => ['nullable', 'exists:countries,id'],
        ]);

        $subIndustry->update($data);

        PlatformAuditLog::record('sub_industry', 'update', 'updated', [
            'sub_industry_id' => $subIndustry->id,
            'industry_id' => $industry->id,
            'slug' => $subIndustry->slug,
        ]);

        return redirect()->route('admin.industries.sub-industries', $industry)
            ->with('status', "Sub-industry \"{$subIndustry->name}\" updated.");
    }

    public function toggle(Industry $industry, SubIndustry $subIndustry): RedirectResponse
    {
        $newStatus = $subIndustry->status === 'active' ? 'inactive' : 'active';
        $subIndustry->update(['status' => $newStatus]);

        PlatformAuditLog::record('sub_industry', 'status', $newStatus, [
            'sub_industry_id' => $subIndustry->id,
            'industry_id' => $industry->id,
            'slug' => $subIndustry->slug,
        ]);

        return redirect()->route('admin.industries.sub-industries', $industry)
            ->with('status', "Sub-industry \"{$subIndustry->name}\" {$newStatus}.");
    }

    public function destroy(Industry $industry, SubIndustry $subIndustry): RedirectResponse
    {
        if ($subIndustry->institutes()->exists()) {
            return back()->withErrors([
                'error' => 'Cannot delete sub-industry with existing institutes. Deactivate it instead.',
            ]);
        }

        $name = $subIndustry->name;
        $subIndustry->delete();

        PlatformAuditLog::record('sub_industry', 'delete', 'deleted', [
            'industry_id' => $industry->id,
            'slug' => $subIndustry->slug,
        ]);

        return redirect()->route('admin.industries.sub-industries', $industry)
            ->with('status', "Sub-industry \"{$name}\" deleted.");
    }

    private function upsertSubIndustry(
        int $industryId,
        ?int $countryId,
        string $name,
        string $slug,
        array $extra = []
    ): SubIndustry {
        $existing = SubIndustry::where('industry_id', $industryId)
            ->where('country_id', $countryId)
            ->where('slug', $slug)
            ->first();

        if ($existing) {
            $existing->update(array_merge($extra, ['name' => $name]));
            return $existing->fresh();
        }

        return SubIndustry::create(array_merge($extra, [
            'industry_id' => $industryId,
            'country_id' => $countryId,
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
        ]));
    }
}
