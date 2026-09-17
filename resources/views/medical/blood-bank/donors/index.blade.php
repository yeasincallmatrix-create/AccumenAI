@extends('layouts.institute')

@section('title', 'Blood Donors — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-person-heart"></i> Blood Donors</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('medical.blood-bank.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-grid"></i> Dashboard
            </a>
            @if($user && $user->hasPermission('medical_bloodbank.create'))
                <a href="{{ route('medical.blood-bank.donors.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> New Donor
                </a>
            @endif
        </div>
    </div>

    <form method="GET" class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Donor #, name, phone" value="{{ request('search') }}">
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
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        @foreach(\App\Models\Medical\BloodDonor::STATUSES as $key => $label)
                            <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
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
                            <th>Donor #</th>
                            <th>Name</th>
                            <th>Blood Group</th>
                            <th>Gender</th>
                            <th>Phone</th>
                            <th>Last Donation</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($donors as $donor)
                            <tr>
                                <td><strong>{{ $donor->donor_number }}</strong></td>
                                <td>{{ $donor->fullName() }}</td>
                                <td><span class="badge bg-light text-dark">{{ $donor->bloodGroupLabel() }}</span></td>
                                <td>{{ $donor->genderLabel() }}</td>
                                <td>{{ $donor->phone ?? '-' }}</td>
                                <td>
                                    @if($donor->last_donation_date)
                                        <x-tdate :value="$donor->last_donation_date" />
                                    @else
                                        <span class="text-muted">Never</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $donor->statusColor() }}">{{ $donor->statusLabel() }}</span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('medical.blood-bank.donors.show', $donor) }}" class="btn btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                                        @if($user && $user->hasPermission('medical_bloodbank.edit'))
                                            <a href="{{ route('medical.blood-bank.donors.edit', $donor) }}" class="btn btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="bi bi-person-heart fs-2 d-block mb-2"></i>
                                    No blood donors found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $donors->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection
