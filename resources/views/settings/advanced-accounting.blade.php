@extends('layouts.institute')

@section('title', 'Advanced Accounting')

@push('styles')
<style>
  /* Match settings index: hide navbar/sidebar on settings sub-pages */
  .topbar, .sidebar, .sidebar-backdrop { display: none !important; }
  .layout { display: block !important; }
  .content { margin-left: 0 !important; padding-top: 1.25rem !important; max-width: 100% !important; }
</style>
@endpush

@section('content')

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title"><i class="bi bi-sliders me-2"></i>Advanced Accounting</h4>
        <p class="page-header-desc mb-0">Toggle full accounting power per institute. Default is simple mode.</p>
    </div>
    <a href="{{ route('settings.index') }}" class="btn btn-outline-secondary rounded-pill px-3">Back to Settings</a>
</div>

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="admin-card p-4">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div class="flex-fill">
            <h5 class="mb-1">Enable Advanced Accounting</h5>
            <p class="text-muted mb-0">Unlock General Ledger, Account Ledger, Journal Entries, Trial Balance, Audit Trail, Period Close, Ratio Analysis and advanced reports.</p>
        </div>
        <form method="POST" action="{{ route('settings.advanced-accounting.toggle') }}">
            @csrf
            <button type="submit" class="btn btn-{{ $enabled ? 'primary' : 'outline-secondary' }}">
                {{ $enabled ? 'ON — click to disable' : 'OFF — click to enable' }}
            </button>
        </form>
    </div>

    <div class="alert mt-3 mb-0 {{ $enabled ? 'alert-success' : 'alert-info' }}">
        @if($enabled)
            Advanced mode is <strong>ON</strong>. All accounting features visible.
        @else
            Simple mode is active. Enable to access GL, AL, journals, Trial Balance and advanced reports.
        @endif
    </div>

    <hr>
    <h6 class="mb-2">What you get in Advanced mode:</h6>
    <ul class="row g-1 small text-muted mb-0">
        <li class="col-md-6">General Ledger</li>
        <li class="col-md-6">Account Ledger</li>
        <li class="col-md-6">Journal Entries</li>
        <li class="col-md-6">Manual Journal</li>
        <li class="col-md-6">Trial Balance</li>
        <li class="col-md-6">Audit Trail</li>
        <li class="col-md-6">Period Close</li>
        <li class="col-md-6">Fiscal Year Close</li>
        <li class="col-md-6">Ratio Analysis</li>
        <li class="col-md-6">Budget Variance</li>
    </ul>
</div>

@endsection
