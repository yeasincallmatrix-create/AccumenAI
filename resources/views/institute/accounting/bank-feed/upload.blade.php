@extends('layouts.standalone')

@section('title', 'Import Bank Statement — AccumenAI')
@section('page_title', 'Accounting')

@section('content')

<div class="standalone-heading">
    <h4>Import Bank Statement</h4>
    <p>Upload a CSV, OFX, or QFX file to import bank transactions.</p>
</div>

<div class="admin-card">
    <form method="POST" action="{{ route('accounting.bank-feed.import') }}" enctype="multipart/form-data">
        @csrf

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Bank Account <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm" name="bank_account_id" required>
                    <option value="">Select bank account...</option>
                    @foreach ($bankAccounts as $acct)
                        <option value="{{ $acct->id }}" @selected(old('bank_account_id') == $acct->id)>{{ $acct->code }} — {{ $acct->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Statement File <span class="text-danger">*</span></label>
                <input type="file" class="form-control form-control-sm" name="file" accept=".csv,.txt,.ofx,.qfx" required>
                <small class="text-muted">Supported: CSV, OFX, QFX (max 10MB)</small>
            </div>
            <div class="col-md-4">
                <label class="form-label">Statement Date</label>
                <input type="date" class="form-control form-control-sm" name="statement_date" value="{{ old('statement_date', now()->toDateString()) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Opening Balance</label>
                <input type="number" class="form-control form-control-sm" name="opening_balance" value="{{ old('opening_balance', '0') }}" step="0.01">
            </div>
            <div class="col-md-4">
                <label class="form-label">Closing Balance</label>
                <input type="number" class="form-control form-control-sm" name="closing_balance" value="{{ old('closing_balance', '0') }}" step="0.01">
            </div>
            <div class="col-12">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-upload me-1"></i>Import</button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('accounting.bank-feed.index') }}">Cancel</a>
            </div>
        </div>
    </form>
</div>

@endsection
