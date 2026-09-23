@extends('layouts.institute')
@section('title','Sales Receipts')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-cash-coin me-2"></i>Sales Receipts</h4>
    <a href="{{ route('sales.receipts.create') }}" class="btn btn-sm btn-primary rounded-pill"><i class="bi bi-plus-lg me-1"></i>Sales Receipt</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-2"><label class="form-label">From</label><input type="date" name="from_date" value="{{ request('from_date') }}" class="form-control form-control-sm"></div>
            <div class="col-md-2"><label class="form-label">To</label><input type="date" name="to_date" value="{{ request('to_date') }}" class="form-control form-control-sm"></div>
            <div class="col-md-3">
                <button class="btn btn-sm btn-primary rounded-pill" type="submit"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="{{ route('sales.receipts.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Reset</a>
            </div>
        </form>
    </div>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Memo #</th><th>Customer</th><th class="text-end">Amount</th><th>Method</th><th>Description</th><th>Date</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse($receipts as $r)
                <tr>
                    <td class="fw-semibold">{{ $r->memo_number }}</td>
                    <td>{{ $r->party?->name ?? '—' }}</td>
                    <td class="text-end fw-semibold">{{ number_format((float)$r->amount,2) }}</td>
                    <td><span class="badge bg-light text-dark">{{ $r->payment_method }}</span></td>
                    <td>{{ \Illuminate\Support\Str::limit($r->description ?? '—', 40) }}</td>
                    <td><x-tdate :value="$r->created_at" fallback="Y-m-d" /></td>
                    <td class="text-end"><a href="{{ route('sales.receipts.show',$r) }}" class="btn btn-sm btn-outline-primary rounded-pill" title="View"><i class="bi bi-eye"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No receipts found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($receipts->hasPages())<div class="card-footer">{{ $receipts->links() }}</div>@endif
</div>
@endsection
