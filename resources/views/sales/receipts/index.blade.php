@extends('layouts.institute')
@section('title','Sales Receipts')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Sales Receipts</h4>
    <a href="{{ route('sales.receipts.create') }}" class="btn btn-sm btn-primary rounded-pill">+ Sales Receipt</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-2"><input type="date" name="from_date" value="{{ request('from_date') }}" class="form-control form-control-sm"></div>
            <div class="col-md-2"><input type="date" name="to_date" value="{{ request('to_date') }}" class="form-control form-control-sm"></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary">Filter</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>Memo #</th><th>Customer</th><th class="text-end">Amount</th><th>Method</th><th>Description</th><th>Date</th></tr></thead>
                <tbody>
                @forelse($receipts as $r)
                    <tr>
                        <td>{{ $r->memo_number }}</td>
                        <td>{{ $r->party?->name ?? '—' }}</td>
                        <td class="text-end">{{ number_format((float)$r->amount,2) }}</td>
                        <td>{{ $r->payment_method }}</td>
                        <td>{{ $r->description ?? '—' }}</td>
                        <td><x-tdate :value="$r->created_at" fallback="Y-m-d" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">No receipts found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $receipts->links() }}
    </div>
</div>
@endsection
