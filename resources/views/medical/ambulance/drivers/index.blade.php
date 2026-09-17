@extends('layouts.institute')

@section('title', 'Ambulance Drivers — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-person-badge"></i> Ambulance Drivers</h4>
        @if($user && $user->hasPermission('medical.ambulance.driver.manage'))
            <a href="{{ route('medical.ambulance.drivers.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle"></i> Register Driver
            </a>
        @endif
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="GET" class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search name/number/phone..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All statuses</option>
                        @foreach(\App\Models\Medical\AmbulanceDriver::STATUSES as $key => $label)
                            <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ $label }}</option>
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
                            <th>Driver #</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>License</th>
                            <th>Status</th>
                            <th>Alerts</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($drivers as $d)
                            <tr>
                                <td><strong>{{ $d->driver_number }}</strong></td>
                                <td>{{ $d->name }} @if($d->isOnActiveTrip())<span class="badge bg-primary">On trip</span>@endif</td>
                                <td>{{ $d->phone }}</td>
                                <td>{{ $d->license_number ?? '—' }}</td>
                                <td><span class="badge bg-{{ $d->statusColor() }}">{{ ucfirst(str_replace('_', ' ', $d->status)) }}</span></td>
                                <td>@if($d->isLicenseExpiring())<span class="badge bg-danger">License expiring</span>@else<span class="text-muted">—</span>@endif</td>
                                <td>
                                    <a href="{{ route('medical.ambulance.drivers.show', $d) }}" class="btn btn-outline-primary btn-sm">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No drivers found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $drivers->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection
