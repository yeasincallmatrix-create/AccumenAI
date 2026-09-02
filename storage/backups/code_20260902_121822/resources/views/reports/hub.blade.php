@extends('layouts.institute')

@section('title', 'Reports Hub — AccumenAI')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-graph-up me-2"></i>Reports Hub</h4>
    <span class="badge bg-primary rounded-pill">{{ $count }} reports available</span>
</div>

@if ($institute)
    <div class="alert alert-info d-flex align-items-center">
        <i class="bi bi-building me-2"></i>
        <div>
            <strong>{{ $institute->name }}</strong> — {{ ucfirst($institute->industry ?? '—') }}
            @if($institute->sub_industry) <small class="text-muted"> → {{ ucwords(str_replace('_',' ',$institute->sub_industry)) }}</small> @endif
            <small class="text-muted ms-2">Branch-aware and module-gated</small>
        </div>
    </div>
@endif

@if (empty($grouped))
    <div class="card"><div class="card-body text-center text-muted py-5">No reports available for your industry, sub-industry, modules and permissions.</div></div>
@else
    @foreach($grouped as $industry => $modules)
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-collection me-2"></i>{{ $industry }} Reports</span>
                <span class="badge bg-light text-dark border">{{ collect($modules)->flatten(1)->count() }} reports</span>
            </div>
            <div class="card-body">
                @foreach($modules as $module => $reports)
                    <h6 class="mt-3 mb-2"><span class="badge bg-secondary rounded-pill">{{ ucfirst($module) }}</span></h6>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-3">
                            <thead>
                                <tr>
                                    <th>Report</th>
                                    <th>Description</th>
                                    <th>Filters</th>
                                    <th class="text-center">Branch</th>
                                    <th class="text-center">Export</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($reports as $r)
                                    <tr>
                                        <td class="fw-semibold">{{ $r['title'] }}<br><small class="text-muted">{{ $r['key'] }}</small></td>
                                        <td><small>{{ $r['description'] }}</small></td>
                                        <td><small>{{ implode(', ', $r['filters'] ?? []) ?: '—' }}</small></td>
                                        <td class="text-center">@if($r['branch'])<span class="badge bg-success rounded-pill">Yes</span>@else<span class="badge bg-light text-dark border">No</span>@endif</td>
                                        <td class="text-center">@if($r['export'])<span class="badge bg-info rounded-pill">CSV</span>@else<span class="text-muted">—</span>@endif @if($r['print'])<span class="badge bg-dark rounded-pill">Print</span>@endif</td>
                                        <td class="text-end">
                                            @php $routeExists = \Illuminate\Support\Facades\Route::has($r['route']); @endphp
                                            @if($routeExists)
                                                <a href="{{ route($r['route']) }}" class="btn btn-sm btn-outline-primary rounded-pill">View</a>
                                            @else
                                                <span class="text-muted small">N/A</span>
                                            @endif
                                            <a href="{{ route('reports.hub.show', $r['key']) }}" class="btn btn-sm btn-outline-secondary rounded-pill ms-1">Hub</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
@endif

<div class="alert alert-secondary small">
    <strong>Industry-aware:</strong> Core reports visible to all industries; Education reports only for <code>industry=education</code> (all 13 sub-industries via <code>config/industry_rules.php</code>); Retail reports only for <code>industry=retail</code>. Sub-industry specific reports (e.g., <code>school</code> vs <code>supermarket</code>) filtered via actual configured values — no fake industries.
    <br><strong>Read-only:</strong> All reports use existing <code>FinancialReportService</code>/<code>SalesReportService</code> etc. — no report request mutates data.
</div>
@endsection
