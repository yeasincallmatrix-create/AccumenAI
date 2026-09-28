@extends('layouts.standalone')

@section('title', 'Price Lists — AccumenAI')
@section('page_title', 'Price Lists')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-tags me-2"></i>মূল্য তালিকা (Price Lists)</h4>
</div>

<div class="filter-card card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('dealership.price_lists.index') }}" class="row g-2">
            <div class="col-md-3"><label class="form-label">Effective Date / কার্যকর তারিখ</label><x-tdate-input name="date" :value="$date" /></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-sm" type="submit">Filter</button></div>
        </form>
    </div>
</div>

<div class="admin-card card mb-3">
    <div class="card-body p-0">
        <table class="table table-striped mb-0">
            <thead><tr><th>Brand</th><th>Product</th><th>Channel</th><th>Price</th><th>From</th><th>To</th><th>Active</th></tr></thead>
            <tbody>
                @forelse($prices as $row)
                    <tr>
                        <td>{{ $row->brand_id }}</td>
                        <td>{{ $row->product_id ?? '—' }}</td>
                        <td>{{ $row->channel }}</td>
                        <td>{{ number_format($row->price, 2) }}</td>
                        <td>{{ $row->effective_from ?? '—' }}</td>
                        <td>{{ $row->effective_to ?? '—' }}</td>
                        <td>{{ $row->is_active ? 'Yes' : 'No' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">কোনো মূল্য নেই (No prices yet)</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $prices->links() }}

<div class="admin-card card">
    <div class="card-header">নতুন মূল্য (New Price)</div>
    <div class="card-body">
        <form method="POST" action="{{ route('dealership.price_lists.store') }}" class="row g-2">
            @csrf
            <div class="col-md-2"><label class="form-label">Brand ID</label><input type="number" name="brand_id" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Product ID</label><input type="number" name="product_id" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">Channel</label>
                <select name="channel" class="form-select"><option value="general">general</option><option value="retail">retail</option><option value="wholesale">wholesale</option><option value="sub_dealer">sub_dealer</option></select>
            </div>
            <div class="col-md-2"><label class="form-label">Price</label><input type="number" step="0.01" name="price" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">From</label><x-tdate-input name="effective_from" /></div>
            <div class="col-md-2"><label class="form-label">To</label><x-tdate-input name="effective_to" /></div>
            <div class="col-12"><button class="btn btn-success btn-sm" type="submit">Save Price</button></div>
        </form>
    </div>
</div>
@endsection
