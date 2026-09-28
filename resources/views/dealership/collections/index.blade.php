@extends('layouts.standalone')

@section('title', 'SR Collections — AccumenAI')
@section('page_title', 'SR Collections')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-cash-coin me-2"></i>কালেকশন (Collections)</h4>
</div>

<div class="admin-card card mb-3">
    <div class="card-body p-0">
        <table class="table table-striped mb-0">
            <thead><tr><th>Receipt No</th><th>Customer</th><th>SR</th><th>Method</th><th>Amount</th><th>Collected On</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($collections as $row)
                    <tr>
                        <td><code>{{ $row->receipt_no }}</code></td>
                        <td>{{ $row->customer_id }}</td>
                        <td>{{ $row->sales_force_id }}</td>
                        <td>{{ $row->method }}</td>
                        <td>{{ number_format($row->amount, 2) }}</td>
                        <td>{{ $row->collected_on }}</td>
                        <td><span class="badge bg-secondary">{{ $row->status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">কোনো কালেকশন নেই (No collections yet)</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $collections->links() }}

<div class="admin-card card">
    <div class="card-header">নতুন কালেকশন (New Collection)</div>
    <div class="card-body">
        <form method="POST" action="{{ route('dealership.collections.store') }}" class="row g-2">
            @csrf
            <div class="col-md-2"><label class="form-label">Customer ID</label><input type="number" name="customer_id" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">SR ID</label><input type="number" name="sales_force_id" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Method</label>
                <select name="method" class="form-select">@foreach($methods as $m)<option value="{{ $m }}">{{ $m }}</option>@endforeach</select>
            </div>
            <div class="col-md-2"><label class="form-label">Amount</label><input type="number" step="0.01" name="amount" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Collected On</label><x-tdate-input name="collected_on" /></div>
            <div class="col-md-2"><label class="form-label">Reference</label><input type="text" name="reference" class="form-control"></div>
            <div class="col-12"><button class="btn btn-success btn-sm" type="submit">Record Collection</button></div>
        </form>
    </div>
</div>
@endsection
