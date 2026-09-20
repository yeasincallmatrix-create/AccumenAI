@extends('layouts.institute')

@section('title', 'Partners')

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
        <h4 class="page-header-title"><i class="bi bi-people me-2"></i>Partners</h4>
        <p class="page-header-desc mb-0">Manage partnership capital and profit shares.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('settings.entity.partnership') }}" class="btn btn-outline-secondary rounded-pill px-3">Back</a>
        <a href="{{ route('settings.business-entity.partnership.create') }}" class="btn btn-primary rounded-pill px-3">+ Add Partner</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="admin-card p-3"><div class="text-muted small">Total Partners</div><div class="h4 mb-0">{{ $partners->total() }}</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3"><div class="text-muted small">Total Capital</div><div class="h4 mb-0">{{ number_format($totalCapital, 2) }}</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3"><div class="text-muted small">Total Share %</div><div class="h4 mb-0">{{ number_format($totalShare, 2) }}%</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3"><div class="text-muted small">Remaining %</div><div class="h4 mb-0">{{ number_format(100 - $totalShare, 2) }}%</div></div></div>
</div>

<div class="admin-card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th class="text-end">Capital</th><th class="text-end">Share %</th><th>Joined</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
                @forelse($partners as $partner)
                    <tr>
                        <td>{{ $partner->name }}</td>
                        <td>{{ $partner->email }}</td>
                        <td>{{ $partner->phone }}</td>
                        <td class="text-end">{{ number_format($partner->capital, 2) }}</td>
                        <td class="text-end">{{ $partner->share_percent }}%</td>
                        <td>{{ $partner->joined_at?->format('d M Y') }}</td>
                        <td><span class="badge bg-{{ $partner->is_active ? 'success' : 'secondary' }}">{{ $partner->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('settings.business-entity.partnership.edit', $partner) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            <form method="POST" action="{{ route('settings.business-entity.partnership.destroy', $partner) }}" class="d-inline" onsubmit="return confirm('Remove this partner?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center py-4 text-muted">No partners added yet. <a href="{{ route('settings.business-entity.partnership.create') }}">Add first partner</a></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($partners->hasPages())
        <div class="p-2 border-top">{{ $partners->links() }}</div>
    @endif
</div>

@endsection
