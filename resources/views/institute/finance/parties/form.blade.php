@extends('layouts.standalone')

@section('title', $party ? 'Edit Party — AccumenAI' : 'New Party — AccumenAI')
@section('page_title', $party ? 'Edit Party' : 'New Party')

@section('content')

<div class="standalone-heading">
    <h4>{{ $party ? 'Edit Party' : 'New Party' }}</h4>
    <p>Parties back the receivables and payables ledger. A party can be a customer, a supplier, or both.</p>
</div>

<div class="admin-card">
    <form method="POST" action="{{ $party ? route('finance.parties.update', $party) : route('finance.parties.store') }}">
        @csrf
        @if ($party) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Type <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm" name="type" required>
                    @foreach ($types as $type)
                        <option value="{{ $type }}" @selected(old('type', $party?->type) === $type)>{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-8">
                <label class="form-label">Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" name="name" value="{{ old('name', $party?->name) }}" required maxlength="150">
            </div>

            <div class="col-md-4">
                <label class="form-label">Phone</label>
                @include('partials.phone', ['name' => 'phone', 'id' => 'party_phone', 'value' => old('phone', $party?->phone)])
            </div>
            <div class="col-md-4">
                <label class="form-label">Email</label>
                <input type="email" class="form-control form-control-sm" name="email" value="{{ old('email', $party?->email) }}" maxlength="150">
            </div>
            <div class="col-md-4">
                <label class="form-label">TIN</label>
                <input type="text" class="form-control form-control-sm" name="tin" value="{{ old('tin', $party?->tin) }}" maxlength="50">
            </div>

            <div class="col-md-4">
                <label class="form-label">Billing currency</label>
                <select class="form-select form-select-sm" name="billing_currency_id">
                    <option value="">— None —</option>
                    @foreach ($currencies as $currency)
                        <option value="{{ $currency->id }}" @selected((string) old('billing_currency_id', $party?->billing_currency_id) === (string) $currency->id)>{{ $currency->code }} — {{ $currency->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Credit limit</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="credit_limit" value="{{ old('credit_limit', $party?->credit_limit) }}">
            </div>

            <div class="col-12">
                <label class="form-label">Address</label>
                <textarea class="form-control form-control-sm" name="address" rows="2" maxlength="2000">{{ old('address', $party?->address) }}</textarea>
            </div>

            <div class="col-12">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-check-lg me-1"></i>{{ $party ? 'Save changes' : 'Create party' }}</button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('finance.parties.index') }}">Cancel</a>
            </div>
        </div>
    </form>
</div>

@endsection