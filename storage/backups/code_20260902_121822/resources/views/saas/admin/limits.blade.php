@extends('layouts.standalone')

@section('title', 'SaaS Feature Limits — AccumenAI')
@section('page_title', 'Package Feature Limits Matrix')

@section('content')
<div class="admin-card mb-4">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-grid-3x3-gap"></i> Module Availability by Package
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Module</th>
                    @foreach($packages as $pkg)
                        <th class="text-center">{{ $pkg->name }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($modules as $mod)
                    <tr>
                        <td>
                            <strong>{{ $mod->name }}</strong>
                            <br><small class="text-muted">{{ $mod->key }}</small>
                        </td>
                        @foreach($packages as $pkg)
                            <td class="text-center">
                                @if($pkg->packageModules->where('module_key', $mod->key)->where('enabled', true)->count())
                                    <i class="bi bi-check-circle-fill text-success"></i>
                                @else
                                    <i class="bi bi-x-circle text-danger"></i>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-building"></i> Institute Package Assignments
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Institute</th>
                    <th>Current Package</th>
                    <th>Max Students</th>
                    <th>Max Teachers</th>
                    <th>Monthly Price</th>
                    <th>Yearly Price</th>
                </tr>
            </thead>
            <tbody>
                @forelse($institutesWithPackages as $inst)
                    <tr>
                        <td>{{ $inst->name }}</td>
                        <td><span class="badge bg-info">{{ $inst->package->name ?? 'FREE' }}</span></td>
                        <td>{{ $inst->package->max_students ?? '—' }}</td>
                        <td>{{ $inst->package->max_teachers ?? '—' }}</td>
                        <td>{{ $inst->package ? number_format($inst->package->price_monthly, 2) . ' BDT' : '—' }}</td>
                        <td>{{ $inst->package ? number_format($inst->package->price_yearly, 2) . ' BDT' : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">No institutes found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
