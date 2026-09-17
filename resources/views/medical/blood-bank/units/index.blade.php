@extends('layouts.institute')

@section('title', 'Blood Units — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-droplet"></i> Blood Units</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('medical.blood-bank.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-grid"></i> Dashboard
            </a>
            @if($user && $user->hasPermission('medical_bloodbank.create'))
                <a href="{{ route('medical.blood-bank.units.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> New Unit
                </a>
            @endif
        </div>
    </div>

    <form method="GET" class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Unit #, donor name" value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Blood Group</label>
                    <select name="blood_group" class="form-select">
                        <option value="">All Groups</option>
                        @foreach(\App\Models\Medical\BloodDonor::BLOOD_GROUPS as $key => $label)
                            <option value="{{ $key }}" @selected(request('blood_group') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Component</label>
                    <select name="component" class="form-select">
                        <option value="">All Components</option>
                        @foreach(\App\Models\Medical\BloodUnit::COMPONENTS as $key => $label)
                            <option value="{{ $key }}" @selected(request('component') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        @foreach(\App\Models\Medical\BloodUnit::STATUSES as $key => $label)
                            <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <div class="form-check mt-4">
                        <input type="checkbox" name="expiring_soon" value="1" class="form-check-input" id="expiring_soon" @checked(request('expiring_soon'))>
                        <label class="form-check-label" for="expiring_soon">Expiring Soon</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Filter</button>
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
                            <th>Unit #</th>
                            <th>Blood Group</th>
                            <th>Component</th>
                            <th>Volume</th>
                            <th>Donor</th>
                            <th>Expiry</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($units as $unit)
                            <tr>
                                <td><strong>{{ $unit->unit_number }}</strong></td>
                                <td><span class="badge bg-light text-dark">{{ $unit->blood_group }}</span></td>
                                <td>{{ $unit->componentLabel() }}</td>
                                <td>{{ $unit->volume_ml }} ml</td>
                                <td>{{ $unit->donor?->fullName() ?? '-' }}</td>
                                <td>
                                    @if($unit->expiry_date)
                                        <x-tdate :value="$unit->expiry_date" />
                                        @if($unit->daysUntilExpiry() !== null && $unit->daysUntilExpiry() <= 7 && $unit->status === 'available')
                                            <br><small class="text-danger">{{ $unit->daysUntilExpiry() }} day(s) left</small>
                                        @endif
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $unit->statusColor() }}">{{ $unit->statusLabel() }}</span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('medical.blood-bank.units.show', $unit) }}" class="btn btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                                        @if($user && $user->hasPermission('medical_bloodbank.edit'))
                                            <a href="{{ route('medical.blood-bank.units.edit', $unit) }}" class="btn btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="bi bi-droplet fs-2 d-block mb-2"></i>
                                    No blood units found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $units->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection
