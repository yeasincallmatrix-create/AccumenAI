@extends('layouts.institute')

@section('title', 'Sole Proprietorship Settings')

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
        <h4 class="page-header-title"><i class="bi bi-person me-2"></i>Sole Proprietorship Settings</h4>
        <p class="page-header-desc mb-0">Owner capital, drawings and simple reports.</p>
    </div>
    <a href="{{ route('settings.business-entity') }}" class="btn btn-outline-secondary rounded-pill px-3">Change entity type</a>
</div>

<div class="admin-card p-4 mb-3">
    <h5>Recommended Accounts</h5>
    <p class="text-muted small">Display-only suggestions — add them from COA if needed.</p>
    <table class="table align-middle mb-0">
        <thead><tr><th>Code</th><th>Name</th><th>Category</th></tr></thead>
        <tbody>
            @foreach($suggested as $acc)
                <tr><td class="font-monospace">{{ $acc['code'] }}</td><td>{{ $acc['name'] }}</td><td>{{ $acc['category'] }}</td></tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="admin-card p-4">
    <h5>Quick Links</h5>
    <ul class="mb-0">
        <li><a href="{{ route('accounting.reports.profit-loss') }}">P&amp;L Statement</a></li>
        <li><a href="{{ route('accounting.reports.balance-sheet') }}">Balance Sheet</a></li>
        <li><a href="{{ route('settings.currency.index') }}">Currency Settings</a></li>
    </ul>
</div>

@endsection
