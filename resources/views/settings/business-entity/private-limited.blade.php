@extends('layouts.institute')

@section('title', 'Private Limited Settings')

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
        <h4 class="page-header-title"><i class="bi bi-bank me-2"></i>Private Limited Settings</h4>
        <p class="page-header-desc mb-0">Share capital, shareholders and dividends.</p>
    </div>
    <a href="{{ route('settings.business-entity') }}" class="btn btn-outline-secondary rounded-pill px-3">Change entity type</a>
</div>

<div class="admin-card p-4 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0">Shareholders</h5>
        <span class="badge bg-secondary">Shareholder CRUD coming in G.2</span>
    </div>
    @if($shareholders->isEmpty())
        <p class="text-muted small mb-0">No shareholders configured yet.</p>
    @else
        <table class="table align-middle mb-0">
            <thead><tr><th>Name</th><th class="text-end">Shares</th><th class="text-end">Share %</th><th>Certificate</th></tr></thead>
            <tbody>
                @foreach($shareholders as $s)
                    <tr><td>{{ $s->name }}</td><td class="text-end">{{ number_format($s->shares) }}</td><td class="text-end">{{ $s->share_percent }}%</td><td>{{ $s->certificate_no }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="admin-card p-4">
    <h5>Recommended Accounts</h5>
    <p class="text-muted small">Display-only suggestions — add them from COA if needed.</p>
    <table class="table align-middle mb-0">
        <thead><tr><th>Code</th><th>Name</th></tr></thead>
        <tbody>
            @foreach($suggested as $acc)
                <tr><td class="font-monospace">{{ $acc['code'] }}</td><td>{{ $acc['name'] }}</td></tr>
            @endforeach
        </tbody>
    </table>
</div>

@endsection
