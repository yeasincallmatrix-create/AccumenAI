@extends('layouts.institute')

@section('title', 'Quotations — AccumenAI')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Quotations</h4>
    <a class="btn btn-primary btn-sm rounded-pill" href="{{ route('sales.quotations.create') }}"><i class="bi bi-plus-lg me-1"></i>New Quotation</a>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Number or customer">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">From</label>
                <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <button class="btn btn-sm btn-primary rounded-pill" type="submit"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="{{ route('sales.quotations.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Number</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Valid Until</th>
                    <th class="text-end">Total</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($quotations as $q)
                    <tr>
                        <td class="fw-semibold">{{ $q->quotation_number }}</td>
                        <td>{{ $q->customer?->name ?? '—' }}</td>
                        <td>{{ $q->quotation_date->format('Y-m-d') }}</td>
                        <td>{{ $q->validity_date->format('Y-m-d') }}</td>
                        <td class="text-end">{{ number_format($q->grand_total, 2) }} {{ $q->currency?->code }}</td>
                        <td>
                            @php $colors = ['draft'=>'secondary','sent'=>'info','accepted'=>'success','rejected'=>'danger','expired'=>'warning','cancelled'=>'dark']; @endphp
                            <span class="badge bg-{{ $colors[$q->status] ?? 'secondary' }}">{{ ucfirst($q->status) }}</span>
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary rounded-pill" href="{{ route('sales.quotations.show', $q) }}"><i class="bi bi-eye"></i></a>
                            @if ($q->isDraft())
                                <a class="btn btn-sm btn-outline-secondary rounded-pill" href="{{ route('sales.quotations.edit', $q) }}"><i class="bi bi-pencil"></i></a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No quotations found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($quotations->hasPages())
        <div class="card-footer">{{ $quotations->links() }}</div>
    @endif
</div>
@endsection
