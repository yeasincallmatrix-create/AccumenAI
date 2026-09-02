@extends('layouts.institute')

@section('title', mawa_e('alumni.reports') . ' — AccumenAI')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ mawa_e('alumni.reports') }}</h4>
        <p class="page-header-desc">{{ mawa_e('alumni.reports_desc') }}</p>
    </div>
    <div class="page-header-actions d-flex gap-2">
        <a class="btn btn-outline-primary" href="{{ route('alumni.index') }}">
            <i class="bi bi-speedometer2 me-1"></i>{{ mawa_e('alumni.nav_dashboard') }}
        </a>
        <a class="btn btn-outline-primary" href="{{ route('alumni.directory') }}">
            <i class="bi bi-people-fill me-1"></i>{{ mawa_e('alumni.nav_directory') }}
        </a>
    </div>
</div>

<div class="admin-card">
    <form class="d-flex flex-wrap gap-2 mb-3 align-items-end" method="GET" action="{{ route('alumni.reports') }}">
        <div style="width:220px">
            <select name="branch_id" class="form-select">
                <option value="">{{ mawa_e('alumni.filter_all_branches') }}</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) $branchId === (string) $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>{{ mawa_e('alumni.apply') }}</button>
        <a class="btn btn-outline-secondary" href="{{ route('alumni.reports') }}" title="{{ mawa_e('alumni.reset_filters') }}">
            <i class="bi bi-arrow-counterclockwise"></i>
        </a>
    </form>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="admin-card h-100">
            <div class="card-body">
                <div class="text-muted small">{{ mawa_e('alumni.stat_total') }}</div>
                <div class="fs-3 fw-bold">{{ $report['totals']['total'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="admin-card h-100">
            <div class="card-body">
                <div class="text-muted small">{{ mawa_e('alumni.stat_active') }}</div>
                <div class="fs-3 fw-bold text-success">{{ $report['totals']['active'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="admin-card h-100">
            <div class="card-body">
                <div class="text-muted small">{{ mawa_e('alumni.stat_inactive') }}</div>
                <div class="fs-3 fw-bold text-secondary">{{ $report['totals']['inactive'] }}</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="admin-card h-100">
            <div class="card-header"><h5 class="mb-0">{{ mawa_e('alumni.report_by_course') }}</h5></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>{{ mawa_e('alumni.th_course') }}</th><th class="text-end">{{ mawa_e('alumni.th_alumni') }}</th></tr></thead>
                    <tbody>
                        @forelse ($report['by_course'] as $row)
                            <tr><td>{{ $row->label }}</td><td class="text-end">{{ $row->total }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="text-center text-muted py-4">{{ mawa_e('alumni.no_data') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="admin-card h-100">
            <div class="card-header"><h5 class="mb-0">{{ mawa_e('alumni.report_by_batch') }}</h5></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>{{ mawa_e('alumni.th_batch') }}</th><th class="text-end">{{ mawa_e('alumni.th_alumni') }}</th></tr></thead>
                    <tbody>
                        @forelse ($report['by_batch'] as $row)
                            <tr><td>{{ $row->label }}</td><td class="text-end">{{ $row->total }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="text-center text-muted py-4">{{ mawa_e('alumni.no_data') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="admin-card h-100">
            <div class="card-header"><h5 class="mb-0">{{ mawa_e('alumni.report_by_completion_year') }}</h5></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>{{ mawa_e('alumni.th_academic_year') }}</th><th class="text-end">{{ mawa_e('alumni.th_alumni') }}</th></tr></thead>
                    <tbody>
                        @forelse ($report['by_academic_year'] as $row)
                            <tr><td>{{ $row->label }}</td><td class="text-end">{{ $row->total }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="text-center text-muted py-4">{{ mawa_e('alumni.no_data') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="admin-card h-100">
            <div class="card-header"><h5 class="mb-0">{{ mawa_e('alumni.report_by_graduation_year') }}</h5></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>{{ mawa_e('alumni.th_year') }}</th><th class="text-end">{{ mawa_e('alumni.th_alumni') }}</th></tr></thead>
                    <tbody>
                        @forelse ($report['by_graduation_year'] as $row)
                            <tr><td>{{ $row->label }}</td><td class="text-end">{{ $row->total }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="text-center text-muted py-4">{{ mawa_e('alumni.no_data') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="admin-card h-100">
            <div class="card-header"><h5 class="mb-0">{{ mawa_e('alumni.report_by_occupation') }}</h5></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>{{ mawa_e('alumni.th_occupation') }}</th><th class="text-end">{{ mawa_e('alumni.th_alumni') }}</th></tr></thead>
                    <tbody>
                        @forelse ($report['by_occupation'] as $row)
                            <tr><td>{{ $row->label }}</td><td class="text-end">{{ $row->total }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="text-center text-muted py-4">{{ mawa_e('alumni.no_data') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="admin-card h-100">
            <div class="card-header"><h5 class="mb-0">{{ mawa_e('alumni.report_top_employers') }}</h5></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>{{ mawa_e('alumni.th_employer') }}</th><th class="text-end">{{ mawa_e('alumni.th_alumni') }}</th></tr></thead>
                    <tbody>
                        @forelse ($report['top_employers'] as $row)
                            <tr><td>{{ $row->label }}</td><td class="text-end">{{ $row->total }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="text-center text-muted py-4">{{ mawa_e('alumni.no_data') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
