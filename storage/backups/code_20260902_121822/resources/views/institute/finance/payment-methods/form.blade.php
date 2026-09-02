@extends('layouts.standalone')

@section('title', $method ? 'Edit Payment Method — AccumenAI' : 'New Payment Method — AccumenAI')
@section('page_title', $method ? 'Edit Payment Method' : 'New Payment Method')

@section('content')

<div class="standalone-heading">
    <h4>{{ $method ? 'Edit Payment Method' : 'New Payment Method' }}</h4>
    <p>Method names must be unique within the institute scope. Linking a default account makes the method post to that account when a payment is recorded.</p>
</div>

<div class="admin-card">
    <form method="POST" action="{{ $method ? route('finance.payment-methods.update', $method) : route('finance.payment-methods.store') }}">
        @csrf
        @if ($method) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" name="name" value="{{ old('name', $method?->name) }}" required maxlength="100" placeholder="e.g. Cash, Bank, bKash">
            </div>
            <div class="col-md-6">
                <label class="form-label">Default account</label>
                <select class="form-select form-select-sm" name="coa_id">
                    <option value="">— None —</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected((string) old('coa_id', $method?->coa_id) === (string) $account->id)>
                            {{ $account->code }} — {{ $account->name }}@if ($account->is_cash) (Cash)@elseif ($account->is_bank) (Bank)@endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', $method?->is_active ?? true))>
                    <label class="form-check-label" for="is_active">Active</label>
                </div>
            </div>

            <div class="col-12">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-check-lg me-1"></i>{{ $method ? 'Save changes' : 'Create method' }}</button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('finance.payment-methods.index') }}">Cancel</a>
            </div>
        </div>
    </form>
</div>

@endsection