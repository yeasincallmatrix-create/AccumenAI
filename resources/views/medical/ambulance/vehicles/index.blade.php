@extends('layouts.institute')

@section('title', 'Ambulance Fleet — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-truck-front"></i> Ambulance Fleet</h4>
        @if($user && $user->hasPermission('medical.ambulance.fleet.manage'))
            <a href="{{ route('medical.ambulance.vehicles.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle"></i> Register Vehicle
            </a>
        @endif
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="GET" class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search vehicle number..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All statuses</option>
                        @foreach($statuses as $key => $label)
                            <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="type" class="form-select form-select-sm">
                        <option value="">All types</option>
                        @foreach($types as $key => $label)
                            <option value="{{ $key }}" {{ request('type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                </div>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Vehicle #</th>
                            <th>Type</th>
                            <th>Make / Model</th>
                            <th>Status</th>
                            <th>Odometer</th>
                            <th>Service / Insurance</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($vehicles as $v)
                            <tr>
                                <td><strong>{{ $v->vehicle_number }}</strong></td>
                                <td>{{ $v->typeLabel() }}</td>
                                <td>{{ $v->make ?? '—' }} {{ $v->model ?? '' }}</td>
                                <td><span class="badge bg-{{ $v->statusColor() }}">{{ ucfirst(str_replace('_', ' ', $v->status)) }}</span></td>
                                <td>{{ $v->odometer_km ?? '—' }} km</td>
                                <td>
                                    @if($v->needsService())<span class="badge bg-warning">Service due</span>@endif
                                    @if($v->isInsuranceExpiringSoon())<span class="badge bg-danger">Insurance expiring</span>@endif
                                    @if(!$v->needsService() && !$v->isInsuranceExpiringSoon())<span class="text-muted">OK</span>@endif
                                </td>
                                <td>
                                    <a href="{{ route('medical.ambulance.vehicles.show', $v) }}" class="btn btn-outline-primary btn-sm">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No vehicles found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $vehicles->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection
