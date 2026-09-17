@extends('layouts.institute')

@section('title', 'Blood Bank — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-droplet-fill"></i> Blood Bank</h4>
        <div class="d-flex gap-2">
            @if($user && $user->hasPermission('medical_bloodbank.create'))
                <a href="{{ route('medical.blood-bank.donors.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> New Donor
                </a>
                <a href="{{ route('medical.blood-bank.units.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> New Unit
                </a>
                <a href="{{ route('medical.blood-bank.requests.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> New Request
                </a>
            @endif
            <a href="{{ route('medical.blood-bank.donors.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-list-ul"></i> Donors
            </a>
            <a href="{{ route('medical.blood-bank.units.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-list-ul"></i> Units
            </a>
            <a href="{{ route('medical.blood-bank.requests.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-list-ul"></i> Requests
            </a>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-md-2">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold">{{ $todayStats['total_donors'] }}</div>
                    <small class="text-muted">Total Donors</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-success">{{ $todayStats['active_units'] }}</div>
                    <small class="text-muted">Active Units</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-warning">{{ $todayStats['pending_requests'] }}</div>
                    <small class="text-muted">Pending Requests</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-primary">{{ $todayStats['fulfilled_today'] }}</div>
                    <small class="text-muted">Fulfilled Today</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-danger">{{ $todayStats['urgent_requests'] }}</div>
                    <small class="text-muted">Urgent Requests</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-secondary">{{ $todayStats['expired_units'] }}</div>
                    <small class="text-muted">Expired Units</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-grid"></i> Blood Group Availability</div>
                <div class="card-body">
                    @php
                        $allGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                    @endphp
                    <div class="row g-2">
                        @foreach($allGroups as $group)
                            @php
                                $count = $availableByGroup[$group] ?? 0;
                                $bgClass = $count === 0 ? 'border-danger' : ($count < 3 ? 'border-warning' : 'border-success');
                            @endphp
                            <div class="col-md-3 col-6">
                                <div class="card text-center {{ $bgClass }}">
                                    <div class="card-body py-3">
                                        <div class="fs-3 fw-bold">{{ $group }}</div>
                                        <div class="fs-5 {{ $count === 0 ? 'text-danger' : ($count < 3 ? 'text-warning' : 'text-success') }}">{{ $count }}</div>
                                        <small class="text-muted">units available</small>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-exclamation-triangle"></i> Low Stock Alerts</div>
                <div class="card-body">
                    @forelse($lowStock as $group => $count)
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <span class="fw-bold">{{ $group }}</span>
                            <span class="badge bg-danger">{{ $count }} unit(s)</span>
                        </div>
                    @empty
                        <p class="text-muted mb-0">All blood groups have sufficient stock.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-clock-history"></i> Expiring Soon (Within 7 Days)</div>
                <div class="card-body">
                    @if($expiringSoon->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Unit #</th>
                                        <th>Blood Group</th>
                                        <th>Component</th>
                                        <th>Donor</th>
                                        <th>Expiry Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($expiringSoon as $unit)
                                        <tr>
                                            <td>
                                                <a href="{{ route('medical.blood-bank.units.show', $unit) }}">
                                                    <strong>{{ $unit->unit_number }}</strong>
                                                </a>
                                            </td>
                                            <td><span class="badge bg-light text-dark">{{ $unit->blood_group }}</span></td>
                                            <td>{{ $unit->component }}</td>
                                            <td>{{ $unit->donor?->fullName() ?? '-' }}</td>
                                            <td><x-tdate :value="$unit->expiry_date" /></td>
                                            <td>
                                                <span class="badge bg-{{ $unit->statusColor() }}">{{ $unit->statusLabel() }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mb-0">No units expiring within the next 7 days.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
