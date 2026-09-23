@extends('layouts.institute')
@section('title','Expenses')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-wallet2 me-2"></i>Expenses</h4>
    <a href="{{ route('purchase.expenses.create') }}" class="btn btn-sm btn-primary rounded-pill"><i class="bi bi-plus-lg me-1"></i>Expense</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-2"><label class="form-label">From</label><input type="date" name="from_date" value="{{ request('from_date') }}" class="form-control form-control-sm"></div>
            <div class="col-md-2"><label class="form-label">To</label><input type="date" name="to_date" value="{{ request('to_date') }}" class="form-control form-control-sm"></div>
            <div class="col-md-3">
                <button class="btn btn-sm btn-primary rounded-pill" type="submit"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="{{ route('purchase.expenses.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Reset</a>
            </div>
        </form>
    </div>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>#</th><th>Date</th><th>Vendor</th><th>Category</th><th class="text-end">Amount</th><th>Description</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse($expenses as $e)
                <tr>
                    <td class="fw-semibold">{{ $e->expense_number }}</td>
                    <td><x-tdate :value="$e->expense_date" fallback="Y-m-d" /></td>
                    <td>{{ $e->vendor_name ?? '—' }}</td>
                    <td>@if($e->expense_category)<span class="badge bg-info">{{ $e->expense_category }}</span>@else—@endif</td>
                    <td class="text-end fw-semibold">{{ number_format((float)$e->amount,2) }} <small class="text-muted">{{ $e->currency }}</small></td>
                    <td>{{ \Illuminate\Support\Str::limit($e->description ?? '—', 40) }}</td>
                    <td class="text-end"><a href="{{ route('purchase.expenses.show',$e) }}" class="btn btn-sm btn-outline-primary rounded-pill" title="View"><i class="bi bi-eye"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No expenses found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($expenses->hasPages())<div class="card-footer">{{ $expenses->links() }}</div>@endif
</div>
@endsection
