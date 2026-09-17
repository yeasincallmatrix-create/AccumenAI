@extends('layouts.institute')

@section('title', 'Blood Requests — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-clipboard2-pulse"></i> Blood Requests</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('medical.blood-bank.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-grid"></i> Dashboard
            </a>
            @if($user && $user->hasPermission('medical_bloodbank.create'))
                <a href="{{ route('medical.blood-bank.requests.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> New Request
                </a>
            @endif
        </div>
    </div>

    <form method="GET" class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Request #, patient name" value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        @foreach(\App\Models\Medical\BloodRequest::STATUSES as $key => $label)
                            <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
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
                    <label class="form-label">Urgency</label>
                    <select name="urgency" class="form-select">
                        <option value="">All Urgency</option>
                        @foreach(\App\Models\Medical\BloodRequest::URGENCY_LEVELS as $key => $label)
                            <option value="{{ $key }}" @selected(request('urgency') === $key)>{{ $label }}</option>
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
                            <th>Request #</th>
                            <th>Patient</th>
                            <th>Blood Group</th>
                            <th>Component</th>
                            <th>Units</th>
                            <th>Urgency</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $bloodRequest)
                            <tr>
                                <td><strong>{{ $bloodRequest->request_number }}</strong></td>
                                <td>{{ $bloodRequest->patient?->full_name ?? '-' }}</td>
                                <td><span class="badge bg-light text-dark">{{ $bloodRequest->blood_group }}</span></td>
                                <td>{{ $bloodRequest->component ?? '-' }}</td>
                                <td>{{ $bloodRequest->units_issued }} / {{ $bloodRequest->units_requested }}</td>
                                <td>
                                    <span class="badge bg-{{ $bloodRequest->urgencyColor() }}">{{ $bloodRequest->urgencyLabel() }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $bloodRequest->statusColor() }}">{{ $bloodRequest->statusLabel() }}</span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('medical.blood-bank.requests.show', $bloodRequest) }}" class="btn btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                                        @if($user && $user->hasPermission('medical_bloodbank.edit') && $bloodRequest->isPending())
                                            <a href="{{ route('medical.blood-bank.requests.edit', $bloodRequest) }}" class="btn btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="bi bi-clipboard2-pulse fs-2 d-block mb-2"></i>
                                    No blood requests found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $requests->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection
