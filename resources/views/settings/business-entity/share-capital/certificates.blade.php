@extends('layouts.institute')

@section('title', 'Share Certificates')

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
        <h4 class="page-header-title"><i class="bi bi-patch-check me-2"></i>Share Certificates</h4>
        <p class="page-header-desc mb-0">Issued certificate register.</p>
    </div>
    <a href="{{ route('settings.share-capital.index') }}" class="btn btn-outline-secondary rounded-pill px-3">Back</a>
</div>

<div class="admin-card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Certificate #</th><th>Shareholder</th><th class="text-end">Shares</th><th class="text-end">Face Value</th><th class="text-end">Total</th><th>Issue Date</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($certificates as $cert)
                    <tr>
                        <td><strong>{{ $cert->certificate_no }}</strong></td>
                        <td>{{ $cert->shareholder?->name }}</td>
                        <td class="text-end">{{ $cert->shares }}</td>
                        <td class="text-end">{{ number_format($cert->face_value, 2) }}</td>
                        <td class="text-end">{{ number_format($cert->total_value, 2) }}</td>
                        <td>{{ $cert->issue_date?->format('d M Y') }}</td>
                        <td><span class="badge bg-{{ $cert->status === 'active' ? 'success' : ($cert->status === 'cancelled' ? 'danger' : 'secondary') }}">{{ ucfirst($cert->status) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center py-3 text-muted">No certificates issued yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($certificates->hasPages())
        <div class="p-2 border-top">{{ $certificates->links() }}</div>
    @endif
</div>

@endsection
