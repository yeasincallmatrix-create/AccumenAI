@extends('layouts.institute')

@section('title', 'Dental Procedures — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-tools"></i> Dental Procedures</h4>
        @if($user && $user->hasPermission('medical.dental.procedure.create'))
            <a href="{{ route('medical.dental.procedures.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle"></i> Record Procedure
            </a>
        @endif
    </div>

    <form method="GET" class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search number/name..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Status</option>
                        @foreach(\App\Models\Medical\DentalProcedure::STATUSES as $k => $v)
                            <option value="{{ $k }}" {{ request('status') === $k ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="category" class="form-select form-select-sm">
                        <option value="">All Categories</option>
                        @foreach(\App\Models\Medical\DentalProcedure::CATEGORIES as $k => $v)
                            <option value="{{ $k }}" {{ request('category') === $k ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i> Filter</button>
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
                            <th>Number</th>
                            <th>Patient</th>
                            <th>Procedure</th>
                            <th>Tooth</th>
                            <th>Category</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Fee</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($procedures as $proc)
                            <tr>
                                <td><strong>{{ $proc->procedure_number }}</strong></td>
                                <td>{{ $proc->patient->full_name ?? 'N/A' }}</td>
                                <td>{{ $proc->procedure_name }}</td>
                                <td>{{ $proc->fullToothName() ?? '—' }}</td>
                                <td><span class="badge bg-info">{{ $proc->categoryLabel() ?? '—' }}</span></td>
                                <td>{{ $proc->performed_at->format('d M Y H:i') }}</td>
                                <td><span class="badge bg-{{ $proc->statusColor() }}">{{ $proc->statusLabel() }}</span></td>
                                <td>{{ number_format($proc->fee, 2) }}</td>
                                <td>
                                    <a href="{{ route('medical.dental.procedures.show', $proc) }}" class="btn btn-outline-primary btn-sm">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">No procedures found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $procedures->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection
