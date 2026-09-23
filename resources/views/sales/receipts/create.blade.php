@extends('layouts.institute')
@section('title','Create Sales Receipt')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Create Sales Receipt</h4>
    <a href="{{ route('sales.receipts.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
</div>
@if ($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('sales.receipts.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Customer <span class="text-danger">*</span></label>
                <select name="party_id" class="form-select" required>
                    <option value="">— Select customer —</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}" {{ old('party_id')==$c->id?'selected':'' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Amount <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" class="form-control" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                    <select name="payment_method" class="form-select" required>
                        @foreach(['cash','bkash','nagad','bank','other'] as $m)
                            <option value="{{ $m }}" {{ old('payment_method')==$m?'selected':'' }}>{{ ucfirst($m) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" value="{{ old('description') }}" class="form-control" maxlength="255">
                </div>
            </div>
            <button class="btn btn-success">Create Receipt</button>
        </form>
    </div>
</div>
@endsection
