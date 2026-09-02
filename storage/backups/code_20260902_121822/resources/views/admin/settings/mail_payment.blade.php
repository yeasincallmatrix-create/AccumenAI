@extends('layouts.standalone')

@php $backUrl = route('admin.settings.index'); @endphp

@section('title', mawa_e('settings_page.mail_payment') . ' — AccumenAI')
@section('page_title', mawa_e('settings_page.mail_payment'))

@section('content')

<div class="standalone-heading">
    <h4>{{ mawa_e('settings_page.mail_payment') }}</h4>
    <p>{{ mawa_e('settings_page.mail_payment_desc') }}</p>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill"></i> {{ $errors->first() }}
    </div>
@endif

<div class="admin-card mb-4">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-envelope"></i> SMTP</div>
    </div>
    <form method="POST" action="{{ route('admin.settings.mail-payment.update') }}">
        @csrf
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label" for="smtp_host">SMTP Host</label>
                <input type="text" id="smtp_host" name="smtp_host" class="form-control" value="{{ $smtpHost }}" placeholder="smtp.gmail.com">
                @error('smtp_host')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="smtp_port">SMTP Port</label>
                <input type="text" id="smtp_port" name="smtp_port" class="form-control" value="{{ $smtpPort }}" placeholder="587">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="smtp_encryption">Encryption</label>
                <select id="smtp_encryption" name="smtp_encryption" class="form-select">
                    <option value="none" {{ $smtpEncryption === 'none' ? 'selected' : '' }}>None</option>
                    <option value="tls" {{ $smtpEncryption === 'tls' ? 'selected' : '' }}>TLS</option>
                    <option value="ssl" {{ $smtpEncryption === 'ssl' ? 'selected' : '' }}>SSL</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="smtp_username">SMTP Username</label>
                <input type="text" id="smtp_username" name="smtp_username" class="form-control" value="{{ $smtpUsername }}" placeholder="you@example.com">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="smtp_password">SMTP Password</label>
                <input type="password" id="smtp_password" name="smtp_password" class="form-control" value="" placeholder="{{ $smtpPasswordMasked ?? '••••••••' }}">
                <div class="form-text small text-muted">Leave blank to keep existing password</div>
            </div>
        </div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Save</button>
    </form>
</div>

<div class="admin-card mb-4">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-credit-card"></i> {{ mawa_e('settings_page.payment_heading') }}</div>
    </div>
    <form method="POST" action="{{ route('admin.settings.mail-payment.update') }}">
        @csrf
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label" for="payment_gateway">Payment Gateway</label>
                <input type="text" id="payment_gateway" name="payment_gateway" class="form-control" value="{{ $paymentGateway }}" placeholder="bKash / Nagad / Stripe">
                @error('payment_gateway')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
        </div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Save</button>
    </form>
</div>

<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-send"></i> {{ mawa_e('settings_page.test_email_heading') }}</div>
    </div>
    <form method="POST" action="{{ route('admin.settings.mail-payment.test') }}">
        @csrf
        <div class="row g-3 mb-3">
            <div class="col-md-8">
                <label class="form-label" for="test_email">Recipient</label>
                <input type="email" id="test_email" name="test_email" class="form-control" value="{{ $admin->email }}" placeholder="you@example.com">
                @error('smtp_test')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
        </div>
        <button class="btn btn-outline-primary" type="submit"><i class="bi bi-send"></i> Save & Test</button>
    </form>
</div>

@endsection