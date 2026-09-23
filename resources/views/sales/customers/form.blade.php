@extends('layouts.standalone')

@section('title', ($isEdit ? 'Edit' : 'New') . ' Customer — AccumenAI')
@section('page_title', ($isEdit ? 'Edit' : 'New') . ' Customer')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">{{ $isEdit ? 'Edit' : 'New' }} Customer</h4>
    <a href="{{ route('sales.customers.manage.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Back</a>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ $isEdit ? route('sales.customers.manage.update', $customer) : route('sales.customers.manage.store') }}">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $customer?->name) }}" class="form-control" required maxlength="150">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    @include('partials.phone', ['name' => 'phone', 'id' => 'sales_customer_phone', 'value' => old('phone', $customer?->phone)])
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" value="{{ old('email', $customer?->email) }}" class="form-control" maxlength="150">
                </div>
                <div class="col-md-6">
                    <label class="form-label">TIN</label>
                    <input type="text" name="tin" value="{{ old('tin', $customer?->tin) }}" class="form-control" maxlength="50">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="3" maxlength="2000">{{ old('address', $customer?->address) }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Credit Limit</label>
                    <input type="number" name="credit_limit" value="{{ old('credit_limit', $customer?->credit_limit) }}" class="form-control" min="0" step="0.01">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Preferred Type</label>
                    <select name="preferred_type" class="form-select">
                        <option value="">— Default —</option>
                        <option value="customer" {{ old('preferred_type', $customer?->preferred_type)==='customer'?'selected':'' }}>Customer</option>
                        <option value="supplier" {{ old('preferred_type', $customer?->preferred_type)==='supplier'?'selected':'' }}>Supplier</option>
                        <option value="both" {{ old('preferred_type', $customer?->preferred_type)==='both'?'selected':'' }}>Both</option>
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label d-block">Can be used as</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="is_customer" value="1" id="isCustomer" {{ old('is_customer', $customer?->is_customer ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="isCustomer">Customer</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="is_vendor" value="1" id="isVendor" {{ old('is_vendor', $customer?->is_vendor) ? 'checked' : '' }}>
                        <label class="form-check-label" for="isVendor">Supplier / Vendor</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="is_both" value="1" id="isBoth" {{ old('is_both', ($customer?->type==='both')) ? 'checked' : '' }}>
                        <label class="form-check-label" for="isBoth">Both (Customer + Vendor)</label>
                    </div>
                </div>
                @if ($isEdit)
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select name="is_active" class="form-select">
                        <option value="1" {{ $customer?->is_active ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ $customer && !$customer->is_active ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                @endif
                <div class="col-12 mt-4">
                    <button type="submit" class="btn btn-primary rounded-pill px-4"><i class="bi bi-check-lg me-1"></i>{{ $isEdit ? 'Update' : 'Create' }} Customer</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
