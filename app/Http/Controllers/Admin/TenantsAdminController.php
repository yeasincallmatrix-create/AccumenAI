<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Institute;
use App\Support\IndustryRules;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantsAdminController extends Controller
{
    public const INSTITUTES_COLUMNS = [
        'serial', 'institute', 'owner', 'package', 'students',
        'subscription', 'status', 'action',
    ];

    public const PER_PAGE_OPTIONS = [25, 50, 75, 100, 200, 500];

    public function index(Request $request): View
    {
        $perPage = (int) $request->query('per_page', 25);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 25;
        }

        $query = Institute::query()
            ->whereNull('deleted_at')
            ->with(['package', 'users.role'])
            ->withCount('students')
            ->when($request->query('q'), fn ($query, $term) => $query
                ->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
                    ->orWhere('slug', 'like', "%{$term}%")
                    ->orWhere('institute_code', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")))
            ->when($request->query('country'), fn ($query, $country) => $query->where('country', $country))
            ->when($request->query('industry'), fn ($query, $industry) => $query->where('industry', $industry))
            ->when($request->query('sub_industry'), fn ($query, $sub) => $query->where('sub_industry', $sub))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status));

        $items = (clone $query)->orderByDesc('id')->paginate($perPage)->withQueryString();

        $allItems = (clone $query)->orderByDesc('id')->get();

        $visibleColumns = $request->user()->preference('institutes_columns', self::INSTITUTES_COLUMNS);
        $visibleColumns = array_values(array_intersect(self::INSTITUTES_COLUMNS, (array) $visibleColumns));

        $countries = Country::orderBy('name')->get();
        $industries = IndustryRules::industries(null);
        $selectedCountry = $request->query('country');
        $selectedIndustry = $request->query('industry');
        $selectedSubIndustry = $request->query('sub_industry');
        $subIndustries = is_string($selectedIndustry) && $selectedIndustry !== ''
            ? IndustryRules::subIndustries('', $selectedIndustry)
            : [];

        return view('admin.tenants.index', [
            'items' => $items,
            'allItems' => $allItems,
            'visibleColumns' => $visibleColumns,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'countries' => $countries,
            'industries' => $industries,
            'subIndustries' => $subIndustries,
            'selectedCountry' => $selectedCountry,
            'selectedIndustry' => $selectedIndustry,
            'selectedSubIndustry' => $selectedSubIndustry,
            'filters' => [
                'q' => $request->query('q'),
                'country' => $selectedCountry,
                'industry' => $selectedIndustry,
                'sub_industry' => $selectedSubIndustry,
                'status' => $request->query('status'),
                'per_page' => $perPage,
            ],
        ]);
    }
}
