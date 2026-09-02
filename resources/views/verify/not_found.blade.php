@extends('layouts.standalone')

@section('title', mawa_e('verify.title') . ' — AccumenAI')
@section('page_title', mawa_e('verify.title'))

@section('content')
<div class="verify-certificate">
    <div class="verify-card">
        <div class="verify-header">
            <div class="verify-icon">
                <i class="bi bi-x-octagon-fill text-danger"></i>
            </div>
            <h4 class="verify-heading">{{ mawa_e('verify.not_found') }}</h4>
            <span class="badge text-bg-secondary verify-badge">{{ mawa_e('verify.no_record') }}</span>
            <p class="text-muted verify-sub">
                {{ mawa_e('verify.no_match') }}
            </p>
        </div>

        <div class="verify-details">
            <div class="alert alert-warning mb-0">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                {{ mawa_e('verify.invalid') }}
            </div>
        </div>

        <div class="verify-footer text-muted small">
            <i class="bi bi-shield-check me-1"></i>
            {{ mawa_e('verify.confirmed') }}
            <span class="float-end">
                <a class="text-decoration-none" href="{{ route('verify.certificate.index') }}"><i class="bi bi-arrow-repeat me-1"></i>{{ mawa_e('verify.check_another') }}</a>
            </span>
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
    .verify-sub { margin: 12px 0 0; font-size: 14px; }
    .verify-badge { font-size: 12px; letter-spacing: .6px; padding: 6px 12px; border-radius: 30px; }
    .verify-details { padding: 24px; }
    .verify-footer {
        padding: 12px 24px;
        border-top: 1px dashed rgba(13, 110, 253, .18);
        background: rgba(13, 110, 253, .03);
    }
    html.monetix-dark .verify-card { background: #1e1f22; border-color: rgba(255,255,255,.1); }
</style>
@endsection