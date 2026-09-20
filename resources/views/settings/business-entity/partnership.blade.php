@extends('layouts.institute')

@section('title', 'Partnership Settings')

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
        <h4 class="page-header-title"><i class="bi bi-people me-2"></i>Partnership Settings</h4>
        <p class="page-header-desc mb-0">Partners, capital accounts and profit-sharing.</p>
    </div>
    <a href="{{ route('settings.business-entity') }}" class="btn btn-outline-secondary rounded-pill px-3">Change entity type</a>
</div>

<div class="admin-card p-4 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0">Partners ({{ $partners->count() }})</h5>
        <a href="{{ route('settings.business-entity.partnership.index') }}" class="btn btn-sm btn-primary">Manage Partners →</a>
    </div>
    @if($partners->isEmpty())
        <p class="text-muted small mb-0">No partners configured yet.</p>
    @else
        <table class="table align-middle mb-0">
            <thead><tr><th>Name</th><th class="text-end">Capital</th><th class="text-end">Share %</th></tr></thead>
            <tbody>
                @foreach($partners as $p)
                    <tr><td>{{ $p->name }}</td><td class="text-end">{{ number_format($p->capital, 2) }}</td><td class="text-end">{{ $p->share_percent }}%</td></tr>
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
