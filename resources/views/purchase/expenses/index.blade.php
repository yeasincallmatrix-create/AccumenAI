@extends('layouts.institute')
@section('title','Expenses')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Expenses</h4>
    <a href="{{ route('purchase.expenses.create') }}" class="btn btn-sm btn-primary rounded-pill">+ Expense</a>
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
                <thead><tr><th>#</th><th>Date</th><th>Vendor</th><th>Category</th><th class="text-end">Amount</th><th>Description</th></tr></thead>
                <tbody>
                @forelse($expenses as $e)
                    <tr>
                        <td>{{ $e->expense_number }}</td>
                        <td><x-tdate :value="$e->expense_date" fallback="Y-m-d" /></td>
                        <td>{{ $e->vendor_name ?? '—' }}</td>
                        <td>{{ $e->expense_category ?? '—' }}</td>
                        <td class="text-end">{{ number_format((float)$e->amount,2) }} {{ $e->currency }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($e->description ?? '', 50) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">No expenses found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $expenses->links() }}
    </div>
</div>
@endsection
