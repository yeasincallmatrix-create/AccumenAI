@extends('layouts.standalone')

@section('title', 'Fiscal Years & Periods — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>Fiscal Years & Periods</h4>
    <p>Posting only lands in an open accounting period. Close a period to lock it against new postings; reopening is an audit event.</p>
</div>

<div class="admin-card mb-3">
    <h6 class="card-title">New fiscal year</h6>
    <form method="POST" action="{{ route('finance.periods.store') }}">
        @csrf
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" name="name" value="{{ old('name', 'FY '.now()->addYear()->format('Y')) }}" required maxlength="50">
            </div>
            <div class="col-md-3">
                <label class="form-label">Start date <span class="text-danger">*</span></label>
                <input type="date" class="form-control form-control-sm" name="start_date" value="{{ old('start_date') }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">End date <span class="text-danger">*</span></label>
                <input type="date" class="form-control form-control-sm" name="end_date" value="{{ old('end_date') }}" required>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary btn-sm w-100" type="submit"><i class="bi bi-plus-lg me-1"></i>Create</button>
            </div>
        </div>
    </form>
</div>

@forelse ($fiscalYears as $year)
    <div class="admin-card mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <div>
                <h6 class="card-title mb-0">
                    {{ $year->name }}
                    <span class="badge text-bg-{{ $year->is_current ? 'success' : 'secondary' }} ms-1">{{ $year->is_current ? 'Current' : 'Archived' }}</span>
                    <span class="badge text-bg-light border ms-1">{{ $year->status }}</span>
                </h6>
                <div class="small text-muted">{{ $year->start_date }} → {{ $year->end_date }}</div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($year->periods->sortBy('start_date') as $period)
                        <tr>
                            <td>{{ $period->name }}</td>
                            <td>{{ $period->start_date }}</td>
                            <td>{{ $period->end_date }}</td>
                            <td>
                                <span class="badge text-bg-{{ $period->status === 'open' ? 'success' : 'secondary' }}">{{ $period->status }}</span>
                            </td>
                            <td class="text-end">
                                @if ($period->status === 'open')
                                    <form method="POST" action="{{ route('finance.periods.close', $period) }}" class="d-inline" data-ajax-submit="1" data-confirm="Close this period? New postings will be blocked.">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-warning" type="submit"><i class="bi bi-lock me-1"></i>Close</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('finance.periods.reopen', $period) }}" class="d-inline" data-ajax-submit="1" data-confirm="Reopen this period for postings?">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-success" type="submit"><i class="bi bi-unlock me-1"></i>Reopen</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@empty
    <div class="admin-card">
        <p class="text-muted mb-0">No fiscal years yet. Create one above.</p>
    </div>
@endforelse

@if ($fiscalYears->hasPages())
    <div class="p-2">{{ $fiscalYears->links() }}</div>
@endif

@endsection