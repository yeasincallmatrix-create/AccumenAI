@extends('layouts.standalone')

@section('title', mawa_e('verify.title') . ' — AccumenAI')
@section('page_title', mawa_e('verify.title'))

@section('content')
<div class="verify-certificate">
    <div class="verify-card">
        <div class="verify-header">
            <div class="verify-icon">
                <i class="bi bi-shield-check text-primary"></i>
            </div>
            <h4 class="verify-heading">{{ mawa_e('verify.heading') }}</h4>
            <p class="text-muted verify-sub">
                {{ mawa_e('verify.description') }}
            </p>
        </div>

        <div class="verify-details">
            <form method="POST" action="{{ route('verify.certificate.check') }}" class="row g-3">
                @csrf
                <div class="col-12">
                    <label for="certificate_number" class="verify-label">{{ mawa_e('verify.certificate_number') }}</label>
                    <input
                        type="text"
                        id="certificate_number"
                        name="certificate_number"
                        class="form-control form-control-lg text-uppercase"
                        placeholder="{{ mawa_e('verify.placeholder') }}"
                        value="{{ old('certificate_number') }}"
                        required
                        maxlength="40"
                        pattern="[A-Za-z0-9\-]+"
                        autocomplete="off"
                        autofocus
                    >
                    @error('certificate_number')
                        <div class="text-danger small mt-1"><i class="bi bi-exclamation-circle-fill me-1"></i>{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <button class="btn btn-primary btn-lg w-100" type="submit">
                        <i class="bi bi-search me-1"></i> {{ mawa_e('verify.check') }}
                    </button>
                </div>
            </form>

            <div class="verify-tip text-muted small mt-3">
                <i class="bi bi-info-circle me-1"></i>
                {{ mawa_e('verify.hint') }}
            </div>
        </div>

        <div class="verify-footer text-muted small">
            <i class="bi bi-shield-check me-1"></i>
            {{ mawa_e('verify.confirmed') }}
        </div>
    </div>
</div>

<style>
    .verify-certificate { max-width: 560px; margin: 24px auto; }
    .verify-card {
        background: #fff;
        border: 1px solid rgba(13, 110, 253, .15);
        border-radius: 18px;
        box-shadow: 0 20px 50px rgba(13, 110, 253, .10);
        overflow: hidden;
    }
    .verify-header {
        text-align: center;
        padding: 32px 24px 24px;
        border-bottom: 1px solid rgba(13, 110, 253, .12);
        background: linear-gradient(180deg, rgba(13, 110, 253, .06), transparent);
    }
    .verify-icon { font-size: 42px; line-height: 1; margin-bottom: 10px; }
    .verify-heading { font-weight: 700; margin-bottom: 8px; }
    .verify-sub { margin: 8px 0 0; font-size: 14px; }
    .verify-details { padding: 24px; }
    .verify-label { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .6px; color: #6c757d; margin-bottom: 6px; }
    .verify-tip { color: #6c757d; }
    .verify-footer {
        padding: 12px 24px;
        border-top: 1px dashed rgba(13, 110, 253, .18);
        background: rgba(13, 110, 253, .03);
    }
    html.monetix-dark .verify-card { background: #1e1f22; border-color: rgba(255,255,255,.1); }
    html.monetix-dark .verify-label, html.monetix-dark .verify-tip { color: #9aa0a6; }
</style>
@endsection