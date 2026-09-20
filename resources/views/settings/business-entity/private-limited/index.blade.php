@extends('layouts.institute')

@section('title', 'Shareholders')

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
        <h4 class="page-header-title"><i class="bi bi-bank me-2"></i>Shareholders</h4>
        <p class="page-header-desc mb-0">Share capital, directors and dividends.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('settings.entity.private-limited') }}" class="btn btn-outline-secondary rounded-pill px-3">Back</a>
        <a href="{{ route('settings.business-entity.private-limited.create') }}" class="btn btn-primary rounded-pill px-3">+ Add Shareholder</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="admin-card p-3"><div class="text-muted small">Total Shares</div><div class="h4 mb-0">{{ number_format($totalShares) }}</div></div></div>
    <div class="col-md-4"><div class="admin-card p-3"><div class="text-muted small">Total Paid-up</div><div class="h4 mb-0">{{ number_format($totalPaidUp, 2) }}</div></div></div>
    <div class="col-md-4"><div class="admin-card p-3"><div class="text-muted small">Directors</div><div class="h4 mb-0">{{ $directors }}</div></div></div>
</div>

<div class="admin-card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Name</th><th class="text-end">Shares</th><th class="text-end">Face Value</th><th class="text-end">Investment</th><th class="text-end">Share %</th><th>Director</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
                @forelse($shareholders as $sh)
                    <tr>
                        <td>{{ $sh->name }}</td>
                        <td class="text-end">{{ number_format($sh->shares) }}</td>
                        <td class="text-end">{{ number_format($sh->face_value, 2) }}</td>
                        <td class="text-end">{{ number_format($sh->total_investment, 2) }}</td>
                        <td class="text-end">{{ $sh->share_percent }}%</td>
                        <td>@if($sh->is_director)<span class="badge bg-info">Director{{ $sh->director_designation ? ' — '.$sh->director_designation : '' }}</span>@else<span class="text-muted">—</span>@endif</td>
                        <td><span class="badge bg-{{ $sh->is_active ? 'success' : 'secondary' }}">{{ $sh->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('settings.business-entity.private-limited.edit', $sh) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            <form method="POST" action="{{ route('settings.business-entity.private-limited.destroy', $sh) }}" class="d-inline" onsubmit="return confirm('Remove this shareholder?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center py-4 text-muted">No shareholders added yet. <a href="{{ route('settings.business-entity.private-limited.create') }}">Add first shareholder</a></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($shareholders->hasPages())
        <div class="p-2 border-top">{{ $shareholders->links() }}</div>
    @endif
</div>

@endsection
