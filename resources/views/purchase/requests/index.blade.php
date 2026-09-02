@extends('layouts.standalone')

@section('title', 'Purchase Requests — AccumenAI')
@section('page_title', 'Purchase Requests')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-clipboard-check me-2"></i>Purchase Requests</h4>
    <a class="btn btn-primary btn-sm rounded-pill" href="{{ route('purchase.requests.create') }}"><i class="bi bi-plus-lg me-1"></i>New Request</a>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@include('purchase._tabs', ['activeTab' => 'requests'])

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Request # / Requester">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
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
                <a href="{{ route('purchase.requests.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Request #</th>
                    <th>Requester</th>
                    <th>Date</th>
                    <th>Required By</th>
                    <th class="text-end">Est. Total</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requests as $pr)
                    <tr>
                        <td class="fw-semibold">{{ $pr->request_number }}</td>
                        <td>{{ $pr->requester?->first_name }} {{ $pr->requester?->last_name }}</td>
                        <td>{{ $pr->request_date?->format('Y-m-d') }}</td>
                        <td>{{ $pr->required_by_date?->format('Y-m-d') ?? '—' }}</td>
                        <td class="text-end">{{ number_format($pr->estimated_total, 2) }}</td>
                        <td>
                            @php $colors = ['draft'=>'secondary','submitted'=>'warning','approved'=>'success','converted'=>'primary','rejected'=>'danger','cancelled'=>'dark']; @endphp
                            <span class="badge bg-{{ $colors[$pr->status] ?? 'secondary' }}">{{ ucfirst(str_replace('_', ' ', $pr->status)) }}</span>
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary rounded-pill" href="{{ route('purchase.requests.show', $pr) }}"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No purchase requests found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($requests->hasPages())
        <div class="card-footer">{{ $requests->links() }}</div>
    @endif
</div>
@endsection
